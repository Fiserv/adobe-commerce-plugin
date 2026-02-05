define([
	'jquery',
	'Magento_Payment/js/view/payment/cc-form',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-enriched-session',
	'Magento_Checkout/js/model/quote',
	'Magento_Ui/js/model/messageList',
	'Magento_Checkout/js/checkout-data',
	'Magento_Checkout/js/model/full-screen-loader',
	'ko',
	'mage/translate',
	'domReady!',
], (
	$,
	Component,
	chAdapter,
	commercehubSession,
	quote,
	globalMessageList,
	checkoutData,
	fullScreenLoader,
	ko,
	$t,
) => {
	'use strict';

	return Component.extend({
		isPlaceOrderActionAllowed: ko.observable(false),
		defaults: {
			template: 'Fiserv_Payments/payment/commercehub/paypal-form',
			code: 'fiserv_paypal',
			active: false,
			sortOrder: 20,
			paymentPayload: {
				sessionId: null,
				type: null,
				orderID: null,
			},
			additionalData: {},
			paymentMethodName: '[name="payment[method]"',
			credentials: undefined,
			paypalComponent: null,
			vaultEnabler: null,
			initializedAmount: 0,
		},

		initialize() {
			this._super();
			quote.billingAddress.subscribe(function (address) {
				this.isPlaceOrderActionAllowed(address !== null);
			}, this);

			quote.shippingAddress.subscribe(async () => {
				if (this.isChecked() === this.getCode()) {
					await this.initchAdapter();
				}
			});

			return this;
		},

		async initializePayPal() {
			await this.loadPayPalForm();
			this.watchPaymentMethods();
		},

		async loadPayPalForm() {
			if (this.isChecked() === this.getCode()) {
				await this.initchAdapter();
			}
		},

		async initchAdapter() {
			const self = this;
			const config = (globalThis.checkoutConfig && globalThis.checkoutConfig.payment) ? globalThis.checkoutConfig.payment[self.getCode()] : {};

			fullScreenLoader.startLoader();
			try {
				if (!globalThis.fiserv || typeof globalThis.fiserv.components.paypal !== 'function') {
					globalMessageList.addErrorMessage({ message: $t('Fiserv SDK is not loaded. Please check your network and configuration.') });
					console.error('[PayPal] Fiserv SDK not loaded or window.fiserv.components.paypal not available');

					return;
				}

				chAdapter.initialize(config);
				let credsResponse; let creds;

				credsResponse = await commercehubSession({});
				creds = credsResponse && credsResponse[chAdapter.credentialsKey];
				if (!creds) {
					throw new Error('No credentials returned from PayPal session');
				}

				self.paymentPayload.sessionId = creds[chAdapter.sessionIdKey];
				await chAdapter.initSdk(config, creds);
				let intent = 'authorize';

				if (config.payment_action.toLowerCase() === 'authorize_capture') {
					intent = 'capture';
				}
				const shippingAddress = this.getMapShippingAddress(this.getShippingAddress());
				const paypalComponentConfig = {
					intent,
					shippingAddress,
				};
				const paypalComponent = await chAdapter.loadPayPalComponent(paypalComponentConfig);

				this.paypalComponent = paypalComponent;
				const buttonsConfig = config.buttonConfig;

				await this.renderPayPalButtons(buttonsConfig);
				this.initializedAmount = quote.totals().grand_total;
				this.observeTotals();
			} catch (error) {
				globalMessageList.addErrorMessage({ message: $t('PayPal credentials error: ') + (error.message || error) });
				console.error('[PayPal] Error in initchAdapter:', error);
			} finally {
				fullScreenLoader.stopLoader();
			}
		},

		/**
         * Renders PayPal and Venmo buttons
         * @param {Object} buttonsConfig - ButtonsConfig object (see Fiserv docs)
         * @returns {Promise}
         */
		async renderPayPalButtons(buttonsConfig) {
			try {
				const containerId = '#paypal-button-container';
				const container = document.querySelector(containerId);

				if (container) {
					while (container.children.length > 0) {
						container.lastChild.remove();
					}
				}

				const config = (globalThis.checkoutConfig && globalThis.checkoutConfig.payment) ? globalThis.checkoutConfig.payment[this.getCode()] : {};
				const buttons = {
					paypal: {
						parentElementId: buttonsConfig.data && buttonsConfig.data.buttons && buttonsConfig.data.buttons.paypal.parentElementId || 'paypal-button-container',
						color: buttonsConfig.data && buttonsConfig.data.buttons && buttonsConfig.data.buttons.paypal.color || 'gold',
						shape: buttonsConfig.data && buttonsConfig.data.buttons && buttonsConfig.data.buttons.paypal.shape || 'rect',
						label: buttonsConfig.data && buttonsConfig.data.buttons && buttonsConfig.data.buttons.paypal.label || 'paypal',
					},
				};

				if (!this.paypalComponent || typeof this.paypalComponent.buttons !== 'function') {
					return;
				}

				return await this.paypalComponent.buttons({
					data: {
						enableVaulting: buttonsConfig.data && buttonsConfig.data.vaulting !== undefined ? buttonsConfig.data.vaulting : false,
						customerConfirmation: buttonsConfig.data && typeof buttonsConfig.data.customerConfirmation === 'string' ? buttonsConfig.data.customerConfirmation : 'REVIEW_AND_PAY',
						buttons,
					},
					hooks: {
						onApprove: async (data, actions) => {
							console.log('on Approve called', data);
							let email = '';

							try {
								if (globalThis.checkoutConfig && globalThis.checkoutConfig.isCustomerLoggedIn && globalThis.checkoutConfig.customerData && globalThis.checkoutConfig.customerData.email) {
									email = globalThis.checkoutConfig.customerData.email;
								} else if (quote && quote.guestEmail) {
									email = quote.guestEmail;
								}
								if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
									email = '';
								}
							} catch {
								email = '';
							}
							this.additionalData.paypal_order_id = data.orderId;
							this.additionalData.email = email;
							// Use inherited placeOrder method to trigger Magento's default flow
							this.placeOrder('parent');
						},
						onCancel: () => {
							console.log('on cancel called');
						},
						onError: (error) => {
							console.log('on error called', error);
						},
					},
				});
			} catch (error) {
				console.error('[PayPal] Error rendering PayPal buttons:', error);
			}
		},

		observeTotals() {
			quote.totals.subscribe((totals) => {
				if (
					this.getCode() === this.isChecked() &&
                    this.initializedAmount &&
                    this.initializedAmount > 0 &&
                    totals &&
                    totals.grand_total &&
                    totals.grand_total != this.initializedAmount
				) {
					location.reload();
				}
			});
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

		/**
         * Check if payment is active
         *
         * @returns {Boolean}
         */
		isActive() {
			const active = this.getCode() === this.isChecked();

			this.active(active);

			return active;
		},

		getCode() {
			return this.code;
		},

		/**
         * Show error message
         *
         * @param {String} errorMessage
         * @private
         */
		showError(errorMessage) {
			globalMessageList.addErrorMessage({
				message: errorMessage,
			});
		},

		getShippingAddress() {
			let shippingAddress = quote.shippingAddress();

			if (!shippingAddress) {
				shippingAddress = checkoutData.getShippingAddressFromData();
			}

			return shippingAddress;
		},

		getMapShippingAddress(address) {
			if (!address) {
				return;
			}
			if (!address.street || !address.firstname || !address.lastname) {
				console.warn('Shipping address is incomplete:', address);

				return;
			}
			const streetArray = Array.isArray(address.street) ?
				address.street :
				Object.values(address.street);
			const mappedAddress = {
				street: streetArray[0] || undefined,
				city: address.city || undefined,
				stateOrProvince: address.region || address.region_id || undefined,
				postalCode: address.postcode || undefined,
				country: address.country_id || address.countryId || address.country || undefined,
			};

			return {
				firstName: address.firstname || undefined,
				lastName: address.lastname || undefined,
				address: mappedAddress,
			};
		},

		/**
         * Set list of observable attributes
         *
         * @returns {exports.initObservable}
         */
		initObservable() {
			this._super()
				.observe(['active']);

			return this;
		},

		watchPaymentMethods(element) {
			const self = this;

			$(self.paymentMethodName).on('click', function () {
				const selected = $(this).attr('id');

				if (selected === self.getCode()) {
					self.loadPayPalForm();
				} else {
					// Clear PayPal button container if another payment method is selected
					$('#paypal-button-container').empty();
				}
			});
		},

		refreshBillingAddress() {
			if (this.isAchActive() && quote.billingAddress()) {
				shpfUtils.setBillingAddress(quote.billingAddress());
				this.initAchIframe();
			}
		},
	});
});
