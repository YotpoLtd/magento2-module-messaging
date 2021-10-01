<?php

namespace Yotpo\SmsBump\Plugin\Customer\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\State;
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
     * @var State
     */
    protected $areaState;

    /**
     * @param CheckoutSession $checkoutSession
     * @param Http $request
     * @param State $areaState
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        Http $request,
        State $areaState
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->areaState = $areaState;
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
        if (Area::AREA_WEBAPI_REST !== $this->areaState->getAreaCode() ||
            stripos($this->request->getRequestString(), 'customers/isEmailAvailable') === false
        ) {
            return $result;
        }
        $this->checkoutSession->setYotpoCustomerEmail($customerEmail);
        return $result;
    }
}
