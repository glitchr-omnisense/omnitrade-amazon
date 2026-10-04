<?php

namespace Omnitrade\Amazon\Action;

use Omnitrade\Action\ActionInterface;
use Omnitrade\Action\ApiAwareInterface;
use Omnitrade\Action\ApiAwareTrait;
use Omnitrade\Amazon\Api;
use Omnitrade\Amazon\Items;
use Omnitrade\Exception\ProviderException;
use Omnitrade\Model\ProductPage;
use Omnitrade\Request\FetchProducts;
use Omnitrade\Request\Request;

/**
 * A search of Amazon's catalogue (searchItems, keywords, at most 10 items):
 * Amazon is not a catalogue to page through, so a query is required, and
 * neither a cursor nor updatedSince is read - the documentation shows no
 * paging; the page has no next.
 */
final class FetchProductsAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof FetchProducts;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof FetchProducts);
        if (null === $request->query || '' === trim($request->query)) {
            throw new ProviderException('amazon', 'Amazon is searched, not listed: give a query.', 'query_required');
        }
        $found = $this->api->searchItems(trim($request->query), $request->limit);
        $request->setResult(new ProductPage(array_map(fn (array $item) => Items::product($item, $this->api->marketplace()), $found['items']), null));
    }
}
