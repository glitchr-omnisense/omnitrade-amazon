# omnitrade/amazon

Amazon for [glitchr/omnitrade](https://github.com/glitchr-studio/omnitrade), through the
**Creators API** - which replaced the Product Advertising API 5 on 2026-05-15 -: a product by
its ASIN or its page, a search, and the affiliate link carrying the Associates partner tag.

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

```php
$amazon = $registry->get('amazon');
$amazon->affiliateLink('https://www.amazon.fr/Jollein/dp/B0C1234567/ref=sr_1_3');   // https://www.amazon.fr/dp/B0C1234567?tag=mysite-21
$amazon->fetchProduct('B0C1234567');                 // getItems
$amazon->fetchProducts(query: 'tour de lit');        // searchItems, 10 items at most
```

Without credentials only `affiliateLink()` is offered (`supports(FetchProduct::class)` is
false): the tag alone writes the link, and the API is opened by Amazon only to accounts with
qualified sales. Nothing is bought here: no API lets a site buy for someone.

See [docs/creators-api.md](docs/creators-api.md). Amazon's programme requires the mention
"En tant que Partenaire Amazon, je réalise un bénéfice sur les achats remplissant les
conditions requises" near the links, and prices shown with the time they were read (at most
24 hours old).

License: LGPL-3.0-or-later.
