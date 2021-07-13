<?php

namespace Yotpo\SmsBump\Model\Sync\Customers;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Safe\Exceptions\DatetimeException;
use Yotpo\SmsBump\Model\Config;
use Yotpo\SmsBump\Model\Sync\Customers\Data as CustomersData;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerFactory;
use Yotpo\SmsBump\Model\Sync\Main as SmsBumpSyncMain;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Framework\App\ResourceConnection;
use Yotpo\SmsBump\Model\Sync\Customers\Logger as YotpoSmsBumpLogger;
use Magento\Customer\Model\Customer;
use function Safe\date;

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
     * @var array<mixed>
     */
    protected $customersToUpdate = [];

    /**
     * Processor constructor.
     * @param AppEmulation $appEmulation
     * @param ResourceConnection $resourceConnection
     * @param Config $yotpoSmsConfig
     * @param SmsBumpSyncMain $yotpoSyncMain
     * @param CustomerFactory $customerFactory
     * @param CustomersData $data
     * @param Logger $yotpoSmsBumpLogger
     */
    public function __construct(
        AppEmulation $appEmulation,
        ResourceConnection $resourceConnection,
        Config $yotpoSmsConfig,
        SmsBumpSyncMain $yotpoSyncMain,
        CustomerFactory $customerFactory,
        CustomersData $data,
        YotpoSmsBumpLogger $yotpoSmsBumpLogger
    ) {
        $this->yotpoSyncMain = $yotpoSyncMain;
        $this->customerFactory = $customerFactory;
        $this->yotpoSmsBumpLogger = $yotpoSmsBumpLogger;
        parent::__construct($appEmulation, $resourceConnection, $yotpoSmsConfig, $data);
    }

    /**
     * Process customers
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException|DatetimeException
     */
    public function process()
    {
        /** @phpstan-ignore-next-line */
        foreach ($this->config->getAllStoreIds(false) as $storeId) {
            $this->emulateFrontendArea($storeId);
            if (!$this->config->isCustomerSyncActive()) {
                $this->yotpoSmsBumpLogger->info('Customer sync is disabled for store : ' . $storeId, []);
                continue;
            }
            $this->yotpoSmsBumpLogger->info('Process customers for store : ' . $storeId, []);
            $this->processEntities();
            $this->stopEnvironmentEmulation();
        }
        if ($this->customersToUpdate) {
            $customersArray = array_merge(...$this->customersToUpdate);
            $this->updateCustomerAttribute(array_unique($customersArray), 1);
        }
    }

    /**
     * Process single customer
     *
     * @param Customer $customer
     * @param null|mixed $customerAddress
     * @throws DatetimeException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @return void
     */
    public function processCustomer($customer, $customerAddress = null)
    {
        /** @phpstan-ignore-next-line */
        foreach ($this->config->getAllStoreIds(false) as $storeId) {
            $this->emulateFrontendArea($storeId);
            if (!$this->config->isCustomerSyncActive()) {
                continue;
            }
            $this->yotpoSmsBumpLogger->info('Process customer for the store : ' . $storeId, []);
            $this->processSingleEntity($customer, $customerAddress);
            $this->stopEnvironmentEmulation();
        }
        if ($this->customersToUpdate) {
            $customersArray = array_merge(...$this->customersToUpdate);
            $this->updateCustomerAttribute(array_unique($customersArray), 1);
        }
    }

    /**
     * Process single customer entity
     *
     * @param Customer $magentoCustomer
     * @param null|mixed $customerAddress
     * @return void
     * @throws DatetimeException
     */
    public function processSingleEntity($magentoCustomer, $customerAddress = null)
    {
        $magentoCustomerId = $magentoCustomer->getId();
        $currentTime = date('Y-m-d H:i:s');
        $yotpoTableFinalData = [];
        $customerToUpdate[] = $magentoCustomerId;
        try {
            //Set the customer attribute value to 0 before sync
            $this->updateCustomerAttribute($customerToUpdate, 0);
            $this->yotpoSmsBumpLogger->info('Customer attribute updated to 0 for customer : ' . $magentoCustomerId, []);

            $response = $this->syncCustomer($magentoCustomer, true, $customerAddress);
            if ($response) {
                $yotpoTableData = $this->prepareYotpoTableData($response, $magentoCustomerId);
                $this->updateLastSyncDate($currentTime);
                $this->yotpoSmsBumpLogger->info('Last sync date updated for customer : '
                    . $magentoCustomerId, []);
                if ($yotpoTableData) {
                    $yotpoTableData['store_id'] = $this->config->getStoreId();
                    $yotpoTableData['synced_to_yotpo'] = $currentTime;
                    $yotpoTableFinalData[] = $yotpoTableData;
                }
                if ($yotpoTableFinalData) {
                    $this->insertOrUpdateYotpoTableData($yotpoTableFinalData);
                    $this->customersToUpdate[] = $customerToUpdate;
                }
            }
        } catch (NoSuchEntityException | LocalizedException $e) {
            $this->yotpoSmsBumpLogger->addError($e->getMessage());
        }
    }

    /**
     * Process customer entities
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws DatetimeException
     */
    public function processEntities()
    {
        $currentTime = date('Y-m-d H:i:s');
        $batchSize = $this->config->getConfig('customers_sync_limit');
        $customerCollection = $this->customerFactory->create();
        $customersToUpdate = [];
        $customerCollection->addAttributeToFilter(
            [
                ['attribute' => 'synced_to_yotpo_customer', 'null' => true],
                ['attribute' => 'synced_to_yotpo_customer', 'eq' => '0'],
            ]
        );
        $customerCollection->getSelect()->limit($batchSize);
        $magentoCustomers = [];
        $yotpoTableFinalData = [];
        foreach ($customerCollection->getItems() as $customer) {
            $magentoCustomers[$customer->getId()] = $customer;
        }
        if ($magentoCustomers) {
            $yotpoSyncedCustomers = $this->getYotpoSyncedCustomers($magentoCustomers);
            foreach ($magentoCustomers as $magentoCustomer) {
                $magentoCustomerId = $magentoCustomer->getId();
                $yotpoTableData = [];
                if ($yotpoSyncedCustomers) {
                    if (array_key_exists($magentoCustomerId, $yotpoSyncedCustomers)) {
                        $responseCode = $yotpoSyncedCustomers[$magentoCustomerId]['response_code'];
                        if (!$this->config->canResync($responseCode)) {
                            $this->yotpoSmsBumpLogger->info('Customer sync cannot be done for customerId: '
                                . $magentoCustomerId . ', due to response code: ' . $responseCode, []);
                            continue;
                        }
                    }
                }
                /** @var Customer $magentoCustomer */
                $response = $this->syncCustomer($magentoCustomer);
                if ($response) {
                    $yotpoTableData = $this->prepareYotpoTableData($response, $magentoCustomerId);
                }

                if ($yotpoTableData) {
                    $yotpoTableData['store_id'] = $this->config->getStoreId();
                    $yotpoTableData['synced_to_yotpo'] = $currentTime;
                    $yotpoTableFinalData[] = $yotpoTableData;
                    $customersToUpdate[] = $magentoCustomerId;
                }
            }
        }
        if ($yotpoTableFinalData) {
            $this->insertOrUpdateYotpoTableData($yotpoTableFinalData);
            $this->customersToUpdate[] = $customersToUpdate;
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
        $customerData = $this->data->prepareData($customer, $realTImeSync, $customerAddress);
        $this->yotpoSmsBumpLogger->info('Customers sync - data prepared', []);
        if (!$customerData) {
            $this->yotpoSmsBumpLogger->info('Customers sync - no new data to sync', []);
            return [];
        }
        $url = $this->config->getEndpoint('customers');
        $customerData['entityLog'] = 'customers';
        $response = $this->yotpoSyncMain->sync('PATCH', $url, $customerData);
        if ($response->getData('is_success')) {
            $this->yotpoSmsBumpLogger->info('Customers sync - success', $customerData);
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
}
