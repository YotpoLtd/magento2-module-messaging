<?php

namespace Yotpo\SmsBump\Model\Sync\Customers;

use Magento\Customer\Model\Address;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Yotpo\SmsBump\Helper\Data as SMSHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Yotpo\Core\Model\Sync\Data\Main;
use Magento\Framework\App\ResourceConnection;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use function GuzzleHttp\json_encode;
use Yotpo\SmsBump\Model\Sync\Data\AbstractData;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\State as AppState;

/**
 * Class Data - Prepare data for customers sync
 */
class Data extends Main
{
    /**
     * @var SMSHelper
     */
    protected $smsHelper;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var LocaleResolver
     */
    protected $localeResolver;

    /**
     * @var array<mixed>
     */
    protected $groupName;

    /**
     * @var GroupCollectionFactory
     */
    protected $groupCollectionFactory;

    /**
     * @var AbstractData
     */
    protected $abstractData;

    /**
     * @var HttpRequest
     */
    protected $request;

    /**
     * @var AppState
     */
    protected $appState;

    /**
     * Data constructor.
     * @param SMSHelper $smsHelper
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManager
     * @param LocaleResolver $localeResolver
     * @param ResourceConnection $resourceConnection
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param AbstractData $abstractData
     * @param HttpRequest $request
     * @param AppState $appState
     */
    public function __construct(
        SMSHelper $smsHelper,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManager,
        LocaleResolver $localeResolver,
        ResourceConnection $resourceConnection,
        GroupCollectionFactory $groupCollectionFactory,
        AbstractData $abstractData,
        HttpRequest $request,
        AppState $appState
    ) {
        $this->smsHelper = $smsHelper;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        $this->localeResolver = $localeResolver;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->abstractData = $abstractData;
        $this->request = $request;
        $this->appState = $appState;
        parent::__construct($resourceConnection);
    }

    /**
     * Prepare customer data
     *
     * @param Customer $customer
     * @param bool $isRealTimeSync
     * @param null|mixed $customerAddress
     * @return array|array[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function prepareData(Customer $customer, $isRealTimeSync, $customerAddress = null)
    {
        $phoneNumber = null;
        $countryId = null;
        $locale = explode('_', $this->localeResolver->getLocale());
        $defaultBillingAddress = $customerAddress ?: $customer->getDefaultBillingAddress();
        if ($defaultBillingAddress) {
            $phoneNumber = $defaultBillingAddress->getTelephone();
            $countryId = $defaultBillingAddress->getCountryId();
        }
        $groupId = $customer->getGroupId();
        $groupName = $this->getGroupName($groupId);
        $gender = null;
        $genderId = $customer->getGender();
        if (is_numeric($genderId)) {
            /** @phpstan-ignore-next-line */
            $gender = $customer->getAttribute('gender')
                ->getSource()->getOptionText($genderId);
        }
        switch ($gender) {
            case 'Female':
                $gender = 'F';
                break;
            case 'Male':
                $gender = 'M';
                break;
            default:
                $gender = null;
                break;
        }
        $data = [
            'customer' => [
                'external_id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'phone_number' => $phoneNumber && $countryId ?
                    $this->smsHelper->formatPhoneNumber(
                        $phoneNumber,
                        $countryId
                    ) : null,
                'first_name' => $customer->getFirstname(),
                'last_name' => $customer->getLastname(),
                'account_created_at' => $this->smsHelper->formatDate($customer->getCreatedAt()),
                'account_status' => $customer->getData('is_active') ? 'enabled' : 'disabled',
                'gender' => $gender,
                'default_language' => $locale[0],
                /** @phpstan-ignore-next-line */
                'default_currency' => $this->storeManager->getStore()->getBaseCurrencyCode(),
                'tags' => $groupName,
                'address' => $defaultBillingAddress ? $this->prepareAddress($defaultBillingAddress) : null,
                'accepts_sms_marketing' => $this->getCustomAttributeValue($customer->getId())
            ]
        ];
        if ($isRealTimeSync) {
            $dataBeforeChange = $this->getDataBeforeChange();
            $newData = json_encode($data);
            if ($dataBeforeChange == $newData) {
                return [];//no change in customer data
            }
            $this->customerSession->setYotpoCustomerData(json_encode($data));
        }
        return $data;
    }

    /**
     * Prepare custom attribute value
     *
     * @param int $customerId
     * @return bool|string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCustomAttributeValue($customerId)
    {
        $postValue = null;
        $customAttributeValue =  $this->abstractData->getSmsMarketingCustomAttributeValue($customerId);
        $postRequest = $this->request->getPost();
        if ($postRequest) {
            $postValue = $this->request->getPostValue('yotpo_accepts_sms_marketing');
            $countryId = $this->request->getPostValue('country_id');
            //If form submit is not from address update
            if (!$countryId && $this->appState->getAreaCode() == 'frontend') {
                return $postValue == 1;
            } else {
                return $customAttributeValue;
            }
        }
        return $customAttributeValue;
    }

    /**
     * Get previous customer data from session
     *
     * @return mixed
     */
    public function getDataBeforeChange()
    {
        return $this->customerSession->getYotpoCustomerData();
    }

    /**
     * Prepare address data
     *
     * @param Address $address
     * @return array<mixed>
     */
    public function prepareAddress($address)
    {
        return $this->abstractData->prepareAddressData($address);
    }

    /**
     * Get customer group name
     *
     * @param int $groupId
     * @return mixed|null
     */
    public function getGroupName($groupId)
    {
        if ($this->groupName === null) {
            $this->groupName = $this->getGroupNames();
        }
        if (array_key_exists($groupId, $this->groupName)) {
            return $this->groupName[$groupId];
        }
        return null;
    }

    /**
     * Get customer group collection
     *
     * @return array<mixed>
     */
    public function getGroupNames()
    {
        $customerGroupCollection = $this->groupCollectionFactory->create();
        $customerGroupsData = [];
        foreach ($customerGroupCollection as $customerGroup) {
            $customerGroupsData[$customerGroup->getId()] = $customerGroup->getCode();
        }
        return $customerGroupsData;
    }
}
