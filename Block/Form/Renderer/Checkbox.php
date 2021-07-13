<?php

namespace Yotpo\SmsBump\Block\Form\Renderer;

use Magento\CustomAttributeManagement\Block\Form\Renderer\AbstractRenderer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Yotpo\SmsBump\Model\Config;

/**
 * Class Checkbox
 * EAV Entity Attribute Form Renderer Block for Checkbox
 */
class Checkbox extends AbstractRenderer
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * Checkbox constructor.
     * @param Template\Context $context
     * @param Config $config
     * @param array<mixed> $data
     */
    public function __construct(
        Template\Context $context,
        Config $config,
        array $data = []
    ) {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
     * Check if sms marketing is enabled in sign-up form
     *
     * @return mixed|string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function isSignUpEnabled()
    {
        return $this->config->getConfig('yotpo_active')
                && $this->config->getConfig('sms_marketing_signup_active');
    }

    /**
     * Get configuration data
     *
     * @return array<mixed>
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getConfigData()
    {
        return [
            'boxHeading' => $this->config->getConfig('sms_marketing_signup_box_heading'),
            'description' => $this->config->getConfig('sms_marketing_signup_description'),
            'message' => $this->config->getConfig('sms_marketing_signup_message'),
            'privacyPolicyText' => $this->config->getConfig('sms_marketing_privacy_policy_text'),
            'privacyPolicyUrl' => $this->config->getConfig('sms_marketing_privacy_policy_link'),
            'customAttribute' => $this->config->getConfig('sms_marketing_custom_attribute')
            ];
    }
}
