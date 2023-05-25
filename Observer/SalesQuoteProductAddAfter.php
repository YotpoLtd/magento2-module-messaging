<?php

namespace Yotpo\SmsBump\Observer;

use Magento\Catalog\Api\ProductRepositoryInterface\Proxy as ProductRepository;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Yotpo\SmsBump\Model\Session;

class SalesQuoteProductAddAfter implements ObserverInterface
{
    protected Session $_session;

    protected ProductRepository $_productRepository;

    public function __construct(
        Session           $session,
        ProductRepository $productRepositoryInterface
    )
    {
        $this->_session = $session;
        $this->_productRepository = $productRepositoryInterface;
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
    protected function saveAddedToCartEvents(array $items = []): void {
        $ts = microtime(true);

        $simpleProducts = array_filter(
            $items,
            fn ($item) => $item->getProductType() === ProductType::TYPE_SIMPLE
        );

        $events = array_map(function ($item) use ($ts) {
            $parent = $item->getParentItem();
            $product = ($parent ?? $item)->getProduct();

            return [
                'type' => 'event',
                'data' => [
                    'event' => 'product_added_to_cart',
                    'entity_type' => 'product',
                    'entity_id' => $product->getId(),
                    'entity_variant' => $parent ? $item->getProduct()->getId() : '',
                    'page' => parse_url($product->getProductUrl(), PHP_URL_PATH),
                    'timestamp' => $ts,
                ],
            ];
        }, $simpleProducts);

        if (!empty($events)) {
            $this->_session->addProductsAddedToCart($events);
        }
    }
}
