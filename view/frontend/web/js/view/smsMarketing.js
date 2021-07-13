define(
    [
        'jquery',
        'ko',
        'uiComponent',
        'mage/url'
    ],
    function ($, ko, Component, url) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Yotpo_SmsBump/smsMarketing'
            },

            getCustomAttrVal: ko.observable(window.checkoutConfig.yotpo.sms_marketing.custom_attr_val),

            isEnabled: function () {
                return window.checkoutConfig.yotpo.sms_marketing.checkout_enabled;
            },

            getHeading: function () {
                return window.checkoutConfig.yotpo.sms_marketing.box_heading;
            },

            getDescription: function () {
                return window.checkoutConfig.yotpo.sms_marketing.description;
            },

            getConsentMessage: function () {
                return window.checkoutConfig.yotpo.sms_marketing.consent_message;
            },

            getPrivacyPolicyText: function () {
                return window.checkoutConfig.yotpo.sms_marketing.privacy_policy_text;
            },

            getPrivacyPolicyUrl: function () {
                return window.BASE_URL + window.checkoutConfig.yotpo.sms_marketing.privacy_policy_url;
            },

            updateCustomerAttribute: function () {
                var linkUrl = url.build('yotposmsbump/smsmarketing/savecustomerattribute');
                var customDiv = $('#yotpo_accepts_sms_marketing');
                var isCheckboxSelected = false;
                if (customDiv) {
                    isCheckboxSelected = customDiv.is(':checked');
                }
                isCheckboxSelected = isCheckboxSelected ? 1 : 0;
                $.ajax({
                    url: linkUrl,
                    type: 'POST',
                    dataType: "json",
                    data: {
                        acceptsSmsMarketing: isCheckboxSelected
                    },
                    success: function (response) {
                    },
                    error: function (response) {
                        console.log('Error::', response)
                    }
                });

                return true;
            }
        });
    }
);
