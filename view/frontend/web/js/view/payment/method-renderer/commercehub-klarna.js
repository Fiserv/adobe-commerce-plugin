/* Magento requires an AMD dependency factory and its strict-mode wrapper. */
/* eslint-disable max-params, max-lines-per-function, prefer-arrow-callback, unicorn/prefer-module */
define([
	'jquery',
	'Magento_Payment/js/view/payment/cc-form',
	'Magento_Ui/js/model/messageList',
	'Magento_Checkout/js/model/full-screen-loader',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-enriched-session',
	'Fiserv_Payments/js/model/locale-observer',
	'Fiserv_Payments/js/model/locale',
	'Magento_Checkout/js/model/quote',
	'Magento_Checkout/js/model/payment/additional-validators',
	'ko',
	'mage/translate',
], function (
	$,
	Component,
	globalMessageList,
	fullScreenLoader,
	chAdapter,
	chSession,
	localeObserver,
	localeModel,
	quote,
	additionalValidators,
	ko,
	$t,
) {
	'use strict';

	return Component.extend({
		defaults: {
			template: 'Fiserv_Payments/payment/commercehub/klarna-form',
			code: 'fiserv_klarna',
		},

		initialize() {
			this._super();
			this.initializeKlarnaState();
			this.updatePlaceOrderAllowed(Boolean(quote.billingAddress()));
			this._subscriptions.push(quote.paymentMethod.subscribe(() => {
				this.invalidateKlarna();
				this.loadKlarnaForm();
			}));

			for (const observable of [
				quote.billingAddress,
				quote.shippingAddress,
				quote.shippingMethod,
				quote.totals,
			]) {
				this._subscriptions.push(observable.subscribe(() => this.checkoutContextChanged()));
			}

			this._subscriptions.push(localeObserver.observe(this, () => this.checkoutContextChanged()));

			return this;
		},

		initializeKlarnaState() {
			this.klarnaReady = ko.observable(false);
			this.klarnaUnavailable = ko.observable(false);
			this.klarnaLoading = ko.observable(false);
			this.additionalData = {};
			this.paymentPayload = { sessionId: undefined, type: 'klarna' };
			this.klarnaComponent = undefined;
			this.localeSubscription = undefined;
			this._subscriptions = [];
			this._elementListeners = [];
			this._generation = 0;
			this._destroyed = false;
			this._rendered = false;
			this._mounting = false;
			this._reloadPending = false;
			this._orderLocked = false;
			this._orderRequestStarted = false;
			this._submissionData = undefined;
			this._readyTimer = undefined;
			this._contextKey = this.getCheckoutContextKey();
		},

		// Called by the standard checkout template's afterRender binding.
		initializeKlarna() {
			this._rendered = true;
			this.loadKlarnaForm();
		},

		isKlarnaSelected() {
			return quote.paymentMethod()?.method === this.getCode();
		},

		getAddressContext(address) {
			if (!address) {
				return [];
			}

			return [
				address.firstname,
				address.lastname,
				address.street,
				address.city,
				address.region,
				address.regionId,
				address.region_id,
				address.regionCode,
				address.region_code,
				address.postcode,
				address.countryId,
				address.country_id,
				address.telephone,
				address.company,
				address.customerAddressId,
			];
		},

		getBaseCurrencyCode(totals) {
			return totals?.base_currency_code || globalThis.checkoutConfig?.quoteData?.base_currency_code ||
                globalThis.checkoutConfig?.totalsData?.base_currency_code;
		},

		// Compare only checkout inputs, not address models (which contain functions/cycles).
		getCheckoutContextKey() {
			const totals = quote.totals();
			const shippingMethod = quote.shippingMethod();

			return JSON.stringify([
				totals ? Number(totals.grand_total) : undefined,
				this.getBaseCurrencyCode(totals),
				totals?.quote_currency_code,
				this.getAddressContext(quote.billingAddress()),
				this.getAddressContext(quote.shippingAddress()),
				shippingMethod?.carrier_code,
				shippingMethod?.method_code,
				localeModel.current(),
				quote.guestEmail,
			]);
		},

		checkoutContextChanged() {
			const key = this.getCheckoutContextKey();

			this.updatePlaceOrderAllowed(Boolean(quote.billingAddress()) && !this._orderLocked);

			if (key === this._contextKey) {
				return;
			}

			this._contextKey = key;
			this.invalidateKlarna();
			this.loadKlarnaForm();
		},

		// Keep Magento's inherited observable/computed; never replace it with shared state.
		updatePlaceOrderAllowed(allowed) {
			if (ko.isWriteableObservable(this.isPlaceOrderActionAllowed)) {
				this.isPlaceOrderActionAllowed(allowed);
			}
		},

		canLoadKlarna() {
			const amount = Number(quote.totals()?.grand_total);

			return !this._destroyed && this._rendered && this.isKlarnaSelected() &&
                !this._orderLocked && Boolean(quote.billingAddress()) && Number.isFinite(amount) && amount > 0;
		},

		isCurrentKlarna(generation, key) {
			return !this._destroyed && generation === this._generation &&
                this.isKlarnaSelected() && key === this.getCheckoutContextKey();
		},

		async loadKlarnaForm() {
			if (!this.canLoadKlarna() || this.klarnaComponent) {
				return;
			}

			if (this._mounting) {
				this._reloadPending = true;

				return;
			}

			const container = document.querySelector('#klarna-button-container');

			if (!container) {
				return;
			}

			this._mounting = true;
			this._reloadPending = false;

			const generation = ++this._generation;
			const key = this.getCheckoutContextKey();

			this._contextKey = key;
			this.klarnaLoading(true);
			this.klarnaUnavailable(false);
			fullScreenLoader.startLoader();

			try {
				const credentials = await this.initializeKlarnaSession(generation, key);

				if (!credentials || !this.isCurrentKlarna(generation, key)) {
					return;
				}

				const context = this.getKlarnaMountContext(container);
				const { sessionId } = credentials;

				// Commit only the original session initialized by this still-current mount.
				this.paymentPayload.sessionId = sessionId;
				this.mountKlarnaComponent(container, context, generation, key);
			} catch (error) {
				this.handleKlarnaLoadError(error, generation, key);
			} finally {
				this.finishKlarnaLoad(generation);
			}
		},

		handleKlarnaLoadError(error, generation, key) {
			if (this.isCurrentKlarna(generation, key)) {
				this.failKlarna(error?.klarnaMessage ||
                    $t('Klarna could not be loaded. Please retry or choose another payment method.'));
			}
		},

		async initializeKlarnaSession(generation, key) {
			const config = this.getPaymentConfig();
			const creds = await chSession({ merchantName: config.storeName, paymentCode: this.getCode() });

			if (!this.isCurrentKlarna(generation, key)) {
				return false;
			}

			if (!creds?.ch_credentials?.sessionId) {
				throw new Error('Missing Klarna payment session');
			}

			// Klarna reads CheckoutContext's default singleton, not a scoped contextId.
			await chAdapter.initSdk(config, creds.ch_credentials);

			if (!this.isCurrentKlarna(generation, key)) {
				return false;
			}

			return creds.ch_credentials;
		},

		getKlarnaMountContext(container) {
			if (!globalThis.customElements?.get('fiserv-klarna-button')) {
				const error = new Error('Klarna custom element is not registered');

				error.klarnaMessage = $t('Klarna is not supported by the loaded payment SDK. Please choose another payment method.');
				throw error;
			}

			const context = globalThis.fiserv.context();

			if (!context || !container.isConnected) {
				throw new Error('Klarna checkout context is unavailable');
			}

			return context;
		},

		finishKlarnaLoad(generation) {
			this._mounting = false;
			fullScreenLoader.stopLoader();

			if (generation === this._generation && !this.klarnaComponent) {
				this.klarnaLoading(false);
			}

			if (this._reloadPending) {
				this._reloadPending = false;
				this.loadKlarnaForm();
			}
		},

		mountKlarnaComponent(container, context, generation, key) {
			const element = document.createElement('fiserv-klarna-button');

			this.applyButtonAppearance(element);
			const { sessionId } = this.paymentPayload;
			const listen = (name, callback, capture = false) => {
				const handler = (event) => {
					if (this.isCurrentKlarna(generation, key) && !this._orderLocked) {
						callback(event);
					}
				};

				element.addEventListener(name, handler, capture);
				this._elementListeners.push([
					element,
					name,
					handler,
					capture,
				]);
			};

			listen('fiserv-klarna-ready', () => {
				clearTimeout(this._readyTimer);
				this.klarnaReady(true);
				this.klarnaLoading(false);
				this.klarnaUnavailable(false);
			});
			listen('fiserv-klarna-hidden', () => {
				this.invalidateKlarna();
				this.klarnaUnavailable(true);
			});
			listen('fiserv-klarna-success', (event) => {
				this.onKlarnaSuccess(event.detail, generation, key, sessionId);
			});
			listen('fiserv-klarna-cancel', (event) => this.onKlarnaCancel(event.detail));
			listen('fiserv-klarna-error', (event) => this.onKlarnaError(event.detail));
			// Only block invalid interactions, never blanket-block SDK button events.
			listen('click', (event) => {
				if (!this.klarnaReady() || !this.validateCheckoutDetails()) {
					event.preventDefault();
					event.stopPropagation();
				}
			}, true);

			if (typeof element.provideContext === 'function') {
				element.provideContext(context);
			} else {
				element.context = context;
			}

			this.klarnaComponent = element;
			this._readyTimer = setTimeout(() => {
				if (this.isCurrentKlarna(generation, key) && !this.klarnaReady() && !this._orderLocked) {
					this.failKlarna($t('Klarna could not be loaded. Please retry or choose another payment method.'));
				}
			}, 30_000);
			container.replaceChildren(element);
		},

		onKlarnaCancel() {
			this.failKlarna($t('Klarna checkout was cancelled. Please retry or choose another payment method.'));
		},

		onKlarnaError() {
			// Provider details may contain sensitive data; do not log or display them.
			this.failKlarna($t('Klarna checkout could not be completed. Please retry or choose another payment method.'));
		},

		onKlarnaSuccess(data, generation, key, sessionId) {
			// Approval data/provider tokens are intentionally not used for Charges checkout.
			if (this._orderLocked || !this.isCurrentKlarna(generation, key) ||
                !sessionId || sessionId !== this.paymentPayload.sessionId) {
				return;
			}

			if (!this.validateCheckoutDetails()) {
				this.failKlarna($t('Please check your checkout details and agreements, then retry Klarna.'));

				return;
			}

			this.additionalData = { payment_session: sessionId, payment_type: 'klarna' };
			this._submissionData = { ...this.additionalData };
			this._orderLocked = true;
			this._orderRequestStarted = false;
			this.detachKlarnaElement();
			this.klarnaReady(false);
			this.updatePlaceOrderAllowed(true);

			try {
				this.placeOrder('parent');

				if (!this._orderRequestStarted && this._orderLocked) {
					this.klarnaOrderFailed();
				}
			} catch {
				this.klarnaOrderFailed();
			}
		},

		getPlaceOrderDeferredObject(...arguments_) {
			this._orderRequestStarted = true;

			const deferred = $.when(this._super(...arguments_));

			deferred.fail(() => this.klarnaOrderFailed());

			return deferred;
		},

		klarnaOrderFailed() {
			if (this._destroyed || !this._orderLocked) {
				return;
			}

			this._orderLocked = false;
			this._submissionData = undefined;
			this.failKlarna($t('The order could not be placed. Please check your checkout details and retry Klarna.'));
			this.updatePlaceOrderAllowed(Boolean(quote.billingAddress()));
		},

		// Only an accepted SDK approval may enter Magento's inherited order flow.
		validate() {
			return this._orderLocked && !this._orderRequestStarted && Boolean(this._submissionData) &&
                this.validateCheckoutDetails();
		},

		// Wallet checkout must not run cc-form's card-field validation.
		validateCheckoutDetails() {
			// Magento validates before setting isPlaceOrderActionAllowed(false).
			return !this._destroyed && this.isKlarnaSelected() && Boolean(quote.billingAddress()) &&
                Boolean(this.paymentPayload.sessionId) && this._contextKey === this.getCheckoutContextKey() &&
                this.isPlaceOrderActionAllowed() && additionalValidators.validate();
		},

		failKlarna(message) {
			this.invalidateKlarna();
			this.klarnaUnavailable(true);
			this.showError(message);
		},

		detachKlarnaElement() {
			clearTimeout(this._readyTimer);
			this._readyTimer = undefined;

			for (const [
				element,
				name,
				handler,
				capture,
			] of this._elementListeners) {
				element.removeEventListener(name, handler, capture);
			}

			this._elementListeners = [];

			if (this.klarnaComponent) {
				this.klarnaComponent.remove();
				this.klarnaComponent = undefined;
			}
		},

		invalidateKlarna() {
			++this._generation;
			this.detachKlarnaElement();
			this.paymentPayload.sessionId = undefined;
			this.additionalData = {};
			this.klarnaReady(false);
			this.klarnaLoading(false);
			this.klarnaUnavailable(false);
			this._reloadPending = false;
		},

		getData() {
			const data = this._submissionData || this.additionalData;

			return {
				method: this.getCode(),
				additional_data: {
					payment_session: data.payment_session || undefined,
					payment_type: 'klarna',
				},
			};
		},

		// Admin-configured appearance; anything outside the SDK's allowed values falls back to 'default'.
		applyButtonAppearance(element) {
			const config = this.getPaymentConfig() || {};
			const allowed = {
				theme: [config.klarnaButtonColor, ['default', 'dark', 'light', 'outlined']],
				shape: [config.klarnaButtonShape, ['default', 'rect', 'pill']],
				'logo-alignment': [config.klarnaLogoAlignment, ['default', 'left', 'center']],
			};

			for (const [attribute, [raw, values]] of Object.entries(allowed)) {
				const value = String(raw || 'default').toLowerCase();

				element.setAttribute(attribute, values.includes(value) ? value : 'default');
			}
		},

		getPaymentConfig() {
			return globalThis.checkoutConfig.payment[this.getCode()];
		},

		showError(message) {
			globalMessageList.addErrorMessage({ message });
		},

		getCode() {
			return this.code;
		},

		showPrivacyStatement() {
			return this.getPaymentConfig()?.show_privacy_statement;
		},

		isBillingAddressRequired() {
			return true;
		},

		isActive() {
			const active = this.getCode() === this.isChecked();

			this.active(active);

			return active;
		},

		destroy() {
			this._destroyed = true;
			this.invalidateKlarna();

			for (const subscription of this._subscriptions) {
				subscription.dispose();
			}

			this._subscriptions = [];
			this.localeSubscription = undefined;

			return this._super();
		},
	});
});
