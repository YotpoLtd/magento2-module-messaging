<?php

namespace Yotpo\SmsBump\Plugin\CustomAttributeManagement\Helper;

use Magento\CustomAttributeManagement\Helper\Data as HelperData;

/**
 * Class Data
 * Add check box option on backend
 */
class Data
{
    /**
     * Return data array of available attribute Input Types
     *
     * @param HelperData $subject
     * @param array<mixed> $result
     * @param string|null $inputType
     * @return array<mixed>
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetAttributeInputTypes(
        HelperData $subject,
        $result,
        $inputType = null
    ) {
        $checkBox = [
            'label' => __('Checkbox'),
            'manage_options' => false,
            'validate_types' => [],
            'validate_filters' => [],
            'filter_types' => [],
            'backend_type' => 'int',
            'default_value' => false,
            /** @phpstan-ignore-next-line */
            'data_model' => \Magento\Customer\Model\Metadata\Form\Checkbox::class
        ];
        if (null === $inputType) {
            $result['checkbox'] = $checkBox;
            return $result;
        } elseif ($inputType == 'checkbox') {
            return $checkBox;
        }
        return $result;
    }
}
