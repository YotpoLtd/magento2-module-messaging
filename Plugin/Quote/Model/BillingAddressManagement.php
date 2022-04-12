<?php

namespace Yotpo\SmsBump\Plugin\Quote\Model;

use Magento\Quote\Model\BillingAddressManagement as QuoteBillingAddressManagement;
use Magento\Quote\Api\Data\AddressInterface;

/**
 * Class BillingAddressManagement
 * Plugin to trigger checkout sync
 */
class BillingAddressManagement extends AbstractCheckoutTrigger
{
    /**
     * @method afterAssign
     * @param QuoteBillingAddressManagement $billingAddressManagement
     * @param int $result
     * @param int $cartId
     * @param AddressInterface $address
     * @param boolean $useForShipping
     * @return int
     */
    public function afterAssign(QuoteBillingAddressManagement $billingAddressManagement, $result, $cartId, AddressInterface $address, $useForShipping = false)
    {
        if ($this->registry->registry('yotpo_smsbump_quote_set_billing_address_plugin')) {
            $this->registry->unregister('yotpo_smsbump_quote_set_billing_address_plugin');
        } elseif ($this->yotpoMessagingConfig->isCheckoutSyncActive()) {
            $quote = $this->quoteRepository->getActive($cartId);
            $this->checkoutSync($quote);
        }

        return $result;
    }
}
