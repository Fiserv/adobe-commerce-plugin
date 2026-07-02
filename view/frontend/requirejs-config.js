var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/billing-address': {
                'Fiserv_Payments/js/view/payment/billing-address-mixin': true
            },
            'Magento_Checkout/js/model/checkout-data-resolver': {
                'Fiserv_Payments/js/model/checkout-data-resolver-mixin': true
            }
        }
    }
};

