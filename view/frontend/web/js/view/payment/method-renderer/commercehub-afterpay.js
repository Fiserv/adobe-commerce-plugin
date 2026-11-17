/* eslint-disable max-lines-per-function, max-params -- AMD uses one factory and one argument per dependency. */
/* eslint-disable compat/compat -- Afterpay requires Web Components and Promises; Opera Mini is unsupported. */
/* eslint-disable object-shorthand -- Preserve Magento's classic function wrappers for _super methods. */
define([
	'jquery',
	'Magento_Payment/js/view/payment/cc-form',
	'Magento_Ui/js/model/messageList',
	'Magento_Checkout/js/model/full-screen-loader',
	'Fiserv_Payments/js/action/create-commercehub-enriched-session',
	'Magento_Checkout/js/model/quote',
	'Magento_Checkout/js/model/payment/additional-validators',
	'Magento_Checkout/js/model/shipping-service',
	'Magento_Customer/js/model/customer',
	'require',
	'ko',
	'mage/translate',
], (
	$, Component, globalMessageList, fullScreenLoader, chSession, quote,
	additionalValidators, shippingService, customer, localRequire, ko, $t,
) => Component.extend({
	defaults: {
		template: 'Fiserv_Payments/payment/commercehub/afterpay-form',
		code: 'fiserv_afterpay',
		additionalData: {},
		paymentPayload: { sessionId: undefined, type: 'afterpay' },
		afterpayComponent: undefined,
		afterpayInitialized: false,
	},

	initialize: function () {
		this._super();
		// Observables and mutable lifecycle state belong to this instance.
		this.isPlaceOrderActionAllowed = ko.observable(Boolean(quote.billingAddress()));
		this.additionalData = {};
		this.paymentPayload = { sessionId: undefined, type: 'afterpay' };
		this.subscriptions = [];
		this.disposed = false;
		this.submitting = false;
		this.loading = false;
		this.approvalPending = false;
		this.shopperEmail = ko.observable(this.readShopperEmail());
		this.observeShopperEmail();
		this.observeBillingAddress();
		this.observeTotals();
		this.watchPaymentMethods();

		return this;
	},

	initializeAfterpay: function () {
		// afterRender can run on the radio before its sibling container exists.
		this.scheduleLoad(0);
	},

	readShopperEmail: function () {
		const email = customer.isLoggedIn() ? customer.customerData?.email : (quote.guestEmail || $('#customer-email').val());
		const value = typeof email === 'string' ? email.trim() : '';

		// Mirrors the SDK's validation so a half-typed guest email never reaches customer-email.
		const [local, domain, ...extra] = value.split('@');
		const isValid = Boolean(local) && Boolean(domain) && extra.length === 0 &&
			!/\s/.test(value) && domain.slice(1, -1).includes('.');

		return isValid ? value : '';
	},

	observeShopperEmail: function () {
		// quote.guestEmail is a plain property, so re-read it when the checkout email field settles.
		this.emailHandler = () => this.shopperEmail(this.readShopperEmail());
		$(document).on('change.fiservAfterpay blur.fiservAfterpay', '#customer-email', this.emailHandler);
		// Shipping-step completion also stores the email; re-sync when it is saved.
		this.subscriptions.push(shippingService.isLoading.subscribe(this.emailHandler));
	},

	observeBillingAddress: function () {
		this.subscriptions.push(quote.billingAddress.subscribe(() => {
			this.isPlaceOrderActionAllowed(Boolean(quote.billingAddress()) && !this.submitting);
		}));
	},

	observeTotals: function () {
		// Address, shipping, total and email changes all require a fresh session.
		this.quoteState = ko.pureComputed(() => this.getQuoteState());
		this.subscriptions.push(this.quoteState.subscribe(() => {
			this.invalidateSession();
			this.scheduleLoad(300);
		}));
	},

	watchPaymentMethods: function () {
		this.subscriptions.push(quote.paymentMethod.subscribe(() => {
			this.invalidateSession();
			this.scheduleLoad(0);
		}));
	},

	getAddressState: function (address) {
		if (!address) {
			return [];
		}

		return [
			address.customerAddressId,
			address.firstname,
			address.lastname,
			address.company,
			address.street,
			address.city,
			address.region,
			address.regionId,
			address.regionCode,
			address.region_id,
			address.postcode,
			address.countryId,
			address.country_id,
			address.telephone,
			address.vatId,
			address.customAttributes,
		];
	},

	getQuoteState: function () {
		const totals = quote.totals() || {};
		const method = quote.shippingMethod();

		return JSON.stringify([
			quote.getQuoteId(),
			Number(totals.grand_total),
			this.getCurrency(),
			this.getBaseCurrency(),
			totals.base_grand_total,
			totals.discount_amount,
			totals.shipping_amount,
			totals.tax_amount,
			totals.items,
			this.getAddressState(quote.billingAddress()),
			this.getAddressState(quote.shippingAddress()),
			method && [method.carrier_code, method.method_code, method.amount],
			shippingService.isLoading(),
			this.shopperEmail(),
		]);
	},

	getCurrency: function () {
		return quote.totals()?.quote_currency_code ||
            globalThis.checkoutConfig?.quoteData?.quote_currency_code ||
            globalThis.checkoutConfig?.totalsData?.quote_currency_code || '';
	},

	getBaseCurrency: function () {
		return quote.totals()?.base_currency_code ||
            globalThis.checkoutConfig?.quoteData?.base_currency_code ||
            globalThis.checkoutConfig?.totalsData?.base_currency_code || '';
	},

	hasValidAmount: function () {
		const total = Number(quote.totals()?.grand_total);

		return Number.isFinite(total) && total > 0;
	},

	canLoad: function () {
		return !this.disposed && !this.submitting && this.getCode() === this.isChecked() &&
			Boolean(this.getPaymentConfig().isActive) && Boolean(quote.billingAddress()) &&
			!shippingService.isLoading() && this.hasValidAmount();
	},

	scheduleLoad: function (delay) {
		clearTimeout(this.reloadTimer);
		if (this.canLoad()) {
			this.reloadTimer = setTimeout(() => this.loadAfterpayForm(), delay);
		}
	},

	withTimeout: function (promise, duration) {
		let timer;
		const timeout = new Promise((resolve, reject) => {
			timer = setTimeout(() => reject(new Error('Payment initialization unavailable.')), duration);
		});

		return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
	},

	loadAfterpayForm: async function () {
		const container = document.querySelector('#afterpay-button-container');

		if (!this.canLoad() || this.loading || this.afterpayComponent || !container) {
			return;
		}
		const snapshot = this.getQuoteState();
		const config = this.getPaymentConfig();
		// A response is only usable while the quote it was created for is still the current one.
		const isCurrent = () => snapshot === this.getQuoteState() && this.canLoad() && container.isConnected;

		this.loading = true;
		fullScreenLoader.startLoader();
		container.replaceChildren();
		try {
			await this.loadAfterpaySdk();
			const response = await this.withTimeout(chSession({
				merchantName: config.storeName,
				paymentCode: this.getCode(),
				amount: { currency: this.getCurrency(), total: Number(quote.totals().grand_total) },
			}), 30_000);

			if (isCurrent()) {
				this.mountAfterpayComponent(container, config, response?.ch_credentials, snapshot);
			}
		} catch {
			if (isCurrent()) {
				this.handleAfterpayError();
			}
		} finally {
			this.loading = false;
			fullScreenLoader.stopLoader();
			if (snapshot !== this.getQuoteState()) {
				this.scheduleLoad(0);
			}
		}
	},

	loadAfterpaySdk: async function () {
		await this.withTimeout(new Promise((resolve, reject) => {
			localRequire(['SDCv2Library'], resolve, reject);
		}), 15_000);

		if (!globalThis.customElements) {
			throw new Error('Incompatible SDK.');
		}
		await this.withTimeout(Promise.all([
			globalThis.customElements.whenDefined('fiserv-context'),
			globalThis.customElements.whenDefined('fiserv-afterpay-button'),
		]), 15_000);
	},

	mountAfterpayComponent: function (container, config, session, snapshot) {
		// SDK event detail is never a source of credentials.
		if (typeof session?.sessionId !== 'string' || !session.sessionId.trim() ||
            typeof session.sdkData !== 'string' || !session.sdkData.trim()) {
			throw new Error('Missing payment session or SDK token.');
		}
		this.paymentPayload.sessionId = session.sessionId;
		this.sessionSnapshot = snapshot;

		const context = document.createElement('fiserv-context');
		const button = document.createElement('fiserv-afterpay-button');
		let contextReady = false;
		let buttonReady = false;
		const ready = () => {
			this.afterpayInitialized = contextReady && buttonReady;
		};

		context.setAttribute('sdk-data', session.sdkData);
		button.setAttribute('theme', config.theme);
		button.setAttribute('shape', config.shape);
		button.setAttribute('size', config.size);
		button.setAttribute('width', config.width);
		if (this.shopperEmail()) {
			// Without it Afterpay shows its own email prompt after the click.
			button.setAttribute('customer-email', this.shopperEmail());
		}

		// Listeners live on the elements, so removing the elements in invalidateSession cleans them up.
		context.addEventListener('fiserv-context-init-ready', () => {
			contextReady = true;
			ready();
		});
		context.addEventListener('fiserv-context-init-error', () => this.handleAfterpayError());
		button.addEventListener('fiserv-afterpay-ready', () => {
			buttonReady = true;
			ready();
		});
		button.addEventListener('fiserv-afterpay-error', () => this.handleAfterpayError());
		button.addEventListener('fiserv-afterpay-success', () => this.onAfterpaySuccess());
		// Cancelling consumes the session; build a fresh one so the button is usable again.
		button.addEventListener('fiserv-afterpay-cancel', () => {
			this.invalidateSession();
			this.scheduleLoad(0);
		});
		// Valid clicks must reach the native SDK button; do not blanket-preventDefault.
		context.addEventListener('click', (event) => {
			if (!this.afterpayInitialized || !this.validate() ||
                !this.isPlaceOrderActionAllowed() || !additionalValidators.validate()) {
				event.preventDefault();
				event.stopImmediatePropagation();
			}
		}, true);
		// Prevent legacy template bindings from cancelling the native button action.
		context.addEventListener('click', (event) => event.stopPropagation());

		this.contextElement = context;
		this.afterpayComponent = button;
		context.append(button);
		container.replaceChildren(context);
	},

	validate: function () {
		// Skip card-field validation; parent checkout still runs additional validators.
		return this.canLoad() && Boolean(this.paymentPayload.sessionId) &&
            this.sessionSnapshot === this.getQuoteState();
	},

	onAfterpaySuccess: function () {
		if (!this.afterpayInitialized || !this.validate() || this.approvalPending || this.submitting) {
			return;
		}
		this.approvalPending = true;
		// Submit only the original backend session reference, never SDK event data.
		this.additionalData = {
			payment_type: 'afterpay',
			payment_session: this.paymentPayload.sessionId,
		};
		try {
			if (this.placeOrder('parent') === false) {
				this.invalidateSession();
			}
		} catch {
			this.submitting = false;
			this.handleAfterpayError();
		}
	},

	placeOrder: function (key, event) {
		if (key !== 'parent' || this.submitting || !this.approvalPending || !this.validate()) {
			return false;
		}

		// Parent runs additionalValidators, including checkout agreements, and redirects.
		return this._super(undefined, event);
	},

	getPlaceOrderDeferredObject: function () {
		this.submitting = true;
		const request = this._super();

		request.fail(() => {
			if (!this.disposed) {
				this.submitting = false;
				this.invalidateSession();
			}
		});

		return request;
	},

	getData: function () {
		return {
			method: this.getCode(),
			additional_data: {
				payment_type: 'afterpay',
				payment_session: this.additionalData.payment_session,
			},
		};
	},

	invalidateSession: function () {
		clearTimeout(this.reloadTimer);
		this.contextElement?.remove();
		this.contextElement = undefined;
		this.afterpayComponent = undefined;
		this.afterpayInitialized = false;
		this.approvalPending = false;
		this.paymentPayload.sessionId = undefined;
		this.additionalData = {};
		this.sessionSnapshot = undefined;
		this.isPlaceOrderActionAllowed(!this.disposed && Boolean(quote.billingAddress()) && !this.submitting);
	},

	handleAfterpayError: function () {
		this.invalidateSession();
		// Never render or log SDK errors: they can contain session credentials.
		this.showError($t('Afterpay checkout could not be completed. Please try again.'));
	},

	getPaymentConfig: function () {
		return globalThis.checkoutConfig?.payment?.[this.getCode()] || {};
	},

	showError: function (message) {
		globalMessageList.addErrorMessage({ message });
	},

	getCode: function () {
		return this.code;
	},

	showPrivacyStatement: function () {
		return this.getPaymentConfig().show_privacy_statement;
	},

	isBillingAddressRequired: function () {
		return true;
	},

	isActive: function () {
		const active = this.getCode() === this.isChecked();

		this.active(active);

		return active;
	},

	destroy: function () {
		this.disposed = true;
		this.invalidateSession();
		for (const subscription of this.subscriptions) {
			subscription.dispose();
		}
		this.quoteState.dispose();
		$(document).off('change.fiservAfterpay blur.fiservAfterpay', '#customer-email', this.emailHandler);

		return this._super();
	},
}));
