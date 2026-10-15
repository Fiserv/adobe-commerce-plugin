/*global define*/
define([
    'require',
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/redirect-on-success',
    'mage/url',
    'ko',
    'mage/translate'
], function (localRequire, $, Component, globalMessageList, fullScreenLoader,
    additionalValidators, quote, redirectOnSuccessAction, urlBuilder, ko, $t) {
    'use strict';

    const containerId = 'affirmvirtualcard-button-container';
    const endpoint = 'fiserv/commercehub/createaffirmvirtualcardsession';
    const methodSelector = '[name="payment[method]"]';
    let sdkPromise;

    return Component.extend({
        defaults: {
            template: 'Fiserv_Payments/payment/commercehub/affirmvirtualcard-form',
            code: 'fiserv_affirm_virutal_card',
            active: false,
            affirmvirtualcardComponent: null,
            affirmvirtualcardInitialized: false,
            initializedAmount: 0,
            redirectAfterPlaceOrder: false
        },

        initialize: function () {
            this._super();
            this.isPlaceOrderActionAllowed = ko.observable(false);
            this.canDisplayAffirmVirtualCard = ko.observable(false);
            this.isPreparingAffirmVirtualCard = ko.observable(false);
            this.isAffirmVirtualCardReady = ko.observable(false);
            this.paymentPayload = {sessionId: null};
            this._subscriptions = [];
            this._generation = 0;
            this._disposed = false;
            this._orderPending = false;
            this._orderComplete = false;
            this._approvalAccepted = false;
            this._modalStarted = false;
            this._contextKey = this.getQuoteKey();
            this.observeBillingAddress();
            this.observeTotals();
            this._subscriptions.push(quote.shippingAddress.subscribe(() => this.quoteChanged()));
            this._subscriptions.push(quote.paymentMethod.subscribe(() => {
                if (this.getCode() !== this.isChecked()) {
                    this.invalidateSession();
                }
            }));
            return this;
        },

        initObservable: function () {
            this._super().observe(['active']);
            return this;
        },

        /** Template afterRender: load dependencies, but do not validate agreements on page load. */
        initializeAffirmVirtualCard: async function () {
            if (this._disposed || !this.isMerchantActive()) {
                return;
            }
            this.watchPaymentMethods();
            try {
                const sdk = await this.loadSdk();

                if (!this._disposed) {
                    this._sdk = sdk;
                    this.canDisplayAffirmVirtualCard(this.dependenciesAvailable());
                }
            } catch (error) {
                if (!this._disposed) {
                    this.canDisplayAffirmVirtualCard(false);
                    this.invalidateSession();
                }
            }
        },

        /**
         * Reuse the shared loader; never inject another SDK or change its URL.
         * A provider/alias mismatch must be fixed centrally, not by loading another version.
         */
        loadSdk: async function () {
            const configuredUrl = this.getPaymentConfig().sdkUrl;
            const expectedUrl = new URL(configuredUrl, window.location.href);
            const allowedUrl = /^https:\/\/commercehub-checkout(?:-cert)?\.fiservapps\.com\/sdk\/\d+\.\d+\.\d+\/checkout\.js$/;

            if (!allowedUrl.test(expectedUrl.href)) {
                throw new Error('SDK unavailable');
            }
            const scripts = Array.from(document.querySelectorAll('script[src]'));
            const sdkScripts = scripts.filter((script) => /\/sdk\/[^/]+\/checkout(?:\.js)?(?:[?#]|$)/.test(script.src));

            if (sdkScripts.some((script) => new URL(script.src).origin + new URL(script.src).pathname !== expectedUrl.href)) {
                throw new Error('SDK unavailable');
            }
            if (window.fiserv && !sdkScripts.length && !localRequire.defined('SDCv2Library')) {
                throw new Error('SDK unavailable');
            }
            if (!sdkPromise) {
                sdkPromise = new Promise((resolve, reject) => {
                    localRequire(['SDCv2Library'], () => resolve(window.fiserv), () => reject(new Error('SDK unavailable')));
                }).catch(() => {
                    sdkPromise = null;
                    throw new Error('SDK unavailable');
                });
            }
            const sdk = await sdkPromise;

            if (!sdk || sdk !== window.fiserv || typeof sdk.init !== 'function' ||
                !sdk.components || typeof sdk.components.affirm !== 'function') {
                throw new Error('SDK unavailable');
            }
            return sdk;
        },

        dependenciesAvailable: function () {
            return Boolean(!this._disposed && this.isMerchantActive() && this._sdk &&
                this._sdk === window.fiserv && typeof this._sdk.init === 'function' &&
                this._sdk.components && typeof this._sdk.components.affirm === 'function');
        },

        /**
         * Explicit Continue/Retry action for the next template step. Validate BEFORE
         * exposing the SDK button (its modal has no documented before-open hook).
         */
        loadAffirmVirtualCardForm: async function () {
            if (!this.dependenciesAvailable() || this.getCode() !== this.isChecked() ||
                this.isPreparingAffirmVirtualCard() || this._orderPending || this._orderComplete) {
                return false;
            }
            if (!quote.billingAddress() || !this.validate() || !additionalValidators.validate()) {
                return false;
            }
            this.invalidateSession();
            const generation = this._generation;
            const quoteKey = this.getQuoteKey();
            const container = document.getElementById(containerId);

            if (!container || !(Number((quote.totals() || {}).grand_total) > 0)) {
                return false;
            }
            this._contextKey = quoteKey;
            this.isPreparingAffirmVirtualCard(true);
            fullScreenLoader.startLoader();
            try {
                // Success MUST certify server-side VCN eligibility and a quote-bound
                // MERCHANT session. No legacy helper, credentials or browser amounts.
                const response = await this.sessionRequest({action: 'create'});

                if (!response || typeof response.sessionId !== 'string' || !response.sessionId.trim() ||
                    typeof response.sdkData !== 'string' || !response.sdkData.trim()) {
                    if (response && typeof response.sessionId === 'string') {
                        this.revokeSession(response.sessionId);
                    }
                    throw new Error('Session unavailable');
                }
                if (!this.isCurrent(generation, quoteKey, container)) {
                    this.revokeSession(response.sessionId);
                    return false;
                }
                this.paymentPayload.sessionId = response.sessionId;
                await this._sdk.init({sdkData: response.sdkData});
                if (!this.isCurrent(generation, quoteKey, container)) {
                    return false;
                }
                await this.mountAffirmVirtualCardComponent(generation, quoteKey, container);
                return this.affirmvirtualcardInitialized;
            } catch (error) {
                if (!this._disposed && generation === this._generation) {
                    this.invalidateSession();
                    this.showError();
                }
                return false;
            } finally {
                if (!this._disposed) {
                    this.isPreparingAffirmVirtualCard(false);
                }
                fullScreenLoader.stopLoader();
            }
        },

        mountAffirmVirtualCardComponent: async function (generation, quoteKey, container) {
            // Install capture guards before the SDK registers its button listeners.
            this.attachContainerClickGuards(container, generation, quoteKey);
            const component = await this._sdk.components.affirm({
                data: {button: {parentElementId: containerId}},
                hooks: {
                    onApprove: () => this.onAffirmVirtualCardSuccess(generation, quoteKey, container),
                    onError: () => this.handleSdkFailure(generation, true),
                    onCancel: () => this.handleSdkFailure(generation, false)
                }
            });

            if (!this.isCurrent(generation, quoteKey, container)) {
                // Preparation is serialized, so no newer mount can own this node yet.
                $(container).empty();
                return;
            }
            this.affirmvirtualcardComponent = component;
            this.affirmvirtualcardInitialized = true;
            this.initializedAmount = Number((quote.totals() || {}).grand_total);
            this.isAffirmVirtualCardReady(true);
        },

        attachContainerClickGuards: function (container, generation, quoteKey) {
            this._buttonContainer = container;
            this._buttonGuard = (event) => {
                if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                const allowed = this.isCurrent(generation, quoteKey, container) &&
                    this.affirmvirtualcardInitialized && !this._modalStarted &&
                    !this._orderPending && !this._approvalAccepted &&
                    quote.billingAddress() && this.validate() && additionalValidators.validate();

                if (!allowed) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    return;
                }
                // A keyboard activation generates a click; lock on that click only.
                if (event.type === 'click') {
                    this._modalStarted = true;
                    // Do not stop propagation here: capture must reach the SDK target.
                }
            };
            container.addEventListener('click', this._buttonGuard, true);
            container.addEventListener('keydown', this._buttonGuard, true);
        },

        isCurrent: function (generation, quoteKey, container) {
            return !this._disposed && generation === this._generation && this.dependenciesAvailable() &&
                this.getCode() === this.isChecked() && quoteKey === this.getQuoteKey() &&
                document.getElementById(containerId) === container && container.isConnected;
        },

        onAffirmVirtualCardSuccess: function (generation, quoteKey, container) {
            if (!this.isCurrent(generation, quoteKey, container) || !this.paymentPayload.sessionId ||
                this._approvalAccepted || this._orderPending || this._orderComplete) {
                return;
            }
            if (!quote.billingAddress() || !this.validate() || !additionalValidators.validate()) {
                this.invalidateSession();
                return;
            }
            // Approval is only permission to submit the reference. The shared card
            // command must verify/complete the session server-side before success.
            this._approvalAccepted = true;
            this.isPlaceOrderActionAllowed(true);
            this.placeOrder('parent');
        },

        placeOrder: function (key) {
            if (key !== 'parent' || this._disposed || !this._approvalAccepted ||
                this._orderPending || this._orderComplete || !this.paymentPayload.sessionId ||
                this._contextKey !== this.getQuoteKey() || this.getCode() !== this.isChecked()) {
                return false;
            }
            this._orderPending = true;
            this._submittedSessionId = this.paymentPayload.sessionId;
            let result;

            try {
                result = this._super();
            } catch (error) {
                this.orderFailed();
                return false;
            }
            if (result === false) {
                this.orderFailed();
            }
            return result;
        },

        getPlaceOrderDeferredObject: function () {
            const deferred = this._super();

            deferred.done(() => {
                this._orderComplete = true;
                this._orderPending = false;
            }).fail(() => this.orderFailed());
            return deferred;
        },

        afterPlaceOrder: function () {
            if (!this._disposed) {
                redirectOnSuccessAction.execute();
            }
        },

        orderFailed: function () {
            const sessionId = this._submittedSessionId;

            this._submittedSessionId = null;
            this._orderPending = false;
            if (sessionId) {
                this.revokeSession(sessionId);
            }
            if (!this._disposed) {
                this.invalidateSession();
                this.showError();
            }
        },

        handleSdkFailure: function (generation, showMessage) {
            if (this._disposed || generation !== this._generation || this._approvalAccepted || this._orderPending) {
                return;
            }
            this.invalidateSession();
            if (showMessage) {
                this.showError();
            }
        },

        /** Local invalidation is immediate; backend invalidation is idempotent and quote-scoped. */
        invalidateSession: function () {
            const sessionId = this.paymentPayload.sessionId;

            this._generation += 1;
            this.paymentPayload.sessionId = null;
            this._approvalAccepted = false;
            this._modalStarted = false;
            this.affirmvirtualcardInitialized = false;
            this.affirmvirtualcardComponent = null;
            this.isAffirmVirtualCardReady(false);
            this.isPlaceOrderActionAllowed(false);
            if (this._buttonContainer) {
                this._buttonContainer.removeEventListener('click', this._buttonGuard, true);
                this._buttonContainer.removeEventListener('keydown', this._buttonGuard, true);
                $(this._buttonContainer).empty();
                this._buttonContainer = null;
                this._buttonGuard = null;
            }
            // Never race an in-flight Magento completion with a cancellation request.
            if (sessionId && !this._orderPending && !this._orderComplete) {
                this.revokeSession(sessionId);
            }
        },

        sessionRequest: function (data) {
            // Form encoding allows Magento's normal form-key validation for guests
            // and customers. The authenticated checkout session determines the quote.
            return $.ajax({
                url: urlBuilder.build(endpoint),
                type: 'POST',
                dataType: 'json',
                cache: false,
                timeout: 30000,
                data: Object.assign({form_key: window.FORM_KEY, paymentCode: this.getCode()}, data)
            });
        },

        revokeSession: function (sessionId) {
            this.sessionRequest({action: 'invalidate', sessionId: sessionId}).fail(() => {
                // Do not surface transport/callback contents. Server expiry and
                // replacement-session invalidation must also prevent stale reuse.
            });
        },

        observeBillingAddress: function () {
            this._subscriptions.push(quote.billingAddress.subscribe(() => this.quoteChanged()));
        },

        observeTotals: function () {
            this._subscriptions.push(quote.totals.subscribe(() => this.quoteChanged()));
        },

        quoteChanged: function () {
            if (!this._disposed && this._contextKey !== this.getQuoteKey()) {
                this._contextKey = this.getQuoteKey();
                this.invalidateSession();
            }
        },

        getAddressKey: function (address) {
            if (!address) {
                return null;
            }
            return [address.customerAddressId, address.firstname, address.lastname,
                address.company, address.street, address.city, address.regionId,
                address.region_id, address.regionCode, address.region,
                address.postcode, address.countryId, address.country_id,
                address.telephone, address.customAttributes];
        },

        getQuoteKey: function () {
            const totals = quote.totals() || {};

            return JSON.stringify([Number(totals.grand_total), totals.quote_currency_code,
                totals.base_currency_code, this.getAddressKey(quote.billingAddress()),
                this.getAddressKey(quote.shippingAddress()), quote.getItems()]);
        },

        watchPaymentMethods: function () {
            if (this._methodHandler) {
                return;
            }
            this._methodHandler = (event) => {
                if (this._disposed) {
                    return;
                }
                if (event.currentTarget.id !== this.getCode()) {
                    this.invalidateSession();
                } else {
                    this.initializeAffirmVirtualCard();
                }
            };
            $(document).on('click.fiservAffirmVirtualCard', methodSelector, this._methodHandler);
        },

        getData: function () {
            return {
                method: this.getCode(),
                additional_data: {payment_session: this.paymentPayload.sessionId}
            };
        },

        getPaymentConfig: function () {
            return (window.checkoutConfig && window.checkoutConfig.payment &&
                window.checkoutConfig.payment[this.getCode()]) || {};
        },

        isMerchantActive: function () {
            const active = this.getPaymentConfig().isActive;

            return active === true || active === 1 || active === '1';
        },

        getCode: function () {
            return this.code;
        },

        getTitle: function () {
            return this.getPaymentConfig().title || $t('Affirm Virtual Card');
        },

        showPrivacyStatement: function () {
            return false;
        },

        isBillingAddressRequired: function () {
            return true;
        },

        isActive: function () {
            const available = this.canDisplayAffirmVirtualCard && this.canDisplayAffirmVirtualCard();
            const active = available && this.dependenciesAvailable() && this.getCode() === this.isChecked();

            this.active(Boolean(active));
            return Boolean(active);
        },

        showError: function () {
            globalMessageList.addErrorMessage({
                message: $t('Unable to complete Affirm Virtual Card checkout. Please try again or choose another payment method.')
            });
        },

        destroy: function () {
            this._disposed = true;
            this.invalidateSession();
            this._subscriptions.forEach((subscription) => subscription.dispose());
            if (this._methodHandler) {
                $(document).off('click.fiservAffirmVirtualCard', methodSelector, this._methodHandler);
            }
            return this._super();
        }
    });
});

