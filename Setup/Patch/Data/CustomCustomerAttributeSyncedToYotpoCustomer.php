<?php

namespace Yotpo\SmsBump\Setup\Patch\Data;

use Yotpo\Core\Model\CustomCustomerAttributeSyncedToYotpoCustomer as CoreCustomCustomerAttributeSyncedToYotpoCustomer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Customer\Model\Customer;

/**
 * Class CustomCustomerAttributeSyncedToYotpoCustomer
 * Add custom attribute to customer
 */
// phpcs:ignore
class CustomCustomerAttributeSyncedToYotpoCustomer extends CoreCustomCustomerAttributeSyncedToYotpoCustomer implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var CustomerSetupFactory
     */
    private $customerSetupFactory;

    /**
     * CustomCustomerAttributeSmsMarketing constructor.
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerSetupFactory $customerSetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerSetupFactory $customerSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerSetupFactory = $customerSetupFactory;
    }

    /**
     * Creates custom attribute - synced_to_yotpo_customer
     *
     * @return void|CustomCustomerAttributeSyncedToYotpoCustomer
     * @throws LocalizedException
     * @throws \Zend_Validate_Exception
     */
    public function apply()
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerSetup->addAttribute(Customer::ENTITY, 'synced_to_yotpo_customer', [
            'type' => 'int',
            'required' => false,
            'visible' => false,
            'user_defined' => false,
            'label' => 'Synced to yotpo customer',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
