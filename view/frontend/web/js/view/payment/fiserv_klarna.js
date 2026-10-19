/* eslint-disable unicorn/filename-case -- Magento payment filenames match their payment codes. */
define(
	[
		'uiComponent',
		'Magento_Checkout/js/model/payment/renderer-list',
	],
	(
		Component,
		rendererList,
	) => {
		const config = globalThis.checkoutConfig?.payment || {};

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
