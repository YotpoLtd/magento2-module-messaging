<?php

namespace Yotpo\SmsBump\Plugin\Customer\Account;

use Magento\Customer\Model\Session;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\SessionException;
use Magento\Framework\UrlInterface;
use Yotpo\SmsBump\Model\AbandonedCart\Data as AbandonedCartData;

/**
 * Class LoginPost
 * Plugin for redirecting to payment page
 */
class LoginPost
{
    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var UrlInterface
     */
    protected $url;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var AbandonedCartData
     */
    protected $abandonedCartData;

    /**
     * LoginPost constructor.
     * @param RedirectFactory $resultRedirectFactory
     * @param UrlInterface $url
     * @param Session $session
     * @param AbandonedCartData $abandonedCartData
     */
    public function __construct(
        RedirectFactory $resultRedirectFactory,
        UrlInterface $url,
        Session $session,
        AbandonedCartData $abandonedCartData
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->url = $url;
        $this->session = $session;
        $this->abandonedCartData = $abandonedCartData;
    }

    /**
     * @param \Magento\Customer\Controller\Account\LoginPost $subject
     * @param Redirect $result
     * @return Redirect
     * @throws NoSuchEntityException
     * @throws SessionException
     */
    public function afterExecute(\Magento\Customer\Controller\Account\LoginPost $subject, Redirect $result)
    {
        if (!$this->session->getCustomerId()) {
            return $result;
        }

        $customRedirectionUrl = $this->url->getUrl('checkout', ['_fragment' => 'payment']);
        $yotpoToken = $this->abandonedCartData->getYotpoToken();
        if ($yotpoToken) {
            $isValidQuote = $this->abandonedCartData->setQuoteData($yotpoToken);
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setUrl($customRedirectionUrl);
            return $resultRedirect;
        }

        return $result;
    }
}
