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
            template: 'Fiserv_Payments/payment/commercehub/paypal-form',
            code: 'fiserv_paypal',
            buttonContainerId: 'paypal-button-container',
            active: false,
            sortOrder: 20,
            initializedAmount: 0.00
        },

        initialize: function () {
            this._super();
            // Observe billing address to allow placeOrder action
            quote.billingAddress.subscribe(function (address) {
                this.isPlaceOrderActionAllowed(address !== null);
            }, this);

            // Observe shipping address change, reinitialize SDK if this payment method is selected
            quote.shippingAddress.subscribe(async () => {
                if (this.isChecked() === this.getCode()) {
                    await this.initChAdapter();
                }
            });

            return this;
        },

        observeTotals: function () {
            quote.totals.subscribe((totals) => {
                if (
                    this.getCode() === this.isChecked() &&
                    this.initializedAmount &&
                    this.initializedAmount > 0.00 &&
                    totals &&
                    totals['grand_total'] &&
                    totals['grand_total'] != this.initializedAmount
                ) {
                    location.reload();
                }
            });
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
                    $('#paypal-button-container').empty();
                }
            });
        }
    });
});
