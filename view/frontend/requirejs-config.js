var config = {
    map: {
        '*': {
            smsMarketingBase: 'Yotpo_SmsBump/js/view/smsMarketingBase',
            smsMarketingPayment: 'Yotpo_SmsBump/js/view/payment/smsMarketing',
            smsMarketingShipping: 'Yotpo_SmsBump/js/view/shipping/smsMarketing'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/view/billing-address': {
                'Yotpo_SmsBump/js/view/billing-address-mixin': true
            }
        }
    }
};
