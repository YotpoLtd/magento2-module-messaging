<?php

namespace Yotpo\SmsBump\Setup\Patch\Data;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Customer\Model\Customer;
use Magento\Eav\Model\Config as EAVConfig;

/**
 * Class CustomCustomerAttributeMarketing
 * Add custom attribute to customer
 */
class CustomCustomerAttributeSmsMarketing implements DataPatchInterface
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
     * @var EAVConfig
     */
    private $eavConfig;

    /**
     * CustomCustomerAttributeSmsMarketing constructor.
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerSetupFactory $customerSetupFactory
     * @param EAVConfig $eavConfig
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerSetupFactory $customerSetupFactory,
        EAVConfig $eavConfig
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->eavConfig = $eavConfig;
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
        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        /** @phpstan-ignore-next-line */
        $attributeSetId = $customerSetup->getDefaultAttributeSetId($customerEntity->getEntityTypeId());
        $attributeGroup = $customerSetup->
                            getDefaultAttributeGroupId(
                                $customerEntity->getEntityTypeId(), /** @phpstan-ignore-line */
                                $attributeSetId
                            );
        $customerSetup->addAttribute(Customer::ENTITY, 'yotpo_accepts_sms_marketing', [
            'type' => 'int',
            'input' => 'checkbox',
            'label' => 'Get a discount on your next order',
            'required' => false,
            'default' => 0,
            'visible' => true,
            'user_defined' => true,
            'system' => false,
            'is_visible_on_front' => true
        ]);
        $newAttribute = $this->eavConfig->getAttribute(Customer::ENTITY, 'yotpo_accepts_sms_marketing');
        $newAttribute->addData([
            'used_in_forms' => ['customer_account_edit','customer_account_create'],
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroup
        ]);
        /** @phpstan-ignore-next-line */
        $newAttribute->save();
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
