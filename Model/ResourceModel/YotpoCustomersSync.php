<?php
declare(strict_types=1);

namespace Yotpo\SmsBump\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class YotpoCustomersSync extends AbstractDb
{
    /**
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('yotpo_customers_sync', 'entity_id');
    }
}
