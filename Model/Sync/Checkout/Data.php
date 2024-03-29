<?php

namespace Yotpo\SmsBump\Model\Sync\Checkout;

use Magento\Bundle\Model\Product\Type as ProductTypeBundle;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ProductTypeConfigurable;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped as ProductTypeGrouped;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\SalesRule\Model\ResourceModel\Coupon\CollectionFactory as CouponCollectionFactory;
use Yotpo\SmsBump\Helper\Data as MessagingDataHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Store\Model\StoreManagerInterface;
use Yotpo\SmsBump\Model\Sync\Data\AbstractData;
use Yotpo\SmsBump\Model\AbandonedCart\Data as AbandonedCartData;

/**
 * Class Data - Prepare data for checkout sync
 */
class Data
{
    const ABANDONED_URL = 'yotpo_messaging/abandonedcart/loadcart/yotpoQuoteToken/';
    const GIFTCARD_STRING = 'giftcard';
    /**
     * @var MessagingDataHelper
     */
    protected $messagingDataHelper;

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
     * @param MessagingDataHelper $messagingDataHelper
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
        MessagingDataHelper $messagingDataHelper,
        CheckoutSession $checkoutSession,
        StoreManagerInterface $storeManager,
        CouponCollectionFactory $couponCollectionFactory,
        AbstractData $abstractData,
        Logger $checkoutLogger,
        ProductRepository $productRepository,
        AddressRepositoryInterface $customerAddressRepository,
        AbandonedCartData $abandonedCartData
    ) {
        $this->messagingDataHelper = $messagingDataHelper;
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
            $this->checkoutLogger->info(
                __(
                    'Did not sync Checkout to Yotpo - Checkout event without address data - Checkout ID: %1',
                    $quote->getId()
                )
            );
            return [];
        }

        $quoteToken = $this->abandonedCartData->getQuoteToken($quote->getId());
        if (!$quoteToken) {
            $quoteToken = $this->abandonedCartData->updateAbandonedCartDataAndReturnToken($quote, $customerEmail);
        }

        $billingAddressData = $this->prepareBillingAddress($quote);
        if (!$billingAddressData || !$billingAddressData['country_code']) {
            $this->checkoutLogger->info(
                __(
                    'Failed to sync Checkout to Yotpo -
                    Billing Address didn\'t contain valid Country ID -
                    Checkout ID: %1, Country ID: %2',
                    $quote->getId(),
                    $quote->getBillingAddress()->getCountryId()
                )
            );
            return [];
        }

        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $checkoutDate = $quote->getUpdatedAt() ?: $quote->getCreatedAt();
        if (!$quote->getCustomerIsGuest() && $quote->getCustomerId()) {
            $isCustomerAcceptsSmsMarketing = $this->abstractData->getSmsMarketingCustomAttributeValue($customerId);
        } else {
            $isCustomerAcceptsSmsMarketing = (bool) $this->checkoutSession->getYotpoSmsMarketing();
        }

        $checkoutData = [
            'token' => $quote->getId(),
            'checkout_date' => $this->messagingDataHelper->formatDate($checkoutDate),
            'landing_site_url' => $baseUrl,
            'customer' => $this->prepareCustomer(
                $customerId,
                $customerEmail,
                $billingAddress,
                $customerData,
                $isCustomerAcceptsSmsMarketing
            ),
            'billing_address' => $billingAddressData,
            'shipping_address' => $this->prepareShippingAddress($quote),
            'currency' => $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : null,
            'line_items' => array_values($this->prepareLineItems($quote)),
            'abandoned_checkout_url' =>
                $this->storeManager->getStore()->getBaseUrl() . self::ABANDONED_URL . $quoteToken
        ];
        $dataBeforeChange = $this->getDataBeforeChange();
        $newCheckoutData = $checkoutData;
        unset($newCheckoutData['checkout_date']);
        $newCheckoutData = json_encode($newCheckoutData);
        if ($dataBeforeChange == $newCheckoutData) {
            $this->checkoutLogger->info(
                __(
                    'Did not sync Checkout to Yotpo - No new data was found - Checkout ID: %1',
                    $quote->getId()
                )
            );
            return [];//no change in quote data
        }

        $this->checkoutSession->setYotpoCheckoutData($newCheckoutData);
        return ['checkout' => $checkoutData];
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
     * Prepare billing address data
     * @param Quote $quote
     * @return array<mixed>
     * @throws LocalizedException
     */
    public function prepareBillingAddress(Quote $quote)
    {
        $newBillingAddress = $quote->getData('newBillingAddress');
        $billingAddress = $newBillingAddress ?: $quote->getBillingAddress();
        if (!$newBillingAddress && $quote->getIsVirtual()) {
            $customer = $quote->getCustomer();
            /** @phpstan-ignore-next-line */
            $defaultCustomerBillingAddressId = $customer->getDefaultBilling();
            if ($defaultCustomerBillingAddressId) {
                $billingAddress = $this->customerAddressRepository->getById($defaultCustomerBillingAddressId);
            }
        }

        return $this->abstractData->prepareAddressData($billingAddress);
    }

    /**
     * Prepare shipping address data
     * @param Quote $quote
     * @return array<mixed>
     * @throws LocalizedException
     */
    public function prepareShippingAddress(Quote $quote)
    {
        $shippingAddress = $quote->getShippingAddress();

        return $this->abstractData->prepareAddressData($shippingAddress);
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
                $appliedRuleIds = $item->getAppliedRuleIds();
                $itemRuleIds = [];
                if ($appliedRuleIds !== null) {
                    $itemRuleIds = explode(',', $appliedRuleIds);
                }

                if ($ruleId != null || !in_array($ruleId, $itemRuleIds)) {
                    $couponCode = null;
                }
                $product = $this->prepareProductObject($item);

                $nonSimpleProductProductTypes = [ProductTypeGrouped::TYPE_CODE, ProductTypeGrouped::TYPE_CODE,
                    ProductTypeConfigurable::TYPE_CODE, ProductTypeBundle::TYPE_CODE, $this::GIFTCARD_STRING];
                if (in_array($item->getProductType(), $nonSimpleProductProductTypes) &&
                    isset($lineItems[$product->getId()])
                ) {
                    $lineItems[$product->getId()]['total_price'] += $item->getData('row_total_incl_tax');
                    $lineItems[$product->getId()]['subtotal_price'] += $item->getRowTotal();
                    $lineItems[$product->getId()]['quantity'] += (integer)$item->getQty();
                } else {
                    $this->lineItemsProductIds[] = $product->getId();
                    $lineItems[$product->getId()] = [
                        'external_product_id' => $product->getId(),
                        'quantity' => (integer)$item->getQty(),
                        'total_price' => $item->getData('row_total_incl_tax'),
                        'subtotal_price' => $item->getRowTotal(),
                        'coupon_code' => $couponCode
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->checkoutLogger->info(
                __(
                    'Failed to sync Checkout when preparing line items for sync - Checkout ID: %1, Error Message: %2',
                    $quote->getId(),
                    $e->getMessage()
                )
            );
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

    /**
     * @param string $customerId
     * @param string $customerEmail
     * @param QuoteAddress $billingAddress
     * @param QuoteAddress|CustomerInterface $customerData
     * @param boolean $isCustomerAcceptsSmsMarketing
     * @return array<mixed>
     */
    private function prepareCustomer(
        $customerId,
        $customerEmail,
        $billingAddress,
        $customerData,
        $isCustomerAcceptsSmsMarketing
    ) {
        return [
            'external_id' => $customerId ?: $customerEmail,
            'email' => $customerEmail,
            'phone_number' => $this->abstractData->preparePhoneNumber($billingAddress),
            'first_name' => $customerData->getFirstname(),
            'last_name' => $customerData->getLastname(),
            'accepts_sms_marketing' => $isCustomerAcceptsSmsMarketing
        ];
    }
}
