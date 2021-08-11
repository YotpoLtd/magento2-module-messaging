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
     * Processor constructor.
     * @param Main $yotpoSyncMain
     * @param Config $yotpoSmsConfig
     * @param Data $checkoutData
     * @param Logger $yotpoSmsBumpLogger
     * @param SMShelper $smsHelper
     */
    public function __construct(
        Main $yotpoSyncMain,
        Config $yotpoSmsConfig,
        CheckoutData $checkoutData,
        YotpoSmsBumpLogger $yotpoSmsBumpLogger,
        SMShelper $smsHelper
    ) {
        $this->yotpoSyncMain = $yotpoSyncMain;
        $this->yotpoSmsConfig = $yotpoSmsConfig;
        $this->checkoutData = $checkoutData;
        $this->yotpoSmsBumpLogger = $yotpoSmsBumpLogger;
        $this->smsHelper = $smsHelper;
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
