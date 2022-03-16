<?php

namespace Yotpo\SmsBump\Model\Sync\Customers;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Yotpo\SmsBump\Model\Config;
use Yotpo\SmsBump\Model\Sync\Customers\Data as CustomersData;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerFactory;
use Yotpo\SmsBump\Model\Sync\Main as SmsBumpSyncMain;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Framework\App\ResourceConnection;
use Yotpo\SmsBump\Model\Sync\Customers\Logger as YotpoSmsBumpLogger;
use Magento\Customer\Model\Customer;
use Yotpo\SmsBump\Api\YotpoCustomersSyncRepositoryInterface;

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
        YotpoCustomersSyncRepositoryInterface $yotpoCustomersSyncRepositoryInterface
    ) {
        $this->yotpoSyncMain = $yotpoSyncMain;
        $this->customerFactory = $customerFactory;
        $this->yotpoSmsBumpLogger = $yotpoSmsBumpLogger;
        $this->storeManager= $storeManager;
        $this->yotpoCustomersSyncRepositoryInterface = $yotpoCustomersSyncRepositoryInterface;
        parent::__construct($appEmulation, $resourceConnection, $yotpoSmsConfig, $data);
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
     */
    public function processSingleEntity($customer, $customerAddress = null)
    {
        $customerId = $customer->getId();
        $currentTime = date('Y-m-d H:i:s');
        $storeId = $this->config->getStoreId();
        try {
            $this->resetCustomerSyncStatus($customerId, $storeId, 0);

            $customerSyncToYotpoResponse = $this->syncCustomer($customer, true, $customerAddress);
            if ($customerSyncToYotpoResponse) {
                $customerSyncData = $this->createCustomerSyncData($customerSyncToYotpoResponse, $customerId);
                $this->updateLastSyncDate($currentTime);
                $customerSyncData = $this->updateCustomerSyncData($customerSyncData, $currentTime);
                $this->insertOrUpdateCustomerSyncData($customerSyncData);
                $this->yotpoSmsBumpLogger->info(
                    __(
                        'Finished syncing Customer to Yotpo - Magento Store ID: %1, Name: %2, CustomerID: %3',
                        $storeId,
                        $this->config->getStoreName($storeId),
                        $customerId
                    )
                );
            }
        } catch (NoSuchEntityException | LocalizedException $e) {
            $this->yotpoSmsBumpLogger->info(
                __(
                    'Failed to sync Customer to Yotpo -
                    Magento Store ID: %1,
                    Name: %2, CustomerID: %3,
                    Exception Message: %4',
                    $storeId,
                    $this->config->getStoreName($storeId),
                    $customerId,
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Process customer entities
     *
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

        $customerCollectionWithYotpoDataQuery =
            $this->createCustomerCollectionWithYotpoSyncDataQuery(
                $storeId,
                $retryCustomerIds,
                $isCustomerAccountShared,
                $websiteId,
                $batchSize
            );
        $customerCollectionWithYotpoSyncData = $customerCollectionWithYotpoDataQuery->getItems();

        if (!count($customerCollectionWithYotpoSyncData)) {
            $this->yotpoSmsBumpLogger->info(
                __(
                    'No customers that should be synced found - Magento Store ID: %1, Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                )
            );
        } else {
            $this->yotpoSmsBumpLogger->info(
                __(
                    'Starting Customers sync to Yotpo Cron job - Magento Store ID: %1, Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                )
            );

            foreach ($customerCollectionWithYotpoSyncData as $customerWithYotpoSyncData) {
                $customerId = explode('-', $customerWithYotpoSyncData->getId())[0];
                $customerId = (int) $customerId;
                $customerWithYotpoSyncData->setId($customerId);
                $customerSyncData = [];
                $responseCode = $customerWithYotpoSyncData['response_code'];

                if (!$this->config->canResync($responseCode, [], $this->isCommandLineSync)) {
                    $this->yotpoSmsBumpLogger->info('Customer sync cannot be done for customerId: '
                        . $customerId . ', due to response code: ' . $responseCode, []);
                    continue;
                }

                /** @var Customer $customerWithYotpoSyncData */
                $response = $this->syncCustomer($customerWithYotpoSyncData);
                if ($response) {
                    $customerSyncData = $this->createCustomerSyncData($response, $customerId);
                }

                if ($customerSyncData) {
                    $customerSyncData = $this->updateCustomerSyncData($customerSyncData, $currentTime);
                    $this->insertOrUpdateCustomerSyncData($customerSyncData);
                }
            }

            $this->yotpoSmsBumpLogger->info(
                __(
                    'Finished Customers sync to Yotpo Cron job - Magento Store ID: %1, Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                )
            );
        }

        $this->updateLastSyncDate($currentTime);
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
     * @param string $customerId
     * @param int $customerStoreId
     * @param int $syncStatus
     * @param boolean $shouldUpdateAllStores
     * @return void
     * @throws NoSuchEntityException|LocalizedException
     */
    public function resetCustomerSyncStatus($customerId, $customerStoreId, $syncStatus, $shouldUpdateAllStores = false)
    {
        $customersSyncData = [];
        $storeIds = [];
        if (!$shouldUpdateAllStores && !$this->config->isCustomerAccountShared()) {
            $storeIds[] = $customerStoreId;
        } else {
            /** @phpstan-ignore-next-line */
            foreach ($this->config->getAllStoreIds(false) as $storeId) {
                $storeIds[] = $storeId;
            }
        }

        foreach ($storeIds as $storeId) {
            $customersSyncData[] = [
                'customer_id' => $customerId,
                'store_id' => $storeId,
                'sync_status' => $syncStatus,
                'response_code' => '200'
            ];
        }

        $this->insertOrUpdateCustomerSyncData($customersSyncData);
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
        $customerSyncData['sync_status'] = 0;
        if ($this->config->canUpdateCustomAttribute($customerSyncData['response_code'])) {
            $customerSyncData['sync_status'] = 1;
        }

        return $customerSyncData;
    }

    /**
     * @param int $storeId
     * @param array<mixed> $retryCustomerIds
     * @param bool $customerAccountShared
     * @param int $websiteId
     * @param int $batchSize
     * @return mixed
     */
    private function createCustomerCollectionWithYotpoSyncDataQuery(
        $storeId,
        $retryCustomerIds,
        $customerAccountShared,
        $websiteId,
        $batchSize
    ) {
        $customerCollection = $this->customerFactory->create();
        $customerCollection->getSelect()->joinLeft(
            ['yotpo_customers_sync' => $customerCollection->getTable('yotpo_customers_sync')],
            'e.entity_id = yotpo_customers_sync.customer_id
            AND (yotpo_customers_sync.store_id is null OR yotpo_customers_sync.store_id = \'' . $storeId . '\')',
            [
                'store_id',
                'sync_status',
                'response_code'
            ]
        );
        if (!$retryCustomerIds) {
            $customerCollection->getSelect()
                ->where('yotpo_customers_sync.sync_status is null OR  yotpo_customers_sync.sync_status = ?', 0);
        } else {
            $customerCollection->getSelect()
                ->where('e.entity_id in (?)', $retryCustomerIds);
        }
        if (!$customerAccountShared) {
            $customerCollection->getSelect()
                ->where('e.website_id = ? ', $websiteId);
        }
        $customerCollection->getSelect()->limit($batchSize);
        return $customerCollection;
    }
}
