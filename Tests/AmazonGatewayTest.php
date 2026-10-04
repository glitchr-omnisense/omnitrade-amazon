<?php

namespace Omnitrade\Amazon\Tests;

use Omnitrade\Amazon\AmazonGatewayFactory;
use Omnitrade\Amazon\Items;
use Omnitrade\Exception\ProviderException;
use Omnitrade\Request\AffiliateLink;
use Omnitrade\Request\FetchProduct;
use Omnitrade\Request\FetchProducts;
use Omnitrade\Request\Purchase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** On recorded answers shaped as the Creators API documentation shows them: no real call is made. */
final class AmazonGatewayTest extends TestCase
{
    /** @var list<array{string, string, array}> */
    private array $calls = [];

    private const ITEM = [
        'asin' => 'B0C1234567',
        'detailPageURL' => 'https://www.amazon.fr/dp/B0C1234567?tag=fete-21',
        'images' => ['primary' => ['large' => ['url' => 'https://m.media-amazon.com/images/I/1.jpg', 'width' => 500, 'height' => 500]]],
        'itemInfo' => [
            'title' => ['displayValue' => 'Tour de lit bébé en coton'],
            'byLineInfo' => ['brand' => ['displayValue' => 'Jollein']],
            'features' => ['displayValues' => ['Coton bio', 'Lavable à 30°']],
            'classifications' => ['productGroup' => ['displayValue' => 'Baby Product']],
        ],
        'offersV2' => ['listings' => [['price' => ['money' => ['amount' => 34.99, 'currency' => 'EUR', 'displayAmount' => '34,99 €']], 'availability' => ['type' => 'IN_STOCK'], 'merchantInfo' => ['name' => 'Amazon.fr', 'id' => 'A1X6FK5RDHNB96']]]],
    ];

    private function gateway(array $options = ['client_id' => 'id', 'client_secret' => 'secret', 'partner_tag' => 'fete-21']): \Omnitrade\GatewayInterface
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->calls[] = [$method, $url, $options];
            $body = json_decode((string) ($options['body'] ?? '{}'), true);

            return match (true) {
                str_ends_with($url, '/auth/o2/token') => new MockResponse(json_encode(['access_token' => 'Atc|token', 'scope' => 'creatorsapi::default', 'token_type' => 'bearer', 'expires_in' => 3600])),
                str_ends_with($url, '/getItems') => new MockResponse(json_encode(['itemsResult' => ['items' => \in_array('B0C1234567', $body['itemIds'] ?? [], true) ? [self::ITEM] : []]])),
                str_ends_with($url, '/searchItems') => new MockResponse(json_encode(['searchResult' => ['totalResultCount' => 1, 'items' => [self::ITEM]]])),
                default => new MockResponse('{}', ['http_code' => 404]),
            };
        });

        return (new AmazonGatewayFactory($http))->create($options);
    }

    public function testAProductByItsPage(): void
    {
        $gateway = $this->gateway();
        $product = $gateway->fetchProduct('https://www.amazon.fr/Jollein-Tour-lit/dp/B0C1234567/ref=sr_1_3?keywords=tour');

        self::assertSame('amazon', $product->provider);
        self::assertSame('B0C1234567', $product->reference);
        self::assertSame('Tour de lit bébé en coton', $product->title);
        self::assertSame('Jollein', $product->brand);
        self::assertSame(3499, $product->price()->amount);
        self::assertSame('Amazon.fr', $product->variants[0]->offer()->merchant->name);
        self::assertSame('https://m.media-amazon.com/images/I/1.jpg', $product->media[0]->url);

        [$tokenMethod, $tokenUrl, $tokenOptions] = $this->calls[0];
        self::assertSame('https://api.amazon.co.uk/auth/o2/token', $tokenUrl, 'credentials 3.2: Europe');
        self::assertSame('creatorsapi::default', json_decode($tokenOptions['body'], true)['scope']);
        [, $url, $options] = $this->calls[1];
        self::assertSame('https://creatorsapi.amazon/catalog/v1/getItems', $url);
        self::assertContains('x-marketplace: www.amazon.fr', $options['headers']);
        self::assertContains('Authorization: Bearer Atc|token', $options['headers']);
        $body = json_decode($options['body'], true);
        self::assertSame(['B0C1234567'], $body['itemIds']);
        self::assertSame('fete-21', $body['partnerTag']);
        self::assertSame('www.amazon.fr', $body['marketplace']);

        $gateway->fetchProduct('B0C1234567');
        self::assertCount(3, $this->calls, 'the token is kept');
        self::assertNull($gateway->fetchProduct('B0NOTFOUND'));
        self::assertNull($gateway->fetchProduct('https://www.amazon.fr/gp/help/customer'));
    }

    public function testASearch(): void
    {
        $page = $this->gateway()->fetchProducts(query: 'tour de lit', limit: 5);
        self::assertCount(1, $page->products);
        self::assertFalse(null !== $page->next);
        self::assertSame(5, json_decode($this->calls[1][2]['body'], true)['itemCount']);

        $this->expectException(ProviderException::class);
        $this->gateway()->fetchProducts();
    }

    public function testWithoutCredentialsOnlyTheLink(): void
    {
        $gateway = $this->gateway(['partner_tag' => 'fete-21']);
        self::assertFalse($gateway->supports(FetchProduct::class));
        self::assertFalse($gateway->supports(FetchProducts::class));
        self::assertFalse($gateway->supports(Purchase::class));
        self::assertTrue($gateway->supports(AffiliateLink::class));

        self::assertSame('https://www.amazon.fr/dp/B0C1234567?tag=fete-21', $gateway->execute(new AffiliateLink('https://www.amazon.fr/Jollein/dp/B0C1234567/ref=x?psc=1'))->getUrl());
        self::assertSame('https://www.amazon.de/dp/B0C1234567?tag=fete-21', $gateway->execute(new AffiliateLink('https://www.amazon.de/dp/B0C1234567'))->getUrl(), 'the store of the address');
        self::assertSame('https://www.amazon.fr/dp/B0C1234567?tag=other-21', $gateway->execute(new AffiliateLink('B0C1234567', 'other-21'))->getUrl());
        self::assertNull($gateway->execute(new AffiliateLink('https://www.amazon.fr/gp/help'))->getUrl());
        self::assertNull((new AmazonGatewayFactory(new MockHttpClient()))->create()->execute(new AffiliateLink('B0C1234567'))->getUrl(), 'no tag, no link');
        self::assertSame([], $this->calls);
    }

    public function testAsins(): void
    {
        self::assertSame('B0C1234567', Items::asin('https://www.amazon.fr/gp/product/B0C1234567?th=1'));
        self::assertSame('2070612759', Items::asin('https://www.amazon.fr/Harry-Potter/dp/2070612759'));
        self::assertNull(Items::asin('https://www.amazon.fr/s?k=poussette'));
        self::assertTrue(Items::isAmazon('https://amzn.eu/d/abc'));
        self::assertFalse(Items::isAmazon('https://notamazon.example/'));
    }
}
