/* eslint-disable max-params, max-lines-per-function, prefer-arrow-callback, unicorn/prefer-module */
/* eslint-disable unicorn/prefer-global-this */
/* eslint-disable object-shorthand, complexity, unicorn/no-null, eqeqeq */
/* eslint-disable unicorn/explicit-length-check, @stylistic/no-mixed-operators */
define([
	'Magento_Checkout/js/view/payment/default',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-enriched-session',
	'Magento_Checkout/js/model/quote',
	'Magento_Customer/js/model/customer',
	'Magento_Ui/js/model/messageList',
	'Magento_Checkout/js/model/full-screen-loader',
	'ko',
	'mage/translate',
	'domReady!',
], function (
	Component,
	chAdapter,
	commerceHubSession,
	quote,
	customer,
	globalMessageList,
	fullScreenLoader,
	ko,
	$t,
) {
	'use strict';

	return Component.extend({
		isPlaceOrderActionAllowed: ko.observable(false),

		defaults: {
			template: 'Fiserv_Payments/payment/commercehub/cashapp-form',
			code: 'fiserv_cashapp',
			active: false,
			additionalData: {},
			cashAppHandle: null,
			cashAppCredentials: null,
			initializationPromise: null,
			initializationGeneration: 0,
			pendingInitialization: false,
			initializedAmount: null,
			reinitializeTimer: null,
			saveCashApp: false,
			useSavedCashApp: false,
			serverCanSave: true,
		},

		initialize: function () {
			this._super();
			this.isPlaceOrderActionAllowed(Boolean(quote.billingAddress()));
			this.useSavedCashApp(this.canUseSavedCashApp());
			this.useSavedCashApp.subscribe(() => {
				this.saveCashApp(false);
				this.destroyCashApp();
				this.initializeCashApp();
			});

			quote.billingAddress.subscribe((address) => {
				this.isPlaceOrderActionAllowed(Boolean(address));
				if (address && this.isChecked() === this.getCode()) {
					this.initializeCashApp();
				}
			});

			quote.totals.subscribe((totals) => {
				const amount = totals && Number(totals.grand_total);

				if (
					this.isChecked() === this.getCode() &&
					this.initializedAmount !== null &&
					amount !== this.initializedAmount
				) {
					clearTimeout(this.reinitializeTimer);
					this.reinitializeTimer = setTimeout(() => {
						this.destroyCashApp();
						this.initializeCashApp();
					}, 250);
				}
			});

			this.isChecked.subscribe((method) => {
				if (method === this.getCode()) {
					this.initializeCashApp();
				} else {
					this.destroyCashApp();
					this.saveCashApp(false);
				}
			});

			customer.isLoggedIn.subscribe((loggedIn) => {
				if (!loggedIn) {
					this.saveCashApp(false);
					this.useSavedCashApp(false);
				}
			});

			return this;
		},

		afterRender: function () {
			if (this.isChecked() === this.getCode()) {
				this.initializeCashApp();
			}
		},

		initializeCashApp: function () {
			if (this.isChecked() !== this.getCode() || this.isUsingSavedCashApp()) {
				return Promise.resolve(null);
			}

			if (this.initializationPromise) {
				this.pendingInitialization = true;

				return this.initializationPromise;
			}

			if (!this.isPlaceOrderActionAllowed() || this.cashAppHandle) {
				return this.initializationPromise || Promise.resolve(this.cashAppHandle);
			}

			this.initializationPromise = this.createCashAppButton();

			return this.initializationPromise;
		},

		createCashAppButton: async function () {
			const config = this.getPaymentConfig();
			const generation = this.initializationGeneration;
			let sdkMode;
			let handle;

			fullScreenLoader.startLoader();
			try {
				sdkMode = await this.initializeCashAppSdk(config, generation);
				if (!this.isCurrentCashAppGeneration(generation)) {
					return null;
				}
				this.clearContainer();
				handle = sdkMode === 'component' ?
					await window.fiserv.components.cashApp(this.getCashAppOptions(config, generation)) :
					await this.createCashAppElements(config, generation);
				if (!this.isCurrentCashAppGeneration(generation)) {
					if (handle && typeof handle.destroy === 'function') {
						handle.destroy();
					}

					return null;
				}

				this.cashAppHandle = handle;
				this.initializedAmount = this.getGrandTotal();
			} catch (error) {
				if (this.isCurrentCashAppGeneration(generation)) {
					this.handleError(error);
				}
			} finally {
				this.initializationPromise = null;
				fullScreenLoader.stopLoader();
				const { pendingInitialization } = this;

				this.pendingInitialization = false;
				if (pendingInitialization && this.isChecked() === this.getCode()) {
					setTimeout(() => this.initializeCashApp(), 0);
				}
			}

			return this.cashAppHandle;
		},

		getPaymentConfig: function () {
			return window.checkoutConfig && window.checkoutConfig.payment &&
				window.checkoutConfig.payment[this.getCode()] || {};
		},

		canSaveCashApp: function () {
			const config = this.getPaymentConfig();

			return customer.isLoggedIn() && config.tokenizationEnabled === true &&
				!config.hasSavedCashApp && this.serverCanSave() && !this.isUsingSavedCashApp();
		},

		canUseSavedCashApp: function () {
			return customer.isLoggedIn() && this.getPaymentConfig().canUseSavedCashApp === true;
		},

		isUsingSavedCashApp: function () {
			return this.canUseSavedCashApp() && this.useSavedCashApp() === true;
		},

		isCurrentCashAppGeneration: function (generation) {
			return generation === this.initializationGeneration && this.isChecked() === this.getCode() &&
				!this.isUsingSavedCashApp();
		},

		initializeCashAppSdk: async function (config, generation) {
			const hasComponentApi = window.fiserv && window.fiserv.components &&
				typeof window.fiserv.components.cashApp === 'function';
			const hasElementApi = window.customElements &&
				window.customElements.get('fiserv-context') &&
				window.customElements.get('fiserv-cashapp-button');

			if (!hasComponentApi && !hasElementApi) {
				throw new Error($t('Fiserv SDK is not loaded. Please check your network and configuration.'));
			}

			chAdapter.initialize(config);
			const credentialsResponse = await commerceHubSession({
				paymentCode: this.getCode(),
				form_key: window.FORM_KEY,
			});

			// An obsolete response must never replace the active session or SDK context.
			if (!this.isCurrentCashAppGeneration(generation)) {
				return null;
			}
			const credentials = credentialsResponse && credentialsResponse[chAdapter.credentialsKey];

			if (!credentials) {
				throw new Error($t('No credentials returned for the Cash App session.'));
			}

			if (typeof credentialsResponse.cashapp_can_save === 'boolean') {
				// Eligibility is session-bound; the save choice is submitted with the order.
				this.serverCanSave(credentialsResponse.cashapp_can_save);
			}
			this.additionalData.payment_session = credentials[chAdapter.sessionIdKey];
			this.cashAppCredentials = credentials;

			if (hasComponentApi) {
				if (typeof window.fiserv.init !== 'function') {
					throw new TypeError($t('Fiserv SDK initialization is not available.'));
				}

				await chAdapter.initSdk(config, credentials);

				return 'component';
			}

			return 'elements';
		},

		createCashAppElements: async function (config, generation) {
			const container = document.querySelector('#cashapp-button-container');
			const credentials = this.cashAppCredentials || {};
			const buttonConfig = config.buttonConfig || {};
			const contextElement = document.createElement('fiserv-context');
			const buttonElement = document.createElement('fiserv-cashapp-button');
			const readyHandler = () => {
				if (this.isCurrentCashAppGeneration(generation)) {
					this.initializedAmount = this.getGrandTotal();
				}
			};
			const approveHandler = () => this.handleApprove(generation);
			const cancelHandler = (event) => {
				if (this.isCurrentCashAppGeneration(generation)) {
					this.handleCancel(event && event.detail);
				}
			};
			const errorHandler = (event) => {
				if (this.isCurrentCashAppGeneration(generation)) {
					this.handleError(event && event.detail || event);
				}
			};
			const contextErrorHandler = errorHandler;

			if (!container) {
				throw new Error($t('Cash App button container was not found.'));
			}

			this.configureCashAppContext(contextElement, config, credentials);

			buttonElement.id = 'cashapp-button';
			buttonElement.setAttribute('theme', buttonConfig.theme || 'DARK');
			buttonElement.setAttribute('shape', buttonConfig.shape || 'SEMIROUND');
			buttonElement.setAttribute('size', buttonConfig.size || 'MEDIUM');
			buttonElement.setAttribute('width', buttonConfig.width || 'STATIC');
			buttonElement.addEventListener('fiserv-cashapp-ready', readyHandler);
			buttonElement.addEventListener('fiserv-cashapp-success', approveHandler);
			buttonElement.addEventListener('fiserv-cashapp-cancel', cancelHandler);
			buttonElement.addEventListener('fiserv-cashapp-error', errorHandler);
			contextElement.addEventListener('fiserv-context-init-error', contextErrorHandler);
			contextElement.append(buttonElement);
			container.append(contextElement);

			if (typeof contextElement.waitForContext === 'function') {
				await contextElement.waitForContext();
			}

			return {
				destroy: () => {
					buttonElement.removeEventListener('fiserv-cashapp-ready', readyHandler);
					buttonElement.removeEventListener('fiserv-cashapp-success', approveHandler);
					buttonElement.removeEventListener('fiserv-cashapp-cancel', cancelHandler);
					buttonElement.removeEventListener('fiserv-cashapp-error', errorHandler);
					contextElement.removeEventListener('fiserv-context-init-error', contextErrorHandler);
					contextElement.remove();
				},
			};
		},

		configureCashAppContext: function (contextElement, config, credentials) {
			const sdkData = credentials[chAdapter.sdkDataKey];

			if (sdkData) {
				contextElement.setAttribute('sdk-data', sdkData);

				return;
			}

			const legacyParameters = {
				environment: config.environment,
				'api-key': config.apiKey,
				'access-token': credentials[chAdapter.accessTokenKey],
				'merchant-id': config.merchantId,
			};
			const missingParameters = Object.keys(legacyParameters).filter((name) => !legacyParameters[name]);

			if (missingParameters.length > 0) {
				throw new Error($t('The Cash App session did not return valid SDK initialization data.'));
			}

			for (const name of Object.keys(legacyParameters)) {
				contextElement.setAttribute(name, legacyParameters[name]);
			}
			this.setCashAppAttribute(contextElement, 'terminal-id', config.terminalId);
		},

		setCashAppAttribute: function (element, name, value) {
			if (value !== undefined && value !== null && value !== '') {
				element.setAttribute(name, value);
			}
		},

		getCashAppOptions: function (config, generation) {
			const buttonConfig = config.buttonConfig || {};

			return {
				data: {
					button: {
						parentElementId: 'cashapp-button-container',
						theme: buttonConfig.theme || 'DARK',
						shape: buttonConfig.shape || 'SEMIROUND',
						size: buttonConfig.size || 'MEDIUM',
						width: buttonConfig.width || 'STATIC',
					},
				},
				hooks: {
					onApprove: () => this.handleApprove(generation),
					onCancel: (data) => {
						if (this.isCurrentCashAppGeneration(generation)) {
							this.handleCancel(data);
						}
					},
					onError: (error) => {
						if (this.isCurrentCashAppGeneration(generation)) {
							this.handleError(error);
						}
					},
				},
			};
		},

		handleApprove: function (generation) {
			if (!this.isCurrentCashAppGeneration(generation) ||
				!this.isPlaceOrderActionAllowed() || !this.additionalData.payment_session) {
				return;
			}

			this.placeOrder();
		},

		handleCancel: function () {
			globalMessageList.addNoticeMessage({
				message: $t('Cash App checkout was cancelled. Please try again.'),
			});
		},

		handleError: function () {
			globalMessageList.addErrorMessage({
				message: $t('Unable to complete Cash App Pay. Please try again or select another payment method.'),
			});
		},

		destroyCashApp: function () {
			clearTimeout(this.reinitializeTimer);
			this.initializationGeneration += 1;
			this.pendingInitialization = false;
			this.cashAppCredentials = null;
			delete this.additionalData.payment_session;

			if (this.cashAppHandle && typeof this.cashAppHandle.destroy === 'function') {
				this.cashAppHandle.destroy();
			}

			this.cashAppHandle = null;
			this.initializedAmount = null;
			this.clearContainer();
		},

		clearContainer: function () {
			const container = document.querySelector('#cashapp-button-container');

			if (container) {
				container.textContent = '';
			}
		},

		getGrandTotal: function () {
			const totals = quote.totals();

			return totals ? Number(totals.grand_total) : null;
		},

		getData: function () {
			if (this.isUsingSavedCashApp()) {
				return {
					method: this.getCode(),
					additional_data: {
						cashapp_use_saved: true,
						cashapp_save_payment_method: false,
					},
				};
			}

			return {
				method: this.getCode(),
				additional_data: {
					...this.additionalData,
					cashapp_use_saved: false,
					cashapp_save_payment_method: this.canSaveCashApp() && this.saveCashApp() === true,
				},
			};
		},

		getCode: function () {
			return this.code;
		},
		isActive: function () {
			const active = this.getCode() === this.isChecked();

			this.active(active);

			return active;
		},

		initObservable: function () {
			this._super().observe(['active', 'saveCashApp', 'serverCanSave', 'useSavedCashApp']);

			return this;
		},
	});
});
