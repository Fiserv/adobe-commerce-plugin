define([
	'jquery',
	'ko',
	'Fiserv_Payments/js/ch-adapter',
	'chBraintreeClient',
	'ch-braintree-hosted-fields',
	'Magento_Checkout/js/model/full-screen-loader',
	'Magento_Checkout/js/model/new-customer-address',
	'Magento_Checkout/js/action/select-billing-address',
	'Magento_Checkout/js/model/payment/method-list'
], function (
	$,
	ko,
	chAdapter,
	chBraintreeClient,
	chBraintreeHostedFields,
	fullScreenLoader,
	newAddress,
	selectBillingAddress,
	methodList
) {
	'use strict';

	return {
		fastlane: undefined,

		authenticateResult: undefined,

		addressComponent: undefined,
		
		isEngaged: ko.observable(false),
		customerId: ko.observable(undefined),
		sessionId: ko.observable(undefined),
		cardId: ko.observable(undefined),
		maskedCardNumber: ko.observable(undefined),
		nameOnCard: ko.observable(undefined),
		cardBrand: ko.observable(undefined),
		cardExpiry: ko.observable(undefined),

		watermarkElementID: 'fiserv-paypal-watermark-container',

		consentElementID: 'fiserv-paypal-consent-container',

		fastlaneInit: async function() {

			var self = this;
			fullScreenLoader.startLoader();

			try {
				const paypal = await window.fiserv.components.paypal();

				window.braintree = window.braintree || {};
				window.braintree.client = chBraintreeClient;
				window.braintree.hostedFields = chBraintreeHostedFields;

				this.fastlane = await paypal.fastlane();
				this.addressComponent = await this.getAddressComponent();
			
				fullScreenLoader.stopLoader();
	
				return this.fastlane;
			}
			catch(e) {
				console.log("unable to load PayPal Fastlane component.");
				fullScreenLoader.stopLoader();
				return undefined;
			}
		},

		renderFastlaneWatermark: async function() {
			try {
				await this.fastlane.renderWatermark("fiserv-paypal-watermark-container");
			} catch (error) {
				fullScreenLoader.stopLoader();
				console.error("Watermark rendering failed:", error);
			}
		},

		getAddressComponent: async function() {
			const inputNames = [
				"firstname",
				"lastname",
				"street[0]",
				"street[1]",
				"country_id",
				"region_id",
				"city",
				"postcode",
				"telephone"
			];

			const fields = [];

			$.each(inputNames, function(_, name) {
				const $input = $('input[name="' + name + '"]');
				const id = $input.attr('id');

				if (id) {
					switch (name) {
						case "firstname":
							fields.firstName = { elementId: id };
							break;
						case "lastname":
							fields.lastName = { elementId: id };
							break;
						case "street[0]":
							fields.houseNumberOrName = { elementId: id };
							break;
						case "street[1]":
							fields.street = { elementId: id };
							break;
						case "city":
							fields.city = { elementId: id };
							break;
						case "region_id":
							fields.stateOrProvince = { elementId: id };
							break;
						case "postcode":
							fields.postalCode = { elementId: id };
							break;
						case "country_id":
							fields.country = { elementId: id };
							break;
					}
				}
			});

			return await window.fiserv.components.address({
				fields: fields,
				hooks: {}
			});
		},

		authenticateEmailForFastlane: async function () {

			const emailInputValue = $("#customer-email").val();

			try {
				this.authenticateResult = await this.fastlane.authenticate({
					email: emailInputValue
				});
				
			} catch (error) {
				fullScreenLoader.stopLoader();
				console.error("Authentication failed:", error);
			}
		},

		processAuthResult: function() {
			if( ! this.authenticateResult.isGuestCheckout) {
				const symbolKey = Object.getOwnPropertySymbols(this.authenticateResult)[0];
				const symbolData = this.authenticateResult[symbolKey];

				const profileData = symbolData.profile;

				const magentoAddress = {
					firstname: profileData.shippingAddress.name.firstName,
					lastname: profileData.shippingAddress.name.lastName,
					street: [profileData.shippingAddress.address.addressLine1, profileData.shippingAddress.address.addressLine2],
					city: profileData.shippingAddress.address.adminArea2,
					region: window.checkoutConfig.payment.fiserv_paypal.regionsData[profileData.shippingAddress.address.countryCode][profileData.shippingAddress.address.adminArea1],
					postcode: profileData.shippingAddress.address.postalCode,
					countryId: profileData.shippingAddress.address.countryCode,
					telephone: profileData.shippingAddress.phoneNumber.nationalNumber,
					email: symbolData.email
				};

				$('input[name="firstname"]').val(magentoAddress.firstname).trigger('change');
				$('input[name="lastname"]').val(magentoAddress.lastname).trigger('change');
				$('input[name="street[0]"]').val(magentoAddress.street[0]).trigger('change');
				$('input[name="street[1]"]').val(magentoAddress.street[1]).trigger('change');
				$('select[name="country_id"]').val(magentoAddress.countryId).trigger('change');
				$('select[name="region_id"]').val(magentoAddress.region.region_id).trigger('change');
				$('input[name="city"]').val(magentoAddress.city).trigger('change');
				$('input[name="postcode"]').val(magentoAddress.postcode).trigger('change');
				$('input[name="telephone"]').val(magentoAddress.telephone).trigger('change');

				const newBillingAddress = newAddress(magentoAddress);

				selectBillingAddress(newBillingAddress);
				this.isEngaged(true);
				this.sessionId(symbolData.sessionId);
				this.cardId(symbolData.profile.card.id);
				this.customerId(symbolData.customerContextId);
				this.maskedCardNumber(symbolData.profile.card.paymentSource.card.lastDigits.padStart(16, 'x'));
				this.nameOnCard(symbolData.profile.card.paymentSource.card.name);
				this.cardBrand(symbolData.profile.card.paymentSource.card.brand);
				this.cardExpiry(symbolData.profile.card.paymentSource.card.expiry);
	
				let currentMethods = methodList();
				methodList([]);
				methodList(currentMethods);
			}
			else {
				this.unengageFastlane();
			}	
		
		},

		unengageFastlane: function() {
			this.isEngaged(false);
			this.sessionId(undefined);
			this.cardId(undefined);
			this.customerId(undefined);
			this.maskedCardNumber(undefined);
			this.nameOnCard(undefined);
			this.cardBrand(undefined);
			this.cardExpiry(undefined);
		},

		getConfigData: function() {
			var self = this;
			let formConfig = {};
			if(this.fastlane) {
				formConfig.paypalFastlane = {
					component: this.fastlane,
					consent: {
						parentElementId: this.consentElementID
					}
				}
			}

			if(this.addressComponent) {
				formConfig.billingAddress = this.addressComponent;
			}

			return formConfig;
		}
	};
});
