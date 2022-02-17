<?php

namespace Yotpo\SmsBump\Model\Sync\Reset;

class Customers extends \Yotpo\Core\Model\Sync\Reset\Customers
{
    const CUSTOMERS_SYNC_TABLE = 'yotpo_customers_sync';
    const CRONJOB_CODES = ['yotpo_cron_smsbump_customers_sync'];

    /**
     * @param int $storeId
     * @return void
     * @throws \Zend_Db_Statement_Exception
     */
    public function resetSync($storeId)
    {
        $this->setStoreId($storeId);
        $this->setCronJobCodes(self::CRONJOB_CODES);
        parent::resetSync($storeId);
        $tableName = $this->resourceConnection->getTableName(self::CUSTOMERS_SYNC_TABLE);
        $this->deleteAllFromTable($tableName, $storeId);
    }
}
