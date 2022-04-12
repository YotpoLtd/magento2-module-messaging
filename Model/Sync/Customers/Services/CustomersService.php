<?php

namespace Yotpo\SmsBump\Model\Sync\Customers\Services;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Yotpo\Core\Model\AbstractJobs;
use Yotpo\SmsBump\Model\Config;
use Yotpo\SmsBump\Model\Sync\Customers\Logger as YotpoCustomersLogger;
use Yotpo\SmsBump\Model\Sync\Customers\Processor as CustomersProcessor;
use Yotpo\SmsBump\Model\Sync\Customers\Main as CustomersProcessorMain;

class CustomersService extends AbstractJobs
{
    /**
     * @var YotpoCustomersLogger
     */
    protected $yotpoCustomersLogger;

    /**
     * @var CustomersProcessor
     */
    protected $customersProcessor;

    /**
     * @var CustomersProcessorMain
     */
    protected $customersProcessorMain;

    /**
     * @var Config
     */
    protected $config;

    /**
     * Customers Service constructor.
     * @param AppEmulation $appEmulation
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param YotpoCustomersLogger $yotpoCustomersLogger
     * @param CustomersProcessor $customersProcessor
     * @param CustomersProcessorMain $customersProcessorMain
     */
    public function __construct(
        AppEmulation $appEmulation,
        ResourceConnection $resourceConnection,
        Config $config,
        YotpoCustomersLogger $yotpoCustomersLogger,
        CustomersProcessor $customersProcessor,
        CustomersProcessorMain $customersProcessorMain
    ) {
        $this->config = $config;
        $this->customersProcessor = $customersProcessor;
        $this->yotpoCustomersLogger = $yotpoCustomersLogger;
        $this->customersProcessorMain = $customersProcessorMain;
        parent::__construct($appEmulation, $resourceConnection);
    }

    /**
     * Retry processing failed customers
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function processCustomersSyncTableResync()
    {
        $storeIds = $this->config->getAllStoreIds(false);

        /** @phpstan-ignore-next-line */
        foreach ($storeIds as $storeId) {
            try {
                $this->emulateFrontendArea($storeId);
                if (!$this->config->isCustomerSyncActive()) {
                    $this->yotpoCustomersLogger->info(
                        __(
                            'Customers retry sync is disabled for - Magento Store ID: %1, Magento Store Name: %2',
                            $storeId,
                            $this->config->getStoreName($storeId)
                        )
                    );
                    $this->stopEnvironmentEmulation();
                    continue;
                }
                $this->yotpoCustomersLogger->info(
                    __(
                        'Starting process Customers retry sync for - Magento Store ID: %1, Magento Store Name: %2',
                        $storeId,
                        $this->config->getStoreName($storeId)
                    )
                );
                $customersIdsForSync = $this->customersProcessorMain->getCustomersIdsForCustomersThatShouldBeRetriedForSync();
                $this->processFailedCustomerEntities($customersIdsForSync);

                $this->yotpoCustomersLogger->info(
                    __(
                        'Finished process Customers retry sync for - Magento Store ID: %1, Magento Store Name: %2',
                        $storeId,
                        $this->config->getStoreName($storeId)
                    )
                );
            } catch (Exception $exception) {
                $this->yotpoCustomersLogger->info(
                    __(
                        'Failed to process Customers retry sync for - Magento Store ID: %1, Magento Store Name: %2, Reason: %3',
                        $storeId,
                        $this->config->getStoreName($storeId),
                        $exception->getMessage()
                    )
                );
            } finally {
                $this->stopEnvironmentEmulation();
            }
        }
    }

    /**
     * Process customer entities
     * @param array $customersIdsForResync
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function processFailedCustomerEntities($customersIdsForResync)
    {
        $storeId = $this->config->getStoreId();
        if (count($customersIdsForResync)) {
            $this->customersProcessor->processEntities($customersIdsForResync, $storeId, false);
        } else {
            $this->yotpoCustomersLogger->info(
                __(
                    'No customers that should be retried found - Magento Store ID: %1, Magento Store Name: %2',
                    $storeId,
                    $this->config->getStoreName($storeId)
                )
            );
        }
    }
}
