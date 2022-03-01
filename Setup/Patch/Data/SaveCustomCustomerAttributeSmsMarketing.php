<?php

namespace Yotpo\SmsBump\Setup\Patch\Data;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Customer\Model\Customer;
use Magento\Eav\Model\Config as EAVConfig;

/**
 * Class SaveCustomCustomerAttributeMarketing
 * Save custom attribute to customer attribute group table if it is not already done
 */
class SaveCustomCustomerAttributeSmsMarketing implements DataPatchInterface
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
     * Creates custom attribute - yotpo_accepts_sms_marketing
     *
     * @return void|SaveCustomCustomerAttributeSmsMarketing
     * @throws LocalizedException
     */
    public function apply()
    {
        $attribute = $this->eavConfig->getAttribute(Customer::ENTITY, 'yotpo_accepts_sms_marketing');
        if ($attribute->getId()) {
            $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
            $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
            /** @phpstan-ignore-next-line */
            $attributeSetId = $customerSetup->getDefaultAttributeSetId($customerEntity->getEntityTypeId());
            $attributeGroup = $customerSetup->
                            getDefaultAttributeGroupId(
                                $customerEntity->getEntityTypeId(), /** @phpstan-ignore-line */
                                $attributeSetId
                            );

            $attribute->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroup
            ]);
            /** @phpstan-ignore-next-line */
            $attribute->save();
        }
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
