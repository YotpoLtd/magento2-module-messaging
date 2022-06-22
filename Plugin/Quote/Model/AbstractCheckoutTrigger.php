<?php

namespace Yotpo\SmsBump\Plugin\Quote\Model;

use Yotpo\SmsBump\Model\Sync\Checkout\Processor as CheckoutProcessor;
use Magento\Framework\Registry;
use Magento\Quote\Api\CartRepositoryInterface;
use Yotpo\SmsBump\Model\Config;
use Magento\Quote\Model\Quote as QuoteModel;
use Magento\Quote\Api\Data\AddressInterface;

/**
 * Class AbstractCheckoutTrigger
 * Abstract class for checkout sync trigger plugins
 */
class AbstractCheckoutTrigger
{
    /**
     * @var CheckoutProcessor
     */
    protected $checkoutProcessor;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var Config
     */
    protected $yotpoMessagingConfig;

    /**
     * @method __construct
     * @param CheckoutProcessor $checkoutProcessor
     * @param Registry $registry
     * @param CartRepositoryInterface $quoteRepository
     * @param Config $yotpoMessagingConfig
     */
    public function __construct(
        CheckoutProcessor $checkoutProcessor,
        Registry $registry,
        CartRepositoryInterface $quoteRepository,
        Config $yotpoMessagingConfig
    ) {
        $this->checkoutProcessor = $checkoutProcessor;
        $this->registry = $registry;
        $this->quoteRepository = $quoteRepository;
        $this->yotpoMessagingConfig = $yotpoMessagingConfig;
    }

    /**
     * @method checkoutSync
     * @param QuoteModel $quote
     */
    protected function checkoutSync(QuoteModel $quote)
    {
        if ($this->yotpoMessagingConfig->isCheckoutSyncActive()) {
            if (!$quote->getId()) {
                return;
            }

            $billingAddress = $quote->getBillingAddress();
            if (!$billingAddress->getCountryId()) {
                return;
            }

            $this->checkoutProcessor->process($quote);
        }
    }
}
