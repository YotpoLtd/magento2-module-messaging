<?php

namespace Yotpo\SmsBump\Model\Sync\Customers\Cron;

use Yotpo\SmsBump\Model\Sync\Customers\Services\CustomersService;

/**
 * Class CustomersSyncRetry - Processes customers which failed to be synced - using cron job
 */
class CustomersSyncRetry
{
    /**
     * @var CustomersService
     */
    protected $customersService;

    /**
     * CustomersSyncRetry constructor.
     * @param CustomersService $customersService
     */
    public function __construct(
        CustomersService $customersService
    ) {
        $this->customersService = $customersService;
    }

    /**
     * Process customers sync
     *
     * @return void
     */
    public function execute()
    {
        $this->customersService->processCustomersSyncTableResync();
    }
}
