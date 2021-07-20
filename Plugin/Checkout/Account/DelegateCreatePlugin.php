<?php

namespace Yotpo\SmsBump\Plugin\Checkout\Account;

use Magento\Checkout\Controller\Account\DelegateCreate;
use Magento\Checkout\Model\Session;

/**
 * Check if guest customer registration is executed.
 */
class DelegateCreatePlugin
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @param Session $session
     */
    public function __construct(
        Session $session
    ) {
        $this->session = $session;
    }

    /**
     * @param DelegateCreate $subject
     * @return mixed
     */
    public function beforeExecute($subject)
    {
        $this->session->setDelegateGuestCustomer(true);
        return null;
    }
}
