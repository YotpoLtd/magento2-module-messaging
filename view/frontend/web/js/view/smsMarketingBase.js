define(
    [
        'jquery',
        'ko',
        'uiComponent',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function ($, ko, Component, url, fullScreenLoader) {
        'use strict';
        return Component.extend({

            getCustomAttrVal: ko.observable(window.checkoutConfig.yotpo.sms_marketing.custom_attr_val),

            isEnabled: function () {
                return true;
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
                var baseURL = window.BASE_URL;
                var privacyPolicyUrl = window.checkoutConfig.yotpo.sms_marketing.privacy_policy_url;
                if (this.isValidUrl(privacyPolicyUrl)){
                    if (privacyPolicyUrl.indexOf('http://') === 0 || privacyPolicyUrl.indexOf('https://') === 0) {
                        return privacyPolicyUrl;
                    } else {
                        return '//' + privacyPolicyUrl.replace(/^\/+/g, '');
                    }
                } else {
                    if (privacyPolicyUrl.indexOf("http://") == 0 || privacyPolicyUrl.indexOf("https://") == 0) {
                        return privacyPolicyUrl.replace(/^\/+/g, '');
                    } else {
                        return baseURL + privacyPolicyUrl.replace(/^\/+/g, '');
                    }
                }
            },

            isValidUrl: function (inputUrl) {
                const matchPattern = /([(http(s)?):\/\/(www\.)?a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*))*?([a-zA-Z0-9][.](?!html)[a-zA-z])/g;
                return matchPattern.test(inputUrl);
            },

            getCheckoutStep: function () {
                return '';
            },

            updateCustomerAttribute: function () {
                var linkUrl = url.build('yotpo/smsmarketing/savecustomerattribute');
                var customDiv = $('#yotpo_accepts_sms_marketing');
                var isCheckboxSelected = false;
                if (customDiv) {
                    isCheckboxSelected = customDiv.is(':checked');
                }
                isCheckboxSelected = isCheckboxSelected ? 1 : 0;
                var checkoutStep = this.getCheckoutStep();
                var customerEmail = '';
                if ($('#customer-email') && $('#customer-email').val()) {
                    customerEmail = $('#customer-email').val();
                }
                fullScreenLoader.startLoader();
                $.ajax({
                    url: linkUrl,
                    type: 'POST',
                    dataType: "json",
                    data: {
                        acceptsSmsMarketing: isCheckboxSelected,
                        checkoutStep: checkoutStep,
                        customerEmail: customerEmail
                    },
                    success: function (response) {
                        fullScreenLoader.stopLoader();
                    },
                    error: function (response) {
                        fullScreenLoader.stopLoader();
                    }
                });

                return true;
            }
        });
    }
);
