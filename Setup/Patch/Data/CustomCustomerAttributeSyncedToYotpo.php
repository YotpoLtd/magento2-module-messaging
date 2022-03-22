<?php

namespace Yotpo\SmsBump\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Yotpo\SmsBump\Model\Config as MessagingConfig;

class CustomCustomerAttributeSyncedToYotpo implements DataPatchInterface
{
    const CUSTOMER_SETUP_FACTORY_SETUP_KEY = 'setup';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var CustomerSetupFactory
     */
    private $customerSetupFactory;

    /**
     * @var MessagingConfig
     */
    private $messagingConfig;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerSetupFactory $customerSetupFactory
     * @param MessagingConfig $messagingConfig
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerSetupFactory $customerSetupFactory,
        MessagingConfig $messagingConfig
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->messagingConfig = $messagingConfig;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $customerSetup = $this->customerSetupFactory->create([ $this::CUSTOMER_SETUP_FACTORY_SETUP_KEY => $this->moduleDataSetup ]);
        $customerSetup->addAttribute(Customer::ENTITY, $this->messagingConfig::SYNCED_TO_YOTPO_CUSTOMER_ATTRIBUTE_NAME, [
            'type' => 'int',
            'label' => 'Synced to Yotpo Customer',
            'required' => false,
            'visible' => false,
            'default' => 0
        ]);
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
