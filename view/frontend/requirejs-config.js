var config = {
    map: {
        '*': {
            smsMarketing: 'Yotpo_SmsBump/js/view/smsMarketing'
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
