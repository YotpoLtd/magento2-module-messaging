<?php

namespace Yotpo\SmsBump\Model\Sync\Customers\Cron;

use Yotpo\SmsBump\Model\Sync\Customers\Processor as CustomersProcessor;

/**
 * Class CustomersSync - Process customers using cron job
 */
class CustomersSync
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
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @return void
     */
    public function processCustomers()
    {
        $this->customersProcessor->process();
    }
}
