<?php

namespace Yotpo\SmsBump\Model\Attribute\Data;

use Magento\Framework\App\RequestInterface;
use Magento\Eav\Model\Attribute\Data\AbstractData;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class Checkbox
 * EAV Attribute Data Model Class For Checkbox
 */
class Checkbox extends AbstractData
{

    /**
     * @param RequestInterface $request
     * @return array<mixed>|bool|int|string
     */
    public function extractValue(RequestInterface $request)
    {
        $value = $this->_applyInputFilter($this->_getRequestValue($request));

        return $value ? 1 : 0;
    }

    /**
     * @param array<mixed>|string $value
     * @return bool
     */
    public function validateValue($value)
    {
        return true;
    }

    /**
     * @param array<mixed>|string $value
     * @return $this|Checkbox
     * @throws LocalizedException
     */
    public function compactValue($value)
    {
        if ($value !== false) {
            $this->getEntity()->setDataUsingMethod($this->getAttribute()->getAttributeCode(), $value);
        }
        return $this;
    }

    /**
     * @param array<mixed>|string $value
     * @return void|Checkbox
     * @throws LocalizedException
     */
    public function restoreValue($value)
    {
        $this->compactValue($value);
    }

    /**
     * @param string $format
     * @return array<mixed>|string
     * @throws LocalizedException
     */
    public function outputValue($format = \Magento\Eav\Model\AttributeDataFactory::OUTPUT_FORMAT_TEXT)
    {
        $value = $this->getEntity()->getData($this->getAttribute()->getAttributeCode());
        $value = $this->_applyOutputFilter($value);
        return $value;
    }
}
