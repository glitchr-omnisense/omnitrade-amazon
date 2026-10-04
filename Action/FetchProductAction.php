<?php

namespace Omnitrade\Amazon\Action;

use Omnitrade\Action\ActionInterface;
use Omnitrade\Action\ApiAwareInterface;
use Omnitrade\Action\ApiAwareTrait;
use Omnitrade\Amazon\Api;
use Omnitrade\Amazon\Items;
use Omnitrade\Request\FetchProduct;
use Omnitrade\Request\Request;

/** A product by its ASIN or an Amazon page naming one (getItems); null when it names none or Amazon has none. */
final class FetchProductAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof FetchProduct;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof FetchProduct);
        $asin = Items::asin((string) $request->reference);
        if (null === $asin) {
            $request->setResult(null);

            return;
        }
        $items = $this->api->getItems([$asin]);
        $request->setResult($items ? Items::product($items[0], $this->api->marketplace()) : null);
    }
}
