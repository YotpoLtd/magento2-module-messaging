<?php

namespace Yotpo\SmsBump\Model\AbandonedCart;

use Magento\Checkout\Model\Type\Onepage;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\Exception\SessionException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Yotpo\Core\Model\AbstractJobs;
use Yotpo\SmsBump\Model\Session as YotpoSmsBumpSession;
use Magento\Framework\App\ResourceConnection;

/**
 * Class Data - Sets quote data of abandoned cart
 */
class Data extends AbstractJobs
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var Onepage
     */
    protected $onepage;

    /**
     * @var YotpoSmsBumpSession
     */
    protected $yotpoSmsBumpSession;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var AppEmulation
     */
    protected $appEmulation;

    /**
     * Data constructor.
     * @param CheckoutSession $checkoutSession
     * @param QuoteRepository $quoteRepository
     * @param Onepage $onepage
     * @param YotpoSmsBumpSession $yotpoSmsBumpSession
     * @param ResourceConnection $resourceConnection
     * @param AppEmulation $appEmulation
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        QuoteRepository $quoteRepository,
        Onepage $onepage,
        YotpoSmsBumpSession $yotpoSmsBumpSession,
        ResourceConnection $resourceConnection,
        AppEmulation $appEmulation
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->onepage = $onepage;
        $this->yotpoSmsBumpSession = $yotpoSmsBumpSession;
        parent::__construct($appEmulation, $resourceConnection);
    }

    /**
     * @return mixed|null
     */
    public function getYotpoToken()
    {
        return $this->yotpoSmsBumpSession->getData('yotpoToken');
    }

    /**
     * @param int $quoteId
     * @return bool
     * @throws NoSuchEntityException
     * @throws SessionException
     */
    public function setQuoteData($quoteId)
    {
        /** @var Quote $abandonedQuote */
        $abandonedQuote = $this->quoteRepository->get($quoteId);
        /** @phpstan-ignore-next-line */
        if (!$abandonedQuote || !$abandonedQuote->getId() || !$abandonedQuote->getIsActive()) {
            return false;
        }
        $this->checkoutSession->start();
        $this->onepage->setQuote($abandonedQuote);
        /** @phpstan-ignore-next-line */
        $abandonedQuote->setIsActive(true)->setReservedOrderId(null);
        $this->quoteRepository->save($abandonedQuote);
        $this->checkoutSession->replaceQuote($abandonedQuote);
        return true;
    }

    /**
     * @param string $yotpoToken
     * @return string
     */
    public function getQuoteId($yotpoToken)
    {
        $connection = $this->resourceConnection->getConnection();
        $query = $connection->select()->from(
            ['e' => $this->resourceConnection->getTableName('yotpo_abandoned_cart')],
            'e.quote_id'
        )->where(
            'quote_token = ?',
            $yotpoToken
        );
        return $connection->fetchOne($query);
    }

    /**
     * @param string $quoteId
     * @return string
     */
    public function getQuoteToken($quoteId)
    {
        $connection = $this->resourceConnection->getConnection();
        $query = $connection->select()->from(
            ['e' => $this->resourceConnection->getTableName('yotpo_abandoned_cart')],
            'e.quote_token'
        )->where(
            'quote_id = ?',
            $quoteId
        );
        return $connection->fetchOne($query);
    }

    /**
     * @param Quote $quote
     * @param string $email
     * @return string
     */
    public function updateAbandonedCartDataAndReturnToken($quote, $email)
    {
        $quoteToken = strtotime('now').uniqid();
        $abandonedData[] = [
            'quote_id'      =>  $quote->getId(),
            'store_id'      =>  $quote->getStoreId(),
            'email'         =>  $email,
            'quote_token'   =>  $quoteToken
        ];
        $this->insertOnDuplicate('yotpo_abandoned_cart', $abandonedData);

        return $quoteToken;
    }
}
