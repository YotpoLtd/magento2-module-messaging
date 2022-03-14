<?php

namespace Yotpo\SmsBump\Model\Sync\Checkout;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Yotpo\SmsBump\Model\Sync\Main;
use Yotpo\SmsBump\Model\Config;
use Yotpo\SmsBump\Model\Sync\Checkout\Data as CheckoutData;
use Yotpo\SmsBump\Model\Sync\Checkout\Logger as YotpoCheckoutLogger;
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
    protected $yotpoChekoutLogger;

    /**
     * @var SMSHelper
     */
    protected $smsHelper;

    /**
     * @var CatalogProcessor
     */
    protected $catalogProcessor;

    const PATCH_METHOD_STRING = 'PATCH';
    const IS_SUCCESS_MESSAGE_KEY = 'is_success';
    const STATUS_CODE_KEY = 'status';
    const RESPONSE_KEY = 'response';
    const REASON_KEY = 'reason';

    /**
     * Processor constructor.
     * @param Main $yotpoSyncMain
     * @param Config $yotpoSmsConfig
     * @param Data $checkoutData
     * @param Logger $yotpoChekoutLogger
     * @param SMSHelper $smsHelper
     * @param CatalogProcessor $catalogProcessor
     */
    public function __construct(
        Main $yotpoSyncMain,
        Config $yotpoSmsConfig,
        CheckoutData $checkoutData,
        YotpoCheckoutLogger $yotpoChekoutLogger,
        SMSHelper $smsHelper,
        CatalogProcessor $catalogProcessor
    ) {
        $this->yotpoSyncMain = $yotpoSyncMain;
        $this->yotpoSmsConfig = $yotpoSmsConfig;
        $this->checkoutData = $checkoutData;
        $this->yotpoChekoutLogger = $yotpoChekoutLogger;
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
            $this->yotpoChekoutLogger->info('Checkout sync - data prepared', []);

            if (!$newCheckoutData) {
                $this->yotpoChekoutLogger->info('Checkout sync - no new data to sync', []);
                return;
            }
            $productIds = $this->checkoutData->getLineItemsIds();
            if ($productIds) {
                $visibleItems = $quote->getAllVisibleItems();
                $storeId = $quote->getStoreId();
                $isProductSyncSuccess = $this->catalogProcessor->syncProducts($productIds, $visibleItems, $storeId);
                if (!$isProductSyncSuccess) {
                    $this->yotpoChekoutLogger->info('Products sync failed in checkout', []);
                    return;
                }
            }

            $method = self::PATCH_METHOD_STRING;
            $url = $this->yotpoSmsConfig->getEndpoint('checkout');
            $newCheckoutData['entityLog'] = 'checkout';
            $syncCheckoutResult = $this->yotpoSyncMain->sync($method, $url, $newCheckoutData, 'api', true);
            if ($syncCheckoutResult->getData(self::IS_SUCCESS_MESSAGE_KEY)) {
                $this->updateLastSyncDate();
                $this->yotpoChekoutLogger->info('Checkout sync - success', []);
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
        $this->yotpoSmsConfig->saveConfig('checkout_last_sync_time', date('Y-m-d H:i:s'));
    }

    /**
     * @param DataObject $syncResult
     * @return void
     */
    private function logCheckoutSyncFailure($syncResult)
    {
        $statusCode = $syncResult->getData(self::STATUS_CODE_KEY);
        $failureReason = $syncResult->getData(self::REASON_KEY);
        $innerResponse = $syncResult->getData(self::RESPONSE_KEY);

        $this->yotpoChekoutLogger->info('Checkout sync - failed', [$statusCode, $failureReason, $innerResponse]);
    }
}
