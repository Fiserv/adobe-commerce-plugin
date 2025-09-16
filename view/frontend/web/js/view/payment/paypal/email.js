define([
	'jquery',
	'uiComponent',
	'Fiserv_Payments/js/action/modify-requirejs',
	'Magento_Customer/js/model/customer',
	'Fiserv_Payments/js/view/payment/paypal/fastlane'
], function (
	$,
	Component,
	modifyRequirejs,
	customerModel,
	fastlaneHelper
) {
	'use strict';

	return Component.extend({
		defaults: {
			template: 'Fiserv_Payments/payment/paypal/email'
		},
		initialize: async function () {
			this._super();

			let maps = {
				'braintree/client.min' : 'chBraintreeClient',
				'braintree/hosted-fields.min' : 'ch-braintree-hosted-fields'
			};
			modifyRequirejs( maps );

			let fastlane = await fastlaneHelper.fastlaneInit();

			await fastlaneHelper.renderFastlaneWatermark();
	
			const fastlaneEmailSubmitButton = $("#email-submit-button");

			fastlaneEmailSubmitButton.on("click", function() {
				fastlaneHelper.authenticateEmailForFastlane();
			});

			return this;
		},
		isCustomerLoggedIn: function() {
			return customerModel.isLoggedIn();
		}
	});
});
