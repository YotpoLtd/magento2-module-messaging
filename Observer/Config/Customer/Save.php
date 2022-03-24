<?php

namespace Yotpo\SmsBump\Observer\Config\Customer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Yotpo\SmsBump\Model\Sync\Customers\CustomerSyncStatus;

/**
 * Class Save
 * Class for magento default customer configuration save events
 */
class Save implements ObserverInterface
{
    const CONFIG_PATH_CUSTOMER_ACCOUNT_SHARE = 'customer/account_share/scope';

    /**
     * @var CustomerSyncStatus
     */
    protected $customerSyncStatus;

    /**
     * @param CustomerSyncStatus $customerSyncStatus
     */
    public function __construct(
        CustomerSyncStatus $customerSyncStatus
    ) {
        $this->customerSyncStatus = $customerSyncStatus;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $customerAccountSettingChanged = $this->checkCustomerAccountSettingChanged($observer);
        if (!$customerAccountSettingChanged) {
            return;
        }
        $this->customerSyncStatus->resetCustomerSyncAttribute();
    }

    /**
     * @param Observer $observer
     * @return bool
     */
    public function checkCustomerAccountSettingChanged(Observer $observer)
    {
        $changedPaths = (array)$observer->getEvent()->getChangedPaths();
        return in_array(self::CONFIG_PATH_CUSTOMER_ACCOUNT_SHARE, $changedPaths);
    }
}
