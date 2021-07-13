<?php

namespace Yotpo\SmsBump\Controller\Adminhtml\SyncForms;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Safe\Exceptions\DatetimeException;
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
     * Index constructor.
     * @param Context $context
     * @param Processor $subscriptionProcessor
     * @param StoreWebsiteRelationInterface $storeWebsiteRelation
     */
    public function __construct(
        Context $context,
        Processor $subscriptionProcessor,
        StoreWebsiteRelationInterface $storeWebsiteRelation
    ) {
        $this->messageManager = $context->getMessageManager();
        $this->subscriptionProcessor = $subscriptionProcessor;
        $this->storeWebsiteRelation = $storeWebsiteRelation;
        parent::__construct($context);
    }

    /**
     * Process subscription forms sync
     *
     * @return ResponseInterface|ResultInterface|void
     * @throws DatetimeException
     */
    public function execute()
    {
        try {
            $storeIds = [];
            $storeId = $this->_request->getParam('store');
            $websiteId = $this->_request->getParam('website');
            if ($storeId && $storeId !== 0) {
                $storeIds[] = $storeId;
                $this->subscriptionProcessor->processStore($storeIds);
            } elseif ($websiteId && $websiteId !== 0) {
                $this->subscriptionProcessor
                   ->processStore($this->storeWebsiteRelation->getStoreByWebsiteId($websiteId));
            } else {
                $this->subscriptionProcessor->process();
            }
        } catch (NoSuchEntityException | LocalizedException $e) {
            $this->messageManager
                ->addErrorMessage(__('Something went wrong while processing the subscription forms sync.'));
        }
    }
}
