/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
	[
		'jquery',
		'Magento_Checkout/js/model/url-builder',
		'mage/storage',
		'Magento_Customer/js/model/customer',
		'Magento_Checkout/js/model/quote',
		'Magento_Checkout/js/action/get-payment-information',
		'Magento_Checkout/js/model/full-screen-loader',
		'Magento_Checkout/js/model/error-processor',
		'Fiserv_Payments/js/model/valuelink/valuelink-messages',
		'mage/translate',
	],
	(
		$,
		urlBuilder,
		storage,
		customer,
		quote,
		getPaymentInformationAction,
		fullScreenLoader,
		errorProcessor,
		messageList,
	) => {
		'use strict';

		return function () {
			let serviceUrl;
			const message = $.mage.__('Gift cards were removed from the cart.');

			serviceUrl = '/fiserv/valuelink/removeallvaluelinkcards';
			const payload = {};

			messageList.clear();
			fullScreenLoader.startLoader();

			$.ajax({
				url: globalThis.checkoutConfig.payment.fiserv_payments.storeUrl + serviceUrl,
				cache: false,
				dataType: 'json',
				data: payload,
				type: 'POST',
				success(response) {
					if (response) {
						$.when(getPaymentInformationAction()).always(() => {
							fullScreenLoader.stopLoader();
						});
						messageList.addSuccessMessage({
							message,
						});
					}
				},
				error(response) {
					errorProcessor.process(response, messageList);
					fullScreenLoader.stopLoader();
				},
			});
		};
	},
);
