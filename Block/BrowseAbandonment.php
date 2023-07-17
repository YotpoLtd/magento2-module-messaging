<?php

namespace Yotpo\SmsBump\Block;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Yotpo\SmsBump\Model\Config as YotpoConfig;
use Magento\Framework\View\Element\Template;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Checkout\Model\SessionFactory as CheckoutSessionFactory;

/**
 * Class BrowseAbandonment - Block file for Browse Abandonment script injection
 */
class BrowseAbandonment extends Template
{
    /**
     * @const string
     */
    const MAGENTO_HOME_PAGE_TYPE_NAME = 'cms_index_index';

    /**
     * @const string
     */
    const MAGENTO_CATEGORY_PAGE_TYPE_NAME = 'catalog_category_view';

    /**
     * @const string
     */
    const MAGENTO_PRODUCT_PAGE_TYPE_NAME = 'catalog_product_view';

    /**
     * @const string
     */
    const MAGENTO_ORDER_CREATED_PAGE_TYPE_NAME = 'checkout_onepage_success';

    /**
     * @const array<string>
     */
    const ELIGIBLE_MAGENTO_PAGE_TYPE_NAMES_FOR_BROWSE_ABANDONMENT = [
        self::MAGENTO_HOME_PAGE_TYPE_NAME,
        self::MAGENTO_CATEGORY_PAGE_TYPE_NAME,
        self::MAGENTO_PRODUCT_PAGE_TYPE_NAME,
        self::MAGENTO_ORDER_CREATED_PAGE_TYPE_NAME
    ];

    /**
     * @const string
     */
    const BROWSE_ABANDONMENT_HOME_PAGE_TYPE_NAME = 'home';

    /**
     * @const string
     */
    const BROWSE_ABANDONMENT_COLLECTION_PAGE_TYPE_NAME = 'collection';

    /**
     * @const string
     */
    const BROWSE_ABANDONMENT_PRODUCT_PAGE_TYPE_NAME = 'product';

    /**
     * @const string
     */
    const BROWSE_ABANDONMENT_ORDER_CREATED_PAGE_TYPE_NAME = 'order_created';

    /**
     * @const array<string, string>
     */
    const MAGENTO_PAGE_TYPE_NAME_TO_BROWSE_ABANDONMENT_PAGE_TYPE_NAME_MAP = [
        self::MAGENTO_HOME_PAGE_TYPE_NAME => self::BROWSE_ABANDONMENT_HOME_PAGE_TYPE_NAME,
        self::MAGENTO_CATEGORY_PAGE_TYPE_NAME => self::BROWSE_ABANDONMENT_COLLECTION_PAGE_TYPE_NAME,
        self::MAGENTO_PRODUCT_PAGE_TYPE_NAME => self::BROWSE_ABANDONMENT_PRODUCT_PAGE_TYPE_NAME,
        self::MAGENTO_ORDER_CREATED_PAGE_TYPE_NAME => self::BROWSE_ABANDONMENT_ORDER_CREATED_PAGE_TYPE_NAME
    ];

    /**
     * @const array<string>
     */
    const BROWSE_ABANDONMENT_PAGE_TYPE_NAMES_ELIGIBLE_FOR_ID_ENRICHMENT = [
        self::BROWSE_ABANDONMENT_COLLECTION_PAGE_TYPE_NAME,
        self::BROWSE_ABANDONMENT_PRODUCT_PAGE_TYPE_NAME
    ];

    /**
     * @var YotpoConfig
     */
    private $yotpoConfig;

    /**
     * @var Registry
     */
    private $coreRegistry;

    /**
     * @var HttpRequest
     */
    private $httpRequest;

    /**
     * @var CheckoutSessionFactory
     */
    private $checkoutSessionFactory;

    /**
     * BrowseAbandonment constructor.
     * @param Context $context
     * @param YotpoConfig $yotpoConfig
     * @param Registry $coreRegistry
     * @param HttpRequest $httpRequest
     * @param CheckoutSessionFactory $checkoutSessionFactory
     * @param array<mixed> $templateData
     */
    public function __construct(
        Context                $context,
        YotpoConfig            $yotpoConfig,
        Registry               $coreRegistry,
        HttpRequest            $httpRequest,
        CheckoutSessionFactory $checkoutSessionFactory,
        array                  $templateData = []
    )
    {
        $this->yotpoConfig = $yotpoConfig;
        $this->coreRegistry = $coreRegistry;
        $this->httpRequest = $httpRequest;
        $this->checkoutSessionFactory = $checkoutSessionFactory;
        parent::__construct($context, $templateData);
    }

    /**
     * Check module is enabled and API tokens set
     *
     * @return boolean
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function isAppKeyAndSecretSet()
    {
        return $this->yotpoConfig->isAppKeyAndSecretSet();
    }

    /**
     * Get if page type is eligible for Browse Abandonment event
     *
     * @return boolean
     */
    public function isPageTypeEligibleForBrowseAbandonmentEvent()
    {
        $magentoPageTypeName = $this->getMagentoPageTypeName();
        return in_array($magentoPageTypeName, self::ELIGIBLE_MAGENTO_PAGE_TYPE_NAMES_FOR_BROWSE_ABANDONMENT);
    }

    /**
     * Get Browse Abandonment event info
     *
     * @return string
     */
    public function getBrowseAbandonmentEventInfo()
    {
        $browseAbandonmentPageType = $this->getBrowseAbandonmentPageType();
        $browseAbandonmentEventInfo = [];
        $browseAbandonmentEventInfo['type'] = $browseAbandonmentPageType;
        $browseAbandonmentEventInfo['data'] = $this->getBrowseAbandonmentEventInfoData($browseAbandonmentPageType);
        return json_encode($browseAbandonmentEventInfo);
    }

    /**
     * Get Browse Abandonment event info data
     *
     * @return array
     */
    private function getBrowseAbandonmentEventInfoData($browseAbandonmentPageType)
    {
        $browseAbandonmentInfoData = [];
        $browseAbandonmentInfoData['type'] = $browseAbandonmentPageType;

        $browseAbandonmentEligiblePageTypeEntityId = $this->getIdByBrowseAbandonmentPageType($browseAbandonmentPageType);
        if ($browseAbandonmentEligiblePageTypeEntityId) {
            $browseAbandonmentInfoData['id'] = $browseAbandonmentEligiblePageTypeEntityId;
        }

        if ($browseAbandonmentPageType === self::BROWSE_ABANDONMENT_ORDER_CREATED_PAGE_TYPE_NAME) {
            $browseAbandonmentInfoData['order_id'] = $this->getOrderIdInOrderCreatedPage();
        }

        return $browseAbandonmentInfoData;
    }

    /**
     * Get the name of the browse abandonment page type according to magento page type
     *
     * @return string
     */
    private function getBrowseAbandonmentPageType()
    {
        $magentoPageTypeName = $this->getMagentoPageTypeName();
        return self::MAGENTO_PAGE_TYPE_NAME_TO_BROWSE_ABANDONMENT_PAGE_TYPE_NAME_MAP[$magentoPageTypeName];
    }

    /**
     * Get the magento page type from request
     *
     * @return string
     */
    private function getMagentoPageTypeName()
    {
        return $this->httpRequest->getFullActionName();
    }

    /**
     * Get Yotpo API Key
     *
     * @return string
     */
    public function getStoreId()
    {
        return $this->yotpoConfig->getAppKey();
    }

    /**
     * Get ID for Browse Abandonment page type
     *
     * @param string $browseAbandonmentPageType
     * @return int
     */
    private function getIdByBrowseAbandonmentPageType($browseAbandonmentPageType)
    {
        if (!in_array($browseAbandonmentPageType, self::BROWSE_ABANDONMENT_PAGE_TYPE_NAMES_ELIGIBLE_FOR_ID_ENRICHMENT)) {
            return null;
        }

        switch ($browseAbandonmentPageType) {
            case self::BROWSE_ABANDONMENT_PRODUCT_PAGE_TYPE_NAME:
                return $this->getProductId();
            case self::BROWSE_ABANDONMENT_COLLECTION_PAGE_TYPE_NAME:
                return $this->getCategoryId();
            default:
                return null;
        }
    }

    /**
     * Get Product ID from product object
     *
     * @return int
     */
    private function getProductId()
    {
        $product = $this->coreRegistry->registry('current_product');
        return $product->getId();
    }

    /**
     * Get Category ID from category object
     *
     * @return int
     */
    private function getCategoryId()
    {
        $category = $this->coreRegistry->registry('current_category');
        return $category->getId();
    }

    /**
     * Get order ID in current checkout session
     *
     * @return int
     */
    private function getOrderIdInOrderCreatedPage()
    {
        $checkoutSession = $this->checkoutSessionFactory->create();
        return $checkoutSession->getLastRealOrder()->getIncrementId();
    }
}
