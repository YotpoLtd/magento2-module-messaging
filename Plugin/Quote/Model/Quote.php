<?php

namespace Yotpo\SmsBump\Plugin\Quote\Model;

use Magento\Quote\Model\Quote as QuoteModel;
use Magento\Quote\Api\Data\AddressInterface;

/**
 * Class Quote
 * Plugin to trigger checkout sync
 */
class Quote extends AbstractCheckoutTrigger
{
    /**
     * @method afterSetBillingAddress
     * @param QuoteModel $quote
     * @param QuoteModel $result
     * @param AddressInterface $address
     * @return QuoteModel
     */
    public function afterSetBillingAddress(QuoteModel $quote, $result, AddressInterface $address = null)
    {
        $this->checkoutSync($quote);
        if (!$this->registry->registry('yotpo_smsbump_quote_set_billing_address_plugin')){
            $this->registry->register('yotpo_smsbump_quote_set_billing_address_plugin', 1);
        }

        return $result;
    }

    /**
     * @method afterSetShippingAddress
     * @param QuoteModel $quote
     * @param QuoteModel $result
     * @param AddressInterface $address
     * @return QuoteModel
     */
    public function afterSetShippingAddress(QuoteModel $quote, $result, AddressInterface $address = null)
    {
        $this->checkoutSync($quote);
        if (!$this->registry->registry('yotpo_smsbump_quote_set_shipping_address_plugin')){
            $this->registry->register('yotpo_smsbump_quote_set_shipping_address_plugin', 1);
        }

        return $result;
    }

    /**
     * @method afterSave
     * @param QuoteModel $quote
     * @param QuoteModel $result
     * @return QuoteModel
     */
    public function afterSave(QuoteModel $quote, $result)
    {
        $this->checkoutSync($quote);
        return $result;
    }
}
