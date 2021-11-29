<?php

namespace Yotpo\SmsBump\Model\Sync\Subscription;

use Magento\Framework\DataObject;
use Yotpo\SmsBump\Model\Config;
use Yotpo\Core\Model\AbstractJobs;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Yotpo\SmsBump\Model\Sync\Main as SmsBumpSyncMain;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Yotpo\SmsBump\Model\Sync\Subscription\Logger as YotpoSmsBumpLogger;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class Processor - Process subscription sync
 */
class Processor extends AbstractJobs
{
    /**
     * @var Config
     */
    protected $yotpoSmsConfig;

    /**
     * @var SmsBumpSyncMain
     */
    protected $yotpoSyncMain;

    /**
     * @var YotpoSmsBumpLogger
     */
    protected $yotpoSmsBumpLogger;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var array <mixed>
     */
    protected $messages = ['success' => [], 'error' => []];

    /**
     * Processor constructor.
     * @param AppEmulation $appEmulation
     * @param ResourceConnection $resourceConnection
     * @param Config $yotpoSmsConfig
     * @param SmsBumpSyncMain $yotpoSyncMain
     * @param YotpoSmsBumpLogger $yotpoSmsBumpLogger
     * @param SerializerInterface $serializer
     */
    public function __construct(
        AppEmulation $appEmulation,
        ResourceConnection $resourceConnection,
        Config $yotpoSmsConfig,
        SmsBumpSyncMain $yotpoSyncMain,
        YotpoSmsBumpLogger $yotpoSmsBumpLogger,
        SerializerInterface $serializer
    ) {
        $this->yotpoSyncMain = $yotpoSyncMain;
        $this->yotpoSmsBumpLogger = $yotpoSmsBumpLogger;
        $this->yotpoSmsConfig = $yotpoSmsConfig;
        $this->serializer = $serializer;
        parent::__construct($appEmulation, $resourceConnection);
    }

    /**
     * Process subscription
     * @param array <mixed> $storeIds
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function process($storeIds = [])
    {
        if (!$storeIds) {
            $storeIds = $this->yotpoSmsConfig->getAllStoreIds(false);
        }
        /** @phpstan-ignore-next-line */
        foreach ($storeIds as $storeId) {
            $this->emulateFrontendArea($storeId);
            if (!$this->yotpoSmsConfig->isEnabled()) {
                $this->addMessage(
                    'error',
                    'Yotpo is disabled for Store  - ' . $this->yotpoSmsConfig->getStoreName($storeId)
                );
                $this->stopEnvironmentEmulation();
                continue;
            }
            $this->yotpoSmsBumpLogger->info(
                'Process subscription for the store : ' . $this->yotpoSmsConfig->getStoreName($storeId),
                []
            );

            $this->processSubscription();
            $this->stopEnvironmentEmulation();
        }
    }

    /**
     * Process subscription
     *
     * @return void
     * @throws NoSuchEntityException
     */
    public function processSubscription()
    {
        $storeId = $this->yotpoSmsConfig->getStoreId();
        $currentTime = date('Y-m-d H:i:s');
        //call to API
        $response = $this->syncSubscriptionForms();
        $storeCode = $this->yotpoSmsConfig->getStoreName($storeId);
        $this->updateLastSyncDate($currentTime);
        if ($response->getData('is_success')) {
            $responseData = $response->getData('response');
            $serializedData = $this->serializer->serialize($responseData);
            $this->yotpoSmsConfig->saveConfig('sync_forms_data', (string)$serializedData);
            $this->yotpoSmsBumpLogger->info('Subscription forms sync - success', []);
            $this->addMessage(
                'success',
                'Subscription forms are synced successfully for Store - ' . $storeCode
            );
        } else {
            $this->addMessage(
                'error',
                'Store not found at API for Store - ' . $storeCode
            );
        }
    }

    /**
     * Api call to get all published forms
     *
     * @return DataObject
     * @throws NoSuchEntityException
     */
    public function syncSubscriptionForms()
    {
        $url = $this->yotpoSmsConfig->getEndpoint('subscription');
        $data['entityLog'] = 'subscription';
        $data['status'] = 'published';
        return $this->yotpoSyncMain->sync($this->yotpoSmsConfig::METHOD_GET, $url, $data, 'api_messaging');
    }

    /**
     * Updates the last sync date to the database
     *
     * @param string $currentTime
     * @return void
     * @throws NoSuchEntityException
     */
    public function updateLastSyncDate($currentTime)
    {
        $this->yotpoSmsConfig->saveConfig('sms_subscription_last_sync_time', $currentTime);
    }

    /**
     * @param string $flag
     * @param string $message
     * @return void
     */
    public function addMessage($flag, $message = '')
    {
        if ($flag == 'success') {
            $this->messages['success'][] = $message;
        }
        if ($flag == 'error') {
            $this->messages['error'][] = $message;
        }
    }

    /**
     * @return array <mixed>
     */
    public function getMessages()
    {
        return $this->messages;
    }
}
