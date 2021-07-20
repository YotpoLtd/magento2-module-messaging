<?php
namespace Yotpo\SmsBump\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Customer\Model\ResourceModel\Attribute\CollectionFactory;
use Magento\Eav\Model\Config as EAVConfig;

/**
 * Prepare the customer custom attributes array
 */
class CustomerCustomAttributes implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var EAVConfig
     */
    private $eavConfig;

    /**
     * CustomerCustomAttributes constructor.
     * @param CollectionFactory $collectionFactory
     * @param EAVConfig $eavConfig
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        EAVConfig $eavConfig
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->eavConfig = $eavConfig;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray(): array
    {
        $attributesData = [];
        $attributesInfo = $this->collectionFactory->create();
        $attribute_data[] = [
            'label' => __('Select from customer attributes'),
            'value' => ''
        ];
        foreach ($attributesInfo as $item) {
            if ($item->getData('is_system') == 0) {
                $attributesData[] = [
                    //'label' => $item->getData('frontend_label'),
                    'label' => $item->getData('attribute_code'),
                    'value' => $item->getData('attribute_code')
                ];
            }
        }
        return $attributesData;
    }
}
