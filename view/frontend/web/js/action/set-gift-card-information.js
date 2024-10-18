/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage',
    'Fiserv_Payments/js/model/valuelink/valuelink-messages',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/get-payment-information',
    'Magento_Checkout/js/model/totals'
], function (
    $,
    quote,
    urlBuilder,
    storage,
    messageList,
    errorProcessor,
    customer,
    fullScreenLoader,
    getPaymentInformationAction,
    totals
) {
    'use strict';

    return function (sessionId, cardBalance) {
        let serviceUrl,
            payload,
            message = $.mage.__('Gift card was added to the cart.');

		serviceUrl = '/fiserv/valuelink/applyvaluelinkcard'
		payload = {
			cartId: quote.getQuoteId(),
			sessionId: sessionId,
			balance: cardBalance
		};

		messageList.clear();
        fullScreenLoader.startLoader();
        
		$.ajax({
			url: window.checkoutConfig.payment.fiserv_payments.storeUrl + serviceUrl,
			cache: false,
			dataType: 'json',
			data: payload,
			type: "POST",
			success: function(response) {
				var deferred = $.Deferred();

				if (response) {
					totals.isLoading(true);
					getPaymentInformationAction(deferred);
					$.when(deferred).done(function () {
						totals.isLoading(false);
					});
					
					messageList.addSuccessMessage({
						'message': message
					});
				}
				fullScreenLoader.stopLoader();
					
			},
			error: function(err) {
				totals.isLoading(false);
 				errorProcessor.process(err, messageList);
				console.log(err);
				fullScreenLoader.stopLoader();
			}
		});
    };
});
