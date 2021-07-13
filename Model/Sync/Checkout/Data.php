<?php

namespace Yotpo\SmsBump\Model\Sync\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\ResourceModel\Coupon\CollectionFactory as CouponCollectionFactory;
use Yotpo\SmsBump\Helper\Data as SMSHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Newsletter\Model\SubscriberFactory;
use Yotpo\SmsBump\Model\Sync\Data\AbstractData;

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
     * @var Logger
     */
    protected $checkoutLogger;

    /**
     * Data constructor.
     * @param SMSHelper $smsHelper
     * @param CheckoutSession $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param SubscriberFactory $subscriberFactory
     * @param CouponCollectionFactory $couponCollectionFactory
     * @param AbstractData $abstractData
     * @param Logger $checkoutLogger
     */
    public function __construct(
        SMSHelper $smsHelper,
        CheckoutSession $checkoutSession,
        StoreManagerInterface $storeManager,
        SubscriberFactory $subscriberFactory,
        CouponCollectionFactory $couponCollectionFactory,
        AbstractData $abstractData,
        Logger $checkoutLogger
    ) {
        $this->smsHelper = $smsHelper;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->subscriberFactory = $subscriberFactory;
        $this->couponCollectionFactory = $couponCollectionFactory;
        $this->abstractData = $abstractData;
        $this->checkoutLogger = $checkoutLogger;
    }

    /**
     * @param Quote $quote
     * @return array|array[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function prepareData(Quote $quote)
    {
        $customAttributeValue = false;
        /** @phpstan-ignore-next-line */
        if ($quote->getCustomer()->getId()) {
            $customerData = $quote->getCustomer();
            $customerId = $customerData->getId();/** @phpstan-ignore-line */
        } else {
            $customerData = $quote->getBillingAddress();
            $customerId = $customerData->getEmail();
        }
        if (!$customerId) {
            return [];
        }
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $checkoutDate = $quote->getUpdatedAt() ?: $quote->getCreatedAt();
        if ($customerId && !$quote->getCustomerIsGuest()) {
            $customAttributeValue = $this->abstractData->getSmsMarketingCustomAttributeValue($customerId);
        }
        $data = [
            'token' => $quote->getId(),
            'checkout_date' => $this->smsHelper->formatDate($checkoutDate),
            'landing_site_url' => $baseUrl,
            'customer' => [
                'external_id' => $customerId,
                'email_address' => $customerData->getEmail() ?: null, /** @phpstan-ignore-line */
                'phone_number' => $quote->getBillingAddress()->getTelephone() ? $this->smsHelper->formatPhoneNumber(
                    $quote->getBillingAddress()->getTelephone(),
                    $quote->getBillingAddress()->getCountryId()
                ) : null,
                'first_name' => $customerData->getFirstname(), /** @phpstan-ignore-line */
                'last_name' => $customerData->getLastname(), /** @phpstan-ignore-line */
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
            $address = $quote->getBillingAddress();
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
                    $lineItems[$item->getProduct()->getId()] = [
                        'external_product_id' => $item->getProduct()->getId(),
                        'quantity' => $item->getQty(),
                        'total_price' => $item->getRowTotal(),
                        'subtotal_price' => $item->getRowTotalWithDiscount(),
                        'coupon_code' => $couponCode
                    ];
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
}
