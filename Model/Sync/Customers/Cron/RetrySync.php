<?php

namespace Yotpo\SmsBump\Model\Sync\Customers\Cron;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Yotpo\SmsBump\Model\Sync\Customers\Processor as CustomersProcessor;

/**
 * Class RetrySync - Process customers retry sync using cron job
 */
class RetrySync
{
    /**
     * @var CustomersProcessor
     */
    protected $customersProcessor;

    /**
     * CustomersSync constructor.
     * @param CustomersProcessor $customersProcessor
     */
    public function __construct(
        CustomersProcessor $customersProcessor
    ) {
        $this->customersProcessor = $customersProcessor;
    }

    /**
     * Process customers sync
     *
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function processRetrySync()
    {
        $this->customersProcessor->processRetrySync();
    }
}
