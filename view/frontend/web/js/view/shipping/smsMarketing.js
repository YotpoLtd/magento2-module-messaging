define(
    [
        'jquery',
        'ko',
        'smsMarketingBase',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function ($, ko, smsMarketingBase, url, fullScreenLoader) {
        'use strict';
        return smsMarketingBase.extend({
            defaults: {
                template: 'Yotpo_SmsBump/smsMarketing'
            },

            isEnabled: function () {
                return window.checkoutConfig.yotpo.sms_marketing.checkout_enabled &&
                    !window.checkoutConfig.quoteData.is_virtual;
            },

            getCheckoutStep: function () {
                return 'shipping';
            }
        });
    }
);
