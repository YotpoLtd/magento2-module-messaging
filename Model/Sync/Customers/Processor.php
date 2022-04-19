<?php

namespace Yotpo\SmsBump\Model\Sync\Customers;

use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerFactory;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Framework\App\ResourceConnection;
use Magento\Customer\Model\Customer;
use Yotpo\SmsBump\Model\Sync\Main as SmsBumpSyncMain;
use Yotpo\SmsBump\Model\Config;
use Yotpo\SmsBump\Model\Sync\Customers\Data as CustomersData;
use Yotpo\SmsBump\Model\Sync\Customers\Logger as YotpoCustomersLogger;
use Yotpo\Core\Model\Sync\Data\Main as YotpoCoreSyncData;
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
     * @var CustomersData
     */
    protected $data;

    /**
     * @var YotpoCustomersLogger
     */
    protected $yotpoCustomersLogger;

    /**
     * @var array<mixed>
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
     * @var YotpoCoreSyncData
     */
    protected $yotpoCoreSyncData;

    /**
     * Processor constructor.
     * @param AppEmulation $appEmulation
     * @param ResourceConnection $resourceConnection
     * @param Config $yotpoSmsConfig
     * @param SmsBumpSyncMain $yotpoSyncMain
     * @param CustomerFactory $customerFactory
     * @param CustomersData $data
     * @param YotpoCustomersLogger $yotpoCustomersLogger
     * @param StoreManagerInterface $storeManager
     * @param YotpoCoreSyncData $yotpoCoreSyncData
     * @param YotpoCustomersSyncRepositoryInterface $yotpoCustomersSyncRepositoryInterface
     */
    public function __construct(
        AppEmulation $appEmulation,
        ResourceConnection $resourceConnection,
        Config $yotpoSmsConfig,
        SmsBumpSyncMain $yotpoSyncMain,
        CustomerFactory $customerFactory,
        CustomersData $data,
        YotpoCustomersLogger $yotpoCustomersLogger,
        StoreManagerInterface $storeManager,
        YotpoCoreSyncData $yotpoCoreSyncData,
        YotpoCustomersSyncRepositoryInterface $yotpoCustomersSyncRepositoryInterface
    ) {
        $this->yotpoSyncMain = $yotpoSyncMain;
        $this->yotpoCustomersLogger = $yotpoCustomersLogger;
        $this->storeManager= $storeManager;
        $this->yotpoCoreSyncData = $yotpoCoreSyncData;
        $this->yotpoCustomersSyncRepositoryInterface = $yotpoCustomersSyncRepositoryInterface;
        parent::__construct($appEmulation, $resourceConnection, $yotpoSmsConfig, $data, $customerFactory, $yotpoCustomersLogger);
    }

    /**
     * Process customers
     * @param array $retryCustomersIds
     * @param array $storeIds
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function process($retryCustomersIds = [], $storeIds = [])
    {
        if (!$storeIds) {
            $storeIds = $this->config->getAllStoreIds(false);
        }
        /** @phpstan-ignore-next-line */
        foreach ($storeIds as $storeId) {
            try {
                if ($this->isCommandLineSync) {
                    // phpcs:ignore
                    echo 'Customers process started for store - ' .
                        $this->config->getStoreName($storeId) . PHP_EOL;
                }
                $this->emulateFrontendArea($storeId);
                if (!$this->config->isCustomerSyncActive()) {
                    $this->yotpoCustomersLogger->info(
                        __(
                            'Customer sync is disabled for - Magento Store ID: %1, Magento Store Name: %2',
                            $storeId,
                            $this->config->getStoreName($storeId)
                        )
                    );
                    if ($this->isCommandLineSync) {
                        // phpcs:ignore
                        echo 'Customer sync is disabled for store - ' .
                            $this->config->getStoreName($storeId) . PHP_EOL;
                    }
                    $this->stopEnvironmentEmulation();
                    continue;
                }
                $this->yotpoCustomersLogger->info(
                    __(
                        'Starting process customers for - Magento Store ID: %1, Magento Store Name: %2',
                        $storeId,
                        $this->config->getStoreName($storeId)
                    )
                );
                $retryCustomersIdsToSync = $this->isCommandLineSync ? $retryCustomersIds[$storeId] : $retryCustomersIds;
                $this->processEntities($retryCustomersIdsToSync);
            } catch (Exception $exception) {
                $this->yotpoCustomersLogger->info(
                    __(
                        'Failed to process Customers for - Magento Store ID: %1, Magento Store Name: %2, Reason: %3',
                        $storeId,
                        $this->config->getStoreName($storeId)
                    )
                );
            } finally {
                $this->stopEnvironmentEmulation();
            }

            $this->yotpoCustomersLogger->info(
                __(
                    'Finished process Customers for - Magento Store ID: %1, Magento Store Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                )
            );
        }
        $this->stopEnvironmentEmulation();
    }

    /**
     * Process customer entities
     * @param array|null $retryCustomersIdsToSync
     * @param string|null $storeId
     * @param boolean|null $isAttributeSync
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function processEntities($retryCustomersIdsToSync = [], $storeId = null, $isAttributeSync = true)
    {
        $storeId = $storeId ?? $this->config->getStoreId();
        $customerCollectionQuery = $this->createCustomersCollectionQuery($retryCustomersIdsToSync, $storeId);
        $customersCollection = $customerCollectionQuery->getItems();

        if (!count($customersCollection)) {
            $this->yotpoCustomersLogger->info(
                __(
                    'No customers that should be synced found - Magento Store ID: %1, Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                )
            );
        } else {
            $this->yotpoCustomersLogger->info(
                __(
                    'Starting Customers sync to Yotpo Cron job - Magento Store ID: %1, Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                )
            );
            $isCustomerAccountShared = $this->config->isCustomerAccountShared();
            $storesIdsEligibleForSync = $this->getEligibleStoresForSync();
            $syncedToYotpoCustomerAttributeCode = $this->yotpoCoreSyncData->getAttributeId($this->config::SYNCED_TO_YOTPO_CUSTOMER_ATTRIBUTE_NAME);
            foreach ($customersCollection as $customer) {
                $customerId = $customer->getId();
                try {
                    if ($isCustomerAccountShared) {
                        $this->syncSharedStoresCustomer($customer, $storesIdsEligibleForSync);
                    } else {
                        /** @var Customer $customer */
                        $this->syncCustomer($customer, $storeId);
                    }

                    if ($isAttributeSync) {
                        $this->insertOrUpdateCustomerAttribute($customerId, $syncedToYotpoCustomerAttributeCode);
                    }
                } catch (Exception $exception) {
                    $this->insertOrUpdateCustomerAttribute($customerId, $syncedToYotpoCustomerAttributeCode);
                    $customerSyncData = $this->createServerErrorCustomerSyncData($customerId, $storeId);
                    $this->insertOrUpdateCustomerSyncData($customerSyncData);
                    $this->yotpoCustomersLogger->info(
                        __(
                            'Failed to sync customer to Yotpo - Magento Store ID: %1, Magento Store Name: %2, Customer ID: %3 Exception Message: %4',
                            $storeId,
                            $this->config->getStoreName($storeId),
                            $customerId,
                            $exception->getMessage()
                        )
                    );
                }
            }

            $this->yotpoCustomersLogger->info(
                __(
                    'Finished Customers sync to Yotpo Cron job - Magento Store ID: %1, Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                )
            );
        }

        $currentTime = date('Y-m-d H:i:s');
        $this->updateLastSyncDate($currentTime);
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
        $storeId = $customer->getStoreId();
        $storeId = $this->config->getStoreId();
        if ($this->config->syncResetInProgress($storeId, 'customer')) {
            $this->yotpoSmsBumpLogger->info(
                __(
                    'Customer sync is skipped because sync reset is in progress - Magento Store ID: %1, Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                )
            );
            return [];
        }
        $customerId = $customer->getId();

        try {
            $isCustomerAccountShared = $this->config->isCustomerAccountShared();
            $this->yotpoCustomersLogger->info(
                __(
                    'Start syncing Customer to Yotpo - Magento Store ID: %1, Magento Store Name: %2, Customer ID: %3',
                    $storeId,
                    $this->config->getStoreName($storeId),
                    $customer->getId()
                )
            );

            if ($isCustomerAccountShared) {
                $storeIdsEligibleForSync = $this->getEligibleStoresForSync();
                $this->syncSharedStoresCustomer($customer, $storeIdsEligibleForSync, true, $customerAddress);
            } else {
                /** @var Customer $customer */
                $this->syncCustomer($customer, $storeId, true, $customerAddress);
            }

            $this->updateSyncedToYotpoCustomerAttributeAsSynced($customerId);

            $this->yotpoCustomersLogger->info(
                __(
                    'Finish syncing Customer to Yotpo - Magento Store ID: %1, Magento Store Name: %2, Customer ID: %3',
                    $storeId,
                    $this->config->getStoreName($storeId),
                    $customer->getId()
                )
            );
        } catch (Exception $exception) {
            $this->updateSyncedToYotpoCustomerAttributeAsSynced($customerId);
            $customerSyncData = $this->createServerErrorCustomerSyncData($customerId, $storeId);
            $this->insertOrUpdateCustomerSyncData($customerSyncData);
            $this->yotpoCustomersLogger->info(
                __(
                    'Failed to sync Customer to Yotpo - Magento Store ID: %1, Magento Store Name: %2, Customer ID: %3, Exception Message: %4',
                    $storeId,
                    $this->config->getStoreName($storeId),
                    $customerId,
                    $exception->getMessage()
                )
            );
        }
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
     * @param Customer $customer
     * @param string $storeId
     * @param boolean $isRealTimeSync
     * @param null $customerAddress
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function syncCustomer(Customer $customer, $storeId, $isRealTimeSync = false, $customerAddress = null)
    {
        $customerId = $customer->getId();
        $customerDataForSync = $this->prepareCustomerDataForSync($customer, $customerId, $isRealTimeSync, $customerAddress);
        $response = $this->executeCustomerSyncRequest($customer, $customerDataForSync);
        if ($response) {
            $customerSyncData = $this->createCustomerSyncData($response, $customerId, $storeId);
            $this->insertOrUpdateCustomerSyncData($customerSyncData);
        }
    }

    /**
     * Calls customer sync api
     * @param Customer $customer
     * @param null|mixed $customerDataForSync
     * @return array<mixed>|DataObject
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function executeCustomerSyncRequest($customer, $customerDataForSync = null)
    {
        $storeId = $this->config->getStoreId();
        if ($this->config->syncResetInProgress($storeId, 'customer')) {
            $this->yotpoSmsBumpLogger->info(
                __(
                    'Customer sync is skipped because sync reset is in progress - Magento Store ID: %1, Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                )
            );
            return [];
        }
        $customerId = $customer->getId();
        $this->yotpoCustomersLogger->info(
            __(
                'Starting syncing Customer to Yotpo - Customer ID: %1',
                $customerId
            )
        );

        if (!$customerDataForSync) {
            $this->yotpoCustomersLogger->info(
                __(
                    'Stopped syncing Customer to Yotpo - no new data to sync - Customer ID: %1',
                    $customerId
                )
            );
            return [];
        }

        $url = $this->config->getEndpoint('customers');
        $customerDataForSync['entityLog'] = 'customers';
        $method = $this->config::PATCH_METHOD_STRING;
        $baseUriKey = 'api';
        $response = $this->yotpoSyncMain->sync($method, $url, $customerDataForSync, $baseUriKey, true);
        if ($response->getData('is_success')) {
            $this->yotpoCustomersLogger->info(
                __(
                    'Finished syncing Customer to Yotpo successfully - Customer ID: %1',
                    $customerId
                )
            );
        }

        return $response;
    }

    /**
     * @param Customer $customer
     * @param array $storesIds
     * @param boolean $isRealTimeSync
     * @param array $customerAddress
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function syncSharedStoresCustomer(Customer $customer, $storesIds, $isRealTimeSync = false, $customerAddress = null)
    {
        foreach ($storesIds as $storeId) {
            $this->emulateFrontendArea($storeId);
            $this->syncCustomer($customer, $storeId, $isRealTimeSync, $customerAddress);
            $this->stopEnvironmentEmulation();
        }
    }

    /**
     * @param Customer $customer
     * @param string $customerId
     * @param boolean $isRealTimeSync
     * @param mixed|null $customerAddress
     * @return mixed
     */
    private function prepareCustomerDataForSync(Customer $customer, $customerId, $isRealTimeSync, $customerAddress = null)
    {
        if (isset($this->customerDataPrepared[$customerId])) {
            $customerDataForSync = $this->customerDataPrepared[$customerId];
        } else {
            $customerDataForSync = $this->data->prepareData($customer, $isRealTimeSync, $customerAddress);
            $this->customerDataPrepared[$customerId] = $customerDataForSync;
        }

        return $customerDataForSync;
    }

    /**
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getEligibleStoresForSync(): array
    {
        $storesIdsEligibleForSync = [];
        $storesIds = $this->config->getAllStoreIds(false);
        foreach ($storesIds as $storeId) {
            if ($this->config->isCustomerSyncActive($storeId)) {
                $storesIdsEligibleForSync[] = $storeId;
            }
        }

        return $storesIdsEligibleForSync;
    }

    /**
     * Updates the last sync date to the database
     *
     * @param string $currentTime
     * @return void
     * @throws NoSuchEntityException
     */
    private function updateLastSyncDate($currentTime)
    {
        $this->config->saveConfig('customers_last_sync_time', $currentTime);
    }

    /**
     * @param string $customerId
     */
    private function updateSyncedToYotpoCustomerAttributeAsSynced($customerId)
    {
        $syncedToYotpoCustomerAttributeCode = $this->yotpoCoreSyncData->getAttributeId($this->config::SYNCED_TO_YOTPO_CUSTOMER_ATTRIBUTE_NAME);
        $this->insertOrUpdateCustomerAttribute($customerId, $syncedToYotpoCustomerAttributeCode);
    }
}
