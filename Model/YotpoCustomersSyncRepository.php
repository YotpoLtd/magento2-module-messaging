<?php
declare(strict_types=1);


namespace Yotpo\SmsBump\Model;

use Magento\Framework\DataObject;
use Yotpo\SmsBump\Api\YotpoCustomersSyncRepositoryInterface;
use Yotpo\SmsBump\Model\ResourceModel\YotpoCustomersSync as ResourceModel;
use Yotpo\SmsBump\Model\ResourceModel\YotpoCustomersSync\CollectionFactory as YotpoCustomersSyncCollectionFactory;

class YotpoCustomersSyncRepository implements YotpoCustomersSyncRepositoryInterface
{
    /**
     * @var ResourceModel
     */
    protected $resource;

    /**
     * @var YotpoCustomersSyncFactory
     */
    protected $modelFactory;

    /**
     * @var YotpoCustomersSyncCollectionFactory
     */
    protected $yotpoCustomersSyncCollectionFactory;

    /**
     * YotpoCustomersSyncRepository constructor.
     * @param ResourceModel $resource
     * @param YotpoCustomersSyncFactory $modelFactory
     * @param YotpoCustomersSyncCollectionFactory $yotpoCustomersSyncCollectionFactory
     */
    public function __construct(
        ResourceModel $resource,
        YotpoCustomersSyncFactory $modelFactory,
        YotpoCustomersSyncCollectionFactory $yotpoCustomersSyncCollectionFactory
    ) {
        $this->resource = $resource;
        $this->modelFactory = $modelFactory;
        $this->yotpoCustomersSyncCollectionFactory = $yotpoCustomersSyncCollectionFactory;
    }

    /**
     * @return DataObject[]
     */
    public function getByResponseCodes()
    {
        $customers = $this->yotpoCustomersSyncCollectionFactory->create();
        $customers
            ->addFieldToFilter('response_code', ['gteq' => \Yotpo\Core\Model\Config::BAD_REQUEST_RESPONSE_CODE])
            ->addFieldToSelect(['customer_id', 'store_id']);
        return $customers->getItems();
    }

    /**
     * @return mixed|YotpoCustomersSync
     */
    public function create()
    {
        return $this->modelFactory->create();
    }
}
