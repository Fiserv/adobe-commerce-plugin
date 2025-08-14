define([
	'uiComponent',
	'ko'
], function (Component, ko) {
	'use strict';

	return Component.extend({
		defaults: {
			template: 'Fiserv_Payments/payment/paypal/email'
		},
		//email: ko.observable(''),
		//tooltip: ko.observable('We\'ll send your order confirmation here.')
	});
});
