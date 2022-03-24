<?php

namespace Yotpo\SmsBump\Model\Config\Backend\Sync;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value as ConfigValue;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Backend model for API cron scheduler
 *
 */
class CustomersScheduler extends ConfigValue
{
    /**
     * @var string[]
     */
    protected $cronJobPaths = [
        'crontab/yotpo_messaging_customers_sync/jobs/yotpo_cron_messaging_customers_backfill_sync/schedule/cron_expr',
        'crontab/yotpo_messaging_customers_sync/jobs/yotpo_cron_messaging_customers_retry_sync/schedule/cron_expr',
    ];

    /**
     * @var ValueFactory
     */
    protected $configValueFactory;

    /**
     * @var string
     */
    protected $runModelPath;

    /**
     * CustomersScheduler constructor.
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ValueFactory $configValueFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb<mixed>|null $resourceCollection
     * @param array<mixed> $data
     * @SuppressWarnings(PHPMD)
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ValueFactory $configValueFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->runModelPath = '';
        $this->configValueFactory = $configValueFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     * @throws \Exception
     */
    public function afterSave()
    {
        $cronExprString = $this->getData('groups/sync_settings/groups/customers_sync/fields/frequency/value');
        foreach ($this->cronJobPaths as $path) {
            try {
                /** @phpstan-ignore-next-line */
                $this->configValueFactory->create()->load(
                    $path,
                    'path'
                )->setValue(
                    $cronExprString
                )->setPath(
                    $path
                )->save();
            } catch (\Exception $e) {
                throw new AlreadyExistsException(__('We can\'t save the cron expression.'));
            }
        }
        return parent::afterSave();
    }
}
