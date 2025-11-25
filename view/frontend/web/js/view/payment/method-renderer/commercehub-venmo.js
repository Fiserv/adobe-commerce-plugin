/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
    'jquery',
    'Fiserv_Payments/js/view/payment/method-renderer/commercehub-paypal-venmo-base',
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

    return PayPalVenmoBase.extend({
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
                this.initchAdapter();
            }
        },

        getData: function () {
            var data = {
                method: this.getCode(),
                additional_data: this.additionalData || {}
            };
            if (this.paymentPayload && this.paymentPayload.sessionId) {
                data.additional_data.payment_session = this.paymentPayload.sessionId;
            }
            return data;
        },

        isVenmoEnabled: function () {
            var config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment[this.getCode()] : {};
            return config && config.venmoConfig && config.venmoConfig.enableVenmo;
        },

        isActive: function () {
            let active = this.getCode() === this.isChecked();
            this.active(active);
            return active;
        },

        initObservable: function () {
            this._super()
                .observe(['active']);
            return this;
        },

        watchPaymentMethods: function () {
            let self = this;
            $(self.paymentMethodName).on("click", function () {
                let selected = $(this).attr("id");
                if (selected === self.getCode()) {
                    self.loadPayPalForm();
                } else {
                    $('#venmo-button-container').empty();
                }
            });
        }
    });
});
