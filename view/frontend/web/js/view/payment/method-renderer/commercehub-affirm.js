define([
	'jquery',
	'Magento_Payment/js/view/payment/cc-form',
	'Magento_Ui/js/model/messageList',
	'Magento_Checkout/js/model/full-screen-loader',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-affirm-session',
	'Magento_Checkout/js/model/quote',
	'Magento_Checkout/js/checkout-data',
	'ko',
	'mage/translate',
], function (
	$,
	Component,
	globalMessageList,
	fullScreenLoader,
	chAdapter,
	chSession,
	quote,
	checkoutData,
	ko,
	$t,
) {
	'use strict';

	return Component.extend({
		defaults: {
			template: 'Fiserv_Payments/payment/commercehub/affirm-form',
			code: 'fiserv_affirm',
			additionalData: {},
			paymentPayload: {
				sessionId: undefined,
				orderId: undefined,
				type: 'affirm',
				publicKeyHash: undefined,
			},
			isPlaceOrderActionAllowed: ko.observable(false),
			canDisplayAffirm: ko.observable(false),
			initializedAmount: 0,
			reloadOnRender: false,
			isInitializing: false,
			reloadTimer: undefined,
			lastBillingAddressKey: '',
			lastShippingAddressKey: '',
			hasTotalsObserver: false,
		},

		initialize() {
			this._super();
			this.observeBillingAddress();
			this.observeShippingAddress();
			this.observeTotals();
			this.updateAffirmAvailability();

			return this;
		},

		afterRenderInit() {
			const config = globalThis.checkoutConfig && globalThis.checkoutConfig.payment ?
				globalThis.checkoutConfig.payment[this.getCode()] :
				undefined;

			if (config && config.initializedAmount) {
				const totals = quote.totals && quote.totals();
				const grandTotal = totals ? totals.grand_total : undefined;

				if (grandTotal && config.initializedAmount !== grandTotal) {
					config.initializedAmount = undefined;
					this.reloadOnRender = true;
				}
			}

			this.watchPaymentMethods();

			if (this.getCode() === this.isChecked() && this.canDisplayAffirm()) {
				this.reloadOnRender = false;
				this.loadAffirmForm();
			}
		},

		observeBillingAddress() {
			quote.billingAddress.subscribe((address) => {
				this.isPlaceOrderActionAllowed(Boolean(address));

				const currentKey = this.getBillingAddressKey(address);

				if (currentKey !== this.lastBillingAddressKey) {
					this.lastBillingAddressKey = currentKey;
					this.scheduleAffirmReload();
				}
			});
		},

		observeShippingAddress() {
			if (!quote.shippingAddress || !quote.shippingAddress.subscribe) {
				return;
			}

			quote.shippingAddress.subscribe((address) => {
				this.updateAffirmAvailability();
				const currentKey = this.getBillingAddressKey(address);

				if (currentKey && currentKey !== this.lastShippingAddressKey) {
					this.lastShippingAddressKey = currentKey;
					this.scheduleAffirmReload();
				}
			});
		},

		observeTotals() {
			if (this.hasTotalsObserver || !quote.totals || !quote.totals.subscribe) {
				return;
			}

			this.hasTotalsObserver = true;
			quote.totals.subscribe((totals) => {
				if (!totals) {
					return;
				}

				const grandTotal = totals.grand_total;

				if (
					this.getCode() === this.isChecked() &&
					this.initializedAmount > 0 &&
					grandTotal &&
					grandTotal !== this.initializedAmount
				) {
					location.reload();
				}
			});
		},

		getBillingAddressKey(address) {
			if (!address) {
				return '';
			}

			const street = Array.isArray(address.street) ?
				address.street.join(' ') :
				(address.street || '');

			return [
				address.firstname,
				address.lastname,
				street,
				address.city,
				address.region_id,
				address.region,
				address.postcode,
				address.countryId,
				address.telephone,
			].filter(Boolean).join('|');
		},

		scheduleAffirmReload() {
			if (this.getCode() !== this.isChecked() || !this.canDisplayAffirm()) {
				return;
			}

			if (this.reloadTimer) {
				clearTimeout(this.reloadTimer);
			}

			this.reloadTimer = setTimeout(() => {
				this.reloadTimer = undefined;
				this.initializeAffirm();
			}, 300);
		},

		async initializeAffirm() {
			if (!this.canDisplayAffirm() || this.isInitializing) {
				return;
			}

			const config = globalThis.checkoutConfig && globalThis.checkoutConfig.payment ?
				globalThis.checkoutConfig.payment[this.getCode()] :
				undefined;

			if (!config) {
				return;
			}

			this.isInitializing = true;
			fullScreenLoader.startLoader();

			try {
				$('#affirm-button-container').empty();
				const creds = await chSession({ merchantName: config.storeName });

				if (!creds || !creds.ch_credentials) {
					throw new Error('No credentials returned from backend');
				}

				this.paymentPayload.sessionId = creds.ch_credentials.sessionId;
				await chAdapter.initSdk(config, creds.ch_credentials);

				const buttonStyle = config.affirmButtonColor || config.affirmButtonStyle || 'default';
				const backendAction = config.paymentAction || config.affirmPaymentAction || config.payment_action || '';
				const actionLower = backendAction.toString().toLowerCase();

				let affirmIntent = 'AUTHORIZE';

				if (actionLower.includes('capture') || actionLower.includes('sale')) {
					affirmIntent = 'CAPTURE';
				}

				const affirmPayload = {
					data: {
						intent: affirmIntent,
						button: {
							parentElementId: 'affirm-button-container',
							color: buttonStyle,
						},
					},
					hooks: {
						onApprove: (data) => {
							this.additionalData.paypal_order_id = data.id || data.orderId;
							this.additionalData.email = this.getCustomerEmail();
							this.placeOrder('parent');
						},
						onError: (error) => {
							globalMessageList.addErrorMessage({
								message: $t('Error during Affirm checkout')
							});
						},
					},
				};

				await globalThis.fiserv.components.affirm(affirmPayload);

				const totals = quote.totals && quote.totals();
				const grandTotal = totals ? totals.grand_total : undefined;

				this.initializedAmount = grandTotal;
				config.initializedAmount = grandTotal;
			} catch (error) {
				globalMessageList.addErrorMessage({
					message: $t('Error during Affirm checkout')
				});
			} finally {
				this.isInitializing = false;
				fullScreenLoader.stopLoader();
			}
		},

		getCustomerEmail() {
			try {
				if (
					globalThis.checkoutConfig &&
					globalThis.checkoutConfig.isCustomerLoggedIn &&
					globalThis.checkoutConfig.customerData &&
					globalThis.checkoutConfig.customerData.email
				) {
					return this.isValidEmail(globalThis.checkoutConfig.customerData.email) ?
						globalThis.checkoutConfig.customerData.email :
						'';
				}

				if (quote && quote.guestEmail) {
					return this.isValidEmail(quote.guestEmail) ? quote.guestEmail : '';
				}
			} catch {
				return '';
			}

			return '';
		},

		isValidEmail(email) {
			if (typeof email !== 'string' || email.length < 3 || /\s/.test(email)) {
				return false;
			}

			const atIndex = email.indexOf('@');
			const dotIndex = email.lastIndexOf('.');

			return atIndex > 0 && dotIndex > atIndex + 1 && dotIndex < email.length - 1;
		},

		loadAffirmForm() {
			if (this.getCode() !== this.isChecked() || !this.canDisplayAffirm()) {
				return;
			}

			this.initializeAffirm();
		},

		watchPaymentMethods() {
			const selector = '[name="payment[method]"]';

			$(document).off('click.fiservAffirm', selector).on('click.fiservAffirm', selector, (event) => {
				if (event.currentTarget.id !== this.getCode()) {
					return;
				}

				this.reloadOnRender = false;
				this.loadAffirmForm();
			});
		},

		isVirtualQuote() {
			if (typeof quote.isVirtual === 'function') {
				return Boolean(quote.isVirtual());
			}

			if (quote.isVirtual !== undefined) {
				return Boolean(quote.isVirtual);
			}

			if (globalThis.checkoutConfig && globalThis.checkoutConfig.quoteData) {
				return Boolean(globalThis.checkoutConfig.quoteData.is_virtual);
			}

			return false;
		},

		getShippingAddress() {
			let shippingAddress = quote.shippingAddress();

			if (!shippingAddress) {
				shippingAddress = checkoutData.getShippingAddressFromData();
			}

			return shippingAddress;
		},

		hasAvailableShippingAddress() {
			return Boolean(this.getBillingAddressKey(this.getShippingAddress()));
		},

		updateAffirmAvailability() {
			const shouldDisplay = !this.isVirtualQuote() && this.hasAvailableShippingAddress();

			this.canDisplayAffirm(shouldDisplay);

			if (!shouldDisplay) {
				this.isPlaceOrderActionAllowed(false);
				$('#affirm-button-container').empty();
			}
		},

		getData() {
			const data = {
				method: this.getCode(),
				additional_data: this.additionalData || {},
			};

			if (this.paymentPayload && this.paymentPayload.sessionId) {
				data.additional_data.payment_session = this.paymentPayload.sessionId;
			}

			return data;
		},

		getCode() {
			return this.code;
		},

		showPrivacyStatement() {
			const config = globalThis.checkoutConfig && globalThis.checkoutConfig.payment ?
				globalThis.checkoutConfig.payment[this.getCode()] :
				undefined;

			return config && config.show_privacy_statement;
		},

		isBillingAddressRequired() {
			return true;
		},

		isActive() {
			const active = this.getCode() === this.isChecked();

			this.active(active);

			return active;
		},
	});
});
