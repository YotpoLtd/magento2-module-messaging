<?php

namespace Yotpo\SmsBump\Plugin\Quote\Model\Cart;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
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
     * @var UrlInterface
     */
    protected $urlInterface;

    /**
     * CartTotalRepository constructor.
     * @param CheckoutProcessor $checkoutProcessor
     * @param CartRepositoryInterface $quoteRepository
     * @param RequestInterface $request
     * @param Config $yotpoSmsConfig
     * @param UrlInterface $urlInterface
     */
    public function __construct(
        CheckoutProcessor $checkoutProcessor,
        CartRepositoryInterface $quoteRepository,
        RequestInterface $request,
        Config $yotpoSmsConfig,
        UrlInterface $urlInterface
    ) {
        $this->checkoutProcessor = $checkoutProcessor;
        $this->quoteRepository = $quoteRepository;
        $this->request = $request;
        $this->yotpoSmsConfig = $yotpoSmsConfig;
        $this->urlInterface = $urlInterface;
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
        $currentUrl = $this->urlInterface->getCurrentUrl();
        $allowedUrls = ['/shipping-information','/totals'];
        $allowedUrlsFromConfig = $this->yotpoSmsConfig->getConfig('checkout_sync_allowed_urls');
        if ($allowedUrlsFromConfig) {
            $allowedUrlConfigValues = explode(',', $allowedUrlsFromConfig);
            $allowedUrlConfigValues = array_map('trim', $allowedUrlConfigValues);
            $allowedUrls = array_merge($allowedUrls, $allowedUrlConfigValues);
        }
        $disAllowedUrls = ['/totals-information', '/checkout/cart/'];
        $urlFound = false;
        foreach ($disAllowedUrls as $disAllowedUrl) {
            if (stripos($currentUrl, $disAllowedUrl) !== false) {
                return $result;
            }
        }
        foreach ($allowedUrls as $allowedUrl) {
            if (stripos($currentUrl, $allowedUrl) !== false) {
                $urlFound = true;
                break;
            }
        }
        if (!$urlFound) {
            return $result;
        }
        if ($this->yotpoSmsConfig->isCheckoutSyncActive()) {
            /** @var Quote $quote */
            $quote = $this->quoteRepository->getActive($cartId);
            $this->checkoutProcessor->process($quote);
        }
        return $result;
    }
}
