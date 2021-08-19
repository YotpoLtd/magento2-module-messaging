<?php

namespace Yotpo\SmsBump\Model\Sync\Checkout;

use Magento\Bundle\Model\Product\Type as ProductTypeBundle;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ProductTypeConfigurable;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped as ProductTypeGrouped;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\SalesRule\Model\ResourceModel\Coupon\CollectionFactory as CouponCollectionFactory;
use Yotpo\SmsBump\Helper\Data as SMSHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Newsletter\Model\SubscriberFactory;
use Yotpo\SmsBump\Model\Sync\Data\AbstractData;
use Yotpo\Core\Model\Sync\Data\Main;

/**
 * Class Data - Prepare data for checkout sync
 */
class Data
{
    /**
     * @var SMSHelper
     */
    protected $smsHelper;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var SubscriberFactory
     */
    protected $subscriberFactory;

    /**
     * @var CouponCollectionFactory
     */
    protected $couponCollectionFactory;

    /**
     * @var AbstractData
     */
    protected $abstractData;

    /**
     * @var Main
     */
    protected $coreMain;

    /**
     * @var Logger
     */
    protected $checkoutLogger;

    /**
     * @var array<mixed>
     */
    protected $lineItemsProductIds = [];

    /**
     * @var array<mixed>
     */
    protected $productOptions;

    /**
     * @var array <mixed>
     */
    protected $groupProductsParents = [];

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * Data constructor.
     * @param SMSHelper $smsHelper
     * @param CheckoutSession $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param SubscriberFactory $subscriberFactory
     * @param CouponCollectionFactory $couponCollectionFactory
     * @param AbstractData $abstractData
     * @param Main $coreMain
     * @param Logger $checkoutLogger
     * @param ProductRepository $productRepository
     */
    public function __construct(
        SMSHelper $smsHelper,
        CheckoutSession $checkoutSession,
        StoreManagerInterface $storeManager,
        SubscriberFactory $subscriberFactory,
        CouponCollectionFactory $couponCollectionFactory,
        AbstractData $abstractData,
        Main $coreMain,
        Logger $checkoutLogger,
        ProductRepository $productRepository
    ) {
        $this->smsHelper = $smsHelper;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->subscriberFactory = $subscriberFactory;
        $this->couponCollectionFactory = $couponCollectionFactory;
        $this->abstractData = $abstractData;
        $this->coreMain = $coreMain;
        $this->checkoutLogger = $checkoutLogger;
        $this->productRepository = $productRepository;
    }

    /**
     * @param Quote $quote
     * @return array|array[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function prepareData(Quote $quote)
    {
        $billingAddress = $quote->getData('newBillingAddress') ?: $quote->getBillingAddress();
        $customAttributeValue = false;
        /** @phpstan-ignore-next-line */
        if ($quote->getCustomer()->getId()) {
            $customerData = $quote->getCustomer();
            $customerId = $customerData->getId();/** @phpstan-ignore-line */
        } else {
            $customerData = $billingAddress;
            $customerId = $customerData->getEmail();
        }
        if (!$customerId) {
            return [];
        }
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $checkoutDate = $quote->getUpdatedAt() ?: $quote->getCreatedAt();
        if (!$quote->getCustomerIsGuest()) {
            $customAttributeValue = $this->abstractData->getSmsMarketingCustomAttributeValue($customerId);
        }
        $data = [
            'token' => $quote->getId(),
            'checkout_date' => $this->smsHelper->formatDate($checkoutDate),
            'landing_site_url' => $baseUrl,
            'customer' => [
                'external_id' => $customerId,
                'email' => $customerData->getEmail() ?: null,
                'phone_number' => $billingAddress->getTelephone() ? $this->smsHelper->formatPhoneNumber(
                    $billingAddress->getTelephone(),
                    $billingAddress->getCountryId()
                ) : null,
                'first_name' => $customerData->getFirstname(),
                'last_name' => $customerData->getLastname(),
                'accepts_sms_marketing' => $customAttributeValue,
                'accepts_email_marketing' => $quote->getCustomer()->getEmail() && /** @phpstan-ignore-line */
                    $this->getAcceptsEmailMarketing(
                    /** @phpstan-ignore-next-line */
                        $quote->getCustomer()->getEmail()
                    )
            ],
            'billing_address' => $this->prepareAddress($quote, 'billing'),
            'shipping_address' => $this->prepareAddress($quote, 'shipping'),
            'currency' => $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : null,
            'line_items' => array_values($this->prepareLineItems($quote))
        ];
        $dataBeforeChange = $this->getDataBeforeChange();
        $newData = json_encode($data);

        if ($dataBeforeChange == $newData) {
            return [];//no change in quote data
        }
        $this->checkoutSession->setYotpoCheckoutData(json_encode($data));
        return ['checkout' => $data];
    }

    /**
     * get previous checkout data from session
     * @return mixed
     */
    public function getDataBeforeChange()
    {
        return $this->checkoutSession->getYotpoCheckoutData();
    }

    /**
     * Prepare address data
     * @param Quote $quote
     * @param string $type
     * @return array<mixed>
     */
    public function prepareAddress(Quote $quote, string $type)
    {
        if ($type == 'billing') {
            $address = $quote->getData('newBillingAddress') ?: $quote->getBillingAddress();
        } else {
            $address = $quote->getShippingAddress();
        }
        return $this->abstractData->prepareAddressData($address);
    }

    /**
     * Prepare line items data
     *
     * @param Quote $quote
     * @return array<mixed>
     */
    public function prepareLineItems($quote)
    {
        $lineItems = [];
        $ruleId = null;
        $couponCode = $quote->getCouponCode();
        if ($couponCode) {
            $coupons = $this->couponCollectionFactory->create();
            $coupons->addFieldToFilter('code', $couponCode);
            if ($couponsData = $coupons->getItems()) {
                foreach ($couponsData as $coupon) {
                    $ruleId = $coupon->getRuleId();
                }
            }
        }
        try {
            foreach ($quote->getAllVisibleItems() as $item) {
                try {
                    $itemRuleIds = explode(',', $item->getAppliedRuleIds());
                    if ($ruleId != null || !in_array($ruleId, $itemRuleIds)) {
                        $couponCode = null;
                    }
                    $product = $this->prepareProductObject($item);
                    if (($item->getProductType() === ProductTypeGrouped::TYPE_CODE
                            || $item->getProductType() === ProductTypeConfigurable::TYPE_CODE
                            || $item->getProductType() === ProductTypeBundle::TYPE_CODE
                            || $item->getProductType() === 'giftcard')
                        && (isset($lineItems[$product->getId()]))) {
                        $lineItems[$product->getId()]['total_price'] +=
                            $item->getData('row_total_incl_tax');
                        $lineItems[$product->getId()]['subtotal_price'] += $item->getRowTotal();
                        $lineItems[$product->getId()]['quantity'] += $item->getQty() * 1;
                    } else {
                        $this->lineItemsProductIds[] = $product->getId();
                        $lineItems[$product->getId()] = [
                            'external_product_id' => $product->getId(),
                            'quantity' => $item->getQty() * 1,
                            'total_price' => $item->getData('row_total_incl_tax'),
                            'subtotal_price' => $item->getRowTotal(),
                            'coupon_code' => $couponCode
                        ];
                    }
                } catch (\Exception $e) {
                    $this->checkoutLogger->info('Checkout sync::prepareLineItems() - exception: ' .
                        $e->getMessage(), []);
                }

            }
        } catch (\Exception $e) {
            $this->checkoutLogger->info('Checkout sync::prepareLineItems() - exception: ' .
                $e->getMessage(), []);
        }
        return $lineItems;
    }

    /**
     * Get the product ids
     *
     * @return array<mixed>
     */
    public function getLineItemsIds()
    {
        return $this->lineItemsProductIds;
    }

    /**
     * @param string $email
     * @return bool
     * @throws LocalizedException
     */
    public function getAcceptsEmailMarketing(string $email)
    {
        $subscriber = $this->subscriberFactory->create();
        $subscriber = $subscriber->loadBySubscriberEmail($email, $this->storeManager->getWebsite(null)->getId());
        return ($subscriber->getId() && $subscriber->isSubscribed());
    }

    /**
     * Get the productIds of the products that are not synced
     *
     * @param array <mixed> $productIds
     * @param  Quote $quote
     * @return mixed
     */
    public function getUnSyncedProductIds($productIds, $quote)
    {
        $quoteItems = [];
        foreach ($quote->getAllVisibleItems() as $quoteItem) {
            $quoteItems[$quoteItem->getProduct()->getId()] = $quoteItem->getProduct();
        }
        return $this->coreMain->getProductIds($productIds, $quote->getStoreId(), $quoteItems);
    }

    /**
     * @param Item $quoteItem
     * @return ProductInterface|Product|mixed|null
     * @throws NoSuchEntityException
     */
    public function prepareProductObject(Item $quoteItem)
    {
        $product = null;
        if ($quoteItem->getProductType() === ProductTypeGrouped::TYPE_CODE) {
            $this->productOptions = json_decode($quoteItem->getOptionByCode('info_buyRequest')->getValue(), true);
            $productId = (isset($this->productOptions['super_product_config']) &&
                isset($this->productOptions['super_product_config']['product_id'])) ?
                $this->productOptions['super_product_config']['product_id'] : null;
            if ($productId && isset($this->groupProductsParents[$productId])) {
                $product = $this->groupProductsParents[$productId];
            } elseif ($productId && !isset($this->groupProductsParents[$productId])) {
                $product = $this->groupProductsParents[$productId] =
                    $this->productRepository->getById($productId);
            }
        } else {
            $product = $quoteItem->getProduct();
        }
        return $product;
    }
}
