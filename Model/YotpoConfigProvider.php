<?php

namespace  Yotpo\SmsBump\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Yotpo\SmsBump\Model\Config as YotpoSmsBumpConfig;
use Magento\Customer\Model\Session as CustomerSession;
use Yotpo\SmsBump\Model\Sync\Data\AbstractData;

/**
 * Configuration provider for Yotpo SMS Marketing
 */
class YotpoConfigProvider implements ConfigProviderInterface
{
    /**
     * @var YotpoSmsBumpConfig
     */
    protected $config;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var AbstractData
     */
    protected $abstractData;

    /**
     * YotpoConfigProvider constructor.
     * @param Config $config
     * @param CustomerSession $customerSession
     * @param AbstractData $abstractData
     */
    public function __construct(
        YotpoSmsBumpConfig $config,
        CustomerSession $customerSession,
        AbstractData $abstractData
    ) {
        $this->config = $config;
        $this->customerSession = $customerSession;
        $this->abstractData = $abstractData;
    }

    /**
     * Sets the config values for Yotpo SMSBump
     *
     * @return array<mixed>
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getConfig()
    {
        $storeId = $this->config->getStoreId();
        $customerId = $this->customerSession->getCustomerId();
        $config = [];
        $config['yotpo']['sms_marketing']['checkout_enabled']  =
            $this->config->getConfig('yotpo_active', $storeId)
            && $this->config->getConfig('sms_marketing_checkout_enable', $storeId);
        $config['yotpo']['sms_marketing']['box_heading']  =
            $this->config->getConfig('sms_marketing_checkout_box_heading', $storeId);
        $config['yotpo']['sms_marketing']['description']  =
            $this->config->getConfig('sms_marketing_checkout_box_description', $storeId);
        $config['yotpo']['sms_marketing']['consent_message']  =
            $this->config->getConfig('sms_marketing_checkout_consent_message', $storeId);
        $config['yotpo']['sms_marketing']['privacy_policy_text']  =
            $this->config->getConfig('sms_marketing_privacy_policy_text', $storeId);
        $config['yotpo']['sms_marketing']['privacy_policy_url']  =
            $this->config->getConfig('sms_marketing_privacy_policy_link', $storeId);

        $config['yotpo']['sms_marketing']['custom_attr_val']  = $customerId ?
            $this->abstractData->getSmsMarketingCustomAttributeValue($customerId) : false;

        return $config;
    }
}
