<?php

namespace Yotpo\SmsBump\Model\Sync\Checkout;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Yotpo\SmsBump\Model\Sync\Main;
use Yotpo\SmsBump\Model\Config as YotpoMessagingConfig;
use Yotpo\SmsBump\Model\Sync\Checkout\Data as CheckoutData;
use Yotpo\SmsBump\Model\Sync\Checkout\Logger as YotpoCheckoutLogger;
use Yotpo\SmsBump\Helper\Data as MessagingDataHelper;
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
     * @var YotpoMessagingConfig
     */
    protected $yotpoMessagingConfig;

    /**
     * @var Data
     */
    protected $checkoutData;

    /**
     * @var Logger
     */
    protected $yotpoCheckoutLogger;

    /**
     * @var MessagingDataHelper
     */
    protected $messagingDataHelper;

    /**
     * @var CatalogProcessor
     */
    protected $catalogProcessor;

    const SYNC_RESULT_IS_SUCCESS_KEY = 'is_success';
    const SYNC_RESULT_STATUS_CODE_KEY = 'status';
    const SYNC_RESULT_RESPONSE_KEY = 'response';
    const SYNC_RESULT_REASON_KEY = 'reason';

    /**
     * Processor constructor.
     * @param Main $yotpoSyncMain
     * @param YotpoMessagingConfig $yotpoMessagingConfig
     * @param Data $checkoutData
     * @param Logger $yotpoCheckoutLogger
     * @param MessagingDataHelper $messagingDataHelper
     * @param CatalogProcessor $catalogProcessor
     */
    public function __construct(
        Main $yotpoSyncMain,
        YotpoMessagingConfig $yotpoMessagingConfig,
        CheckoutData $checkoutData,
        YotpoCheckoutLogger $yotpoCheckoutLogger,
        MessagingDataHelper $messagingDataHelper,
        CatalogProcessor $catalogProcessor
    ) {
        $this->yotpoSyncMain = $yotpoSyncMain;
        $this->yotpoMessagingConfig = $yotpoMessagingConfig;
        $this->checkoutData = $checkoutData;
        $this->yotpoCheckoutLogger = $yotpoCheckoutLogger;
        $this->messagingDataHelper = $messagingDataHelper;
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
        $isCheckoutSyncEnabled = $this->yotpoMessagingConfig->isCheckoutSyncActive();
        if ($isCheckoutSyncEnabled) {
            $newCheckoutData = $this->checkoutData->prepareData($quote);
            $this->yotpoCheckoutLogger->info('Checkout sync - data prepared', []);

            if (!$newCheckoutData) {
                $this->yotpoCheckoutLogger->info('Checkout sync - no new data to sync', []);
                return;
            }
            $productIds = $this->checkoutData->getLineItemsIds();
            if ($productIds) {
                $visibleItems = $quote->getAllVisibleItems();
                $storeId = $quote->getStoreId();
                $isProductSyncSuccess = $this->catalogProcessor->syncProducts($productIds, $visibleItems, $storeId);
                if (!$isProductSyncSuccess) {
                    $this->yotpoCheckoutLogger->info('Products sync failed in checkout', []);
                    return;
                }
            }

            $method = $this->yotpoMessagingConfig::PATCH_METHOD_STRING;
            $url = $this->yotpoMessagingConfig->getEndpoint('checkout');
            $newCheckoutData['entityLog'] = 'checkout';
            $syncCheckoutResult = $this->yotpoSyncMain->sync($method, $url, $newCheckoutData, 'api', true);
            if ($syncCheckoutResult->getData(self::SYNC_RESULT_IS_SUCCESS_KEY)) {
                $this->updateLastSyncDate();
                $this->yotpoCheckoutLogger->info('Checkout sync - success', []);
            } else {
                $this->logCheckoutSyncFailure($syncCheckoutResult);
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
        $this->yotpoMessagingConfig->saveConfig('checkout_last_sync_time', date('Y-m-d H:i:s'));
    }

    /**
     * @param DataObject $syncResult
     * @return void
     */
    private function logCheckoutSyncFailure($syncResult)
    {
        $statusCode = $syncResult->getData(self::SYNC_RESULT_STATUS_CODE_KEY);
        $failureReason = $syncResult->getData(self::SYNC_RESULT_REASON_KEY);
        $innerResponse = $syncResult->getData(self::SYNC_RESULT_RESPONSE_KEY);

        $this->yotpoCheckoutLogger->info('Checkout sync - failed', [$statusCode, $failureReason, $innerResponse]);
    }
}
