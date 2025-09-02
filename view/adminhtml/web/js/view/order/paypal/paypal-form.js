define([
    'ko',
    'uiComponent',
    'Fiserv_Payments/js/paypal-adapter',
    'Magento_Ui/js/modal/alert',
    'jquery',
    'domReady!'
], function(
    ko,
    Component,
    paypalAdapter,
    alert,
    $
) {
    'use strict';

    return Component.extend({
        defaults: {
            paymentSessionInputId: 'fiserv_paypal_payment_session',
            imports: {
                onActiveChange: 'active'
            }
        },

        initialize: function (config) {
            if (typeof(config.paymentConfig) === "undefined") {
                throw new Error('Required parameter paymentConfig not found!');
            }
            this.paymentConfig = config.paymentConfig;
            this.code = 'fiserv_paypal';
            this.selector = 'edit_form';
            this.paymentMethodName = '[name="payment[method]"]';
            this.$selector = $('#' + this.selector);
            this.initObservable();
            if ($('input#p_method_fiserv_paypal').is(':checked')) {
                this.initPayment();
                this.onActiveChange(true);
            }
        },

        initObservable: function () {
            var self = this;
            self.$selector = $('#' + self.selector);
            this._super()
                .observe([
                    'active'
                ]);
            self.$selector.off('changePaymentMethod.' + self.code)
                .on('changePaymentMethod.' + self.code, self.changePaymentMethod.bind(self));
            return this;
        },

        initPayment: function () {
            if (this.paymentInitiated === true) {
                return;
            }
            var self = this;
            self.paymentInitiated = true;
            self.renderPayPalButtons();
        },

        renderPayPalButtons: function () {
            var self = this;
            var config = self.paymentConfig;
            paypalAdapter.getPayPalCredentials(
                config.storeUrl,
                function (creds) {
                    paypalAdapter.initSdk(config, creds).then(function () {
                        paypalAdapter.loadPayPalComponent({
                            customerId: config.customerId,
                            intent: 'authorize'
                        }).then(function (paypalComponent) {
                            paypalComponent.buttons({
                                data: {
                                    buttons: {
                                        paypal: {
                                            parentElementId: 'paypal-button-container'
                                        },
                                        venmo: {
                                            parentElementId: 'venmo-button-container'
                                        }
                                    }
                                },
                                hooks: {
                                    onApprove: function (data, actions) {
                                        self.setPaymentSessionInput(data.sessionId);
                                        self.placeOrder(data.sessionId);
                                    },
                                    onError: function (err) {
                                        self.showError(err && err.message ? err.message : 'PayPal error');
                                    },
                                    onCancel: function () {},
                                    onShippingAddressChange: function () {},
                                    onShippingOptionsChange: function () {},
                                    onClick: function () {},
                                    onInit: function () {}
                                }
                            });
                        });
                    });
                },
                function (errMsg) {
                    self.showError(errMsg);
                }
            );
        },

        setPaymentSessionInput: function (sessionId) {
            $("input#fiserv_paypal_payment_session").val(sessionId);
        },

        placeOrder: function (sessionId) {
            this.setPaymentSessionInput(sessionId);
            $('#' + this.selector).trigger('realOrder');
        },

        showError: function (errorMessage) {
            alert({
                content: errorMessage
            });
        },

        isActive: function () {
            return $('[value="' + this.code + '"]' + this.paymentMethodName).prop('checked');
        },

        onActiveChange: function (isActive) {
            if (!isActive) {
                return;
            }
            this.renderPayPalButtons();
        },

        changePaymentMethod: function (event, method) {
            this.active(method === this.code);
            this.onActiveChange(this.active());
            return this;
        }
    });
});
