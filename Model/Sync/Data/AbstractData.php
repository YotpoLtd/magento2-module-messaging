<?php

namespace Yotpo\SmsBump\Model\Sync\Data;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Yotpo\SmsBump\Model\Config;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Customer\Model\Address as CustomerAddress;
use Yotpo\SmsBump\Helper\Data as MessagingDataHelper;

/**
 * Class AbstractData for common methods
 */
class AbstractData
{
    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var MessagingDataHelper
     */
    protected $messagingDataHelper;

    /**
     * @var Config
     */
    protected $config;

    /**
     * AbstractData constructor.
     * @param CustomerRepositoryInterface $customerRepository
     * @param MessagingDataHelper $messagingDataHelper
     * @param Config $config
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        MessagingDataHelper $messagingDataHelper,
        Config $config
    ) {
        $this->customerRepository = $customerRepository;
        $this->messagingDataHelper = $messagingDataHelper;
        $this->config = $config;
    }

    /**
     * @param int $customerId
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getSmsMarketingCustomAttributeValue($customerId)
    {
        $isAcceptsSmsMarketing = false;
        $attributeCode = $this->config->getConfig('sms_marketing_custom_attribute', $this->config->getStoreId());
        $customAttribute = $this->customerRepository->getById($customerId)
            ->getCustomAttribute($attributeCode);
        if ($customAttribute) {
            $isAcceptsSmsMarketing = $customAttribute->getValue();
        }
        return $isAcceptsSmsMarketing == 1;
    }

    /**
     * @return mixed|string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getSMSMarketingAttributeCode()
    {
        return $this->config->getConfig('sms_marketing_custom_attribute', $this->config->getStoreId());
    }

    /**
     * Prepare address data
     *
     * @param QuoteAddress|CustomerAddress $address
     * @return array<mixed>
     */
    public function prepareAddressData($address)
    {
        $region = $address->getRegion();
        /** @phpstan-ignore-next-line */
        if (is_object($region)) {
            $state = $region->getRegion();
            $provinceCode = $region->getRegionCode();
        } else {
            $state = $address->getRegion();
            $provinceCode = $address->getRegionCode();
        }

        $street = $address->getStreet();
        return [
            'address1' => is_array($street) && count($street) >= 1 ? $street[0] : $street,
            'address2' => is_array($street) && count($street) > 1 ? $street[1] : null,
            'city' => $address->getCity(),
            'company' => $address->getCompany(),
            'state' => $state,
            'zip' => $address->getPostcode(),
            'province_code' => $provinceCode,
            'country_code' => $address->getCountryId(),
            'phone_number' => $this->preparePhoneNumber($address)
        ];
    }

    /**
     * @param QuoteAddress|CustomerAddress $address
     * @return string|null
     */
    public function preparePhoneNumber($address)
    {
        return $address->getTelephone() ? $this->messagingDataHelper->formatPhoneNumber(
            $address->getTelephone(),
            $address->getCountryId()
        ) : null;
    }
}
