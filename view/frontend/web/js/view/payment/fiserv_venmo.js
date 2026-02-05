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
		const venmoCode = 'fiserv_venmo';

		if (config[venmoCode] && config[venmoCode].isActive) {
			rendererList.push({
				type: venmoCode,
				component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-venmo',
			});
		}

		/** Add view logic here if needed */
		return Component.extend({});
	},
);
