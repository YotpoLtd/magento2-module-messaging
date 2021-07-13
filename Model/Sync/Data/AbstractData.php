<?php

namespace Yotpo\SmsBump\Model\Sync\Data;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Yotpo\SmsBump\Model\Config;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Customer\Model\Address as CustomerAddress;
use Yotpo\SmsBump\Helper\Data as SMSHelper;

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
     * @var SMSHelper
     */
    protected $smsHelper;

    /**
     * @var Config
     */
    protected $config;

    /**
     * AbstractData constructor.
     * @param CustomerRepositoryInterface $customerRepository
     * @param SMSHelper $smsHelper
     * @param Config $config
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        SMShelper $smsHelper,
        Config $config
    ) {
        $this->customerRepository = $customerRepository;
        $this->smsHelper = $smsHelper;
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
        $customAttributeValue = false;
        $attributeCode = $this->config->getConfig('sms_marketing_custom_attribute', $this->config->getStoreId());
        $customAttribute = $this->customerRepository->getById($customerId)
            ->getCustomAttribute($attributeCode);
        if ($customAttribute) {
            $customAttributeValue = $customAttribute->getValue();
        }
        return $customAttributeValue == 1;
    }

    /**
     * Prepare address data
     *
     * @param QuoteAddress|CustomerAddress $address
     * @return array<mixed>
     */
    public function prepareAddressData($address)
    {
        $street = $address->getStreet();
        return [
            'address1' => is_array($street) && count($street) >= 1 ? $street[0] : $street,
            'address2' => is_array($street) && count($street) > 1 ? $street[1] : null,
            'city' => $address->getCity(),
            'company' => $address->getCompany(),
            'state' => $address->getRegion(),
            'zip' => $address->getPostcode(),
            'province_code' => $address->getRegionCode(),
            'country_code' => $address->getCountryId(),
            'phone_number' => $address->getTelephone() ? $this->smsHelper->formatPhoneNumber(
                $address->getTelephone(),
                $address->getCountryId()
            ) : null
        ];
    }
}
