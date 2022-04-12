<?php

namespace Yotpo\SmsBump\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Model\Customer;
use Yotpo\SmsBump\Model\Sync\Customers\Processor as CustomersProcessor;
use Yotpo\SmsBump\Model\Sync\Customers\Services\CustomersAttributesService;
use Yotpo\SmsBump\Model\Config;
use Magento\Framework\App\RequestInterface;
use Magento\Checkout\Model\Session;

/**
 * Class CustomerAddressUpdate
 * Observer when customer address is added/updated
 */
class CustomerAddressUpdate implements ObserverInterface
{
    /**
     * @var CustomersProcessor
     */
    protected $customersProcessor;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var CustomersAttributesService
     */
    protected $customersAttributesService;

    /**
     * CustomerAddressUpdate constructor.
     * @param CustomersProcessor $customersProcessor
     * @param Config $config
     * @param RequestInterface $request
     * @param Session $session
     * @param CustomersAttributesService $customersAttributesService
     */
    public function __construct(
        CustomersProcessor $customersProcessor,
        Config $config,
        RequestInterface $request,
        Session $session,
        CustomersAttributesService $customersAttributesService
    ) {
        $this->customersProcessor = $customersProcessor;
        $this->config = $config;
        $this->request = $request;
        $this->session = $session;
        $this->customersAttributesService = $customersAttributesService;
    }

    /**
     * @param Observer $observer
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $customerAddress = $observer->getCustomerAddress();
        if ($this->session->getDelegateGuestCustomer() && !$customerAddress->getDefaultBilling()) {
            return;
        } else {
            $this->session->unsDelegateGuestCustomer();
        }
        $customer = $customerAddress->getCustomer();
        $customerStoreId = $customer->getStoreId();
        $isCustomerSyncActive = $this->config->isCustomerSyncActive($customerStoreId);

        if (!$this->request->getParam('custSync')) {
            $this->customersAttributesService->updateSyncedToYotpoCustomerAttribute($customer, 0);

            $customerAddress = $customerAddress->getDefaultBilling() ? $customerAddress : null;
            if ($isCustomerSyncActive && $customerAddress) {
                /** @phpstan-ignore-next-line */
                $this->request->setParam('custSync', true);//to avoid multiple calls for a single save.
                $this->customersProcessor->processCustomer($customer, $customerAddress);
            }
        }
    }
}
