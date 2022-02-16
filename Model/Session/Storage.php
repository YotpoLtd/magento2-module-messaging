<?php
namespace Yotpo\SmsBump\Model\Session;

use Magento\Customer\Model\Config\Share;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class Storage extends \Magento\Framework\Session\Storage
{

    /**
     * @param Share $configShare
     * @param StoreManagerInterface $storeManager
     * @param string $namespace
     * @param array <mixed> $data
     * @throws LocalizedException
     */
    public function __construct(
        Share $configShare,
        StoreManagerInterface $storeManager,
        $namespace = 'yotpo_messaging',
        array $data = []
    ) {
        if ($configShare->isWebsiteScope()) {
            $namespace .= '_' . $storeManager->getWebsite()->getCode();
        }
        parent::__construct($namespace, $data);
    }
}
