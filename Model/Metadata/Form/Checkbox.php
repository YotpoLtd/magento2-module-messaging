<?php

namespace Yotpo\SmsBump\Model\Metadata\Form;

use Magento\Customer\Model\Metadata\ElementFactory;
use Magento\Customer\Model\Metadata\Form\AbstractData;
use Magento\Framework\App\RequestInterface;

/**
 * Class Checkbox
 * Model class for checkbox field
 */
class Checkbox extends AbstractData
{
    /**
     * @param RequestInterface $request
     * @return array<mixed>|int|string
     */
    public function extractValue(RequestInterface $request)
    {
        $value = $this->_applyInputFilter($this->_getRequestValue($request));
        if ($value) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * @param array<mixed>|string|null $value
     * @return array<mixed>|bool
     */
    public function validateValue($value)
    {
        return true;
    }

    /**
     * @param array<mixed>|string $value
     * @return array<mixed>|bool|string
     */
    public function compactValue($value)
    {
        return $value;
    }

    /**
     * @param array<mixed>|string $value
     * @return array<mixed>|bool|string
     */
    public function restoreValue($value)
    {
        return $this->compactValue($value);
    }

    /**
     * @param string $format
     * @return array<mixed>|string
     */
    public function outputValue($format = ElementFactory::OUTPUT_FORMAT_TEXT)
    {
        return $this->_applyOutputFilter((string)$this->_value);
    }
}
