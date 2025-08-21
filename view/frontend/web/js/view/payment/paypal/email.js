define([
	'uiComponent',
	'ko',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-session',
	'Fiserv_Payments/js/action/modify-requirejs'
], function (
	Component,
	ko,
	chAdapter,
	chSession,
	modifyRequirejs
) {
	'use strict';

	return Component.extend({
		defaults: {
			template: 'Fiserv_Payments/payment/paypal/email'
		},
		initialize: async function () {
			this._super();

			let maps = {
				'braintree/client.min' : 'ch-braintree-client',
				'braintree/hosted-fields.min' : 'ch-braintree-hosted-fields'
			};
			modifyRequirejs( maps );
			let credsResponse = undefined;
			var self = this;
			try {
				credsResponse = await chSession( { "threeDSecure" : false } );
			} catch (error) {
				console.log("An error occurred while starting Commercehub payment session: ".concat(error));
				return;
			}

			let credentials = credsResponse["ch_credentials"];

			await chAdapter.initSdk(
				window.checkoutConfig.payment['fiserv_commercehub'],
				credentials
			);

			const paypal = await window.fiserv.components.paypal();

			const fastlane = await paypal.fastlane({metadata: { geoLocOverride: "US" }});

			return this;
		}
	});
});
