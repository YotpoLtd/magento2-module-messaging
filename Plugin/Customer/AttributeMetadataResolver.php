<?php

namespace Yotpo\SmsBump\Plugin\Customer;

use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Type;
use Magento\Customer\Model\AttributeMetadataResolver as CustomerAttributeMetadataResolver;

/**
 * AttributeMetadataResolver - add extra check for checkbox attribute
 */
class AttributeMetadataResolver
{
    /**
     * @param CustomerAttributeMetadataResolver $subject
     * @param array <mixed> $result
     * @param AbstractAttribute $attribute
     * @param Type $entityType
     * @param bool $allowToShowHiddenAttributes
     * @return array <mixed>
     */
    public function afterGetAttributesMeta(
        CustomerAttributeMetadataResolver $subject,
        $result,
        AbstractAttribute $attribute,
        Type $entityType,
        bool $allowToShowHiddenAttributes
    ) {
        if ($attribute->getFrontendInput() === 'checkbox') {
            $result['arguments']['data']['config']['valueMap'] = [
                'true' => '1',
                'false' => '0',
            ];
        }
        return $result;
    }
}
