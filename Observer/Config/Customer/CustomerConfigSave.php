<?php

namespace Yotpo\SmsBump\Observer\Config\Customer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Yotpo\SmsBump\Model\Sync\Customers\Services\CustomersAttributesService;
use Yotpo\SmsBump\Model\Config;

/**
 * Class for Customer Config Save Observer
 */
class CustomerConfigSave implements ObserverInterface
{
    /**
     * Customer account is shared config path
     */
    const CUSTOMER_ACCOUNT_SHARE_CONFIG_PATH = 'customer/account_share/scope';

    /**
     * @param Config $config
     */
    protected $config;

    /**
     * @param Config $config
     * @param CustomersAttributesService $customersAttributesService
     */
    protected $customersAttributesService;

    public function __construct(
        Config $config,
        CustomersAttributesService $customersAttributesService
    ) {
        $this->config = $config;
        $this->customersAttributesService = $customersAttributesService;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $isCustomerAccountShareSettingChangedToEnabled = $this->checkCustomerAccountShareSettingChangedToEnabled($observer);
        if (!$isCustomerAccountShareSettingChangedToEnabled) {
            return;
        }

        $this->customersAttributesService->resetCustomersSyncedToYotpoAttribute();
    }

    /**
     * @param Observer $observer
     * @return bool
     */
    public function checkCustomerAccountShareSettingChangedToEnabled(Observer $observer)
    {
        $changedConfigurationsPaths = (array) $observer->getEvent()->getChangedPaths();
        if (in_array($this::CUSTOMER_ACCOUNT_SHARE_CONFIG_PATH, $changedConfigurationsPaths) &&
            $this->config->isCustomerAccountShared()
        ) {
            return true;
        }

        return false;
    }
}
