define(
	[
		'uiComponent',
		'Magento_Checkout/js/model/payment/renderer-list',
	],
	function (
		Component,
		rendererList,
	) {
		'use strict';

		let config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment : {};
		let paypalCode = 'fiserv_paypal';
		let fastlaneCode = 'fastlane';

		if (config[paypalCode] && config[paypalCode].isActive && config[paypalCode][fastlaneCode]) {
			rendererList.push({
				type: paypalCode + "_" + fastlaneCode,
				component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-paypal-fastlane',
			});
		}

		/** Add view logic here if needed */
        return Component.extend({});
	}
);
