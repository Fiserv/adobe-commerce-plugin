/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
    'jquery',
    'Fiserv_Payments/js/commercehub-paypal-venmo-base',
    'Magento_Checkout/js/model/quote',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/full-screen-loader',
    'ko',
    'mage/translate',
    'domReady!'
], function (
    $,
    PayPalVenmoBase,
    quote,
    globalMessageList,
    checkoutData,
    fullScreenLoader,
    ko,
    $t
) {
    'use strict';

    return PayPalVenmoBase.extend(PaymentMethodMixin, {
        defaults: {
            template: 'Fiserv_Payments/payment/commercehub/venmo-form',
            code: 'fiserv_venmo',
            buttonContainerId: 'venmo-button-container',
            active: false,
            sortOrder: 20
        },

        initialize: function () {
            this._super();
            quote.billingAddress.subscribe(function (address) {
                this.isPlaceOrderActionAllowed(address !== null);
            }, this);

            this.loadPayPalForm();

            return this;
        },

        loadPayPalForm: function () {
            if (this.isChecked() === this.getCode()) {
                this.initChAdapter();
            }
        },

        isVenmoEnabled: function () {
            var config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment[this.getCode()] : {};
            return config && config.venmoConfig && config.venmoConfig.enableVenmo;
        }
    });
});
