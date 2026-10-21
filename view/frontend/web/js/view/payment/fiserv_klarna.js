/* eslint-disable unicorn/filename-case -- Magento requires the fiserv_klarna AMD filename. */
define(
	[
		'uiComponent',
		'Magento_Checkout/js/model/payment/renderer-list',
	],
	(
		Component,
		rendererList,
	) => {
		// eslint-disable-next-line unicorn/prefer-module -- Magento requires strict AMD, not ES modules.
		'use strict';

		const config = (globalThis.checkoutConfig && globalThis.checkoutConfig.payment) ?
			globalThis.checkoutConfig.payment :
			{};

		const klarnaCode = 'fiserv_klarna';

		if (config[klarnaCode] && config[klarnaCode].isActive) {
			rendererList.push(
				{
					type: klarnaCode,
					component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-klarna',
				},
			);
		}

		return Component.extend({});
	},
);
