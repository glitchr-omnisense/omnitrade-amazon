<?php

namespace Omnitrade\Amazon;

use Omnitrade\Model\Media;
use Omnitrade\Model\Merchant;
use Omnitrade\Model\Money;
use Omnitrade\Model\Offer;
use Omnitrade\Model\Product;
use Omnitrade\Model\ProductVariant;

/** A Creators API item as an Omnitrade Product, and an ASIN found in an Amazon address. */
final class Items
{
    /** "B0C1234567" in /dp/B0C1234567, /gp/product/B0C1234567, /exec/obidos/ASIN/..., or a bare ASIN. */
    public static function asin(string $idOrUrl): ?string
    {
        $idOrUrl = trim($idOrUrl);
        if (preg_match('~^[A-Z0-9]{10}$~', $idOrUrl)) {
            return $idOrUrl;
        }
        if (preg_match('~/(?:dp|gp/product|gp/aw/d|exec/obidos/ASIN|o/ASIN)/([A-Z0-9]{10})(?:[/?#]|$)~i', $idOrUrl, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    public static function isAmazon(string $url): bool
    {
        $host = strtolower((string) parse_url($url, \PHP_URL_HOST));

        return (bool) preg_match('~(^|\.)amazon\.[a-z.]+$|(^|\.)amzn\.(to|eu)$~', $host);
    }

    /** @param array<string, mixed> $item */
    public static function product(array $item, string $marketplace): Product
    {
        $asin = (string) ($item['asin'] ?? '');
        $info = (array) ($item['itemInfo'] ?? []);
        $title = (string) ($info['title']['displayValue'] ?? $asin);
        $brand = $info['byLineInfo']['brand']['displayValue'] ?? $info['byLineInfo']['manufacturer']['displayValue'] ?? null;
        $features = array_values(array_filter((array) ($info['features']['displayValues'] ?? []), 'is_string'));
        $url = (string) ($item['detailPageURL'] ?? sprintf('https://%s/dp/%s', $marketplace, $asin));
        $amazon = new Merchant(ucfirst(preg_replace('~^www\.~', '', $marketplace)), null, 'https://'.$marketplace);

        $offers = [];
        foreach ((array) ($item['offersV2']['listings'] ?? []) as $listing) {
            $money = $listing['price']['money'] ?? null;
            if (!\is_array($money) || !isset($money['amount'], $money['currency'])) {
                continue;
            }
            $availability = strtolower((string) ($listing['availability']['type'] ?? 'in_stock'));
            $offers[] = new Offer(
                Money::fromDecimal((string) $money['amount'], (string) $money['currency']),
                null,
                !str_contains(str_replace(['_', ' '], '', $availability), 'outofstock') && 'unavailable' !== $availability,
                $url,
                isset($listing['merchantInfo']['name']) ? new Merchant((string) $listing['merchantInfo']['name'], $listing['merchantInfo']['id'] ?? null) : $amazon,
                null,
                true,
            );
        }

        $media = [];
        foreach (array_merge([$item['images']['primary'] ?? null], (array) ($item['images']['variants'] ?? [])) as $image) {
            $large = \is_array($image) ? ($image['large'] ?? $image['medium'] ?? $image['small'] ?? null) : null;
            if (\is_array($large) && isset($large['url'])) {
                $media[] = new Media((string) $large['url'], $title, Media::IMAGE, isset($large['width']) ? (int) $large['width'] : null, isset($large['height']) ? (int) $large['height'] : null);
            }
        }

        $ean = $info['externalIds']['eaNs']['displayValues'][0] ?? $info['externalIds']['eans']['displayValues'][0] ?? null;
        $category = $info['classifications']['productGroup']['displayValue'] ?? null;

        return new Product(
            provider: 'amazon',
            reference: $asin,
            title: $title,
            // HTML, as Product::$description has it: the features (text) as a list.
            description: $features ? '<ul>'.implode('', array_map(static fn (string $feature): string => '<li>'.htmlspecialchars($feature, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8').'</li>', $features)).'</ul>' : null,
            handle: $asin,
            brand: \is_string($brand) ? $brand : null,
            url: $url,
            categories: \is_string($category) ? [$category] : [],
            variants: [new ProductVariant($asin, null, $asin, \is_string($ean) ? $ean : null, $offers, [], null, $media[0] ?? null)],
            media: $media,
            merchant: $amazon,
            raw: $item,
        );
    }
}
