<?php

namespace Yotpo\SmsBump\Model;

use Magento\Customer\Model\Context;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class CustomerSession extends Session
{

    /**
     * Logout customer
     *
     * @return $this
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @api
     */
    public function logoutCustomer()
    {
        if ($this->isLoggedIn()) {
            $this->_logout();
        }
        $this->_httpContext->unsValue(Context::CONTEXT_AUTH);
        return $this;
    }
}
