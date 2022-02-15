<?php

namespace Yotpo\SmsBump\Model\Sync\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Yotpo\SmsBump\Model\Sync\Main;
use Yotpo\SmsBump\Model\Config;
use Yotpo\SmsBump\Model\Sync\Checkout\Data as CheckoutData;
use Yotpo\SmsBump\Model\Sync\Checkout\Logger as YotpoLogger;
use Yotpo\SmsBump\Helper\Data as SMSHelper;
use Yotpo\Core\Model\Sync\Catalog\Processor as CatalogProcessor;
use Yotpo\Core\Http\YotpoRetry;

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
    protected $yotpoConfig;

    /**
     * @var Data
     */
    protected $checkoutData;

    /**
     * @var Logger
     */
    protected $yotpoLogger;

    /**
     * @var SMSHelper
     */
    protected $smsHelper;

    /**
     * @var CatalogProcessor
     */
    protected $catalogProcessor;

    /**
     * @var YotpoRetry
     */
    protected $yotpoRetry;

    /**
     * Processor constructor.
     * @param Main $yotpoSyncMain
     * @param Config $yotpoSmsConfig
     * @param Data $checkoutData
     * @param Logger $yotpoLogger
     * @param SMSHelper $smsHelper
     * @param CatalogProcessor $catalogProcessor
     * @param YotpoRetry $yotpoRetry
     */
    public function __construct(
        Main $yotpoSyncMain,
        Config $yotpoSmsConfig,
        CheckoutData $checkoutData,
        YotpoLogger $yotpoLogger,
        SMSHelper $smsHelper,
        CatalogProcessor $catalogProcessor,
        YotpoRetry $yotpoRetry
    ) {
        $this->yotpoSyncMain = $yotpoSyncMain;
        $this->yotpoConfig = $yotpoSmsConfig;
        $this->checkoutData = $checkoutData;
        $this->yotpoLogger = $yotpoLogger;
        $this->smsHelper = $smsHelper;
        $this->catalogProcessor = $catalogProcessor;
        $this->yotpoRetry = $yotpoRetry;
    }

    const IS_SUCCESS_MESSAGE_KEY = 'is_success';
    const STATUS_CODE_KEY = 'status';
    const RESPONSE_KEY = 'response';
    const REASON_KEY = 'reason';

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
        $isCheckoutSyncEnabled = $this->yotpoConfig->isCheckoutSyncActive();
        if ($isCheckoutSyncEnabled) {
            $newCheckoutData = $this->checkoutData->prepareData($quote);
            $this->yotpoLogger->info('Checkout sync - data prepared', []);

            if (!$newCheckoutData) {
                $this->yotpoLogger->info('Checkout sync - no new data to sync', []);
                return;
            }
            $productIds = $this->checkoutData->getLineItemsIds();
            if ($productIds) {
                $visibleItems = $quote->getAllVisibleItems();
                $storeId = $quote->getStoreId();
                $isProductSyncSuccess = $this->catalogProcessor->syncProducts($productIds, $visibleItems, $storeId);
                if (!$isProductSyncSuccess) {
                    $this->yotpoLogger->error('Products sync failed in checkout', []);
                    return;
                }
            }

            $method = 'PATCH';
            $url = $this->yotpoConfig->getEndpoint('checkout');
            $newCheckoutData['entityLog'] = 'checkout';
            $syncFunction = call_user_func_array('syncCheckout', array($method, $url, $newCheckoutData));
            $syncResult = $this->yotpoRetry->retryRequest($syncFunction);
            if ($syncResult->getData(self::IS_SUCCESS_MESSAGE_KEY)) {
                $this->updateLastSyncDate();
                $this->yotpoLogger->info('Checkout sync - success', []);
            } else {
                $this->logCheckoutSyncFailure($syncResult);
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
        $this->yotpoConfig->saveConfig('checkout_last_sync_time', date('Y-m-d H:i:s'));
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $newCheckoutData
     * @return \Magento\Framework\DataObject
     */
    private function syncCheckout($method, $url, array $newCheckoutData)
    {
        return $this->yotpoSyncMain->sync($method, $url, $newCheckoutData);
    }

    /**
     * @param $syncResult
     * @return void
     */
    private function logCheckoutSyncFailure($syncResult)
    {
        $statusCode = $syncResult->getData(self::STATUS_CODE_KEY);
        $failureReason = $syncResult->getData(self::REASON_KEY);
        $innerResponse = $syncResult->getData(self::RESPONSE_KEY);

        $this->yotpoLogger->error('Checkout sync - failed', [$statusCode, $failureReason, $innerResponse]);
    }
}
