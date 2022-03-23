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
use Magento\Framework\App\State as AppState;
use Yotpo\SmsBump\Model\Sync\Customers\Services\CustomersAttributesService;

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
     * @var AppState
     */
    protected $appState;

    /**
     * @var CustomersAttributesService
     */
    protected $customersAttributesService;

    /**
     * CustomerSaveAfter constructor.
     * @param CustomersProcessor $customersProcessor
     * @param Config $yotpoSmsConfig
     * @param RequestInterface $request
     * @param Session $session
     * @param AppState $appState
     * @param CustomersAttributesService $customersAttributesService
     */
    public function __construct(
        CustomersProcessor $customersProcessor,
        Config $yotpoSmsConfig,
        RequestInterface $request,
        Session $session,
        AppState $appState,
        CustomersAttributesService $customersAttributesService
    ) {
        $this->customersProcessor = $customersProcessor;
        $this->yotpoSmsConfig = $yotpoSmsConfig;
        $this->request = $request;
        $this->session = $session;
        $this->appState = $appState;
        $this->customersAttributesService = $customersAttributesService;
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
        $isCustomerSyncActive = $this->yotpoSmsConfig->isCustomerSyncActive();
        if (!$this->request->getParam('custSync')) {
            $this->customersAttributesService->updateSyncedToYotpoCustomerAttribute($customer, false);

            $isCheckoutInProgress = $this->request->getParam('_checkout_in_progress', null);
            if ($isCustomerSyncActive && $isCheckoutInProgress === null) {
                $isActive = 1;
                /** @phpstan-ignore-next-line */
                $this->request->setParam('custSync', true);//to avoid multiple calls for a single save.
                /** @phpstan-ignore-next-line */
                $postValue = $this->request->getPost('customer', null);

                if ($this->appState->getAreaCode() == 'frontend') {
                    /** @var Customer $customer */
                    $customer->setData('is_active_yotpo', $isActive);
                } elseif (is_array($postValue) && isset($postValue['is_active'])) {
                    $isActive = $postValue['is_active'];
                    /** @var Customer $customer */
                    $customer->setData('is_active_yotpo', $isActive);
                }

                $this->customersProcessor->processCustomer($customer);
            }
        }
    }
}
