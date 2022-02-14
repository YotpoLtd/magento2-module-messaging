<?php

namespace Yotpo\SmsBump\Model\Sync\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Yotpo\SmsBump\Model\Sync\Main;
use Yotpo\SmsBump\Model\Config;
use Yotpo\SmsBump\Model\Sync\Checkout\Data as CheckoutData;
use Yotpo\SmsBump\Model\Sync\Checkout\Logger as YotpoSmsBumpLogger;
use Yotpo\SmsBump\Helper\Data as SMSHelper;
use Yotpo\Core\Model\Sync\Catalog\Processor as CatalogProcessor;

/**
 * Class Processor - Process checkout sync
 */
class Processor
{

    /**
     * @var Main
     */
    protected $yotpoSyncMain;

    /**
     * @var Config
     */
    protected $yotpoSmsConfig;

    /**
     * @var Data
     */
    protected $checkoutData;

    /**
     * @var Logger
     */
    protected $yotpoSmsBumpLogger;

    /**
     * @var SMSHelper
     */
    protected $smsHelper;

    /**
     * @var CatalogProcessor
     */
    protected $catalogProcessor;

    /**
     * Processor constructor.
     * @param Main $yotpoSyncMain
     * @param Config $yotpoSmsConfig
     * @param Data $checkoutData
     * @param Logger $yotpoSmsBumpLogger
     * @param SMSHelper $smsHelper
     * @param CatalogProcessor $catalogProcessor
     */
    public function __construct(
        Main $yotpoSyncMain,
        Config $yotpoSmsConfig,
        CheckoutData $checkoutData,
        YotpoSmsBumpLogger $yotpoSmsBumpLogger,
        SMSHelper $smsHelper,
        CatalogProcessor $catalogProcessor
    ) {
        $this->yotpoSyncMain = $yotpoSyncMain;
        $this->yotpoSmsConfig = $yotpoSmsConfig;
        $this->checkoutData = $checkoutData;
        $this->yotpoSmsBumpLogger = $yotpoSmsBumpLogger;
        $this->smsHelper = $smsHelper;
        $this->catalogProcessor = $catalogProcessor;
    }

    /**
     * Process checkout sync
     *
     * @param Quote $quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @return void
     */
    public function process(Quote $quote)
    {
        $isCheckoutSyncEnabled = $this->yotpoSmsConfig->isCheckoutSyncActive();
        if ($isCheckoutSyncEnabled) {
            $newCheckoutData = $this->checkoutData->prepareData($quote);
            $this->yotpoSmsBumpLogger->info('Checkout sync - data prepared', []);

            if (!$newCheckoutData) {
                $this->yotpoSmsBumpLogger->info('Checkout sync - no new data to sync', []);
                return;
            }
            $productIds = $this->checkoutData->getLineItemsIds();
            if ($productIds) {
                $isProductSyncSuccess = $this->catalogProcessor->syncProducts($productIds, $quote);
                if (!$isProductSyncSuccess) {
                    $this->yotpoSmsBumpLogger->info('Products sync failed in checkout', []);
                    return;
                }
            }
            $url = $this->yotpoSmsConfig->getEndpoint('checkout');
            $newCheckoutData['entityLog'] = 'checkout';
            $sync = $this->yotpoSyncMain->sync('PATCH', $url, $newCheckoutData);
            if ($sync->getData('is_success')) {
                $this->updateLastSyncDate();
                $this->yotpoSmsBumpLogger->info('Checkout sync - success', []);
            } else {
                $this->yotpoSmsBumpLogger->info('Checkout sync - failed', []);
            }
        }
    }

    /**
     * Updates the last sync date to the database
     *
     * @throws NoSuchEntityException
     * @return void
     */
    public function updateLastSyncDate()
    {
        $this->yotpoSmsConfig->saveConfig('checkout_last_sync_time', date('Y-m-d H:i:s'));
    }
}
