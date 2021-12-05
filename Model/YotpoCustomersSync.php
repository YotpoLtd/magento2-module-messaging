<?php
declare(strict_types=1);

namespace Yotpo\SmsBump\Model;

use Yotpo\SmsBump\Model\ResourceModel\YotpoCustomersSync as YotpoCustomersSyncResourceModel;
use Magento\Framework\Model\AbstractModel;

class YotpoCustomersSync extends AbstractModel
{
    const CACHE_TAG = 'yotpo_customers_sync';
    const ENTITY_ID = 'entity_id';

    protected function _construct()
    {
        $this->_init(YotpoCustomersSyncResourceModel::class);
    }
}
