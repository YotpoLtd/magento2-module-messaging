<?php

namespace Yotpo\SmsBump\Model\Sync\Checkout;

use Magento\Bundle\Model\Product\Type as ProductTypeBundle;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ProductTypeConfigurable;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped as ProductTypeGrouped;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\SalesRule\Model\ResourceModel\Coupon\CollectionFactory as CouponCollectionFactory;
use Yotpo\SmsBump\Helper\Data as SMSHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Store\Model\StoreManagerInterface;
use Yotpo\SmsBump\Model\Sync\Data\AbstractData;
use Yotpo\SmsBump\Model\AbandonedCart\Data as AbandonedCartData;

/**
 * Class Data - Prepare data for checkout sync
 */
class Data
{
    const ABANDONED_URL = 'yotposmsbump/abandonedcart/loadcart/yotpoQuoteToken/';
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
     * @var CouponCollectionFactory
     */
    protected $couponCollectionFactory;

    /**
     * @var AbstractData
     */
    protected $abstractData;

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
     * @var AddressRepositoryInterface
     */
    protected $customerAddressRepository;

    /**
     * @var AbandonedCartData
     */
    protected $abandonedCartData;

    /**
     * Data constructor.
     * @param SMSHelper $smsHelper
     * @param CheckoutSession $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param CouponCollectionFactory $couponCollectionFactory
     * @param AbstractData $abstractData
     * @param Logger $checkoutLogger
     * @param ProductRepository $productRepository
     * @param AddressRepositoryInterface $customerAddressRepository
     * @param AbandonedCartData $abandonedCartData
     */
    public function __construct(
        SMSHelper $smsHelper,
        CheckoutSession $checkoutSession,
        StoreManagerInterface $storeManager,
        CouponCollectionFactory $couponCollectionFactory,
        AbstractData $abstractData,
        Logger $checkoutLogger,
        ProductRepository $productRepository,
        AddressRepositoryInterface $customerAddressRepository,
        AbandonedCartData $abandonedCartData
    ) {
        $this->smsHelper = $smsHelper;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->couponCollectionFactory = $couponCollectionFactory;
        $this->abstractData = $abstractData;
        $this->checkoutLogger = $checkoutLogger;
        $this->productRepository = $productRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->abandonedCartData = $abandonedCartData;
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
        $customerEmail = $quote->getBillingAddress()->getEmail();
        /** @phpstan-ignore-next-line */
        if ($quote->getCustomer()->getId()) {
            $customerData = $quote->getCustomer();
            $customerId = $customerData->getId();/** @phpstan-ignore-line */
        } else {
            $customerData = $billingAddress;
            $customerId = $customerData->getEmail();
            if (!$customerEmail) {
                $customerEmail = $this->checkoutSession->getYotpoCustomerEmail();
            }
        }
        if (!$customerId && !$customerEmail) {
            return [];
        }

        $quoteToken = $this->abandonedCartData->getQuoteToken($quote->getId());
        if (!$quoteToken) {
            $quoteToken = $this->abandonedCartData->updateAbandonedCartDataAndReturnToken($quote, $customerEmail);
        }

        $billingAddressData = $this->prepareAddress($quote, 'billing');
        if (!$billingAddressData || !$billingAddressData['country_code']) {
            return [];
        }
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $checkoutDate = $quote->getUpdatedAt() ?: $quote->getCreatedAt();
        if (!$quote->getCustomerIsGuest() && $quote->getCustomerId()) {
            $customAttributeValue = $this->abstractData->getSmsMarketingCustomAttributeValue($customerId);
        } else {
            $customAttributeValue = (bool) $this->checkoutSession->getYotpoSmsMarketing();
        }
        $data = [
            'token' => $quote->getId(),
            'checkout_date' => $this->smsHelper->formatDate($checkoutDate),
            'landing_site_url' => $baseUrl,
            'customer' => [
                'external_id' => $customerId ?: $customerEmail,
                'email' => $customerEmail,
                'phone_number' => $billingAddress->getTelephone() ? $this->smsHelper->formatPhoneNumber(
                    $billingAddress->getTelephone(),
                    $billingAddress->getCountryId()
                ) : null,
                'first_name' => $customerData->getFirstname(),
                'last_name' => $customerData->getLastname(),
                'accepts_sms_marketing' => $customAttributeValue
            ],
            'billing_address' => $billingAddressData,
            'shipping_address' => $this->prepareAddress($quote, 'shipping'),
            'currency' => $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : null,
            'line_items' => array_values($this->prepareLineItems($quote)),
            'abandoned_checkout_url' =>
                $this->storeManager->getStore()->getBaseUrl() . self::ABANDONED_URL . $quoteToken
        ];
        $dataBeforeChange = $this->getDataBeforeChange();
        $newData = $data;
        unset($newData['checkout_date']);
        $newData = json_encode($newData);
        if ($dataBeforeChange == $newData) {
            return [];//no change in quote data
        }
        $this->checkoutSession->setYotpoCheckoutData($newData);
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
     * @throws LocalizedException
     */
    public function prepareAddress(Quote $quote, string $type)
    {
        if ($type == 'billing') {
            $address = $quote->getData('newBillingAddress') ?: $quote->getBillingAddress();
            if (!$address->getCountryId() && $quote->getIsVirtual()) {
                $customer = $quote->getCustomer();
                /** @phpstan-ignore-next-line */
                $customerAddressId = $customer->getDefaultBilling();
                if ($customerAddressId) {
                    $address = $this->customerAddressRepository->getById($customerAddressId);
                }
            }
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
