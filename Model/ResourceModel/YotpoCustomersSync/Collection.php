<?php

namespace Yotpo\SmsBump\Model\ResourceModel\YotpoCustomersSync;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Yotpo\SmsBump\Model\ResourceModel\YotpoCustomersSync as ResourceYotpoCustomersSync;
use Yotpo\SmsBump\Model\YotpoCustomersSync;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    /**
     * Resource collection initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(YotpoCustomersSync::class, ResourceYotpoCustomersSync::class);
    }
}
