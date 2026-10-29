# omnitrade/amazon

Amazon for [omnisense/omnitrade](https://github.com/glitchr-omnisense/omnitrade), through the
**Creators API** - which replaced the Product Advertising API 5 on 2026-05-15 -: a product by its
ASIN or its page, a search, and the affiliate link carrying the Associates partner tag. Nothing
is bought here: no API lets a site buy for someone.

| Operation | Call |
|---|---|
| affiliate link | none: written from the ASIN and the tag |
| token | OAuth 2 client credentials: `POST https://api.amazon.co.uk/auth/o2/token` (credential version 3.2, Europe), `https://api.amazon.com/auth/o2/token` (3.1, North America), `https://api.amazon.co.jp/auth/o2/token` (3.3, Far East) |
| product | `POST https://creatorsapi.amazon/catalog/v1/getItems` |
| products (a search) | `POST https://creatorsapi.amazon/catalog/v1/searchItems` |

There is no sandbox: the API answers an account's real catalogue.

## Installation

```sh
composer require omnitrade/amazon
```

Requires PHP 8.2, `omnisense/omnitrade` (1.x) and `symfony/http-client` (6.4, 7 or 8). In a
Symfony application with `Omnitrade\Bridge\Symfony\OmnitradeBundle`, the factory is registered as
soon as the package is installed.

```php
$gateway = (new AmazonGatewayFactory($http))->create(['partner_tag' => 'mysite-21']);   // $http: the application's HTTP client; none given, the factory makes its own
```

## Credentials

- The **Associates partner tag** (`mysite-21`), from the Amazon Associates account of the store
  linked to: enough for the links.
- The **Creators API credentials**, a client id and a client secret (Associates Central → Tools →
  Creators API), with their **credential version** (3.1, 3.2 or 3.3, which names the token
  endpoint). Amazon opens the API only to accounts with qualified sales: until then, only
  `affiliateLink()` is offered, and the product can be read from its page with `omnitrade/web`.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `client_id`, `client_secret` | ?string | `null` | for the reads | the Creators API credentials; without both, `fetchProduct()` and `fetchProducts()` are not supported (`supports()` is false) |
| `partner_tag` | ?string | `null` | for links | the Associates tag (`mysite-21`): in every request and every link; without it `affiliateLink()` answers `null` |
| `marketplace` | string | `www.amazon.fr` | no | the store read; sent in the body and as the `x-marketplace` header; the host of a link to a bare ASIN |
| `credential_version` | string | `3.2` | no | which token endpoint: `3.1` `api.amazon.com`, `3.2` `api.amazon.co.uk`, `3.3` `api.amazon.co.jp` (`/auth/o2/token`); another value without `token_url` is an `InvalidConfigException` when the gateway is built |
| `token_url` | ?string | `null` | no | another token endpoint (earlier, v2 credentials use regional ones) |
| `api_url` | string | `https://creatorsapi.amazon/catalog/v1` | no | |
| `timeout` | int | `10` | no | seconds |

No option is required: `create([])` builds a gateway that answers every link `null`.

```yaml
omnitrade:
    gateways:
        amazon:
            factory: amazon
            options:
                client_id: '%env(AMAZON_CREATORS_CLIENT_ID)%'
                client_secret: '%env(AMAZON_CREATORS_CLIENT_SECRET)%'
                partner_tag: '%env(AMAZON_CREATORS_PARTNER_TAG)%'   # mysite-21
                marketplace: www.amazon.fr
                credential_version: '3.2'    # 3.1 North America, 3.2 Europe, 3.3 Far East
```

## Capabilities

| Request | Supported |
|---|---|
| `AffiliateLink` | yes, always |
| `FetchProduct` | with `client_id` and `client_secret` (`getItems`) |
| `FetchProducts` | with `client_id` and `client_secret`: a search (`searchItems`), a query required |
| payments, `FetchOrder`, `FetchInventory`, `Notify`, connected accounts, subscriptions | no |

## Behaviour

**Affiliate links.** No call. The ASIN is the reference itself when it is ten capitals and digits,
else the one in an Amazon address's `/dp/`, `/gp/product/`, `/gp/aw/d/`, `/exec/obidos/ASIN/` or
`/o/ASIN/`. The link is `https://<host>/dp/<ASIN>?tag=<tag>`: the host is the address's when it is
an Amazon store (`www.amazon.de` stays `www.amazon.de`), else `marketplace`; the tag is the
request's when given, else `partner_tag`. Everything else in the address (its path's title,
`ref=`, its query) is dropped. `null` without a tag, or for a reference that names no ASIN (a
shortened `amzn.eu` link names none).

**Token.** Asked for on the first read, not when the gateway is built: a JSON body with
`grant_type: client_credentials`, the client id and secret and `scope: creatorsapi::default`. It
is kept in the API client until a minute before it runs out (`expires_in`, an hour when Amazon does
not say).

**Product, search.** `getItems` with the ASIN (`itemIdType: ASIN`), or `searchItems` with the
query as `keywords` and the limit as `itemCount` (1 to 10); each body carries `partnerTag`,
`marketplace` and the resources asked for. A product's reference that names no ASIN answers
`null` without a call; an ASIN Amazon does not have, `null`. A search without a query is a
`ProviderException` (`query_required`); the cursor and `updatedSince` are not read and the page
has no next. What is sent and how an item becomes a `Product`:
[the Creators API](creators-api.md#omnitradeamazon-the-creators-api).

## Example

```php
<?php // amazon.php: links with the partner tag, then a product read through the Creators API, on answers shaped as its documentation shows them

require __DIR__.'/vendor/autoload.php';

use Omnitrade\Amazon\AmazonGatewayFactory;
use Omnitrade\Request\FetchProduct;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$item = [
    'asin' => 'B0C1234567',
    'detailPageURL' => 'https://www.amazon.fr/dp/B0C1234567?tag=mysite-21',
    'images' => ['primary' => ['large' => ['url' => 'https://m.media-amazon.com/images/I/1.jpg', 'width' => 500, 'height' => 500]]],
    'itemInfo' => [
        'title' => ['displayValue' => 'Tour de lit bébé en coton'],
        'byLineInfo' => ['brand' => ['displayValue' => 'Jollein']],
        'features' => ['displayValues' => ['Coton bio', 'Lavable à 30°']],
        'classifications' => ['productGroup' => ['displayValue' => 'Baby Product']],
    ],
    'offersV2' => ['listings' => [['price' => ['money' => ['amount' => 34.99, 'currency' => 'EUR']], 'availability' => ['type' => 'IN_STOCK'], 'merchantInfo' => ['name' => 'Amazon.fr', 'id' => 'A1X6FK5RDHNB96']]]],
];
$sent = [];
$http = new MockHttpClient(static function (string $method, string $url) use ($item, &$sent): MockResponse {
    $sent[] = $method.' '.$url;

    return new MockResponse(json_encode(match (true) {
        str_ends_with($url, '/auth/o2/token') => ['access_token' => 'Atc|not_a_real_token', 'token_type' => 'bearer', 'expires_in' => 3600],
        str_ends_with($url, '/getItems') => ['itemsResult' => ['items' => [$item]]],
    }));
});

// The tag alone: links, no call.
$links = (new AmazonGatewayFactory($http))->create(['partner_tag' => 'mysite-21']);
var_dump($links->supports(FetchProduct::class));
echo $links->affiliateLink('https://www.amazon.fr/Jollein-Tour-lit/dp/B0C1234567/ref=sr_1_3?keywords=tour'), "\n";
echo $links->affiliateLink('B0C1234567', tag: 'other-21'), "\n";
var_dump($links->affiliateLink('https://amzn.eu/d/abc123'));

// With the Creators API credentials: the product itself.
$amazon = (new AmazonGatewayFactory($http))->create(['partner_tag' => 'mysite-21', 'client_id' => 'not_a_real_id', 'client_secret' => 'not_a_real_secret']);
$product = $amazon->fetchProduct('https://www.amazon.fr/dp/B0C1234567');
printf("%s %s by %s, %s from %s, %s\n", $product->reference, $product->title, $product->brand, $product->price(), $product->variants[0]->offer()->merchant->name, implode(' / ', $product->categories));
echo $product->description, "\n", $product->url, "\n";
echo implode("\n", $sent), "\n";
```

```
$ php amazon.php
bool(false)
https://www.amazon.fr/dp/B0C1234567?tag=mysite-21
https://www.amazon.fr/dp/B0C1234567?tag=other-21
NULL
B0C1234567 Tour de lit bébé en coton by Jollein, 34.99 EUR from Amazon.fr, Baby Product
<ul><li>Coton bio</li><li>Lavable à 30°</li></ul>
https://www.amazon.fr/dp/B0C1234567?tag=mysite-21
POST https://api.amazon.co.uk/auth/o2/token
POST https://creatorsapi.amazon/catalog/v1/getItems
```

(run on 2026-10-25 in omnisense/omnitrade's harness image, PHP 8.4; with `HttpClient::create()`
and an eligible account's credentials the same lines read the real item.)

The programme's terms ask for a mention near the links ("En tant que Partenaire Amazon, je
réalise un bénéfice sur les achats remplissant les conditions requises" on amazon.fr) and for
prices shown with the time they were read, at most 24 hours old (the README). In the core's Docker
harness: `docker compose run --rm omnitrade link amazon B0C1234567`, `catalogue amazon
--query='tour de lit'`.

## Errors

Every failure is an `Omnitrade\Exception\ProviderException` whose `provider` is `amazon`:

- an HTTP status of 400 or more: the message is `errors[0].message` (or `Message`), `providerCode`
  its `code`, else the HTTP status;
- a body that is not JSON: `The Creators API answered HTTP <status> with a body that is not
  JSON.`; a network failure: `The Creators API did not answer: ...` (`The token endpoint did not
  answer: ...` for the token), the exception as `previous`;
- no token: Amazon's `error_description` or `error` (`No token (HTTP <status>).` when it says
  nothing), `providerCode` its `error` or the status;
- `query_required` for a search without a query; `no_credentials` when the API is reached without
  credentials (the gateway does not offer the reads then, so only an action given by hand gets
  there).

An unknown `credential_version` without `token_url` is an `InvalidConfigException` when the
gateway is built, even for a gateway that only writes links. Payments, `FetchOrder`,
`FetchInventory` and `Notify` raise `RequestNotSupportedException` before any call, and so do the
reads without credentials.

## Verified, and not

Tested offline, 16 tests on answers shaped as the Creators API documentation shows them:
`Tests/AmazonGatewayTest.php` (a product by its page, the token kept, the token endpoint of
version 3.2, the headers and the body; a search; without credentials only the link; ASINs in
addresses) and `Tests/AmazonContractTest.php`, the family's conformance suite with credentials
given (nothing to fix).

Not verified: no call was made with real credentials (the API is opened to accounts with
qualified sales). The field names of `externalIds` and `availability.type` are assumed from
PA-API 5's lowerCamelCase equivalents ([the Creators API](creators-api.md#not-supported-and-not-verified)).

## Responsibility

An independent, unofficial client, not affiliated with, endorsed by or sponsored by Amazon
(Amazon.com, Inc. and its affiliates). Whoever runs it complies with the Amazon Associates
Program's terms ([Associates Program Operating
Agreement](https://affiliate-program.amazon.com/help/operating/agreement)) and obtains what they
require: see the README's Responsibility section.
