<?php

namespace Yotpo\SmsBump\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class SyncFormsButton
 *
 * Sync new or updated subscription forms.
 */
class SyncFormsButton extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Yotpo_SmsBump::system/config/sync_forms_button.phtml';

    /**
     * Remove scope label
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element) // @codingStandardsIgnoreLine - required by parent class
    {
        return $this->_toHtml();
    }

    /**
     * Return ajax url for sync-forms button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('yotpo_smsbump/syncforms/index');
    }

    /**
     * Generate Sync Forms button HTML
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(/** @phpstan-ignore-line */
            \Magento\Backend\Block\Widget\Button::class
        )->setData([
                'id'    => 'yotpo_sync_forms_btn',
                'label' => __('Sync forms'),
            ]);

        return $button->toHtml();
    }

    /**
     * Get current store scope
     *
     * @return int|mixed
     */
    public function getStoreScope()
    {
        return $this->getRequest()->getParam('store') ? : 0;
    }

    /**
     * Get current website scope
     *
     * @return int|mixed
     */
    public function getWebsiteScope()
    {
        return $this->getRequest()->getParam('website') ? : 0;
    }
}
