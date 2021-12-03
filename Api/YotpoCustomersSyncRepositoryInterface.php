<?php
declare(strict_types=1);

namespace Yotpo\SmsBump\Api;

use Magento\Framework\DataObject;

/**
 * Grid CRUD interface.
 * @api
 */
interface YotpoCustomersSyncRepositoryInterface
{
    /**
     * @return DataObject[]
     */
    public function getByResponseCodes();

    /**
     * @return mixed
     */
    public function create();
}
