<?php

namespace Yotpo\SmsBump\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Yotpo\SmsBump\Model\Session;

/**
 * CustomerData implementations for our "yotposms-customer-behaviour" private content section
 */
class CustomerBehaviour implements SectionSourceInterface
{
    private CurrentCustomer $currentCustomer;

    public function __construct(CurrentCustomer $currentCustomer, Session $session)
    {
        $this->currentCustomer = $currentCustomer;
    }

    /**
     * @inheritDoc
     */
    public function getSectionData(): array
    {
        $customer_id = $this->currentCustomer->getCustomerId();

        return compact('customer_id');
    }
}
