<?php

namespace Yotpo\SmsBump\Observer;

use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Yotpo\SmsBump\Model\Session;

class SalesQuoteProductAddAfter implements ObserverInterface
{
    protected Session $session;


    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        /**
         * @type QuoteItem[] $items
         */
        $items = $observer->getData('items');

        $this->saveAddedToCartEvents($items);
    }

    /**
     * Generate and store "product_added_to_cart" events in session
     *
     * @param QuoteItem[] $items
     * @return void
     */
    protected function saveAddedToCartEvents(array $items = []): void
    {
        $ts = microtime(true);

        $simpleProducts = array_filter(
            $items,
            fn($item) => $item->getProductType() === ProductType::TYPE_SIMPLE
        );

        $productsInCart = array_map(function ($item) use ($ts) {
            $parent = $item->getParentItem();
            $product = ($parent ?? $item)->getProduct();

            return [
                'id' => $product->getId(),
                'variant_id' => $parent ? $item->getProduct()->getId() : null,
                'page' => parse_url($product->getProductUrl(), PHP_URL_PATH),
                'timestamp' => $ts,
            ];
        }, $simpleProducts);

        if (!empty($productsInCart)) {
            $this->session->addProductsAddedToCart($productsInCart);
        }
    }
}
