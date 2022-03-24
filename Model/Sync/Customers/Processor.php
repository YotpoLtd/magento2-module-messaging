<?php

namespace Yotpo\SmsBump\Model\Sync\Customers;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Yotpo\SmsBump\Model\Config;
use Yotpo\SmsBump\Model\ResourceModel\YotpoCustomersSync\CollectionFactory as YotpoCustomersSyncCollectionFactory;
use Yotpo\SmsBump\Model\Sync\Customers\Data as CustomersData;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerFactory;
use Yotpo\SmsBump\Model\Sync\Main as SmsBumpSyncMain;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Framework\App\ResourceConnection;
use Yotpo\SmsBump\Model\Sync\Customers\Logger as YotpoSmsBumpLogger;
use Magento\Customer\Model\Customer;
use Yotpo\SmsBump\Api\YotpoCustomersSyncRepositoryInterface;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as CronCollectionFactory;

/**
 * Class Processor - Process customers sync
 */
class Processor extends Main
{
    /**
     * @var SmsBumpSyncMain
     */
    protected $yotpoSyncMain;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var CustomersData
     */
    protected $data;

    /**
     * @var Logger
     */
    protected $yotpoSmsBumpLogger;

    /**
     * @var array <mixed>
     */
    protected $customerDataPrepared;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var bool
     */
    protected $isCommandLineSync = false;

    /**
     * @var YotpoCustomersSyncRepositoryInterface
     */
    protected $yotpoCustomersSyncRepositoryInterface;

    /**
     * @var array <int>
     */
    protected $customerSyncEnabledStores = [];

    /**
     * @var array <mixed>
     */
    protected $customersSyncedByStore = [];

    /**
     * @var bool
     */
    protected $customerRetrySync = false;

    /**
     * @var YotpoCustomersSyncCollectionFactory
     */
    protected $yotpoCustomersSyncCollectionFactory;

    /**
     * Processor constructor.
     * @param AppEmulation $appEmulation
     * @param ResourceConnection $resourceConnection
     * @param Config $yotpoSmsConfig
     * @param SmsBumpSyncMain $yotpoSyncMain
     * @param CustomerFactory $customerFactory
     * @param CustomersData $data
     * @param Logger $yotpoSmsBumpLogger
     * @param StoreManagerInterface $storeManager
     * @param YotpoCustomersSyncRepositoryInterface $yotpoCustomersSyncRepositoryInterface
     * @param CronCollectionFactory $cronCollectionFactory
     * @param YotpoCustomersSyncCollectionFactory $yotpoCustomersSyncCollectionFactory
     */
    public function __construct(
        AppEmulation $appEmulation,
        ResourceConnection $resourceConnection,
        Config $yotpoSmsConfig,
        SmsBumpSyncMain $yotpoSyncMain,
        CustomerFactory $customerFactory,
        CustomersData $data,
        YotpoSmsBumpLogger $yotpoSmsBumpLogger,
        StoreManagerInterface $storeManager,
        YotpoCustomersSyncRepositoryInterface $yotpoCustomersSyncRepositoryInterface,
        CronCollectionFactory $cronCollectionFactory,
        YotpoCustomersSyncCollectionFactory $yotpoCustomersSyncCollectionFactory
    ) {
        $this->yotpoSyncMain = $yotpoSyncMain;
        $this->customerFactory = $customerFactory;
        $this->yotpoSmsBumpLogger = $yotpoSmsBumpLogger;
        $this->storeManager= $storeManager;
        $this->yotpoCustomersSyncRepositoryInterface = $yotpoCustomersSyncRepositoryInterface;
        $this->yotpoCustomersSyncCollectionFactory = $yotpoCustomersSyncCollectionFactory;
        parent::__construct($appEmulation, $resourceConnection, $yotpoSmsConfig, $data, $cronCollectionFactory);
    }

    /**
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function processBackFillSync()
    {
        $this->setCustomerRetrySync(false);
        $jobCode = Config::YOTPO_CRON_JOB_CODE_CUSTOMERS_RETRY_SYNC;
        $isCustomerRetrySyncIsRunning = $this->checkCustomerSyncCronIsRunning($jobCode);
        if ($isCustomerRetrySyncIsRunning) {
            $this->yotpoSmsBumpLogger->info(
                __(
                    'Customer backfill sync is skipped because customer retry sync job is already running'
                ),
                []
            );
            return;
        }
        $this->process();
    }

    /**
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function processRetrySync()
    {
        $this->setCustomerRetrySync(true);
        $jobCode = Config::YOTPO_CRON_JOB_CODE_CUSTOMERS_BACKFILL_SYNC;
        $isCustomerBackFillSyncIsRunning = $this->checkCustomerSyncCronIsRunning($jobCode);
        if ($isCustomerBackFillSyncIsRunning) {
            $this->yotpoSmsBumpLogger->info(
                __(
                    'Customer retry sync is skipped because customer backfill sync job is already running'
                ),
                []
            );
            return;
        }
        $this->process();
    }

    /**
     * Process customers
     *
     * @param array <mixed> $retryCustomers
     * @param array <mixed> $storeIds
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function process($retryCustomers = [], $storeIds = [])
    {
        if (!$storeIds) {
            $storeIds = $this->config->getAllStoreIds(false);
        }
        $this->setCustomerSyncEnabledStores();
        /** @phpstan-ignore-next-line */
        foreach ($storeIds as $storeId) {
            if ($this->isCommandLineSync) {
                // phpcs:ignore
                echo 'Customers process started for store - ' .
                    $this->config->getStoreName($storeId) . PHP_EOL;
            }
            $this->emulateFrontendArea($storeId);
            if (!$this->config->isCustomerSyncActive()) {
                $this->yotpoSmsBumpLogger->info(
                    __(
                        'Customer sync is disabled for Magento Store ID: %1, Name: %2',
                        $storeId,
                        $this->config->getStoreName($storeId)
                    ),
                    []
                );
                if ($this->isCommandLineSync) {
                    // phpcs:ignore
                    echo 'Customer sync is disabled for store - ' .
                        $this->config->getStoreName($storeId) . PHP_EOL;
                }
                $this->stopEnvironmentEmulation();
                continue;
            }
            $this->yotpoSmsBumpLogger->info(
                __(
                    'Process customers for Magento Store ID: %1, Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                ),
                []
            );
            $retryCustomerIds = $retryCustomers[$storeId] ?? $retryCustomers;
            $this->processEntities($retryCustomerIds);
            $this->stopEnvironmentEmulation();
        }
        $this->stopEnvironmentEmulation();
    }

    /**
     * Process single customer
     *
     * @param Customer $customer
     * @param null|mixed $customerAddress
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @return void
     */
    public function processCustomer($customer, $customerAddress = null)
    {
        $this->setCustomerSyncEnabledStores();
        $isCustomerAccountShared = $this->config->isCustomerAccountShared();
        /** @phpstan-ignore-next-line */
        foreach ($this->config->getAllStoreIds(false) as $storeId) {
            $this->emulateFrontendArea($storeId);

            if (!$this->config->isCustomerSyncActive()) {
                $this->stopEnvironmentEmulation();
                continue;
            }

            if (!$isCustomerAccountShared &&
                $this->storeManager->getStore($storeId)->getWebsiteId() != $customer->getWebsiteId()
            ) {
                $this->stopEnvironmentEmulation();
                continue;
            }

            $this->yotpoSmsBumpLogger->info(
                __(
                    'Starting syncing Customer to Yotpo - Magento Store ID: %1, Name: %2, Customer ID: %3',
                    $storeId,
                    $this->config->getStoreName($storeId),
                    $customer->getId()
                )
            );
            $this->processSingleEntity($customer, $customerAddress);
            $this->stopEnvironmentEmulation();
        }
        $this->stopEnvironmentEmulation();
    }

    /**
     * Process single customer entity
     *
     * @param Customer $customer
     * @param null|mixed $customerAddress
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function processSingleEntity($customer, $customerAddress = null)
    {
        $currentTime = date('Y-m-d H:i:s');
        $this->doCustomerSyncActions($customer, $currentTime, true, $customerAddress);
        $this->updateLastSyncDate($currentTime);
    }

    /**
     * Process customer entities
     * @param array <mixed> $retryCustomerIds
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function processEntities($retryCustomerIds = [])
    {
        $storeId = $this->config->getStoreId();
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
        $currentTime = date('Y-m-d H:i:s');
        $batchSize = $this->config->getConfig('customers_sync_limit');
        $isCustomerAccountShared = $this->config->isCustomerAccountShared();
        if ($this->isCustomerRetrySync()) {
            $processLabel = 'retry sync';
        } else {
            $processLabel = 'backfill sync';
        }

        $customerCollectionData =
            $this->createCustomerCollection($retryCustomerIds, $isCustomerAccountShared, $websiteId, $batchSize);

        if (!count($customerCollectionData)) {
            $this->yotpoSmsBumpLogger->info(
                __(
                    'No customers that should be found for ' . $processLabel .'- Magento Store ID: %1, Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                )
            );
        } else {
            $this->yotpoSmsBumpLogger->info(
                __(
                    'Starting Customers ' . $processLabel . ' to Yotpo Cron job - Magento Store ID: %1, Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                )
            );

            foreach ($customerCollectionData as $customerData) {
                $this->doCustomerSyncActions($customerData, $currentTime);
            }

            $this->yotpoSmsBumpLogger->info(
                __(
                    'Finished Customers ' . $processLabel . ' to Yotpo Cron job - Magento Store ID: %1, Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                )
            );
        }

        $this->updateLastSyncDate($currentTime);
    }

    /**
     * @param Customer $customer
     * @param string $currentTime
     * @param bool $isRealTimeSync
     * @param null|mixed $customerAddress
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function doCustomerSyncActions($customer, $currentTime, $isRealTimeSync = false, $customerAddress = null)
    {
        $customerSyncData = [];
        $exception = false;
        $customerId = $customer->getId();
        $storeId = $this->config->getStoreId();
        $isCustomerAccountShared = $this->config->isCustomerAccountShared();
        try {
            $response = $this->syncCustomer($customer);
            if ($response) {
                $customerSyncData = $this->createCustomerSyncData($response, $customerId);
                $this->updateCustomersSyncedByStoreData($customerId, $storeId, $response);
                $this->updateCustomerSyncStatusAttribute($customerId, $storeId, $isCustomerAccountShared);
            }
        } catch (NoSuchEntityException | LocalizedException $e) {
            $this->writeToLogWithException($customerId, $e->getMessage());
            $exception = true;
            $customerSyncData = $this->createCustomerSyncDataOnException($customerId);
        }
        if ($customerSyncData) {
            $customerSyncData = $this->updateCustomerSyncData($customerSyncData, $currentTime);
            if ($exception) {
                $customerSyncData['should_retry'] = $this->isCustomerRetrySync() ? 0 : 1;
            }
            $this->insertOrUpdateCustomerSyncData($customerSyncData);
        }
    }
    /**
     * Calls customer sync api
     *
     * @param Customer $customer
     * @param bool $isRealTimeSync
     * @param null|mixed $customerAddress
     * @return array<mixed>|DataObject
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function syncCustomer($customer, $isRealTimeSync = false, $customerAddress = null)
    {
        $customerId = $customer->getId();
        $this->yotpoSmsBumpLogger->info(
            __(
                'Starting syncing Customer to Yotpo - Customer ID: %1',
                $customerId
            )
        );

        if (isset($this->customerDataPrepared[$customerId])) {
            $customerData = $this->customerDataPrepared[$customerId];
        } else {
            $customerData = $this->data->prepareData($customer, $isRealTimeSync, $customerAddress);
            $this->customerDataPrepared[$customerId] = $customerData;
        }
        if (!$customerData) {
            $this->yotpoSmsBumpLogger->info(
                __(
                    'Stopped syncing Customer to Yotpo - no new data to sync - Customer ID: %1',
                    $customerId
                )
            );
            return [];
        }

        $url = $this->config->getEndpoint('customers');
        $customerData['entityLog'] = 'customers';
        $response = $this->yotpoSyncMain->sync('PATCH', $url, $customerData);
        if ($response->getData('is_success')) {
            $this->yotpoSmsBumpLogger->info(
                __(
                    'Finished syncing Customer to Yotpo successfully - Customer ID: %1',
                    $customerId
                )
            );
        }

        return $response;
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
        $this->config->saveConfig('customers_last_sync_time', $currentTime);
    }

    /**
     * @param int $customerId
     * @return void
     */
    public function resetCustomerSyncAttributeStatus($customerId)
    {
        $this->insertOrUpdateCustomerSyncAttributeStatus($customerId, 0);
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @return void
     */
    public function retryCustomersSync()
    {
        $this->isCommandLineSync = true;
        $customerIds = [];
        $storeIds = [];
        $customerByStore = [];
        $items = $this->yotpoCustomersSyncRepositoryInterface->getByResponseCodes();
        foreach ($items as $item) {
            $customerIds[] = $item['customer_id'];
            $storeIds[] = $item['store_id'];
            $customerByStore[$item['store_id']][] = $item['customer_id'];
        }
        if ($customerIds) {
            $this->process($customerByStore, array_unique($storeIds));
        } else {
            // phpcs:ignore
            echo 'No customer data to process.' . PHP_EOL;
        }
    }

    /**
     * @param array<mixed> $customerSyncData
     * @param string $currentTime
     * @return array<mixed>
     */
    private function updateCustomerSyncData(array $customerSyncData, $currentTime)
    {
        $customerSyncData['store_id'] = $this->config->getStoreId();
        $customerSyncData['synced_to_yotpo'] = $currentTime;
        $customerSyncData['should_retry'] = (int) $this->config->shouldRetryCustomer(
            $customerSyncData['response_code']
        );
        return $customerSyncData;
    }

    /**
     * @param array<mixed> $retryCustomerIds
     * @param bool $customerAccountShared
     * @param string|int|null $websiteId
     * @param integer $batchSize
     * @return array<mixed>
     */
    private function createCustomerCollection(array $retryCustomerIds, $customerAccountShared, $websiteId, $batchSize)
    {
        $storeId = $this->config->getStoreId();

        if ($this->isCustomerRetrySync()) {
            $retryCustomerIds = $this->getCustomerIdsToRetry($batchSize);
            if (!$retryCustomerIds) {
                $this->yotpoSmsBumpLogger->info(
                    __(
                        'There are no customer records left to retry - Magento Store ID: %1, Name: %2',
                        $storeId,
                        $this->config->getStoreName($storeId)
                    )
                );
                return [];
            }
        }
        $attributeCode = Config::YOTPO_CUSTOM_ATTRIBUTE_SYNCED_TO_YOTPO_CUSTOMER;
        $customerCollection = $this->customerFactory->create();
        if (!$retryCustomerIds) {
            $customerCollection->addAttributeToFilter(
                [
                    ['attribute' => $attributeCode, 'null' => true],
                    ['attribute' => $attributeCode, 'eq' => '0'],
                ]
            );
        } else {
            $customerCollection->addFieldToFilter('entity_id', ['in' => $retryCustomerIds]);
        }
        if (!$customerAccountShared) {
            $customerCollection->addFieldToFilter('website_id', $websiteId);
        }
        $customerCollection->getSelect()->limit($batchSize);
        return $customerCollection->getItems();
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @return void
     */
    public function setCustomerSyncEnabledStores()
    {
        if ($this->customerSyncEnabledStores) {
            return;
        }
        $storeIds = $this->config->getAllStoreIds(false);
        if (!$storeIds) {
            return;
        }
        foreach ($storeIds as $storeId) {
            if ($this->config->isCustomerSyncActive($storeId)) {
                $this->customerSyncEnabledStores[] = $storeId;
            }
        }
    }

    /**
     * @param int $customerId
     * @param int $storeId
     * @param array<mixed>|DataObject $apiResponse
     * @return void
     */
    public function updateCustomersSyncedByStoreData($customerId, $storeId, $apiResponse)
    {
        if (!$apiResponse) {
            return;
        }
        $successFullResponseCodes = [Config::SUCCESS_RESPONSE_CODE, Config::CREATED_STATUS_CODE];
        if (is_object($apiResponse) &&
            in_array($apiResponse->getStatus(), $successFullResponseCodes)
        ) {
            $this->customersSyncedByStore[$customerId][] = $storeId;
        }
    }

    /**
     * @param int $customerId
     * @param int $storeId
     * @param boolean $isCustomerAccountShared
     * @return void
     */
    public function updateCustomerSyncStatusAttribute($customerId, $storeId, $isCustomerAccountShared)
    {
        if ($isCustomerAccountShared) {
            $storesToCheck = $this->customerSyncEnabledStores;
        } else {
            $storesToCheck = [$storeId];
        }
        if (!$storesToCheck) {
            return;
        }

        $customerSyncedByStoresSoFar = $this->customersSyncedByStore[$customerId] ?? [];
        $customersPendingToSyncByStores = array_diff($storesToCheck, $customerSyncedByStoresSoFar);
        if (!$customersPendingToSyncByStores) {
            $this->insertOrUpdateCustomerSyncAttributeStatus($customerId, 1);
            $this->yotpoSmsBumpLogger->info(
                __(
                    'Updated customer sync attribute value as 1 - Customer ID: %1',
                    $customerId
                )
            );
        }
    }

    /**
     * collect customer records that needs a retry
     * @param int $batchSize
     * @return array<mixed>
     * @throws NoSuchEntityException
     */
    public function getCustomerIdsToRetry($batchSize)
    {
        $storeId = $this->config->getStoreId();
        $customers = $this->yotpoCustomersSyncCollectionFactory->create();
        $customers
            ->addFieldToFilter('should_retry', '1')
            ->addFieldToFilter('store_id', "$storeId")
            ->addFieldToSelect('customer_id');
        $customers->getSelect()->limit($batchSize);
        return $customers->getColumnValues('customer_id');
    }

    /**
     * @return bool
     */
    public function isCustomerRetrySync()
    {
        return $this->customerRetrySync;
    }

    /**
     * @param bool $flag
     * @return void
     */
    public function setCustomerRetrySync($flag)
    {
        $this->customerRetrySync = $flag;
    }

    /**
     * @param int $customerId
     * @param string $exceptionMessage
     * @return void
     * @throws NoSuchEntityException
     */
    protected function writeToLogWithException($customerId, $exceptionMessage)
    {
        $storeId = $this->config->getStoreId();
        $storeName = $this->config->getStoreName($storeId);
        $this->yotpoSmsBumpLogger->info(
            __(
                'Failed to sync Customer to Yotpo -
                            Magento Store ID: %1,
                            Name: %2, CustomerID: %3,
                            Exception Message: %4',
                $storeId,
                $storeName,
                $customerId,
                $exceptionMessage
            )
        );
    }
}
