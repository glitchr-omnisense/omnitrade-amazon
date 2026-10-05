<?php

namespace Omnitrade\Amazon;

use Omnitrade\Amazon\Action\AffiliateLinkAction;
use Omnitrade\Amazon\Action\FetchProductAction;
use Omnitrade\Amazon\Action\FetchProductsAction;
use Omnitrade\Config;
use Omnitrade\Exception\InvalidConfigException;
use Omnitrade\GatewayFactory;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Amazon, through the Creators API (which replaced the Product Advertising
 * API 5 on 2026-05-15): a product by its ASIN or its page (getItems), a
 * search (searchItems), and the affiliate link carrying the Associates
 * partner tag. Nothing is bought here: no API lets a site buy for someone.
 *
 *   options:
 *     client_id: '%env(AMAZON_CREATORS_CLIENT_ID)%'          # the Creators API credentials
 *     client_secret: '%env(AMAZON_CREATORS_CLIENT_SECRET)%'
 *     partner_tag: '%env(AMAZON_CREATORS_PARTNER_TAG)%'      # mysite-21
 *     marketplace: www.amazon.fr                              # the store the items and prices are read in
 *     credential_version: '3.2'                               # 3.1 North America, 3.2 Europe, 3.3 Far East: the token endpoint
 *     token_url: null                                         # another token endpoint (a v2 Cognito one)
 *     api_url: 'https://creatorsapi.amazon/catalog/v1'
 *     timeout: 10
 *
 * Without credentials - an account not yet eligible, the API being opened
 * only after qualified sales - only affiliateLink() is offered: the tag
 * alone is enough to write it, and the product is read from its page
 * (omnitrade/web) meanwhile.
 */
final class AmazonGatewayFactory extends GatewayFactory
{
    public const TOKEN_URLS = [
        '3.1' => 'https://api.amazon.com/auth/o2/token',
        '3.2' => 'https://api.amazon.co.uk/auth/o2/token',
        '3.3' => 'https://api.amazon.co.jp/auth/o2/token',
    ];

    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnitrade.factory_name' => 'amazon',
            'omnitrade.factory_title' => 'Amazon',
            'omnitrade.required_options' => [],
            'client_id' => null,
            'client_secret' => null,
            'partner_tag' => null,
            'marketplace' => 'www.amazon.fr',
            'credential_version' => '3.2',
            'token_url' => null,
            'api_url' => Api::API_URL,
            'timeout' => 10,
            'omnitrade.api' => function (Config $c) {
                $tokenUrl = $c['token_url'] ?: (self::TOKEN_URLS[(string) $c['credential_version']] ?? throw new InvalidConfigException(sprintf('Unknown Creators API credential version "%s": 3.1, 3.2 or 3.3, or a token_url.', $c['credential_version'])));

                return new Api($this->http ?? HttpClient::create(), (string) $c['client_id'], (string) $c['client_secret'], (string) $c['partner_tag'], (string) $c['marketplace'], $tokenUrl, (string) $c['api_url'], (int) $c['timeout']);
            },
            'omnitrade.action.affiliate' => static fn (Config $c) => new AffiliateLinkAction((string) $c['marketplace'], (string) $c['partner_tag']),
            // The API's reads only with credentials: without, supports() says no.
            'omnitrade.action.product' => static fn (Config $c) => $c['client_id'] && $c['client_secret'] ? new FetchProductAction() : null,
            'omnitrade.action.products' => static fn (Config $c) => $c['client_id'] && $c['client_secret'] ? new FetchProductsAction() : null,
        ]);
    }
}
