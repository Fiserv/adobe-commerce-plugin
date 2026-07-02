define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/action/select-billing-address'
], function (quote, checkoutData, selectBillingAddressAction) {
    'use strict';

    return function (resolver) {
        // Save the original before we override it.
        var originalResolveBillingAddress = resolver.resolveBillingAddress.bind(resolver);

        resolver.resolveBillingAddress = function () {
            if (checkoutData.getBillingAddressFromData()) {
                return originalResolveBillingAddress();
            }

            var shippingAddress = quote.shippingAddress();

            if (shippingAddress && !(quote.isVirtual && quote.isVirtual())) {
                selectBillingAddressAction(shippingAddress);
                return;
            }

            return originalResolveBillingAddress();
        };

        return resolver;
    };
});


