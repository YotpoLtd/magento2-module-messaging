<?php

namespace Yotpo\SmsBump\Model\Sync\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Yotpo\SmsBump\Model\Sync\Main;
use Yotpo\SmsBump\Model\Config;
use Yotpo\SmsBump\Model\Sync\Checkout\Data as CheckoutData;
use Yotpo\SmsBump\Model\Sync\Checkout\Logger as YotpoSmsBumpLogger;
use Yotpo\SmsBump\Helper\Data as SMShelper;
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
     * @var SMShelper
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
     * @param SMShelper $smsHelper
     * @param CatalogProcessor $catalogProcessor
     */
    public function __construct(
        Main $yotpoSyncMain,
        Config $yotpoSmsConfig,
        CheckoutData $checkoutData,
        YotpoSmsBumpLogger $yotpoSmsBumpLogger,
        SMShelper $smsHelper,
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
        $isCheckoutSyncEnabled = $this->yotpoSmsConfig->getConfig('checkout_sync_active');
        if ($isCheckoutSyncEnabled) {
            $newCheckoutData = $this->checkoutData->prepareData($quote);
            $this->yotpoSmsBumpLogger->info('Checkout sync - data prepared', []);
            if (!$newCheckoutData) {
                $this->yotpoSmsBumpLogger->info('Checkout sync - no new data to sync', []);
                return;
            }
            $productIds = $this->checkoutData->getLineItemsIds();
            if ($productIds) {
                $isProductSyncSuccess = $this->checkAndSyncProducts($productIds, $quote);
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
                $this->yotpoSmsBumpLogger->info('Checkout sync - success', $newCheckoutData);
            } else {
                $this->yotpoSmsBumpLogger->info('Checkout sync - failed', $newCheckoutData);
            }
        }
    }

    /**
     * Check and sync the products if not already synced
     *
     * @param array <mixed> $productIds
     * @param Quote $quote
     * @return bool
     */
    public function checkAndSyncProducts($productIds, $quote)
    {
        $unSyncedProductIds = $this->checkoutData->getUnSyncedProductIds($productIds, $quote);
        if ($unSyncedProductIds) {
            return $this->catalogProcessor->processCheckoutProducts($unSyncedProductIds);
        }
        return true;
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
