<?php

namespace Yotpo\SmsBump\Model\Sync\Customers;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerFactory;
use Magento\Framework\App\ResourceConnection;
use Yotpo\SmsBump\Model\Config;
use Yotpo\Core\Model\Sync\Customers\Processor as CoreCustomersProcessor;
use Yotpo\SmsBump\Model\Sync\Customers\Logger as YotpoCustomersLogger;

/**
 * Class Main - Manage Customers sync
 */
class Main extends CoreCustomersProcessor
{
    /**
     * Customers sync limit config key
     */
    const CUSTOMERS_SYNC_LIMIT_CONFIG_KEY = 'customers_sync_limit';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Data
     */
    protected $data;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var YotpoCustomersLogger
     */
    protected $yotpoCustomersLogger;

    /**
     * Customer sync batch size retrieved from configuration
     */
    protected $customersSyncBatchSize;

    /**
     * Main constructor.
     * @param AppEmulation $appEmulation
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param Data $data
     * @param CustomerFactory $customerFactory
     * @param YotpoCustomersLogger $yotpoCustomersLogger
     */
    public function __construct(
        AppEmulation $appEmulation,
        ResourceConnection $resourceConnection,
        Config $config,
        Data $data,
        CustomerFactory $customerFactory,
        YotpoCustomersLogger $yotpoCustomersLogger
    ) {
        $this->config = $config;
        $this->data = $data;
        $this->customerFactory = $customerFactory;
        $this->resourceConnection = $resourceConnection;
        $this->yotpoCustomersLogger = $yotpoCustomersLogger;
        $this->customersSyncBatchSize = $this->config->getConfig($this::CUSTOMERS_SYNC_LIMIT_CONFIG_KEY);
        parent::__construct($appEmulation, $resourceConnection);
    }

    /**
     * @param array $retryCustomersIds
     * @param int $storeId
     * @return mixed
     */
    public function createCustomersCollectionQuery($retryCustomersIds, $storeId)
    {
        $customersCollectionQuery = $this->customerFactory->create();
        $customersCollectionQuery->addAttributeToSelect('*');
        if ($retryCustomersIds) {
            $customersCollectionQuery
                ->addFieldToFilter('entity_id', ['in' => $retryCustomersIds])
                ->getSelect();
            return $customersCollectionQuery;
        }

        $syncedToYotpoCustomerAttributeName = $this->config::SYNCED_TO_YOTPO_CUSTOMER_ATTRIBUTE_NAME;
        $customersCollectionQuery
            ->addFieldToFilter('store_id', $storeId)
            ->addAttributeToFilter([
                [ 'attribute' => $syncedToYotpoCustomerAttributeName, 'null' => true ],
                [ 'attribute' => $syncedToYotpoCustomerAttributeName, 'eq' => 0 ]
            ])
            ->getSelect()
            ->limit($this->customersSyncBatchSize);

        return $customersCollectionQuery;
    }

    /**
     * @return array<string>
     */
    public function getCustomersIdsForCustomersThatShouldBeRetriedForSync()
    {
        $storeId = $this->config->getStoreId();
        $connection = $this->resourceConnection->getConnection();
        $shouldRetryCustomersQuery = $connection->select()->from(
            [$this->resourceConnection->getTableName($this->config::YOTPO_CUSTOMERS_SYNC_TABLE_NAME)],
            ['customer_id']
        )->where(
            'store_id = ?',
            $storeId
        )->where(
            'should_retry = ?',
            1
        )->limit(
            $this->customersSyncBatchSize
        );

        $customersIdsMapForSync = $connection->fetchAssoc($shouldRetryCustomersQuery, 'customer_id');
        return array_keys($customersIdsMapForSync);
    }

    /**
     * Prepares Customer sync table data
     * @param DataObject $customerSyncToYotpoResponse
     * @param int $magentoCustomerId
     * @param string $storeId
     * @return array
     */
    public function createCustomerSyncData($customerSyncToYotpoResponse, $magentoCustomerId, $storeId)
    {
        $currentTime = date('Y-m-d H:i:s');
        $statusCode = $customerSyncToYotpoResponse->getData('status');
        $shouldRetry = $this->config->isNetworkRetriableResponse($statusCode);
        $customerSyncData = [
            /** @phpstan-ignore-next-line */
            'customer_id' => $magentoCustomerId,
            'response_code' => $statusCode,
            'should_retry' => $shouldRetry,
            'store_id' => $storeId,
            'synced_to_yotpo' => $currentTime
        ];

        return $customerSyncData;
    }

    /**
     * Inserts or updates custom table data
     *
     * @param array $customerSyncData
     * @return void
     */
    public function insertOrUpdateCustomerSyncData($customerSyncData)
    {
        $this->insertOnDuplicate($this->config::YOTPO_CUSTOMERS_SYNC_TABLE_NAME, [$customerSyncData]);
    }

    /**
     * @param int $customerId
     * @param string $attributeCode
     * @param boolean $isSynced
     * @return void
     * @throws NoSuchEntityException
     */
    public function insertOrUpdateCustomerAttribute($customerId, $attributeCode, $isSynced = true)
    {
        $customerEntityIntData = [
            'attribute_id' => $attributeCode,
            'entity_id' => $customerId,
            'value' => $isSynced
        ];

        $this->insertOnDuplicate($this->config::CUSTOMER_ENTITY_INT_TABLE_NAME, [$customerEntityIntData]);
    }
}
