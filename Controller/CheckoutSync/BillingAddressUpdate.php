<?php

namespace Yotpo\SmsBump\Controller\CheckoutSync;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Checkout\Model\Session as CheckoutSession;
use Yotpo\SmsBump\Model\Sync\Checkout\Processor as CheckoutProcessor;

/**
 * Class BillingAddressUpdate - Calls checkout sync when billing address is updated
 */
class BillingAddressUpdate implements ActionInterface
{
    /**
     * Json Factory
     *
     * @var JsonFactory
     */
    protected $jsonResultFactory;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CheckoutProcessor
     */
    protected $checkoutProcessor;

    /**
     * BillingAddressUpdate constructor.
     * @param JsonFactory $jsonResultFactory
     * @param RequestInterface $request
     * @param CheckoutSession $checkoutSession
     * @param CheckoutProcessor $checkoutProcessor
     */
    public function __construct(
        JsonFactory $jsonResultFactory,
        RequestInterface $request,
        CheckoutSession $checkoutSession,
        CheckoutProcessor $checkoutProcessor
    ) {
        $this->jsonResultFactory = $jsonResultFactory;
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->checkoutProcessor = $checkoutProcessor;
    }

    /**
     * Calls checkout sync
     *
     * @return ResponseInterface|Json|ResultInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $fieldsMapping = [
            'country_id' => 'countryId',
            'region_id' => 'regionId',
            'region_code' => 'regionCode',
            'region' => 'region',
            'street' => 'street',
            'company' => 'company',
            'telephone' => 'telephone',
            'postcode' => 'postcode',
            'city' => 'city',
            'firstname' => 'firstname',
            'lastname' => 'lastname'
        ];
        $newBillingAddress = $this->request->getParam('newAddress', null);
        if ($newBillingAddress) {
            $address = json_decode($newBillingAddress, true);
            $newBillingAddressObject = new \Magento\Framework\DataObject();
            foreach ($fieldsMapping as $key => $value) {
                $newBillingAddressObject->setData($key, $address[$value] ?? '');
            }
            $quote = $this->checkoutSession->getQuote();
            $quote->setData('newBillingAddress', $newBillingAddressObject);
            $this->checkoutProcessor->process($quote);
        }
        return $this->jsonResultFactory->create();
    }
}
