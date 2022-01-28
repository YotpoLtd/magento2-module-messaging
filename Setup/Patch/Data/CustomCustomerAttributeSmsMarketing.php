<?php

namespace Yotpo\SmsBump\Setup\Patch\Data;

use Yotpo\Core\Model\CustomCustomerAttributeSmsMarketing as CoreCustomCustomerAttributeSmsMarketing;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Customer\Model\Customer;

/**
 * Class CustomCustomerAttributeMarketing
 * Add custom attribute to customer
 */
class CustomCustomerAttributeSmsMarketing extends CoreCustomCustomerAttributeSmsMarketing implements DataPatchInterface
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
     * @return void|CustomCustomerAttributeSmsMarketing
     * @throws LocalizedException
     * @throws \Zend_Validate_Exception
     */
    public function apply()
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerSetup->addAttribute(Customer::ENTITY, 'yotpo_accepts_sms_marketing', [
            'type' => 'int',
            'input' => 'checkbox',
            'label' => 'Yotpo Accepts SMS Marketing',
            'required' => false,
            'default' => 0,
            'visible' => true,
            'user_defined' => true,
            'system' => false,
            'is_visible_on_front' => true
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
