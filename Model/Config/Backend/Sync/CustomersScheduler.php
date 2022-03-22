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
     * Path of the yotpo messaging customers sync cron expression string
     */
    // phpcs:ignore
    const YOTPO_MESSAGING_CUSTOMERS_SYNC_CRON_EXPRESSION_PATH = 'crontab/yotpo_messaging_customers_sync/jobs/yotpo_cron_messaging_customers_sync/schedule/cron_expr';

    /**
     * @var ValueFactory
     */
    protected $_configValueFactory;

    /**
     * @var string
     */
    protected $_runModelPath;

    /**
     * CustomersScheduler constructor.
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $scopeConfig
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
        ScopeConfigInterface $scopeConfig,
        TypeListInterface $cacheTypeList,
        ValueFactory $configValueFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_runModelPath = '';
        $this->_configValueFactory = $configValueFactory;
        parent::__construct($context, $registry, $scopeConfig, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     * @throws \Exception
     */
    public function afterSave()
    {
        $customersCronExpressionString = $this->getData('groups/sync_settings/groups/customers_sync/fields/frequency/value');
        try {
            $this->configureCronCustomersSync($customersCronExpressionString);
        } catch (\Exception $exception) {
            throw new AlreadyExistsException(__('We can\'t save the cron expression.'));
        }
        return parent::afterSave();
    }

    /**
     * @param $customersCronExpressionString
     * @return void
     */
    private function configureCronCustomersSync($customersCronExpressionString)
    {
        /** @phpstan-ignore-next-line */
        $this->_configValueFactory->create()->load(
            self::YOTPO_MESSAGING_CUSTOMERS_SYNC_CRON_EXPRESSION_PATH,
            'path'
        )->setValue(
            $customersCronExpressionString
        )->setPath(
            self::YOTPO_MESSAGING_CUSTOMERS_SYNC_CRON_EXPRESSION_PATH
        )->save();
    }
}
