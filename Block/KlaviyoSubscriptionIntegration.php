<?php

namespace Yotpo\SmsBump\Block;

use Magento\Framework\View\Element\Template;
use Yotpo\SmsBump\Model\Config as YotpoConfig;

class KlaviyoSubscriptionIntegration extends \Magento\Framework\View\Element\Template
{
    protected YotpoConfig $config;

    public function __construct(Template\Context $context, YotpoConfig $config, array $data = [])
    {

        $this->config = $config;
        parent::__construct($context, $data);
    }

    public function shouldCaptureKlaviyoForms()
    {
        return $this->config->isAppKeyAndSecretSet() && $this->config->getConfig('sms_marketing_capture_klaviyo_forms');
    }


}
