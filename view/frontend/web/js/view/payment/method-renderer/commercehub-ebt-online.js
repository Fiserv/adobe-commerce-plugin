/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/* eslint-disable
	max-lines-per-function,
	max-params,
	prefer-arrow-callback,
	unicorn/prefer-module,
	unicorn/no-null,
	no-invalid-this,
	complexity,
	compat/compat,
	require-await,
	@stylistic/max-statements-per-line,
	sonarjs/cognitive-complexity,
	max-lines,
	consistent-return,
	default-case
*/
/* browser:true */
define(
	[
		'underscore',
		'jquery',
		'Magento_Checkout/js/view/payment/default',
		'Fiserv_Payments/js/ch-adapter',
		'Fiserv_Payments/js/action/create-commercehub-enriched-session',
		'Magento_Checkout/js/model/quote',
		'Magento_Checkout/js/checkout-data',
		'Magento_Ui/js/model/messageList',
		'Magento_Checkout/js/model/full-screen-loader',
		'Magento_Checkout/js/model/payment/additional-validators',
		'ko',
		'mage/translate',
		'domReady!',
	],
	function (
		_,
		$,
		Component,
		chAdapter,
		chSession,
		quote,
		checkoutData,
		globalMessageList,
		fullScreenLoader,
		additionalValidators,
		ko,
		$t,
	) {
		'use strict';

		return Component.extend({
			isPlaceOrderActionAllowed: ko.observable(false),
			defaults: {
				template: 'Fiserv_Payments/payment/commercehub/ebt-online-form',
				active: false,
				code: 'fiserv_ebt_online',
				paymentPayload: {
					sessionId: null,
				},
				additionalData: {},
				isIframeValid: false,
				credentials: undefined,
				isIframeReady: false,
				pendingIframeReload: false,
			},

			/**
			 * @returns {exports.initialize}
			 */
			initialize() {
				this._super();
				this.code = 'fiserv_ebt_online';

				quote.billingAddress.subscribe(function (address) {
					this.isPlaceOrderActionAllowed(address !== null);
				}, this);

				this.initializeChAdapter();
				return this;
			},

			/**
			 * @returns {exports.initObservable}
			 */
			initObservable() {
				this._super().observe(['active']);
				return this;
			},

			/**
			 * Wire up ch-adapter with payment config and iframe callbacks.
			 */
			initializeChAdapter() {
				chAdapter.initialize(
					this.getPaymentConfig(),
					() => { this.iframeLoadSuccess(); },
					(valid) => { this.iframeValidHandler(valid); },
					null,
					null,
					null,
					null,
				);
			},

			/**
			 * Called via afterRender on the ebtSubCategory field.
			 * Triggers iframe load when EBT is the selected payment method.
			 */
			loadSdcForm() {
				this.initializeChAdapter();

				if (this.isChecked() === this.code) {
					this.loadIframe()
						.catch((e) => { this.iframeLoadFailure(e); });
				}
			},

			/**
			 * Called via afterRender on the radio input.
			 * Sets up payment method switch listener.
			 */
			setupPaymentMethod() {
				this.watchPaymentMethods();
			},

			/**
			 * Listen for payment method radio changes to load/destroy the iframe.
			 */
			watchPaymentMethods() {
				$('[name="payment[method]"]').off('click.fiservEbtOnlineMethod');
				$('[name="payment[method]"]').on('click.fiservEbtOnlineMethod', (event) => {
					if (event.currentTarget.id === this.getCode()) {
						this.initializeChAdapter();
						this.loadIframe()
							.catch((e) => { this.iframeLoadFailure(e); });
					} else {
						this.destroyEbtIframe(true);
					}
				});
			},

			/**
			 * Initialize the Fiserv SDK and instantiate the paymentFields iframe.
			 */
			async loadIframe() {
				fullScreenLoader.startLoader();

				try {
					await this.initCheckoutSdk();

					chAdapter.instantiateIframe(
						() => { this.iframeLoadSuccess(); },
						(e) => { this.iframeLoadFailure(e); },
					);
				} catch (e) {
					this.iframeLoadFailure(e);
				}
			},

			/**
			 * Create the payment session and initialize the Fiserv SDK.
			 */
			async initCheckoutSdk() {
				const credsResponse = await chSession({});
				this.credentials = credsResponse['ch_credentials'];
				await chAdapter.initSdk(this.getPaymentConfig(), this.credentials);
			},

			iframeLoadSuccess() {
				fullScreenLoader.stopLoader();
				this.isIframeReady = true;
			},

			iframeLoadFailure(message) {
				this.isIframeReady = false;
				fullScreenLoader.stopLoader();
				this.showError($t('Failed to load EBT payment form. Please try again.'));
				console.error('[EBT Online] iframe load failure:', message);
			},

			/**
			 * Enable/disable the Place Order button based on iframe form validity.
			 */
			iframeValidHandler(valid) {
				this.isIframeValid = valid;
				$('#fiserv-ebt-online-checkout-submit').prop('disabled', !valid);
			},

			/**
			 * Open the EBT PIN pad modal via the SDK paymentFields instance.
			 */
			openEbtPinPad() {
				if (chAdapter.sdcv2Form && typeof chAdapter.sdcv2Form.openEbtPinPad === 'function') {
					chAdapter.sdcv2Form.openEbtPinPad();
				} else {
					console.warn('[EBT Online] openEbtPinPad is not available. SDK may not be initialized yet.');
				}
			},

			/**
			 * Destroy iframe and optionally reset readiness flags.
			 */
			destroyEbtIframe(resetFlags) {
				chAdapter.destroyIframe();

				for (const selector of [
					'#fiserv_ebt_online-card-number',
					'#fiserv_ebt_online-ebt-sub-category',
					'#fiserv_ebt_online-expiration-month',
					'#fiserv_ebt_online-expiration-year',
				]) {
					const el = $(selector);
					if (el.length > 0) {
						el.empty();
					}
				}

				if (resetFlags === true) {
					this.isIframeReady = false;
					this.isIframeValid = false;
					this.pendingIframeReload = false;
					$('#fiserv-ebt-online-checkout-submit').prop('disabled', true);
				}
			},

			/**
			 * Submit the hosted fields form and capture the session ID.
			 */
			async submitIframe() {
				if (!additionalValidators.validate()) {
					return;
				}

				fullScreenLoader.startLoader();

				chAdapter.submitCardForm(
					globalThis.checkoutConfig.payment.fiserv_payments.storeUrl,
					(sessionId) => { this.iframeRunSuccess(sessionId); },
					(e) => { this.iframeRunFailure(e); },
					this.credentials,
				);
			},

			iframeRunSuccess(sessionId) {
				this.paymentPayload.sessionId = sessionId;
				fullScreenLoader.stopLoader();
				this.placeOrderClick();
			},

			iframeRunFailure(message) {
				this.showError($t('EBT payment failed. Please try again.'));
				fullScreenLoader.stopLoader();
				chAdapter.resetIframe();
				console.error('[EBT Online] submitCardForm failure:', message);
			},

			/**
			 * Trigger Magento order placement after session is captured.
			 */
			placeOrderClick() {
				if (this.paymentPayload.sessionId) {
					this.placeOrder('parent');
				}
			},

			/**
			 * @returns {Object}
			 */
			getData() {
				const data = {
					method: this.getCode(),
					additional_data: {
						payment_session: this.paymentPayload.sessionId,
					},
				};

				data.additional_data = _.extend(data.additional_data, this.additionalData);
				return data;
			},

			/**
			 * @param {String} key
			 * @returns {Boolean}
			 */
			placeOrder(key) {
				if (key) {
					$(document).one('ajaxError', () => {
						this.destroyEbtIframe(true);
						this.loadIframe().catch((e) => { this.iframeLoadFailure(e); });
					});

					return this._super();
				}

				return false;
			},

			/**
			 * Show a global error message.
			 */
			showError(errorMessage) {
				globalMessageList.addErrorMessage({ message: errorMessage });
			},

			/**
			 * Build the payment config object expected by ch-adapter.
			 * Merges base CommerceHub credentials with EBT-specific formConfig.
			 */
			getPaymentConfig() {
				const paymentConfig = globalThis.checkoutConfig && globalThis.checkoutConfig.payment
					? globalThis.checkoutConfig.payment
					: {};
				const baseCommerceHubConfig = paymentConfig.fiserv_commercehub || {};
				const sharedPaymentsConfig = paymentConfig.fiserv_payments || {};
				const ebtConfig = paymentConfig[this.code] || {};

				return {
					...baseCommerceHubConfig,
					...sharedPaymentsConfig,
					...ebtConfig,
					formConfig: ebtConfig.formConfig
						|| baseCommerceHubConfig.formConfig
						|| sharedPaymentsConfig.formConfig,
				};
			},

			/**
			 * @returns {String}
			 */
			getCode() {
				return this.code;
			},

			/**
			 * Returns true when admin has enabled expiration month/year fields.
			 *
			 * @returns {Boolean}
			 */
			getEbtShowExpiration() {
				const config = window.checkoutConfig && window.checkoutConfig.payment
					? window.checkoutConfig.payment[this.code]
					: null;

				return Boolean(config && config.showExpiration);
			},

			/**
			 * @returns {Boolean}
			 */
			isActive() {
				const active = this.getCode() === this.isChecked();
				this.active(active);
				return active;
			},
		});
	},
);
