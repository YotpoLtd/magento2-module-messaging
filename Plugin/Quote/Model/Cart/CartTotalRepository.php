<?php

namespace Yotpo\SmsBump\Plugin\Quote\Model\Cart;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\TotalsInterface as QuoteTotalsInterface;
use Magento\Quote\Model\Quote;
use Yotpo\SmsBump\Model\Sync\Checkout\Processor as CheckoutProcessor;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Cart\CartTotalRepository as CartRepository;
use Magento\Framework\App\RequestInterface;
use Yotpo\SmsBump\Model\Config;

/**
 * Class CartTotalRepository
 * Plugin to trigger checkout sync
 */
class CartTotalRepository
{
    /**
     * @var CheckoutProcessor
     */
    protected $checkoutProcessor;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Config
     */
    protected $yotpoSmsConfig;

    /**
     * CartTotalRepository constructor.
     * @param CheckoutProcessor $checkoutProcessor
     * @param CartRepositoryInterface $quoteRepository
     * @param RequestInterface $request
     * @param Config $yotpoSmsConfig
     */
    public function __construct(
        CheckoutProcessor $checkoutProcessor,
        CartRepositoryInterface $quoteRepository,
        RequestInterface $request,
        Config $yotpoSmsConfig
    ) {
        $this->checkoutProcessor = $checkoutProcessor;
        $this->quoteRepository = $quoteRepository;
        $this->request = $request;
        $this->yotpoSmsConfig = $yotpoSmsConfig;
    }

    /**
     * @param CartRepository $cartTotalRepository
     * @param QuoteTotalsInterface $result
     * @param int $cartId
     * @return QuoteTotalsInterface
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function afterGet(
        CartRepository $cartTotalRepository,
        QuoteTotalsInterface $result,
        int $cartId
    ) {
        if ($this->yotpoSmsConfig->isCheckoutSyncActive()) {
            /** @var Quote $quote */
            $quote = $this->quoteRepository->getActive($cartId);
            $this->checkoutProcessor->process($quote);
        }
        return $result;
    }
}
