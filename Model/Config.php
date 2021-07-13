<?php

namespace Yotpo\SmsBump\Model;

use Magento\Config\Model\ResourceModel\Config as ConfigResource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\StoreManagerInterface;
use Yotpo\Core\Model\Config as CoreConfig;

/**
 * Class Config - Manage Configuration settings
 */
class Config extends CoreConfig
{
    /**
     * Custom attribute code for SMS marketing
     */
    const YOTPO_CUSTOM_ATTRIBUTE_SMS_MARKETING = 'yotpo_accepts_sms_marketing';

    /**
     * @var string[]
     */
    protected $endPoints = [
        'checkout'  => 'checkouts',
        'customers' => 'customers',
        'subscription' => 'subscription-forms'
    ];

    /**
     * @var mixed[]
     */
    protected $smsBumpConfig = [
        'checkout_sync_active' => ['path' => 'yotpo_core/sync_settings/checkout_sync/enable'],
        'checkout_last_sync_time' => ['path' =>
                                        'yotpo_core/sync_settings/checkout_sync/last_sync_time',
                                        'read_from_db' => true
                                     ],
        'customers_sync_active' => ['path' => 'yotpo_core/sync_settings/customers_sync/enable'],
        'customers_last_sync_time' => ['path' =>
            'yotpo_core/sync_settings/customers_sync/last_sync_time',
            'read_from_db' => true
        ],
        'customers_sync_limit' =>
            ['path' => 'yotpo_core/sync_settings/customers_sync/sync_limit_customers'],
        'sync_forms_data' => ['path' =>
            'yotpo_core/sms_subscription/sync_forms_data'
        ],
        'sms_subscription_last_sync_time' => ['path' =>
            'yotpo_core/sms_subscription/last_sync_time'
        ],
        'sms_marketing_signup_active' => ['path' => 'yotpo_core/marketing_settings/signup_enable'],
        'sms_marketing_signup_box_heading' => ['path' => 'yotpo_core/marketing_settings/signup_box_heading'],
        'sms_marketing_signup_description' => ['path' => 'yotpo_core/marketing_settings/signup_box_description'],
        'sms_marketing_signup_message' => ['path' => 'yotpo_core/marketing_settings/signup_consent_message'],
        'sms_marketing_privacy_policy_text' => ['path' => 'yotpo_core/marketing_settings/privacy_policy_text'],
        'sms_marketing_privacy_policy_link' => ['path' => 'yotpo_core/marketing_settings/privacy_policy_link'],
        'sms_marketing_checkout_enable' => ['path' => 'yotpo_core/marketing_settings/checkout_enable'],
        'sms_marketing_checkout_box_heading' => ['path' => 'yotpo_core/marketing_settings/checkout_box_heading'],
        'sms_marketing_checkout_box_description' =>
            ['path' => 'yotpo_core/marketing_settings/checkout_box_description'],
        'sms_marketing_checkout_consent_message' =>
            ['path' => 'yotpo_core/marketing_settings/checkout_consent_message'],
        'sms_marketing_custom_attribute' => ['path' => 'yotpo_core/marketing_settings/attr_customer']
    ];

    /**
     * Config constructor.
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ModuleListInterface $moduleList
     * @param EncryptorInterface $encryptor
     * @param WriterInterface $configWriter
     * @param ConfigResource $configResource
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        ModuleListInterface $moduleList,
        EncryptorInterface $encryptor,
        WriterInterface $configWriter,
        ConfigResource $configResource
    ) {
        parent::__construct(
            $storeManager,
            $scopeConfig,
            $moduleList,
            $encryptor,
            $configWriter,
            $configResource
        );
        $this->config = array_merge($this->config, $this->smsBumpConfig);
    }

    /**
     * @param string $key
     * @param array<mixed> $search
     * @param array<mixed> $repl
     * @return string
     */
    public function getEndpoint(string $key, array $search = [], array $repl = []): string
    {
        return $this->endPoints[$key];
    }

    /**
     * Check if Yotpo is enabled and if customer sync is active.
     *
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function isCustomerSyncActive()
    {
        return $this->isEnabled() && $this->getConfig('customers_sync_active');
    }

    /**
     * Check if Yotpo is enabled and if checkout sync is active.
     *
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function isCheckoutSyncActive()
    {
        return $this->isEnabled() && $this->getConfig('checkout_sync_active');
    }
}
