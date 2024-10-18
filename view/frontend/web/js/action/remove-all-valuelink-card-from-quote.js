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
        'mage/translate'
    ],
    function (
        $,
        urlBuilder,
        storage,
        customer,
        quote,
        getPaymentInformationAction,
        fullScreenLoader,
        errorProcessor,
        messageList
    ) {
        'use strict';

        return function () {
            let serviceUrl,
                message = $.mage.__('Gift cards were removed from the cart.');

			serviceUrl = '/fiserv/valuelink/removeallvaluelinkcards'
			let payload = {
			};

            messageList.clear();
            fullScreenLoader.startLoader();
			
			$.ajax({
				url: window.checkoutConfig.payment.fiserv_payments.storeUrl + serviceUrl,
				cache: false,
				dataType: 'json',
				data: payload,
				type: 'POST',
				success: function (response) {
                    if (response) {
                        $.when(getPaymentInformationAction()).always(function () {
                            fullScreenLoader.stopLoader();
                        });
                        messageList.addSuccessMessage({
                            'message': message
                        });
                    }
                },
				error: function (response) {
                    errorProcessor.process(response, messageList);
                    fullScreenLoader.stopLoader();
                }
			});
        };
    }
);
