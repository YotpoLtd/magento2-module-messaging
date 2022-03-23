<?php

namespace Yotpo\SmsBump\Model\Sync\Customers;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Framework\App\ResourceConnection;
use Yotpo\SmsBump\Model\Config;
use Yotpo\Core\Model\Sync\Customers\Processor as CoreCustomersProcessor;

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
     * Main constructor.
     * @param AppEmulation $appEmulation
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param Data $data
     */
    public function __construct(
        AppEmulation $appEmulation,
        ResourceConnection $resourceConnection,
        Config $config,
        Data $data
    ) {
        $this->config =  $config;
        $this->data   =  $data;
        $this->resourceConnection = $resourceConnection;
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
     * Inserts or updates custom table data
     *
     * @param array<mixed> $customerSyncData
     * @return void
     */
    public function insertOrUpdateCustomerSyncData($customerSyncData)
    {
        $this->insertOnDuplicate('yotpo_customers_sync', [$customerSyncData]);
    }
}
