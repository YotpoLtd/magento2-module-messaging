<?php

namespace Yotpo\SmsBump\Model\Sync\Customers;

use Magento\Framework\App\ResourceConnection;
use Yotpo\SmsBump\Model\Sync\Customers\Data as YotpoMessagingCustomersData;
use Yotpo\SmsBump\Model\Config as YotpoMessagingConfig;

class CustomerSyncStatus
{

    /**
     * @var Data
     */
    protected $yotpoMessagingCustomersData;

    /**
     * @var YotpoMessagingConfig
     */
    protected $yotpoMessagingConfig;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param Data $yotpoMessagingCustomersData
     * @param YotpoMessagingConfig $yotpoMessagingConfig
     */
    public function __construct(
        YotpoMessagingCustomersData $yotpoMessagingCustomersData,
        YotpoMessagingConfig $yotpoMessagingConfig,
        ResourceConnection $resourceConnection
    ) {
        $this->yotpoMessagingCustomersData = $yotpoMessagingCustomersData;
        $this->yotpoMessagingConfig = $yotpoMessagingConfig;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @return void
     */
    public function resetCustomerSyncAttribute()
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('customer_entity_int');
        $select = $connection->select()
            ->from($tableName, 'value_id')
            ->where(
                'attribute_id = ?',
                $this->yotpoMessagingCustomersData->getAttributeId(
                    YotpoMessagingConfig::YOTPO_CUSTOM_ATTRIBUTE_SYNCED_TO_YOTPO_CUSTOMER
                )
            )
            ->where('value = ?', 1);
        $rows = $connection->fetchCol($select);
        if (!$rows) {
            return;
        }
        $updateLimit = $this->yotpoMessagingConfig->getUpdateSqlLimit();
        $rows = array_chunk($rows, $updateLimit);
        $count = count($rows);
        for ($i=0; $i<$count; $i++) {
            $cond   =   [
                'value_id IN (?) ' => $rows[$i]
            ];
            $connection->update(
                $tableName,
                ['value' => 0],
                $cond
            );
        }
    }
}
