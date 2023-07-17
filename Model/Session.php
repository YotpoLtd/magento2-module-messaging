<?php

namespace Yotpo\SmsBump\Model;

use Magento\Framework\Session\SessionManager;

/**
 * Message session model
 *
 * @method bool hasProductsAddedToCart() Magic method, true if "products_added_to_cart" is set
 * @method mixed getProductsAddedToCart() Magic method, returns array of unclaimed "products_added_to_cart" events or null if none
 */
class Session extends SessionManager
{
    /**
     * Saves "product_added_to_cart" events in session
     *
     * @param array $productsInCart
     * @return void
     */
    public function addProductsAddedToCart(array $productsInCart): void
    {
        $sessionData = $this->getProductsAddedToCart() ?: [];

        array_push($sessionData, ...$productsInCart);

        $this->setProductsAddedToCart($sessionData);
    }
}
