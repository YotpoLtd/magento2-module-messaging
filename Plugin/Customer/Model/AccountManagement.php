<?php

namespace Yotpo\SmsBump\Plugin\Customer\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;

/**
 * AccountManagement - Save guest email on session
 */
class AccountManagement
{

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var Http
     */
    protected $request;

    /**
     * @param CheckoutSession $checkoutSession
     * @param Http $request
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        Http $request
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
    }

    /**
     * @param \Magento\Customer\Model\AccountManagement $subject
     * @param bool $result
     * @param string $customerEmail
     * @return bool
     * @throws LocalizedException
     */
    public function afterIsEmailAvailable(\Magento\Customer\Model\AccountManagement $subject, $result, $customerEmail)
    {
        if (stripos($this->request->getRequestString(), 'customers/isEmailAvailable') === false) {
            return $result;
        }
        $this->checkoutSession->setYotpoCustomerEmail($customerEmail);
        return $result;
    }
}
