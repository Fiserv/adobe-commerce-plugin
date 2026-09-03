/* eslint-disable unicorn/filename-case, prefer-arrow-callback, unicorn/prefer-module */
define([
	'uiComponent',
	'Magento_Checkout/js/model/payment/renderer-list',
], function (
	Component,
	rendererList,
) {
	'use strict';

	rendererList.push({
		type: 'fiserv_cashapp',
		component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-cashapp',
	});

	return Component.extend({});
});
