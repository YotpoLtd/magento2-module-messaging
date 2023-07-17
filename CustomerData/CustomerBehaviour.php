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

    private Session $customerBehaviourSession;

    public function __construct(CurrentCustomer $currentCustomer, Session $session)
    {
        $this->currentCustomer = $currentCustomer;

        $this->customerBehaviourSession = $session;
    }

    /**
     * @inheritDoc
     */
    public function getSectionData(): array
    {
        $customer_id = $this->currentCustomer->getCustomerId();

        $products_added_to_cart = $this->customerBehaviourSession->getData('products_added_to_cart', true);

        return compact('customer_id', 'products_added_to_cart');
    }
}
