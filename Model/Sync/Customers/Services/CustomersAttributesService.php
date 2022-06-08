<?php

namespace Yotpo\SmsBump\Model\Sync\Customers\Services;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Customer\Model\Customer;
use Yotpo\Core\Model\AbstractJobs;
use Yotpo\SmsBump\Model\Sync\Customers\Main as CustomersMain;
use Yotpo\Core\Model\Sync\Data\Main as YotpoCoreSyncData;
use Yotpo\SmsBump\Model\Config;

/**
 * Class CustomersAttributesService
 * Service for updating customers attributes in the db
 */
class CustomersAttributesService extends AbstractJobs
{
    /**
     * Maximum reset customers sync retries attempts
     */
    const MAXIMUM_RESET_CUSTOMERS_SYNC_RETRIES_ATTEMPTS = 3;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var CustomersMain
     */
    protected $customersMain;

    /**
     * @var YotpoCoreSyncData
     */
    protected $yotpoCoreSyncData;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * Saves the reset customers sync attempts for retrying rest
     */
    protected $currentResetCustomersSyncAttempts;

    /**
     * Data constructor.
     * @param Config $config
     * @param CustomersMain $customersMain
     * @param YotpoCoreSyncData $yotpoCoreSyncData
     * @param AppEmulation $appEmulation
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Config $config,
        CustomersMain $customersMain,
        YotpoCoreSyncData $yotpoCoreSyncData,
        AppEmulation $appEmulation,
        ResourceConnection $resourceConnection
    ) {
        $this->config = $config;
        $this->customersMain = $customersMain;
        $this->yotpoCoreSyncData = $yotpoCoreSyncData;
        $this->resourceConnection = $resourceConnection;
        $this->currentResetCustomersSyncAttempts = 0;
        parent::__construct($appEmulation, $resourceConnection);
    }

    /**
     * @param Customer $customer
     * @param boolean $attributeValue
     * @return void
     */
    public function updateSyncedToYotpoCustomerAttribute($customer, $attributeValue)
    {
        $syncedToYotpoCustomerAttributeCode = $this->yotpoCoreSyncData->getAttributeId($this->config::SYNCED_TO_YOTPO_CUSTOMER_ATTRIBUTE_NAME);
        $this->customersMain->insertOrUpdateCustomerAttribute(
            $customer->getId(),
            $syncedToYotpoCustomerAttributeCode,
            $attributeValue
        );
    }

    /**
     * @return void
     */
    public function resetCustomersSyncedToYotpoAttribute()
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $syncedToYotpoCustomerAttributeCode = $this->yotpoCoreSyncData->getAttributeId($this->config::SYNCED_TO_YOTPO_CUSTOMER_ATTRIBUTE_NAME);
            $customerEntityIntTableName = $this->config::CUSTOMER_ENTITY_INT_TABLE_NAME;
            $sqlUpdateLimit = $this->config->getUpdateSqlLimit();
            $customersThatWereProcesssedQuery = $connection->select()->from(
                [ $this->resourceConnection->getTableName($customerEntityIntTableName) ],
                ['value_id']
            )->where(
                'attribute_id = ?',
                $syncedToYotpoCustomerAttributeCode
            )->where(
                'value = ?',
                1
            )->limit(
                $sqlUpdateLimit
            );

            while(count($rowsToUpdate = $connection->fetchAssoc($customersThatWereProcesssedQuery))) {
                $connection->update(
                    $customerEntityIntTableName,
                    [ 'value' => 0 ],
                    [ 'value_id IN (?) ' => $rowsToUpdate ]
                );
            }
        } catch (\Exception $exception) {
            $this->currentResetCustomersSyncAttempts++;
            if (!($this->currentResetCustomersSyncAttempts == $this::MAXIMUM_RESET_CUSTOMERS_SYNC_RETRIES_ATTEMPTS)) {
                $this->resetCustomersSyncedToYotpoAttribute();
            }
        }
    }
}
