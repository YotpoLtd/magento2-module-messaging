<?php

namespace Yotpo\SmsBump\Model;

use Magento\Framework\Session\Config\ConfigInterface;
use Magento\Framework\Session\SaveHandlerInterface;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\Session\SessionStartChecker;
use Magento\Framework\Session\SidResolverInterface;
use Magento\Framework\Session\StorageInterface;
use Magento\Framework\Session\ValidatorInterface;

/**
 * Message session model
 */
class Session extends SessionManager
{

    protected Logger\Main $_logger;

    public function __construct(
        \Magento\Framework\App\Request\Http                    $request,
        SidResolverInterface                                   $sidResolver,
        ConfigInterface                                        $sessionConfig,
        SaveHandlerInterface                                   $saveHandler,
        ValidatorInterface                                     $validator,
        StorageInterface                                       $storage,
        \Magento\Framework\Stdlib\CookieManagerInterface       $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Magento\Framework\App\State                           $appState,
        SessionStartChecker                                    $sessionStartChecker = null,
    )
    {
        parent::__construct($request, $sidResolver, $sessionConfig, $saveHandler, $validator, $storage, $cookieManager, $cookieMetadataFactory, $appState, $sessionStartChecker);
    }

    /**
     * Saves "product_added_to_cart" events in session
     *
     * @param array $events
     * @return void
     */
    public function addProductsAddedToCart(array $events)
    {
        $sessionData = $this->getProductsAddedToCart() ?: [];

        array_push($sessionData, ...$events);

        $this->setProductsAddedToCart($sessionData);
    }
}
