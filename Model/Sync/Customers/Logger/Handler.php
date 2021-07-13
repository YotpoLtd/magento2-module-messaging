<?php

namespace Yotpo\SmsBump\Model\Sync\Customers\Logger;

use Yotpo\SmsBump\Model\Logger\Handler as SmsBumpMainHandler;

/**
 * Class Handler - For customized logging
 */
class Handler extends SmsBumpMainHandler
{
    /** @phpstan-ignore-next-line */
    const FILE_NAME = BP . '/var/log/yotpo/customers.log';

    /**
     * File name
     *
     * @var string
     */
    protected $fileName = '/var/log/yotpo/customers.log';
}
