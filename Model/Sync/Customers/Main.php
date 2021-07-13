<?php

namespace Yotpo\SmsBump\Model\Sync\Customers;

use Magento\Framework\Exception\NoSuchEntityException;
use Yotpo\Core\Model\AbstractJobs;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Framework\App\ResourceConnection;
use Yotpo\SmsBump\Model\Config;

/**
 * Class Main - Manage Customers sync
 */
class Main extends AbstractJobs
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
        $table      =   $connection->getTableName('yotpo_customers_sync');
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
     * Prepares custom table tada
     *
     * @param array<mixed>|\Magento\Framework\DataObject $response
     * @param int|null $magentoCustomerId
     * @return array<mixed>
     */
    public function prepareYotpoTableData($response, $magentoCustomerId)
    {
        $data = [
            /** @phpstan-ignore-next-line */
            'response_code' =>  $response->getData('status'),
            'customer_id'   =>  $magentoCustomerId,
        ];
        return $data;
    }

    /**
     * Inserts or updates custom table data
     *
     * @param array<mixed> $yotpoTableFinalData
     * @return void
     */
    public function insertOrUpdateYotpoTableData($yotpoTableFinalData)
    {
        $finalData = [];
        foreach ($yotpoTableFinalData as $data) {
            $finalData[] = [
                'customer_id'        =>  $data['customer_id'],
                'synced_to_yotpo'    =>  $data['synced_to_yotpo'],
                'response_code'      =>  $data['response_code'],
                'store_id'           =>  $data['store_id']
            ];
        }
        $this->insertOnDuplicate('yotpo_customers_sync', $finalData);
    }
}
