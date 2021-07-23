<?php

namespace Yotpo\SmsBump\ViewModel;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Yotpo\SmsBump\Model\Config;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class SyncForms - Gets the data from config table
 */
class SyncForms implements ArgumentInterface
{
    /**
     * @var Config
     */
    protected $yotpoSmsConfig;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * SyncForms constructor.
     *
     * @param Config $yotpoSmsConfig
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Config $yotpoSmsConfig,
        SerializerInterface $serializer
    ) {
        $this->yotpoSmsConfig = $yotpoSmsConfig;
        $this->serializer = $serializer;
    }

    /**
     * Get the response data from config
     *
     * @return mixed
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getSubscriptionData()
    {
        $responseData = [];
        $data = $this->yotpoSmsConfig->getConfig('sync_forms_data');
        if ($data) {
            $unserializedData = $this->serializer->unserialize($data);
            /** @phpstan-ignore-next-line */
            if (isset($unserializedData['forms'])) {
                $responseData = $unserializedData['forms'];
            }
        }
        return $responseData;
    }
}
