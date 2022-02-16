<?php

namespace Yotpo\SmsBump\Controller\AbandonedCart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Yotpo\SmsBump\Model\CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Quote\Model\QuoteRepository;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Checkout\Model\DefaultConfigProvider;
use Yotpo\SmsBump\Model\Session as YotpoSmsBumpSession;
use Yotpo\SmsBump\Model\AbandonedCart\Data as AbandonedCartData;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;

/**
 * LoadCart - Reload cart and direct to checkout step
 */
class LoadCart implements ActionInterface
{
    const NO_ACTIVE_QUOTE = "Quote not found";
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var Onepage
     */
    protected $onepage;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var RedirectInterface
     */
    protected $redirect;

    /**
     * @var DefaultConfigProvider
     */
    protected $defaultConfigProvider;

    /**
     * @var YotpoSmsBumpSession
     */
    protected $yotpoSmsBumpSession;

    /**
     * @var AbandonedCartData
     */
    protected $abandonedCartData;

    /**
     * @var MessageManagerInterface
     */
    private $messageManager;

    /**
     * LoadCart constructor.
     * @param Context $context
     * @param Request $request
     * @param CheckoutSession $checkoutSession
     * @param QuoteRepository $quoteRepository
     * @param RedirectFactory $resultRedirectFactory
     * @param Onepage $onepage
     * @param CustomerSession $customerSession
     * @param DefaultConfigProvider $defaultConfigProvider
     * @param YotpoSmsBumpSession $yotpoSmsBumpSession
     * @param AbandonedCartData $abandonedCartData
     * @param MessageManagerInterface $messageManager
     */
    public function __construct(
        Context $context,
        Request $request,
        CheckoutSession $checkoutSession,
        QuoteRepository $quoteRepository,
        RedirectFactory $resultRedirectFactory,
        Onepage $onepage,
        CustomerSession $customerSession,
        DefaultConfigProvider $defaultConfigProvider,
        YotpoSmsBumpSession $yotpoSmsBumpSession,
        AbandonedCartData $abandonedCartData,
        MessageManagerInterface $messageManager
    ) {
        $this->redirect = $context->getRedirect();
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->onepage = $onepage;
        $this->customerSession = $customerSession;
        $this->defaultConfigProvider = $defaultConfigProvider;
        $this->yotpoSmsBumpSession = $yotpoSmsBumpSession;
        $this->abandonedCartData = $abandonedCartData;
        $this->messageManager = $messageManager;
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface|Page
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $yotpoQuoteToken = $this->request->getParam('yotpoQuoteToken', null);
        if ($yotpoQuoteToken === null) {
            $this->messageManager->addErrorMessage(self::NO_ACTIVE_QUOTE);
            return $this->resultRedirectFactory->create()->setPath('/');
        }

        $abandonedCartQuoteId = (int)$this->abandonedCartData->getQuoteId($yotpoQuoteToken);
        if (!$abandonedCartQuoteId) {
            $this->messageManager->addErrorMessage(self::NO_ACTIVE_QUOTE);
            return $this->resultRedirectFactory->create()->setPath('/');
        }

        $abandonedQuote = $this->quoteRepository->get($abandonedCartQuoteId);
        /** @phpstan-ignore-next-line */
        if (!$abandonedQuote || !$abandonedQuote->getId() || !$abandonedQuote->getIsActive()) {
            $this->messageManager->addErrorMessage(self::NO_ACTIVE_QUOTE);
            return $this->resultRedirectFactory->create()->setPath('/');
        }

        $customerSessionCustomerId = $this->customerSession->getId();
        $isDifferentCustomer = false;
        if ($customerSessionCustomerId) {
            $email = $this->customerSession->getCustomer()->getEmail();
            $abandonedQuoteEmail = $abandonedQuote->getCustomer()->getEmail();
            if ($email !== $abandonedQuoteEmail) {
                $isDifferentCustomer = true;
                $this->customerSession->logoutCustomer();
            }
        }

        /** @phpstan-ignore-next-line */
        if ($abandonedQuote->getCustomerId()) {
            $this->yotpoSmsBumpSession->start();
            $this->yotpoSmsBumpSession->setData('yotpoQuoteToken', $abandonedCartQuoteId);
            if ($isDifferentCustomer) {
                return $this->resultRedirectFactory->create()->setPath('customer/account/login');
            }
        }

        $isValidQuote = $this->abandonedCartData->setQuoteData($abandonedCartQuoteId);
        if (!$isValidQuote) {
            return $this->resultRedirectFactory->create()->setPath('/');
        }

        $this->checkoutSession->setQuoteId($abandonedCartQuoteId);
        $resultPage = $this->resultRedirectFactory->create()->setPath('checkout/cart');
        $resultPage->setHeader('Yotpo-Abandoned-Cart', 'true');
        return $resultPage;
    }
}
