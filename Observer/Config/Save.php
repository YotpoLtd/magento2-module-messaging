<?php

namespace Yotpo\SmsBump\Observer\Config;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Class Save
 * Class for config save events
 */
class Save implements ObserverInterface
{

    /**
     * @var CronFrequency
     */
    protected $yotpoSmsCronFrequency;

    /**
     * @param CronFrequency $yotpoSmsCronFrequency
     */
    public function __construct(
        CronFrequency $yotpoSmsCronFrequency
    ) {
        $this->yotpoSmsCronFrequency = $yotpoSmsCronFrequency;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $this->yotpoSmsCronFrequency->doCronFrequencyChanges($observer);
    }
}
