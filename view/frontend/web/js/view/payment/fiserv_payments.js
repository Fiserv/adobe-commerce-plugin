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

		const config = globalThis.checkoutConfig.payment;
		const ch = 'fiserv_commercehub';

		if (config[ch].isActive) {
			rendererList.push(
				{
					type: ch,
					component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-form',
				},
			);
		}

		/** Add view logic here if needed */
		return Component.extend({});
	},
);
