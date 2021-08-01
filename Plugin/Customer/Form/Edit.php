<?php

namespace Yotpo\SmsBump\Plugin\Customer\Form;

use Yotpo\SmsBump\Model\Config;
use Magento\Customer\Block\Form\Edit as CustomerFormEdit;

/**
 * Class Edit - Manage Block settings
 */
class Edit
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param CustomerFormEdit $subject
     * @return void
     */
    public function beforeToHtml(CustomerFormEdit $subject)
    {
        $template = $this->config->isCustomAttributeModuleExists() ?
            'Yotpo_SmsBump::customer/form/edit-ee.phtml' : 'Yotpo_SmsBump::customer/form/edit.phtml';
        $subject->setTemplate($template);
    }
}
