<?php

namespace Yotpo\SmsBump\Model\Sync\Reset;

class Customers extends \Yotpo\Core\Model\Sync\Reset\Customers
{
    const CUSTOMERS_SYNC_TABLE = 'yotpo_customers_sync';
    const CRONJOB_CODES = ['yotpo_cron_messaging_customers_sync'];
    const YOTPO_ENTITY_NAME = 'customer';

    /**
     * @return array <string>
     */
    protected function getTableResourceNames()
    {
        return [self::CUSTOMERS_SYNC_TABLE];
    }

    /**
     * @return array <string>
     */
    protected function getCronJobCodes()
    {
        return self::CRONJOB_CODES;
    }

    /**
     * @return string
     */
    public function getYotpoEntityName()
    {
        return self::YOTPO_ENTITY_NAME;
    }

    /**
     * @param int $storeId
     * @param boolean $skipSyncTables
     * @return void
     * @throws \Zend_Db_Statement_Exception
     */
    public function resetSync($storeId, $skipSyncTables = false)
    {
        parent::resetSync($storeId);
        $this->setResetInProgressConfig($storeId, '0');
    }
}
