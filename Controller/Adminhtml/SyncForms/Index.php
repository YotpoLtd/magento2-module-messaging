<?php

namespace Yotpo\SmsBump\Controller\Adminhtml\SyncForms;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Yotpo\SmsBump\Model\Sync\Subscription\Processor;
use Magento\Store\Api\StoreWebsiteRelationInterface;

/**
 * Class Index
 * Sync subscription forms
 */
class Index extends Action
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var Processor
     */
    protected $subscriptionProcessor;

    /**
     * @var StoreWebsiteRelationInterface
     */
    private $storeWebsiteRelation;

    /**
     * Json Factory
     *
     * @var JsonFactory
     */
    protected $jsonResultFactory;

    /**
     * Index constructor.
     * @param Context $context
     * @param Processor $subscriptionProcessor
     * @param StoreWebsiteRelationInterface $storeWebsiteRelation
     * @param JsonFactory $jsonResultFactory
     */
    public function __construct(
        Context $context,
        Processor $subscriptionProcessor,
        StoreWebsiteRelationInterface $storeWebsiteRelation,
        JsonFactory $jsonResultFactory
    ) {
        $this->messageManager = $context->getMessageManager();
        $this->subscriptionProcessor = $subscriptionProcessor;
        $this->storeWebsiteRelation = $storeWebsiteRelation;
        $this->jsonResultFactory = $jsonResultFactory;
        parent::__construct($context);
    }

    /**
     * Process subscription forms sync
     *
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        try {
            $storeIds = [];
            $storeId = $this->_request->getParam('store');
            $websiteId = $this->_request->getParam('website');
            if ($storeId && $storeId !== 0) {
                $storeIds[] = $storeId;
                $status = (int) $this->subscriptionProcessor->processStore($storeIds);
            } elseif ($websiteId && $websiteId !== 0) {
                $status = (int) $this->subscriptionProcessor
                   ->processStore($this->storeWebsiteRelation->getStoreByWebsiteId($websiteId));
            } else {
                $status = (int) $this->subscriptionProcessor->process();
            }
        } catch (NoSuchEntityException | LocalizedException $e) {
            $status = 0;
            $this->messageManager
                ->addErrorMessage(__('Store not found.'));
        }
        $result = $this->jsonResultFactory->create();
        return $result->setData(['status' => $status]);
    }
}
