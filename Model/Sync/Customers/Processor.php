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
     * Processor constructor.
     * @param AppEmulation $appEmulation
     * @param ResourceConnection $resourceConnection
     * @param Config $yotpoSmsConfig
     * @param SmsBumpSyncMain $yotpoSyncMain
     * @param CustomerFactory $customerFactory
     * @param CustomersData $data
     * @param Logger $yotpoSmsBumpLogger
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        AppEmulation $appEmulation,
        ResourceConnection $resourceConnection,
        Config $yotpoSmsConfig,
        SmsBumpSyncMain $yotpoSyncMain,
        CustomerFactory $customerFactory,
        CustomersData $data,
        YotpoSmsBumpLogger $yotpoSmsBumpLogger,
        StoreManagerInterface $storeManager
    ) {
        $this->yotpoSyncMain = $yotpoSyncMain;
        $this->customerFactory = $customerFactory;
        $this->yotpoSmsBumpLogger = $yotpoSmsBumpLogger;
        $this->storeManager= $storeManager;
        parent::__construct($appEmulation, $resourceConnection, $yotpoSmsConfig, $data);
    }

    /**
     * Process customers
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function process()
    {
        /** @phpstan-ignore-next-line */
        foreach ($this->config->getAllStoreIds(false) as $storeId) {
            $this->emulateFrontendArea($storeId);
            if (!$this->config->isCustomerSyncActive()) {
                $this->yotpoSmsBumpLogger->info('Customer sync is disabled for store : ' . $storeId, []);
                $this->stopEnvironmentEmulation();
                continue;
            }
            $this->yotpoSmsBumpLogger->info('Process customers for store : ' . $storeId, []);
            $this->processEntities();
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
        $customerAccountShared = $this->config->isCustomerAccountShared();
        /** @phpstan-ignore-next-line */
        foreach ($this->config->getAllStoreIds(false) as $storeId) {
            $this->emulateFrontendArea($storeId);
            if (!$this->config->isCustomerSyncActive() || (
                    !$customerAccountShared &&
                    $this->storeManager->getStore($storeId)->getWebsiteId() != $customer->getWebsiteId()
                )
            ) {
                $this->stopEnvironmentEmulation();
                continue;
            }
            $this->yotpoSmsBumpLogger->info('Process customer for the store : ' . $storeId, []);
            $this->processSingleEntity($customer, $customerAddress);
            $this->stopEnvironmentEmulation();
        }
        $this->stopEnvironmentEmulation();
    }

    /**
     * Process single customer entity
     *
     * @param Customer $magentoCustomer
     * @param null|mixed $customerAddress
     * @return void
     * @throws NoSuchEntityException
     */
    public function processSingleEntity($magentoCustomer, $customerAddress = null)
    {
        $magentoCustomerId = $magentoCustomer->getId();
        $currentTime = date('Y-m-d H:i:s');
        $yotpoTableFinalData = [];
        $customerToUpdate[] = $magentoCustomerId;
        $storeId = $this->config->getStoreId();
        try {
            $this->forceUpdateCustomerSyncStatus($customerToUpdate, $storeId, 0);

            $response = $this->syncCustomer($magentoCustomer, true, $customerAddress);
            if ($response) {
                $yotpoTableData = $this->prepareYotpoTableData($response, $magentoCustomerId);
                $this->updateLastSyncDate($currentTime);
                $this->yotpoSmsBumpLogger->info('Last sync date updated for customer : '
                    . $magentoCustomerId, []);
                if ($yotpoTableData) {
                    $yotpoTableData['store_id'] = $this->config->getStoreId();
                    $yotpoTableData['synced_to_yotpo'] = $currentTime;
                    $yotpoTableData['sync_status'] = 0;
                    if ($this->config->canUpdateCustomAttribute($yotpoTableData['response_code'])) {
                        $yotpoTableData['sync_status'] = 1;
                    }
                    $yotpoTableFinalData[] = $yotpoTableData;
                }
                if ($yotpoTableFinalData) {
                    $this->insertOrUpdateYotpoTableData($yotpoTableFinalData);
                }
            }
        } catch (NoSuchEntityException | LocalizedException $e) {
            $this->yotpoSmsBumpLogger->info($e->getMessage(), []);
        }
    }

    /**
     * Process customer entities
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function processEntities()
    {
        $storeId = $this->config->getStoreId();
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
        $currentTime = date('Y-m-d H:i:s');
        $batchSize = $this->config->getConfig('customers_sync_limit');
        $customerAccountShared = $this->config->isCustomerAccountShared();

        $customerCollection = $this->customerFactory->create();
        $customerCollection->getSelect()->joinLeft(
            ['yotpo_customers_sync' => $customerCollection->getTable('yotpo_customers_sync')],
            'e.entity_id = yotpo_customers_sync.customer_id
            AND (yotpo_customers_sync.store_id is null OR yotpo_customers_sync.store_id = \''.$storeId.'\')',
            [
                'store_id',
                'sync_status',
                'response_code'
            ]
        );
        $customerCollection->getSelect()
            ->where('yotpo_customers_sync.sync_status is null OR  yotpo_customers_sync.sync_status = ?', 0);
        if (!$customerAccountShared) {
            $customerCollection->getSelect()
                ->where('e.website_id = ? ', $websiteId);
        }
        $customerCollection->getSelect()->limit($batchSize);
        $magentoCustomers = [];
        $yotpoTableFinalData = [];
        $customersToUpdate = [];
        foreach ($customerCollection->getItems() as $customer) {
            $id = $customer->getId();
            $id = explode('-', $id);
            $customer->setId($id[0]);
            $magentoCustomers[$customer->getId()] = $customer;
        }
        if ($magentoCustomers) {
            foreach ($magentoCustomers as $magentoCustomer) {
                $magentoCustomerId = $magentoCustomer->getId();
                $yotpoTableData = [];
                $responseCode = $magentoCustomer['response_code'];
                if (!$this->config->canResync($responseCode)) {
                    $customersToUpdate[] = $magentoCustomerId;
                    $this->yotpoSmsBumpLogger->info('Customer sync cannot be done for customerId: '
                        . $magentoCustomerId . ', due to response code: ' . $responseCode, []);
                    continue;
                }
                /** @var Customer $magentoCustomer */
                $response = $this->syncCustomer($magentoCustomer);
                if ($response) {
                    $yotpoTableData = $this->prepareYotpoTableData($response, $magentoCustomerId);
                }

                if ($yotpoTableData) {
                    $yotpoTableData['store_id'] = $this->config->getStoreId();
                    $yotpoTableData['synced_to_yotpo'] = $currentTime;
                    $yotpoTableData['sync_status'] = 0;
                    if ($this->config->canUpdateCustomAttribute($yotpoTableData['response_code'])) {
                        $yotpoTableData['sync_status'] = 1;
                    }
                    $yotpoTableFinalData[] = $yotpoTableData;
                }
            }
        } else {
            $this->yotpoSmsBumpLogger->info('Empty data - Magento Store ID - ' . $storeId, []);
        }
        if ($yotpoTableFinalData) {
            $this->insertOrUpdateYotpoTableData($yotpoTableFinalData);
        }
        $this->updateLastSyncDate($currentTime);
    }

    /**
     * Calls customer sync api
     *
     * @param Customer $customer
     * @param bool $realTImeSync
     * @param null|mixed $customerAddress
     * @return array<mixed>|DataObject
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function syncCustomer($customer, $realTImeSync = false, $customerAddress = null)
    {
        if (isset($this->customerDataPrepared[$customer->getId()])) {
            $customerData = $this->customerDataPrepared[$customer->getId()];
        } else {
            $customerData = $this->data->prepareData($customer, $realTImeSync, $customerAddress);
            $this->customerDataPrepared[$customer->getId()] = $customerData;
        }

        $this->yotpoSmsBumpLogger->info('Customers sync - data prepared', []);
        if (!$customerData) {
            $this->yotpoSmsBumpLogger->info('Customers sync - no new data to sync', []);
            return [];
        }
        $url = $this->config->getEndpoint('customers');
        $customerData['entityLog'] = 'customers';
        $response = $this->yotpoSyncMain->sync('PATCH', $url, $customerData);
        if ($response->getData('is_success')) {
            $this->yotpoSmsBumpLogger->info('Customers sync - success', []);
        }
        return $response;
    }

    /**
     * Update custom attribute - synced_to_yotpo_customer
     *
     * @param array<mixed> $customerIds
     * @param int $value
     * @return void
     */
    public function updateCustomerAttribute($customerIds, $value)
    {
        $dataToInsertOrUpdate = [];
        $attributeId = $this->data->getAttributeId('synced_to_yotpo_customer');
        foreach ($customerIds as $customerId) {
            $data = [
                'attribute_id' => $attributeId,
                'entity_id' => $customerId,
                'value' => $value
            ];
            $dataToInsertOrUpdate[] = $data;
        }
        $this->insertOnDuplicate('customer_entity_int', $dataToInsertOrUpdate);
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
     * @param array <mixed> $customerIds
     * @param int $customerStoreId
     * @param int $value
     * @param boolean $updateAllStores
     * @return void
     * @throws NoSuchEntityException|LocalizedException
     */
    public function forceUpdateCustomerSyncStatus($customerIds, $customerStoreId, $value, $updateAllStores = false)
    {
        $dataToInsertOrUpdate = [];
        $storeIds = [];
        if ($updateAllStores) {
            if ($this->config->isCustomerAccountShared()) {
                /** @phpstan-ignore-next-line */
                foreach ($this->config->getAllStoreIds(false) as $storeId) {
                    $storeIds[] = $storeId;
                }
            } else {
                $storeIds[] = $customerStoreId;
            }
        } else {
            $storeIds[] = $customerStoreId;
        }

        foreach ($customerIds as $customerId) {
            foreach ($storeIds as $storeId) {
                $data = [
                    'customer_id' => $customerId,
                    'store_id' => $storeId,
                    'sync_status' => $value,
                    'response_code' => '200'
                ];
                $dataToInsertOrUpdate[] = $data;
            }
        }
        $this->insertOnDuplicate('yotpo_customers_sync', $dataToInsertOrUpdate);
    }
}
