<?php

namespace Omnitrade\Amazon\Action;

use Omnitrade\Action\ActionInterface;
use Omnitrade\Amazon\Items;
use Omnitrade\Request\AffiliateLink;
use Omnitrade\Request\Request;

/**
 * The product's page with the Associates partner tag: https://<marketplace>/dp/<ASIN>?tag=<tag>,
 * from an ASIN or any Amazon address naming one. No API call. Null without a
 * tag, or for an address that names no product.
 */
final class AffiliateLinkAction implements ActionInterface
{
    public function __construct(private readonly string $marketplace, private readonly string $partnerTag = '')
    {
    }

    public function supports(Request $request): bool
    {
        return $request instanceof AffiliateLink;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof AffiliateLink);
        $tag = $request->tag ?: $this->partnerTag;
        $asin = Items::asin((string) $request->reference);
        if ('' === $tag || null === $asin) {
            $request->setResult(null);

            return;
        }
        $host = $request->reference->url && Items::isAmazon($request->reference->url) && !str_contains((string) parse_url($request->reference->url, \PHP_URL_HOST), 'amzn.')
            ? strtolower((string) parse_url($request->reference->url, \PHP_URL_HOST))
            : $this->marketplace;

        $request->setResult(sprintf('https://%s/dp/%s?tag=%s', $host, $asin, rawurlencode($tag)));
    }
}
