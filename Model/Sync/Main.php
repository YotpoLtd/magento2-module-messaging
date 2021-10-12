<?php
namespace Yotpo\SmsBump\Model\Sync;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Yotpo\Core\Model\Api\Request as YotpoCoreApiRequest;

/**
 * Class Main as a common point of SmsBump checkout sync
 */
class Main
{
    /**
     * @var YotpoCoreApiRequest
     */
    protected $yotpoCoreApiRequest;

    /**
     * Main constructor.
     * @param YotpoCoreApiRequest $yotpoCoreApiRequest
     */
    public function __construct(YotpoCoreApiRequest $yotpoCoreApiRequest)
    {
        $this->yotpoCoreApiRequest = $yotpoCoreApiRequest;
    }

    /**
     * Sync magento data to Yotpo API
     *
     * @param string $method
     * @param string $url
     * @param array<mixed> $data
     * @param string $baseUrlKey
     * @return DataObject
     * @throws NoSuchEntityException
     */
    public function sync($method, $url, array $data = [], $baseUrlKey = 'api'): DataObject
    {
        if (array_key_exists('entityLog', $data)) {
            $logHandler = '';
            switch ($data['entityLog']) {
                case 'checkout':
                    $logHandler  =  \Yotpo\SmsBump\Model\Sync\Checkout\Logger\Handler::class;
                    break;
                case 'customers':
                    $logHandler  =  \Yotpo\SmsBump\Model\Sync\Customers\Logger\Handler::class;
                    break;
                case 'subscription':
                    $logHandler  =  \Yotpo\SmsBump\Model\Sync\Subscription\Logger\Handler::class;
                    break;
                default:
                    break;
            }
            $data['entityLog'] = $logHandler;
        }
        return $this->yotpoCoreApiRequest->send($method, $url, $data, $baseUrlKey);
    }
}
