define([
    'Magento_Ui/js/form/form',
    'Magento_Checkout/js/model/quote',
    'mage/url',
    'mage/storage',
    'jquery',
    'Magento_Checkout/js/model/full-screen-loader'
], function (
    Component,
    quote,
    url,
    storage,
    $,
    fullScreenLoader
) {
    'use strict';

    return function (Component) {
        return Component.extend({
            useShippingAddress: function () {
                this._super();
                if (this.isAddressSameAsShipping()) {
                    this.triggerCheckoutSync();
                }
                return true;
            },

            /**
             * Update address action
             */
            updateAddress: function () {
                this._super();
                this.triggerCheckoutSync();
            },

            /**
             * Trigger checkout sync
             */
            triggerCheckoutSync: function () {
                var linkUrl = url.build('yotpo_messaging/checkoutsync/billingaddressupdate');
                fullScreenLoader.startLoader();
                $.ajax({
                    url: linkUrl,
                    data: {
                        newAddress: JSON.stringify(quote.billingAddress())
                    },
                    type: 'post',
                    dataType: 'json',
                    context: this
                }).done(function (response) {
                    fullScreenLoader.stopLoader();
                }).fail(function (error) {
                    fullScreenLoader.stopLoader();
                });
            }
        });
    };
});
