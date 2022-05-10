<?php

namespace Yotpo\SmsBump\Plugin\Quote\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\ShippingAddressManagement as QuoteShippingAddressManagement;
use Magento\Quote\Api\Data\AddressInterface;

/**
 * Class ShippingAddressManagement
 * Plugin to trigger checkout sync
 */
class ShippingAddressManagement extends AbstractCheckoutTrigger
{
    /**
     * @param QuoteShippingAddressManagement $shippingAddressManagement
     * @param int $result
     * @param int $cartId
     * @param AddressInterface $address
     * @return mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function afterAssign(
        QuoteShippingAddressManagement $shippingAddressManagement,
        $result,
        $cartId,
        AddressInterface $address
    ) {
        if ($this->registry->registry('yotpo_smsbump_quote_set_shipping_address_plugin')) {
            $this->registry->unregister('yotpo_smsbump_quote_set_shipping_address_plugin');
        } elseif ($this->yotpoMessagingConfig->isCheckoutSyncActive()) {
            /** @var \Magento\Quote\Model\Quote $quote **/
            $quote = $this->quoteRepository->getActive($cartId);
            $this->checkoutSync($quote);
        }

        return $result;
    }
}
