<?php

namespace Yotpo\SmsBump\Plugin\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Framework\Api\CustomAttributesDataInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\Quote;
use Magento\Ui\Component\Form\Element\Multiline;
use Yotpo\SmsBump\Model\Session as YotpoSmsBumpSession;
use Yotpo\SmsBump\Model\Sync\Checkout\Processor as CheckoutProcessor;

/**
 * Check if guest customer registration is executed.
 */
class DefaultConfigProviderPlugin
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var YotpoSmsBumpSession
     */
    private $yotpoSmsBumpSession;

    /**
     * @var AddressMetadataInterface
     */
    private $addressMetadata;

    /**
     * @var CheckoutProcessor
     */
    protected $checkoutProcessor;

    /**
     * @param CheckoutSession $checkoutSession
     * @param ResourceConnection $resourceConnection
     * @param YotpoSmsbumpSession $yotpoSmsBumpSession
     * @param CheckoutProcessor $checkoutProcessor
     * @param AddressMetadataInterface $addressMetadata
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        ResourceConnection $resourceConnection,
        YotpoSmsbumpSession $yotpoSmsBumpSession,
        CheckoutProcessor $checkoutProcessor,
        AddressMetadataInterface $addressMetadata
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->resourceConnection = $resourceConnection;
        $this->yotpoSmsBumpSession = $yotpoSmsBumpSession;
        $this->checkoutProcessor = $checkoutProcessor;
        /** @phpstan-ignore-next-line */
        $this->addressMetadata = $addressMetadata ?: ObjectManager::getInstance()->get(AddressMetadataInterface::class);
    }

    /**
     * @param DefaultConfigProvider $subject
     * @param array <mixed> $result
     * @return mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function afterGetConfig($subject, $result)
    {
        $yotpoAbandonedQuoteId = $this->yotpoSmsBumpSession->getData('yotpoQuoteToken');
        $quote = $this->checkoutSession->getQuote();
        $billingAddress = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();
        $email = $shippingAddress->getEmail();

        if (!$email) {
            $items = $this->getAbandonedCartData($quote);
            if ($items) {
                foreach ($items as $item) {
                    $email = $item['email'];
                }
            }
        }
        if ($email && (!$result['isCustomerLoggedIn'] || $yotpoAbandonedQuoteId)) {
            $shippingAddressFromData = $this->getAddressFromData($shippingAddress);
            $billingAddressFromData = $this->getAddressFromData($billingAddress);
            $result['shippingAddressFromData'] = $shippingAddressFromData;
            if ($shippingAddressFromData != $billingAddressFromData) {
                $result['billingAddressFromData'] = $billingAddressFromData;
            }
            $result['validatedEmailValue'] = $email;
        }

        if ($billingAddress->getCountryId()) {
            $this->checkoutProcessor->process($quote);
        }

        return $result;
    }

    /**
     * @param Quote $quote
     * @return array <mixed>
     */
    private function getAbandonedCartData($quote)
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()->from(
            ['yotpoAbandonedCart' => $this->resourceConnection->getTableName('yotpo_abandoned_cart')],
            ['email']
        )->where(
            'quote_id = ?',
            $quote->getId()
        )->where(
            'store_id = ?',
            $quote->getStoreId()
        );
        return $connection->fetchAssoc($select, []);
    }

    /**
     * Create address data appropriate to fill checkout address form
     *
     * @param AddressInterface $address
     * @return array <mixed>
     * @throws LocalizedException
     */
    private function getAddressFromData(AddressInterface $address)
    {
        $addressData = [];
        $attributesMetadata = $this->addressMetadata->getAllAttributesMetadata();
        foreach ($attributesMetadata as $attributeMetadata) {
            if (!$attributeMetadata->isVisible()) {
                continue;
            }
            $attributeCode = $attributeMetadata->getAttributeCode();
            /** @phpstan-ignore-next-line */
            $attributeData = $address->getData($attributeCode);
            if ($attributeData) {
                if ($attributeMetadata->getFrontendInput() === Multiline::NAME) {
                    $attributeData = \is_array($attributeData) ? $attributeData : explode("\n", $attributeData);
                    $attributeData = (object)$attributeData;
                }
                if ($attributeMetadata->isUserDefined()) {
                    $addressData[CustomAttributesDataInterface::CUSTOM_ATTRIBUTES][$attributeCode] = $attributeData;
                    continue;
                }
                $addressData[$attributeCode] = $attributeData;
            }
        }
        return $addressData;
    }
}
