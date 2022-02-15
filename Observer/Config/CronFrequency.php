<?php

namespace Yotpo\SmsBump\Observer\Config;

use Magento\Framework\App\ResourceConnection;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as CronCollection;
use Magento\Framework\Event\Observer;
use Yotpo\SmsBump\Model\Config as YotpoSmsConfig;
use Yotpo\Core\Observer\Config\CronFrequency as YotpoCoreCronFrequency;
use Yotpo\Core\Model\Config as YotpoCoreConfig;

/**
 * CronFrequency - Manage cron schedule table for sync jobs
 */
class CronFrequency extends YotpoCoreCronFrequency
{

    /**
     * @var CronCollection
     */
    protected $cronCollection;

    /**
     * @var YotpoSmsConfig
     */
    protected $yotpoSmsConfig;

    /**
     * @var array <mixed>
     */
    protected $cronFrequency = [
        'customers_sync_frequency' => ['config_path' => '','job_code' => 'yotpo_cron_messaging_customers_sync']
    ];

    /**
     * @param CronCollection $cronCollection
     * @param YotpoCoreConfig $yotpoCoreConfig
     * @param ResourceConnection $resourceConnection
     * @param YotpoSmsConfig $yotpoSmsConfig
     */
    public function __construct(
        CronCollection $cronCollection,
        YotpoCoreConfig $yotpoCoreConfig,
        ResourceConnection $resourceConnection,
        YotpoSmsConfig $yotpoSmsConfig
    ) {
        $this->yotpoSmsConfig = $yotpoSmsConfig;
        parent::__construct(
            $cronCollection,
            $yotpoCoreConfig,
            $resourceConnection
        );
    }

    /**
     * @param Observer $observer
     * @return array <mixed>
     */
    public function checkCronFrequencyChanged(Observer $observer)
    {
        $changedPaths = (array)$observer->getEvent()->getChangedPaths();
        $cronFrequencyValues = [];
        foreach ($this->cronFrequency as $key => $item) {
            $this->cronFrequency[$key]['config_path'] = $this->yotpoSmsConfig->getConfigPath($key);
            $cronFrequencyValues[] = $this->cronFrequency[$key]['config_path'];
        }
        return array_intersect($cronFrequencyValues, $changedPaths);
    }
}
