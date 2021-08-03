<?php

namespace Yotpo\SmsBump\Plugin\Customer\Form;

use Yotpo\SmsBump\Model\Config;
use \Magento\Customer\Block\Form\Register as CustomerRegister;

/**
 * Class Register - Manage Block settings
 */
class Register
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
     * @param CustomerRegister $subject
     * @return void
     */
    public function beforeToHtml(CustomerRegister $subject)
    {
        $template = $this->config->isCustomAttributeModuleExists() ?
            'Yotpo_SmsBump::customer/form/register-ee.phtml' : 'Yotpo_SmsBump::customer/form/register.phtml';
        $subject->setTemplate($template);
    }
}
