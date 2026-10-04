---
title: The Creators API
order: 10
---

# omnitrade/amazon: the Creators API

## Installation

```sh
composer require omnitrade/amazon symfony/http-client
```

## Options

| Option | Default | |
|---|---|---|
| `client_id`, `client_secret` | none | the Creators API credentials (Associates Central → Tools → Creators API); without them only `affiliateLink()` |
| `partner_tag` | none | the Associates tag (`mysite-21`): in every request and every link |
| `marketplace` | `www.amazon.fr` | the store read; sent in the body and as the `x-marketplace` header |
| `credential_version` | `3.2` | which token endpoint: `3.1` `api.amazon.com`, `3.2` `api.amazon.co.uk`, `3.3` `api.amazon.co.jp` (`/auth/o2/token`) |
| `token_url` | none | another token endpoint (earlier, v2 credentials use regional ones) |
| `api_url` | `https://creatorsapi.amazon/catalog/v1` | |
| `timeout` | `10` | seconds |

## Requests

| Request | Call | |
|---|---|---|
| `AffiliateLink` | none | `https://<store>/dp/<ASIN>?tag=<tag>`: the ASIN from the id or from `/dp/`, `/gp/product/`, `/gp/aw/d/`, `/exec/obidos/ASIN/` in an Amazon address; the store of the address when it has one, else `marketplace`. Null without a tag or an ASIN (a shortened `amzn.eu` link names none). |
| `FetchProduct` | `POST {api_url}/getItems` | `itemIds: [ASIN]`, `itemIdType: ASIN`; null when Amazon has no such item |
| `FetchProducts` | `POST {api_url}/searchItems` | `keywords` = the query (required), `itemCount` = the limit (1 to 10). No cursor, no `updatedSince`: the page has no next. |

Each call carries `Authorization: Bearer <token>`, `x-marketplace`, and in
its JSON body `partnerTag`, `marketplace` and `resources` (lowerCamelCase):
`images.primary.large`, `images.variants.large`, `itemInfo.title`,
`itemInfo.byLineInfo`, `itemInfo.features`, `itemInfo.externalIds`,
`itemInfo.classifications`, `offersV2.listings.price`,
`offersV2.listings.availability`, `offersV2.listings.merchantInfo`,
`parentASIN`.

The token is an OAuth 2.0 client-credentials one (`grant_type`,
`client_id`, `client_secret`, `scope: creatorsapi::default`, as JSON), kept
in memory until a minute before it runs out (an hour).

## What is mapped

| Product | From |
|---|---|
| `reference`, `handle`, the variant's `sku` | `asin` |
| `title` | `itemInfo.title.displayValue` |
| `brand` | `itemInfo.byLineInfo.brand.displayValue` (else `manufacturer`) |
| `description` | `itemInfo.features.displayValues`, one per line |
| `url` | `detailPageURL` (it carries the tag) |
| `media` | `images.primary.large`, `images.variants[].large` (`url`, `width`, `height`) |
| offers | `offersV2.listings[]`: `price.money.amount` / `.currency`, `availability.type`, `merchantInfo.name` |
| `categories` | `itemInfo.classifications.productGroup.displayValue` |

## Not supported, and not verified

Purchases, stock, variations, browse nodes, paging a search, the "offers"
of other sellers beyond the listings returned: not offered - Amazon's
documentation read on 2026-10-04 (`get-started/using-curl`, the migration
guide from PA-API, `api-reference/operations/search-items`) shows the token
call, `getItems` and `searchItems` only in the detail used here. The tests
run on answers shaped as that documentation shows them; no call was made
with real credentials (the API is opened to accounts with qualified sales).
The field names of `externalIds` and `availability.type` are assumed from
PA-API 5's lowerCamelCase equivalents: check them on a first real answer
(`Product::$raw` keeps it whole).
