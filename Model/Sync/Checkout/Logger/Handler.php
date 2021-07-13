<?php

namespace Yotpo\SmsBump\Model\Sync\Checkout\Logger;

use Yotpo\SmsBump\Model\Logger\Handler as SmsBumpMainHandler;

/**
 * Class Handler for custom logger
 */
class Handler extends SmsBumpMainHandler
{
    /** @phpstan-ignore-next-line */
    const FILE_NAME = BP . '/var/log/yotpo/checkout.log';

    /**
     * File name
     *
     * @var string
     */
    protected $fileName = '/var/log/yotpo/checkout.log';
}
