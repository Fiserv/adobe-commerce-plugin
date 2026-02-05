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

		const applepayCode = 'fiserv_applepay';

		if (config[applepayCode] && config[applepayCode].isActive) {
			rendererList.push(
				{
					type: applepayCode,
					component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-applepay',
				},
			);
		}

		return Component.extend({});
	},
);
