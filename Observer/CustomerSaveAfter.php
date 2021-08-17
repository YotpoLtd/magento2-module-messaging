<?php

namespace Yotpo\SmsBump\Observer;

use Magento\Customer\Model\Customer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Yotpo\SmsBump\Model\Sync\Customers\Processor as CustomersProcessor;
use Yotpo\SmsBump\Model\Config;
use Magento\Framework\App\RequestInterface;
use Magento\Checkout\Model\Session;

/**
 * Class CustomerSaveAfter
 * Observer when customer data is updated
 */
class CustomerSaveAfter implements ObserverInterface
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
     * CustomerSaveAfter constructor.
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
        if ($this->session->getDelegateGuestCustomer()) {
            return;
        }
        $customer = $observer->getEvent()->getCustomer();
        $syncActive = $this->yotpoSmsConfig->isCustomerSyncActive();
        if (!$this->request->getParam('custSync')) {
            $this->customersProcessor->forceUpdateCustomerSyncStatus(
                [$customer->getId()],
                $customer->getStoreId(),
                0,
                true
            );
            if ($syncActive) {
                /** @phpstan-ignore-next-line */
                $this->request->setParam('custSync', true);//to avoid multiple calls for a single save.
                $checkoutInProgress = $this->request->getParam('_checkout_in_progress', null);
                if ($checkoutInProgress === null) {
                    /** @var Customer $customer */
                    $this->customersProcessor->processCustomer($customer);
                }
            }
        }
    }
}
