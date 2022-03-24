<?php

namespace Yotpo\SmsBump\Model\Sync\Customers;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Framework\App\ResourceConnection;
use Yotpo\SmsBump\Model\Config;
use Yotpo\Core\Model\Sync\Customers\Processor as CoreCustomersProcessor;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as CronCollectionFactory;

/**
 * Class Main - Manage Customers sync
 */
class Main extends CoreCustomersProcessor
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Data
     */
    protected $data;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var CronCollectionFactory
     */
    protected $cronCollectionFactory;

    /**
     * Main constructor.
     * @param AppEmulation $appEmulation
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param Data $data
     * @param CronCollectionFactory $cronCollectionFactory
     */
    public function __construct(
        AppEmulation $appEmulation,
        ResourceConnection $resourceConnection,
        Config $config,
        Data $data,
        CronCollectionFactory $cronCollectionFactory
    ) {
        $this->config =  $config;
        $this->data   =  $data;
        $this->resourceConnection = $resourceConnection;
        $this->cronCollectionFactory = $cronCollectionFactory;
        parent::__construct($appEmulation, $resourceConnection);
    }

    /**
     * Get synced customers
     *
     * @param array<mixed> $magentoCustomers
     * @return array<mixed>
     * @throws NoSuchEntityException
     */
    public function getYotpoSyncedCustomers($magentoCustomers)
    {
        $return     =   [];
        $connection =   $this->resourceConnection->getConnection();
        $storeId    =   $this->config->getStoreId();
        $table      =   $this->resourceConnection->getTableName('yotpo_customers_sync');
        $customers  =   $connection->select()
            ->from($table)
            ->where('customer_id IN(?) ', array_keys($magentoCustomers))
            ->where('store_id=(?)', $storeId);
        $customers =   $connection->fetchAssoc($customers, []);
        foreach ($customers as $cust) {
            $return[$cust['customer_id']]  =   $cust;
        }
        return $return;
    }

    /**
     * Prepares custom table data
     *
     * @param array<mixed>|DataObject $customerSyncToYotpoResponse
     * @param int|null $magentoCustomerId
     * @return array<mixed>
     */
    public function createCustomerSyncData($customerSyncToYotpoResponse, $magentoCustomerId)
    {
        $customerSyncData = [
            /** @phpstan-ignore-next-line */
            'response_code' =>  $customerSyncToYotpoResponse->getData('status'),
            'customer_id'   =>  $magentoCustomerId
        ];
        return $customerSyncData;
    }

    /**
     * @param int $magentoCustomerId
     * @return array<mixed>
     */
    public function createCustomerSyncDataOnException($magentoCustomerId)
    {
        $customerSyncData = [
            'response_code' =>  '',
            'customer_id'   =>  $magentoCustomerId
        ];
        return $customerSyncData;
    }

    /**
     * Inserts or updates custom table data
     *
     * @param array<mixed> $customerSyncData
     * @return void
     */
    public function insertOrUpdateCustomerSyncData($customerSyncData)
    {
        $this->insertOnDuplicate('yotpo_customers_sync', [$customerSyncData]);
    }

    /**
     * @param int $customerId
     * @param int $syncStatus
     * @return void
     */
    public function insertOrUpdateCustomerSyncAttributeStatus($customerId, $syncStatus)
    {
        $attributeCode = Config::YOTPO_CUSTOM_ATTRIBUTE_SYNCED_TO_YOTPO_CUSTOMER;
        $attributeId = $this->data->getAttributeId($attributeCode);
        $attributeDataToUpdate = [
            [
                'entity_id' => $customerId,
                'attribute_id' => $attributeId,
                'value' => $syncStatus
            ]
        ];
        $this->insertOnDuplicate('customer_entity_int', $attributeDataToUpdate);
    }

    /**
     * @param string $jobCode
     * @return bool
     */
    public function checkCustomerSyncCronIsRunning($jobCode)
    {
        try {
            $cronCollection = $this->cronCollectionFactory->create();
            $cronCollection->addFieldToFilter('status', \Magento\Cron\Model\Schedule::STATUS_RUNNING);
            $cronCollection->addFieldToFilter('job_code', $jobCode);
            $cronScheduleItems = $cronCollection->getItems();
            $customersRetryCronIsRunning = (bool) count($cronScheduleItems);
        } catch (\Exception $e) {
            $customersRetryCronIsRunning = false;
        }
        return $customersRetryCronIsRunning;
    }
}
