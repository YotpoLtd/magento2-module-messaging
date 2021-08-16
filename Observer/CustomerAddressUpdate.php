<?php

namespace Yotpo\SmsBump\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Yotpo\SmsBump\Model\Sync\Customers\Processor as CustomersProcessor;
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
    protected $yotpoSmsConfig;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Session
     */
    protected $session;

    /**
     * CustomerAddressUpdate constructor.
     * @param CustomersProcessor $customersProcessor
     * @param Config $yotpoSmsConfig
     * @param RequestInterface $request
     * @param Session $session
     */
    public function __construct(
        CustomersProcessor $customersProcessor,
        Config $yotpoSmsConfig,
        RequestInterface $request,
        Session $session
    ) {
        $this->customersProcessor = $customersProcessor;
        $this->yotpoSmsConfig = $yotpoSmsConfig;
        $this->request = $request;
        $this->session = $session;
    }

    /**
     * @param Observer $observer
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $address = $observer->getCustomerAddress();
        if ($this->session->getDelegateGuestCustomer() && !$address->getDefaultBilling()) {
            return;
        } else {
            $this->session->unsDelegateGuestCustomer();
        }
        $customer = $address->getCustomer();
        $syncActive = $this->yotpoSmsConfig->isCustomerSyncActive(
            $address->getCustomer()->getStoreId(),
            ScopeInterface::SCOPE_STORE
        );
        if (!$this->request->getParam('custSync')) {
            $this->customersProcessor->forceUpdateCustomerSyncStatus(
                [$customer->getId()],
                $customer->getStoreId(),
                0,
                true
            );
            if ($syncActive) {
                $customerAddress = $address->getDefaultBilling() ? $address : null;
                if ($customerAddress) {
                    /** @phpstan-ignore-next-line */
                    $this->request->setParam('custSync', true);//to avoid multiple calls for a single save.
                    $this->customersProcessor->processCustomer($customer, $customerAddress);
                }
            }
        }
    }
}
