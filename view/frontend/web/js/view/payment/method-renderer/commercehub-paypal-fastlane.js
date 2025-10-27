/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
    'jquery',
    'Magento_Payment/js/view/payment/cc-form',
    'Magento_Checkout/js/model/quote',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/full-screen-loader',
	'Magento_Checkout/js/model/payment/method-list',
	'Fiserv_Payments/js/view/payment/paypal/fastlane',
    'ko',
    'mage/translate',
    'domReady!'
], function (
    $,
    Component,
    quote,
    globalMessageList,
    checkoutData,
    fullScreenLoader,
	methodList,
	fastlaneHelper,
    ko,
    $t
) {
    'use strict';

    return Component.extend({
        isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),
        defaults: {
            template: 'Fiserv_Payments/payment/commercehub/paypal-fastlane-form',
            code: 'fiserv_paypal_fastlane',
            active: false,
            sortOrder: 1,
            additionalData: {},
            paymentMethodName: '[name="payment[method]"',
			isEngaged: fastlaneHelper.isEngaged,
			maskedCardNumber: fastlaneHelper.maskedCardNumber,
			nameOnCard: fastlaneHelper.nameOnCard,
			cardExpiry: fastlaneHelper.cardExpiry,
			cardBrand: fastlaneHelper.cardBrand,
			fastlaneApplied: false
		},

        initialize: function () {
            quote.billingAddress.subscribe(function (address) {
                this.isPlaceOrderActionAllowed(address !== null);
            }, this);

            this._super();
			
			let currentMethods = methodList();
			methodList([]);
			methodList.subscribe((methods) => {
				this.handleFastlaneEngagement(methods);
			});
			methodList(currentMethods);

			return this;
		},

		handleFastlaneEngagement: function (methods)
		{
			if (this.fastlaneApplied || !this.isEngaged())
			{
				return;
			}

			let code = this.getCode();
			let method = methods.find((m) => {
				return m.method == code || m.code == code;
			});

			if (method)
			{
				this.fastlaneApplied = true;

				checkoutData.setSelectedPaymentMethod(code);
				this.setMethod(method);
			}
		},

		setMethod: function (method)
		{
			method.__disableTmpl = {
				title: true
			};
			quote.paymentMethod(method);
		},

		getData: function () {
			var data = {
				'method': this.getCode(),
				'additional_data': {
					'fastlane_customer': fastlaneHelper.customerId(),
					'fastlane_card_id': fastlaneHelper.cardId(),
					'fastlane_session_id': fastlaneHelper.sessionId()
				}
			};

			data['additional_data'] = _.extend(data['additional_data'], this.additionalData); 

			return data;
		}, 

        /**
         * Check if payment is active
         *
         * @returns {Boolean}
         */
        isActive: function () {
          	let active = this.getCode() === this.isChecked();
            this.active(active);
            return active;
        },

		getTitle: function() {
			return "PayPal Fastlane";		
		},
		
		getCode: function () {
            return this.code;
        },

        /**
         * Show error message
         *
         * @param {String} errorMessage
         * @private
         */
        showError: function (errorMessage) {
            globalMessageList.addErrorMessage({
                message: errorMessage
            });
        },

        /**
		 * Set list of observable attributes
		 *
		 * @returns {exports.initObservable}
		 */
		initObservable: function () {
			this._super()
				.observe(['active']);
			return this;
		},

		placeOrder: function () {
			if (this.isEngaged === false) { this.showError("PayPal Fastlane is not an active payment method."); return; }

			return this._super();
		},

        refreshBillingAddress: function () {
			if (this.isAchActive() && quote.billingAddress()) {
				shpfUtils.setBillingAddress(quote.billingAddress());
				this.initAchIframe();
			}
		}
    });
});
