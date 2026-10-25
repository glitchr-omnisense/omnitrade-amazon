<?php

namespace Omnitrade\Amazon\Tests;

use Omnitrade\Amazon\AmazonGatewayFactory;
use Omnitrade\GatewayFactoryInterface;
use Omnitrade\Testing\GatewayContractTests;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Omnitrade's conformance suite, on the Creators API's recorded answers (credentials given: its reads on). */
final class AmazonContractTest extends TestCase
{
    use GatewayContractTests;

    private const ITEM = [
        'asin' => 'B0C1234567',
        'detailPageURL' => 'https://www.amazon.fr/dp/B0C1234567?tag=shop-21',
        'itemInfo' => ['title' => ['displayValue' => 'Cotton cot bumper']],
        'offersV2' => ['listings' => [['price' => ['money' => ['amount' => 34.99, 'currency' => 'EUR']], 'availability' => ['type' => 'IN_STOCK']]]],
    ];

    protected function factory(HttpClientInterface $http): GatewayFactoryInterface
    {
        return new AmazonGatewayFactory($http);
    }

    protected function options(): array
    {
        return ['client_id' => 'id', 'client_secret' => 'secret', 'partner_tag' => 'shop-21'];
    }

    protected function productReference(): string
    {
        return 'B0C1234567';
    }

    protected function catalogueQuery(): ?string
    {
        return 'cot bumper';
    }

    protected function recordedAnswers(): array
    {
        return [
            'POST /auth/o2/token' => ['access_token' => 'Atc|token', 'token_type' => 'bearer', 'expires_in' => 3600],
            'POST /catalog/v1/getItems' => ['itemsResult' => ['items' => [self::ITEM]]],
            'POST /catalog/v1/searchItems' => ['searchResult' => ['totalResultCount' => 1, 'items' => [self::ITEM]]],
        ];
    }
}
