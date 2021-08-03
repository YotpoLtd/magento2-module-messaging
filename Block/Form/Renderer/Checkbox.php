<?php

namespace Yotpo\SmsBump\Block\Form\Renderer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Yotpo\SmsBump\Model\Config;

/**
 * Class Checkbox
 * EAV Entity Attribute Form Renderer Block for Checkbox
 */
class Checkbox extends Template
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * Checkbox constructor.
     * @param Template\Context $context
     * @param Config $config
     * @param CustomerRepositoryInterface $customerRepository
     * @param Session $customerSession
     * @param array<mixed> $data
     */
    public function __construct(
        Template\Context $context,
        Config $config,
        CustomerRepositoryInterface $customerRepository,
        Session $customerSession,
        array $data = []
    ) {
        $this->config = $config;
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
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

    /**
     * @return false|mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getIsChecked()
    {
        $return = false;
        if ($customerId = $this->customerSession->getCustomerId()) {
            $customer = $this->customerRepository->getById($customerId);
            $attribute = $customer->getCustomAttribute('yotpo_accepts_sms_marketing');
            $return = $attribute ? $attribute->getValue() : false;
        } else {
            $formData = $this->getFormData()->getData();
            if ($formData && array_key_exists('yotpo_accepts_sms_marketing', $formData)) {
                $return = $formData['yotpo_accepts_sms_marketing'];
            }
        }
        return $return;
    }
}
