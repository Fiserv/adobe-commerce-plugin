define([
		'ko',
		'uiComponent',
		'Fiserv_Payments/js/action/ensure-array-entries',
        'Fiserv_Payments/js/action/create-commercehub-session',
		'Fiserv_Payments/js/ch-adapter',
		'Magento_Ui/js/modal/alert',
		'Magento_Ui/js/lib/view/utils/dom-observer',
		'jquery',
		'domReady!'
	], function(
		ko,
		Component,
		ensureArrayEntries,
        chSession,
		chIframe,
		alert,
		domObserver,
		$
	) {
		'use strict';

		return Component.extend({
			defaults: {
				paymentSessionInputId: 'fiserv_commercehub_payment_session',
				iframeValid: false,
				imports: { onActiveChange: 'active' }
			},

			/**
			 * @returns {exports.initialize}
			 */
			initialize: function (config) {

				if (typeof(config.paymentConfig) === "undefined") {
					throw new Error('Required parameter paymentConfig not found!');
				}
				this.paymentConfig = config.paymentConfig;


				if($('input#p_method_fiserv_commercehub').is(':checked')) {
					this.initPayment();
					this.onActiveChange(true);
				}
			},

			/**
			 * @returns {exports.initialize}
			 */
			initPayment: function () {
				if(this.paymentInitiated === true) {
					return;
				}
				let config = this.paymentConfig;
				if (typeof(config) === "undefined") {
					throw new Error('Required parameter paymentConfig not found!');
				}

				var self = this;

				// defaults aren't being set for some reason...?
				self.paymentConfig = config;
				self.code = 'fiserv_commercehub';
				self.$selector = null;
				self.selector = 'edit_form';
				self.paymentMethodName = '[name="payment[method]"]';
				ensureArrayEntries();

				chIframe.initialize(
					self.paymentConfig,
					self.iframeLoadSuccess.bind(self),
					self.iframeValidHandler.bind(self),
					(brand) => { this.cardBrandChangeHandler(brand); },
					(data) => { this.fieldValidityHandler(data); },
					(data) => { this.fieldFocusHandler(data); }
				);
				this.initObservable();
				this.paymentInitiated = true;
			},



			/**
			 * Set list of observable attributes
			 * @returns {exports.initObservable}
			 */
			initObservable: function () {
				var self = this;

				self.$selector = $('#' + self.selector);
				this._super()
					.observe([
						'active'
					]);

				// re-init payment method events
				self.$selector.off('changePaymentMethod.' + self.code)
					.on('changePaymentMethod.' + self.code, self.changePaymentMethod.bind(self));

				return this;
			},


			createIframe: async function() {
				this.beginAsyncFlow();

                let credsResponse = undefined;
                try {
                    credsResponse = await chSession(this.buildSessionPayload());
                    this.paymentSessionData = credsResponse["ch_credentials"];
                } catch (error) {
                    console.log("An error occurred while starting Commercehub payment session: ".concat(error));
                    this.iframeLoadFailure("Unable to start payment session. Please try again.");
                    return;
                }

                if (!this.paymentSessionData) {
                    this.iframeLoadFailure("Unable to start payment session. Please try again.");
                    return;
                }

                this.contextId = crypto.randomUUID();

                try {
                    await chIframe.initSdk(
                        this.paymentConfig,
                        this.paymentSessionData,
                        this.contextId
                    );
                } catch (error) {
                    let errDetail = (error && error.message) ? error.message : String(error);
                    console.error("An error occurred while initializing Commercehub SDK:", error);
                    this.iframeLoadFailure("Unable to initialize payment: " + errDetail + ". Please try again.");
                    return;
                }

                let iframePromise = new Promise((resolve, reject) => {
					chIframe.instantiateIframe(
						resolve,
						reject
					);
				});
				iframePromise.then((data) => {

				}).catch((error) => {
					this.iframeLoadFailure(error);
				});

			},

			destroyIframe: function() {
				chIframe.destroyIframe();
			},

			iframeLoadSuccess: function (data) {
				this.endAsyncFlow();
			},

			iframeLoadFailure: function(message) {
				this.endAsyncFlow();
				this.showError(message);
			},

			iframeRunFailure: function() {
				this.endAsyncFlow();
				this.showError("Card capture failure. Please try again.");
				chIframe.resetIframe();
				this.hideSurchargeConfirmStep();
			},

			iframeValidHandler: function(valid) {
				this.iframeValid = valid;
			},

			beginAsyncFlow: function() {
				$('body').trigger('processStart');
			},

			endAsyncFlow: function() {
				$('body').trigger('processStop');
			},

			/**
			 * Show error message
			 *
			 * @param {String} errorMessage
			 * @private
			 */
			showError: function (errorMessage) {
				alert({
					content: errorMessage
				});
			},

			placeOrder: function() {
                if (!this.paymentSessionData || !this.paymentSessionData["sessionId"]) {
                    this.showError("Payment session not established. Please try again.");
                    return;
                }
				this.setPaymentSessionInput(this.paymentSessionData["sessionId"]);
				$('#' + this.selector).trigger('realOrder');
			},

			setPaymentSessionInput: function(sessionId) {
				this.getPaymentSessionInput().val(sessionId);
			},

			getPaymentSessionInput: function() {
				return $("input#fiserv_commercehub_payment_session");
			},

			/**
			 * Begin order flow (i.e. secure card capture)
			 */
			startOrderFlow: function () {
				if (!this.iframeValid) {
					this.dismissAdminLoader();
					return false;
				}

			if (!this.isSurchargeEnabled()) {
				this._captureCard();
				return false;
			}

				if (this.surchargeShown) {
					this.dismissAdminLoader();
					return false;
				}

				this.fetchAndShowSurcharge();
				return false;
			},

			dismissAdminLoader: function() {
				const stop = () => {
					$('body').trigger('processStop');
					$('#' + this.selector).trigger('processStop');
					$('.popup.popup-loading').hide();
				};
				stop();
				setTimeout(stop, 0);
			},

			// Handle payment method switching
			isActive: function () {
				return $('[value="' + this.code + '"]' + this.paymentMethodName).prop('checked');
			},


			/**
			 * Triggered when payment changed
			 *
			 * @param {Boolean} isActive
			 */
			onActiveChange: function (isActive) {
				if (!isActive) {
					this.$selector.off('submitOrder.' + this.code);
					this.destroyIframe();
					this.hideSurchargeConfirmStep();
					return;
				}

				this.disableEventListeners();
				window.order.addExcludedPaymentMethod(this.code);
				this.enableEventListeners();
				this.destroyIframe();
				this.createIframe();
			},


			/**
			 * Enable/disable current payment method
			 * @param {Object} event
			 * @param {String} method
			 * @returns {exports.changePaymentMethod}
			 */
			changePaymentMethod: function (event, method) {
				this.active(method === this.code);
				this.onActiveChange(this.active());
				return this;
			},

			/**
			 * Enable form event listeners
			 */
			enableEventListeners: function () {
				this.$selector.on('submitOrder.' + this.code, this.startOrderFlow.bind(this));
				$('#sdc-unmask-number').on('click', () => {this.unmaskCardNumber();});
				$('#sdc-mask-number').on('click', () => {this.maskCardNumber();});
				$('#sdc-unmask-security').on('click', () => {this.unmaskSecurity();});
				$('#sdc-mask-security').on('click', () => {this.maskSecurity();});
			},

			/**
			 * Disable form event listeners
			 */
			disableEventListeners: function () {
				this.$selector.off('submitOrder');
				this.$selector.off('submit');
				$('#sdc-unmask-number').off('click');
				$('sdc-mask-number').off('click');
				$('#sdc-unmask-security').off('click');
				$('sdc-mask-security').off('click');
			},

			getCardBrandIcon: function() {
				return $('#sdc-card-brand-icon');
			},

			setCardBrandIconClass: function(cssClass) {
				let icon = this.getCardBrandIcon();
				icon.removeClass();
				icon.addClass('sdc-card-brand-icon');
				if (typeof(cssClass) !== "undefined")
				{
					icon.addClass(cssClass);
				}
			},

			cardBrandChangeHandler: function(brand) {
				let icon = this.getCardBrandIcon();

				if (this._lastCardBrand && brand && this._lastCardBrand !== brand) {
					this.hideSurchargeConfirmStep();
				}
				this._lastCardBrand = brand || null;

				switch (brand) {
					case null:
						this.setCardBrandIconClass();
						break;
					case 'visa':
						this.setCardBrandIconClass('sdc-card-brand-icon-visa');
						break;
					case 'mastercard':
						this.setCardBrandIconClass('sdc-card-brand-icon-mastercard');
						break;
					case 'american-express':
						this.setCardBrandIconClass('sdc-card-brand-icon-amex');
						break;
					case 'diners-club':
						this.setCardBrandIconClass('sdc-card-brand-icon-diners');
						break;
					case 'discover':
						this.setCardBrandIconClass('sdc-card-brand-icon-discover');
						break;
					case 'jcb':
						this.setCardBrandIconClass('sdc-card-brand-icon-jcb');
						break;
					case 'unionpay':
						this.setCardBrandIconClass('sdc-card-brand-icon-union');
						break;
					case 'maeestro':
						this.setCardBrandIconClass('sdc-card-brand-icon-maeestro');
						break;
					case 'elo':
						this.setCardBrandIconClass('sdc-card-brand-icon-elo');
						break;
				}
			},

			getSdcFieldFrame: function(name) {
				switch(name)
				{
					case "cardNumber":
						return $('#sdc-card-number-frame');
					case "nameOnCard":
						return $('#sdc-card-name-frame');
					case "securityCode":
						return $('#sdc-security-code-frame');
					case "expirationMonth":
						return $('#sdc-exp-month-frame');
					case "expirationYear":
						return $('#sdc-exp-year-frame');
				}

				return undefined;
			},

			getInvalidFieldMessages: function() {
				return this.paymentConfig['invalidFields'];
			},

			getSdcFieldInvalidMessageContainer: function(name) {
				switch(name)
				{
					case "cardNumber":
						return $('#sdc-card-number-invalid-message');
					case "nameOnCard":
						return $('#sdc-card-name-invalid-message');
					case "securityCode":
						return $('#sdc-security-code-invalid-message');
					case "expirationMonth":
						return $('#sdc-exp-month-invalid-message');
					case "expirationYear":
						return $('#sdc-exp-year-invalid-message');
				}

				return undefined;

			},

			getSdcInvalidFieldMessageText: function(name) {
				switch(name)
				{
					case "cardNumber":
						return this.getInvalidFieldMessages()["cardNumber"];
					case "nameOnCard":
						return this.getInvalidFieldMessages()["nameOnCard"];
					case "securityCode":
						return this.getInvalidFieldMessages()["securityCode"];
					case "expirationMonth":
						return this.getInvalidFieldMessages()["expirationMonth"];
					case "expirationYear":
						return this.getInvalidFieldMessages()["expirationYear"];
				}

				return "";

			},

			fieldValidityHandler: function(data) {
				let frame = this.getSdcFieldFrame(data["field"]);
				let mess = this.getSdcFieldInvalidMessageContainer(data["field"]);

				if (data?.field === "cardNumber") {
					this.hideSurchargeConfirmStep();
				}

				if (typeof(frame) !== "undefined")
				{
					if (data["isValid"] === true)
					{
						frame.removeClass('sdc-error-field');
						frame.addClass('sdc-valid-field');
						mess.addClass('sdc-hidden');
					} else if (data["shouldShowError"] === true)
					{
						mess.text(this.getSdcInvalidFieldMessageText(data["field"]));
						frame.removeClass('sdc-valid-field');
						frame.addClass('sdc-error-field');
						mess.removeClass('sdc-hidden');
					} else
					{
						frame.removeClass('sdc-valid-field');
						frame.removeClass('sdc-error-field');
						mess.addClass('sdc-hidden');
					}
				}
			},

			fieldFocusHandler: function(data) {
				let frame = this.getSdcFieldFrame(data);

				if(typeof(frame) !== "undefined") {
					if(frame[0].contains(document.activeElement) === true) {
						frame.addClass('sdc-focused-field');
					} else
					{
						frame.removeClass('sdc-focused-field');
					}
				}
			},


			getNumberUnmaskButton: function() {
				return $('#sdc-unmask-number');
			},

			getSecurityUnmaskButton: function() {
				return $('#sdc-unmask-security');
			},

			getNumberMaskButton: function() {
				return $('#sdc-mask-number');
			},

			getSecurityMaskButton: function() {
				return $('#sdc-mask-security');
			},

			unmaskCardNumber: function() {
				chIframe.unmask('cardNumber');
				let unmaskButton = this.getNumberUnmaskButton();
				let maskButton = this.getNumberMaskButton();
				unmaskButton.addClass('sdc-hidden');
				maskButton.removeClass('sdc-hidden');
			},

			maskCardNumber: function() {
				chIframe.mask('cardNumber');
				let unmaskButton = this.getNumberUnmaskButton();
				let maskButton = this.getNumberMaskButton();
				unmaskButton.removeClass('sdc-hidden');
				maskButton.addClass('sdc-hidden');
			},

			unmaskSecurity: function() {
				chIframe.unmask('securityCode');
				let unmaskButton = this.getSecurityUnmaskButton();
				let maskButton = this.getSecurityMaskButton();
				unmaskButton.addClass('sdc-hidden');
				maskButton.removeClass('sdc-hidden');
			},

			maskSecurity: function() {
				chIframe.mask('securityCode');
				let unmaskButton = this.getSecurityUnmaskButton();
				let maskButton = this.getSecurityMaskButton();
				unmaskButton.removeClass('sdc-hidden');
				maskButton.addClass('sdc-hidden');
			},

			isSurchargeEnabled: function() {
				return !!this.paymentConfig["surchargeEnabled"];
			},

	getGrandTotalPriceElement: function() {
		return $('#grand-total').find('.price').first();
	},

	parseCurrencyAmount: function(text) {
		return parseFloat((text || '').replace(/[^0-9.]/g, '')) || 0;
	},

	buildSessionPayload: function() {
		const total = this.parseCurrencyAmount(this.getGrandTotalPriceElement().text());

		const payload = {
			storeUrl: this.paymentConfig.storeUrl,
			amount: { total: total, currency: this.paymentConfig['currencyCode'] ?? 'USD' },
			country: 'US'
		};

		const full = this.buildAdminBillingAddress();
		if (full) payload.billingAddress = full.address;
		return payload;
	},

		buildAdminBillingAddress: function() {
			try {
				const $regionSelect = $('#order-billing_address_region_id');
				const stateOrProvince = ($regionSelect.length && $regionSelect.val())
					? $regionSelect.find('option:selected').text()
					: $('#order-billing_address_region').val();

				return {
					firstName: $('#order-billing_address_firstname').val(),
					lastName: $('#order-billing_address_lastname').val(),
					address: {
						street: $('#order-billing_address_street0').val(),
						houseNumberOrName: $('#order-billing_address_street1').val(),
						city: $('#order-billing_address_city').val(),
						stateOrProvince: stateOrProvince,
						postalCode: $('#order-billing_address_postcode').val(),
						country: $('#order-billing_address_country_id').val()
					}
				};
			} catch (e) {
				return undefined;
			}
		},

		_captureCard: function() {
			chIframe.submitIframeForm(
				() => { this.placeOrder(); },
				() => { this.iframeRunFailure(); }
			);
		},

		fetchAndShowSurcharge: async function() {
			if (this.surchargeLoading) { return; }
			this.surchargeLoading = true;
			this.dismissAdminLoader();

			try {
				const surchargeDetails = await chIframe.getSurchargeEstimate(this.buildAdminBillingAddress());

				if (surchargeDetails?.error?.length) {
					this.showError('Unable to retrieve surcharge estimate: ' + surchargeDetails.error[0].message);
					return;
				}

				const surchargeAmount = parseFloat(surchargeDetails?.amountComponent?.surcharge) || 0;

				if (surchargeAmount > 0) {
					this.showSurchargeConfirmStep(surchargeAmount);
				} else {
					// Card is not surcharge-eligible — proceed straight to capture.
					this.beginAsyncFlow();
					this._captureCard();
				}
			} catch (e) {
				this.showError('Unable to retrieve surcharge estimate. Please try again.');
			} finally {
				this.surchargeLoading = false;
			}
		},

			showSurchargeConfirmStep: function(amount) {
				const $submitBtn = $('#submit_order_top_button');
				if ($submitBtn.length && !$('#fiserv-surcharge-confirm-btn').length) {
					const $confirmBtn = $('<button>', {
						id: 'fiserv-surcharge-confirm-btn',
						type: 'button',
						'class': $submitBtn.attr('class'),
						title: 'Confirm & Place Order'
					}).html('<span>Confirm & Place Order</span>');

				$confirmBtn.on('click', () => {
					this.beginAsyncFlow();
					this._captureCard();
				});

					$submitBtn.after($confirmBtn);
					$submitBtn.hide();
				}
				this.getOtherSubmitButtons().hide();

				this.injectSurchargeRow(amount);
				this.surchargeShown = true;
			},

		hideSurchargeConfirmStep: function() {
			$('#fiserv-surcharge-confirm-btn').remove();
			$('#submit_order_top_button').show();
			this.getOtherSubmitButtons().show();
			this.removeSurchargeRow();
			this.surchargeLoading = false;
			this.surchargeShown = false;
		},

			getOtherSubmitButtons: function() {
				return $('#submit_order_bottom_button, .order-totals-actions .actions button');
			},

		injectSurchargeRow: function(amount) {
				this.removeSurchargeRow();

				const $grandTotalRow = $('#grand-total');
				if (!$grandTotalRow.length) {
					return;
				}

			const formattedAmount = '$' + amount.toFixed(2);

			const $row = $(
				'<tr id="fiserv-admin-surcharge-total-row" class="row-totals">' +
					'<td class="admin__total-mark" colspan="1">Surcharge</td>' +
					'<td class="admin__total-amount">' +
						'<span class="price">' + formattedAmount + '</span>' +
					'</td>' +
				'</tr>'
			);
			$grandTotalRow.before($row);

			const $grandTotalPrice = this.getGrandTotalPriceElement();
			if ($grandTotalPrice.length) {
				if (!this._originalGrandTotalText) {
					this._originalGrandTotalText = $grandTotalPrice.text();
				}
				const base = this.parseCurrencyAmount(this._originalGrandTotalText);
				$grandTotalPrice.text('$' + (base + amount).toFixed(2));
			}
		},

		removeSurchargeRow: function() {
			$('#fiserv-admin-surcharge-total-row').remove();
			if (this._originalGrandTotalText) {
				this.getGrandTotalPriceElement().text(this._originalGrandTotalText);
				this._originalGrandTotalText = undefined;
			}
		}
		});
	}
);
