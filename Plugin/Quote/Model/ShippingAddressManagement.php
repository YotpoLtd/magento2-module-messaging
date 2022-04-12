<?php

namespace Yotpo\SmsBump\Plugin\Quote\Model;

use Magento\Quote\Model\ShippingAddressManagement as QuoteShippingAddressManagement;
use Magento\Quote\Api\Data\AddressInterface;

/**
 * Class ShippingAddressManagement
 * Plugin to trigger checkout sync
 */
class ShippingAddressManagement extends AbstractCheckoutTrigger
{
    /**
     * @method afterAssign
     * @param QuoteShippingAddressManagement $shippingAddressManagement
     * @param int $result
     * @param int $cartId
     * @param AddressInterface $address
     * @return int
     */
    public function afterAssign(QuoteShippingAddressManagement $shippingAddressManagement, $result, $cartId, AddressInterface $address)
    {
        if ($this->registry->registry('yotpo_smsbump_quote_set_shipping_address_plugin')) {
            $this->registry->unregister('yotpo_smsbump_quote_set_shipping_address_plugin');
        } elseif ($this->yotpoMessagingConfig->isCheckoutSyncActive()) {
            $quote = $this->quoteRepository->getActive($cartId);
            $this->checkoutSync($quote);
        }

        return $result;
    }
}
