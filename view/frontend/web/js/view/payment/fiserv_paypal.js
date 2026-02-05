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

		if (config[paypalCode] && config[paypalCode].isActive) {
			rendererList.push({
				type: paypalCode,
				component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-paypal',
			});
		}

		/** Add view logic here if needed */
		return Component.extend({});
	},
);
