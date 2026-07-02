/** ensures the billing address always starts as the shipping address that was chosen on the shipping step */
define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-billing-address'
], function (quote, selectBillingAddressAction) {

    return function (Component) {
        return Component.extend({

            /** @inheritdoc */
            initialize: function () {
                var self = this;

                this._super();

                setTimeout(function () {self.syncBillingWithShipping();}, 0);

                quote.shippingAddress.subscribe(function () {
                    if (self.isAddressSameAsShipping && self.isAddressSameAsShipping()) {
                        self.syncBillingWithShipping();
                    }
                });

                return this;
            },

            syncBillingWithShipping: function () {
                var shippingAddress = quote.shippingAddress();

                if (!shippingAddress || (quote.isVirtual && quote.isVirtual())) {return;}

                selectBillingAddressAction(shippingAddress);

                if (this.isAddressSameAsShipping && !this.isAddressSameAsShipping()) {
                    this.isAddressSameAsShipping(true);
                }
            }
        });
    };
});
