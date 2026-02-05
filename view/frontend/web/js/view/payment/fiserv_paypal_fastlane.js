define(
	[
		'uiComponent',
		'Magento_Checkout/js/model/payment/renderer-list',
	],
	(
		Component,
		rendererList,
	) => {
		'use strict';

		const config = (globalThis.checkoutConfig && globalThis.checkoutConfig.payment) ? globalThis.checkoutConfig.payment : {};
		const paypalCode = 'fiserv_paypal';
		const fastlaneCode = 'fastlane';

		if (config[paypalCode] && config[paypalCode].isActive && config[paypalCode][fastlaneCode]) {
			rendererList.push({
				type: `${paypalCode}_${fastlaneCode}`,
				component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-paypal-fastlane',
			});
		}

		/** Add view logic here if needed */
		return Component.extend({});
	},
);
