<?php

namespace Yotpo\SmsBump\Model\Sync\Reset;

class Customers extends \Yotpo\Core\Model\Sync\Reset\Customers
{
    const CUSTOMERS_SYNC_TABLE = 'yotpo_customers_sync';
    const CRONJOB_CODES = ['yotpo_cron_messaging_customers_sync'];

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
}
