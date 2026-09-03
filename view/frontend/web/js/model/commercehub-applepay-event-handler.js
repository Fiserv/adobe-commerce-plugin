define([
	'Magento_Ui/js/model/messageList',
	'mage/translate'
], function (
	globalMessageList,
	$t
) {

	class CommerceHubApplePayEventHandler {
		constructor(applepayBase) {
			if (typeof applepayBase === 'undefined') {
				throw new Error('applepayBase not found. Unable to initialize Apple Pay event handler.');
			}

			this.applepayBase = applepayBase;
		}

		// Called once the Apple Pay button has been mounted.
		initializedHook() {}

		getAppleOrderConfig() {
			return {
				includeShipping: true,
				shippingType: 'SHIPPING',
				requiredShippingContactFields: ['postalAddress', 'name', 'phone', 'email']
			};
		}

		handleApproval(data) {
			this.applepayBase.paymentPayload.publicKeyHash = data.publicKeyHash || null;

			try {
				this.applepayBase.onApplePaySuccess(data.details, data);
				if (typeof data.completePayment === 'function') {
					data.completePayment('SUCCESS');
				}
			} catch (e) {
				if (typeof data.completePayment === 'function') {
					data.completePayment('FAILURE');
				}
				throw e;
			}
		}

		handleCancel(data) {
			if (typeof data?.completePayment === 'function') {
				data.completePayment('FAILURE');
			}
		}

		handleError(data) {
			if (typeof data?.completePayment === 'function') {
				data.completePayment('FAILURE');
			}
			this.showError($t('Error during Apple Pay checkout'));
		}

		showError(message) {
			globalMessageList.addErrorMessage({ message });
		}

		async handlePaymentMethodChange(response) {
			if (response && typeof response.respond === 'function') {
				response.respond({});
			}
		}

		async handleShippingAddressChange(response) {
			if (response && typeof response.respond === 'function') {
				response.respond({});
			}
		}

		async handleShippingOptionsChange(response) {
			if (response && typeof response.respond === 'function') {
				response.respond({});
			}
		}

		async handleCouponCodeChange(response) {
			if (response && typeof response.respond === 'function') {
				response.respond({});
			}
		}
	}

	return CommerceHubApplePayEventHandler;
});

