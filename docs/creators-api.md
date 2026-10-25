---
title: The Creators API
order: 10
---

## omnitrade/amazon: the Creators API

What each read sends, and how an item becomes a `Product`. Installation, credentials and options:
at the head of this manual.

### Requests

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

### What is mapped

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
| the variant's `barcode` | `itemInfo.externalIds.eaNs.displayValues[0]` (else `eans`) |
| `merchant` | the store itself: `Amazon.fr` for `www.amazon.fr`, its URL `https://www.amazon.fr` |

Each item is one product with one variant (its reference the ASIN, its image the first picture),
`status` active, no stock, no options. Each listing with a price is an offer: the price from
`money.amount` (a decimal) and `money.currency`, `available` unless `availability.type` says out
of stock or `unavailable`, `url` the detail page, `merchant` the listing's `merchantInfo` (name,
id) or the store, `taxIncluded` true, no struck-through price. A listing without a price is left
out; an item without a title takes its ASIN as title. The images' `alt` is the title. The
description is the features as plain text, one per line, not HTML.

### Not supported, and not verified

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
