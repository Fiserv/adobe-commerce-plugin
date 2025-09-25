define([
	'jquery',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-session',
	'chBraintreeClient',
	'ch-braintree-hosted-fields',
	'Magento_Checkout/js/model/new-customer-address',
	'Magento_Checkout/js/action/select-billing-address'
], function (
	$,
	chAdapter,
	chSession,
	chBraintreeClient,
	chBraintreeHostedFields,
	newAddress,
	selectBillingAddress
) {
	'use strict';

	return {
		fastlane: undefined,

		authenticateResult: undefined,

		addressComponent: undefined,

		watermarkElementID: 'fiserv-paypal-watermark-container',

		consentElementID: 'fiserv-paypal-consent-container',

		fastlaneInit: async function() {

			var self = this;

			const paypal = await window.fiserv.components.paypal();

			window.braintree = window.braintree || {};
			window.braintree.client = chBraintreeClient;
			window.braintree.hostedFields = chBraintreeHostedFields;

			this.fastlane = await paypal.fastlane();
			this.addressComponent = await this.getAddressComponent();

			return this.fastlane;
		},

		renderFastlaneWatermark: async function() {
			try {
				await this.fastlane.renderWatermark("fiserv-paypal-watermark-container");
			} catch (error) {
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
				paypalFastlane: {
					watermark: {
						parentElementId: 'fiserv-paypal-watermark-container'
					}
				},
				hooks: {}
			});
		},

		authenticateEmailForFastlane: async function () {

			const emailInputValue = $("#customer-email").val();

			try {
				this.authenticateResult = await this.fastlane.authenticate({
					email: emailInputValue
				});

				if( ! this.authenticateResult.isGuestCheckout) {
					const symbolKey = Object.getOwnPropertySymbols(this.authenticateResult)[0];
					const symbolData = this.authenticateResult[symbolKey];
					console.log(symbolData.customerContextId)
					console.log(this.authenticateResult);

					const profileData = symbolData.profile;

					const magentoAddress = {
						firstname: profileData.shippingAddress.name.firstName,
						lastname: profileData.shippingAddress.name.lastName,
						street: [profileData.shippingAddress.address.addressLine1, profileData.shippingAddress.address.addressLine2],
						city: profileData.shippingAddress.address.adminArea2,
						region: profileData.shippingAddress.address.adminArea1,
						postcode: profileData.shippingAddress.address.postalCode,
						countryId: profileData.shippingAddress.address.countryCode,
						telephone: profileData.shippingAddress.phoneNumber.nationalNumber
					};


					$('input[name="firstname"]').val(magentoAddress.firstname);
					$('input[name="lastname"]').val(magentoAddress.lastname);
					$('input[name="street[0]"]').val(magentoAddress.street[0]);
					$('input[name="street[1]"]').val(magentoAddress.street[1]);
					$('input[name="country_id"]').val(magentoAddress.countryId);
					$('input[name="region_id"]').val(magentoAddress.region);
					$('input[name="city"]').val(magentoAddress.city);
					$('input[name="postcode"]').val(magentoAddress.postcode);
					$('input[name="telephone"]').val(magentoAddress.telephone);

					const newBillingAddress = newAddress(magentoAddress);

					selectBillingAddress(newBillingAddress);
				}
			} catch (error) {
				console.error("Authentication failed:", error);
			}
		}
	};
});
