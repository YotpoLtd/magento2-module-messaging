<?php

namespace Yotpo\SmsBump\Model;

use Magento\Config\Model\ResourceModel\Config as ConfigResource;
use Magento\Eav\Model\Entity;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\ScopeInterface;
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
     * Custom Customer attribute name for synced to yotpo customer
     */
    const SYNCED_TO_YOTPO_CUSTOMER_ATTRIBUTE_NAME = 'synced_to_yotpo_customer';

    /**
     * Customer entity int table name
     */
    const CUSTOMER_ENTITY_INT_TABLE_NAME = 'customer_entity_int';

    /**
     * Yotpo customers sync table name
     */
    const YOTPO_CUSTOMERS_SYNC_TABLE_NAME = 'yotpo_customers_sync';

    /**
     * HTTP Request PATCH method string
     */
    const PATCH_METHOD_STRING = 'PATCH';

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
        'sync_forms_data' =>
            ['path' => 'yotpo_core/widget_settings/sms_subscription/sync_forms_data'],
        'sms_subscription_last_sync_time' =>
            ['path' => 'yotpo_core/widget_settings/sms_subscription/last_sync_time'],
        'sms_marketing_signup_active' =>
            ['path' => 'yotpo_core/widget_settings/marketing_settings/signup_enable'],
        'sms_marketing_signup_box_heading' =>
            ['path' => 'yotpo_core/widget_settings/marketing_settings/signup_box_heading'],
        'sms_marketing_signup_description' =>
            ['path' => 'yotpo_core/widget_settings/marketing_settings/signup_box_description'],
        'sms_marketing_signup_message' =>
            ['path' => 'yotpo_core/widget_settings/marketing_settings/signup_consent_message'],
        'sms_marketing_privacy_policy_text' =>
            ['path' => 'yotpo_core/widget_settings/marketing_settings/privacy_policy_text'],
        'sms_marketing_privacy_policy_link' =>
            ['path' => 'yotpo_core/widget_settings/marketing_settings/privacy_policy_link'],
        'sms_marketing_capture_klaviyo_forms' =>
            ['path' => 'yotpo_core/widget_settings/sms_subscription/capture_klaviyo_forms'],
        'sms_marketing_checkout_enable' =>
            ['path' => 'yotpo_core/widget_settings/marketing_settings/checkout_enable'],
        'sms_marketing_checkout_box_heading' =>
            ['path' => 'yotpo_core/widget_settings/marketing_settings/checkout_box_heading'],
        'sms_marketing_checkout_box_description' =>
            ['path' => 'yotpo_core/widget_settings/marketing_settings/checkout_box_description'],
        'sms_marketing_checkout_consent_message' =>
            ['path' => 'yotpo_core/widget_settings/marketing_settings/checkout_consent_message'],
        'sms_marketing_custom_attribute' =>
            ['path' => 'yotpo_core/widget_settings/marketing_settings/attr_customer'],
        'customer_account_shared' =>
            ['path' => 'customer/account_share/scope'],
        'customers_sync_frequency' =>
            ['path' => 'yotpo_core/sync_settings/customers_sync/frequency']
    ];

    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * Config constructor.
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ModuleListInterface $moduleList
     * @param EncryptorInterface $encryptor
     * @param WriterInterface $configWriter
     * @param ConfigResource $configResource
     * @param ProductMetadataInterface $productMetadata
     * @param Entity $entity
     * @param Manager $moduleManager
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        ModuleListInterface $moduleList,
        EncryptorInterface $encryptor,
        WriterInterface $configWriter,
        ConfigResource $configResource,
        ProductMetadataInterface $productMetadata,
        Entity $entity,
        Manager $moduleManager
    ) {
        parent::__construct(
            $storeManager,
            $scopeConfig,
            $moduleList,
            $encryptor,
            $configWriter,
            $configResource,
            $productMetadata,
            $entity
        );
        $this->config = array_merge($this->config, $this->smsBumpConfig);
        $this->moduleManager = $moduleManager;
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
     * @param int|null $scopeId
     * @param string $scope
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function isCustomerSyncActive(int $scopeId = null, $scope = ScopeInterface::SCOPE_STORE)
    {
        return $this->isEnabled($scopeId, $scope) && $this->getConfig('customers_sync_active', $scopeId, $scope);
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

    /**
     * @return bool
     */
    public function isCustomAttributeModuleExists(): bool
    {
        return $this->moduleManager->isEnabled('Magento_CustomerCustomAttributes');
    }

    /**
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function isCustomerAccountShared()
    {
        $config = $this->getConfig('customer_account_shared');
        return !$config || $config == \Magento\Customer\Model\Config\Share::SHARE_GLOBAL;
    }
}
