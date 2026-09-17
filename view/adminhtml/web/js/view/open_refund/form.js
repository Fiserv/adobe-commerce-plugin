/**
 * Open Refund — Admin card form SDK integration.
 *
 * Reuses existing ch-adapter (same module used by admin order create form).
 * Handles:
 *  - Customer typeahead search + selection
 *  - Dynamic vault token dropdown per selected customer
 *  - Hosted-fields (SDK) card capture + form submit intercept
 *  - Hosted-fields fallback notification (Jira AC: notify merchant, provide retry path)
 */
define([
    'uiComponent',
    'Fiserv_Payments/js/action/create-commercehub-session',
    'Fiserv_Payments/js/ch-adapter',
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/alert',
    'domReady!',
], function (
    Component,
    chSession,
    chIframe,
    $,
    $t,
    alert,
) {
    'use strict';

    const SEARCH_DELAY_MS = 300;

    /** Merchant-facing message shown when hosted fields cannot load (Jira: Hosted Fields Fallback AC) */
    const FALLBACK_MSG = $t(
        'The card entry form could not be loaded. This may be caused by a network issue or the Commerce Hub service being temporarily unavailable.<br><br><strong>Options:</strong><ul><li>Refresh the page and try again.</li><li>If the problem persists, process the refund manually in the Commerce Hub portal.</li></ul>',
    );

    return Component.extend({

        defaults: {
            formId:           'open-refund-form',
            sourceSelectId:   'source_type',
            sessionInputId:   'or_payment_session',
            tokenizedTokenInputId: 'or_tokenized_payment_token',
            submitBtnId:      'or-submit-btn',
            cardSectionId:    'or-card-section',
            iframeValid:      false,
            paymentInitiated: false,
            paymentSessionData: null,
		submitInProgress: false,
		allowNativeSubmit: false,
            customerSearchUrl:  '',
            customerTokensUrl:  '',
		tokenizeSessionUrl: '',
		customerSearchXhr:  null,
		highlightedCustomerIndex: -1,
		customerResults: []
	},

        initialize: function (config) {
            this._super();

            if (!config.paymentConfig) {
                console.error('[OpenRefund] paymentConfig not provided — SDK will not load.');
                return;
            }

            this.paymentConfig      = config.paymentConfig;
            this.customerSearchUrl  = config.customerSearchUrl  || '';
            this.customerTokensUrl  = config.customerTokensUrl  || '';
			this.tokenizeSessionUrl = config.tokenizeSessionUrl || '';

            this._bindCustomerSearch();
            this._bindSourceToggle();
            this._bindFormSubmit();

            if (this._isHostedFieldsSourceSelected()) {
                this._initSdk();
            }
        },

        // ── Customer search ───────────────────────────────────────────────────

        _bindCustomerSearch: function () {
            let timer;
		const $searchInput = $('#or-customer-search');

            $searchInput.on('input', (e) => {
                clearTimeout(timer);
                const q = $(e.currentTarget).val().trim();
		    if (q.length > 0 && $('#customer_id').val()) {
			    // Keep submitted customer_id aligned with what is visibly being searched.
			     this._clearCustomer();
			}
                if (q.length < 2) {
                    this._hideCustomerResults();
                    return;
                }
        this._showCustomerMessage($t('Searching customers...'));
                timer = setTimeout(() => {
                    this._searchCustomers(q);
                }, SEARCH_DELAY_MS);
            });
		$searchInput.on('keydown', (e) => {
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				this._moveCustomerHighlight(1);
				return;
			}

			if (e.key === 'ArrowUp') {
				e.preventDefault();
        this._moveCustomerHighlight(-1);
				return;
			}

			if (e.key === 'Enter') {
				if ($('#or-customer-results:visible').length) {
					e.preventDefault();
          this._selectHighlightedCustomer();
				}
			}
		});

            // Close results when clicking outside
            $(document).on('click.orCustomer', (e) => {
                if (!$(e.target).closest('#or-customer-search-wrap').length) {
                    this._hideCustomerResults();
                }
            });
        },

        _searchCustomers: function (query) {
            if (!this.customerSearchUrl) { return; }

    if (this.customerSearchXhr) {
      this.customerSearchXhr.abort();
		}

  this.customerSearchXhr = $.ajax({
                url:      this.customerSearchUrl,
                type:     'GET',
                dataType: 'json',
                data:     { query: query },
    success:  (data) => {
      this._renderCustomerResults(data);
		},
    error:    (_xhr, textStatus) => {
			if (textStatus !== 'abort') {
        this._showCustomerMessage($t('Unable to search customers right now.'));
			}
		},
    complete: () => {
      this.customerSearchXhr = null;
		}
            });
        },

        _renderCustomerResults: function (customers) {
            const $list = $('#or-customer-results');
            $list.empty();

    this.customerResults = Array.isArray(customers) ? customers : [];
    this.highlightedCustomerIndex = this.customerResults.length ? 0 : -1;

    if (this.customerResults.length === 0) {
      this._showCustomerMessage($t('No customers found.'));
                return;
            }

            $.each(this.customerResults, (i, c) => {
                const $li = $('<li class="or-result-item" tabindex="0"></li>')
                    .data('customer', c)
		    .attr('data-index', i)
                    .html('<strong>' + this._esc(c.name) + '</strong> <span class="or-email">' + this._esc(c.email) + '</span>');

        if (i === this.highlightedCustomerIndex) {
			    $li.addClass('or-result-active');
		    }

                $li.on('click keypress', (e) => {
                    if (e.type === 'click' || e.which === 13) {
          this._selectCustomer(c);
                    }
                });

                $list.append($li);
            });

            $list.show();
        },

        _selectCustomer: function (customer) {
            // Set the hidden customer_id
            $('#customer_id').val(customer.id);
            // Show the selection badge
            $('#or-customer-search').val('');
            $('#or-selected-customer').html(
                '<span class="or-customer-badge">' +
                this._esc(customer.name) + ' &lt;' + this._esc(customer.email) + '&gt;' +
                ' <a href="#" id="or-clear-customer" title="' + this._esc($t('Change customer')) + '">✕</a>' +
                '</span>'
            ).show();

            this._hideCustomerResults();
            this._fetchCustomerTokens(customer.id);

            $('#or-clear-customer').on('click', (e) => {
                e.preventDefault();
                this._clearCustomer();
            });
        },

        _clearCustomer: function () {
            $('#customer_id').val('');
            $('#or-selected-customer').hide().empty();
            this._clearTokenDropdown();
        },

        _hideCustomerResults: function () {
		this.customerResults = [];
		this.highlightedCustomerIndex = -1;
            $('#or-customer-results').hide().empty();
        },

	    _showCustomerMessage: function (message) {
        const $list = $('#or-customer-results');
		    this.customerResults = [];
		    this.highlightedCustomerIndex = -1;
		    $list.empty().append('<li class="or-no-results">' + this._esc(message) + '</li>').show();
	    },

	    _moveCustomerHighlight: function (step) {
        const lastIndex = this.customerResults.length - 1;

		    if (lastIndex < 0) {
			    return;
		    }

		    this.highlightedCustomerIndex += step;
		    if (this.highlightedCustomerIndex > lastIndex) {
			    this.highlightedCustomerIndex = 0;
		    }
		    if (this.highlightedCustomerIndex < 0) {
			    this.highlightedCustomerIndex = lastIndex;
		    }

		    $('#or-customer-results .or-result-item').removeClass('or-result-active');
		    $('#or-customer-results .or-result-item[data-index="' + this.highlightedCustomerIndex + '"]')
		    .addClass('or-result-active');
	    },

	    _selectHighlightedCustomer: function () {
		    if (this.highlightedCustomerIndex < 0 || !this.customerResults[this.highlightedCustomerIndex]) {
			    return;
		    }

		    this._selectCustomer(this.customerResults[this.highlightedCustomerIndex]);
	    },

        // ── Token dropdown ────────────────────────────────────────────────────

        _fetchCustomerTokens: function (customerId) {
            if (!this.customerTokensUrl || !customerId) { return; }

      const hashSelect = $('#payment_token_hash');
      const loadingIndicator = $('#tokenized-loading');
      const noneMessage = $('#tokenized-none');

            hashSelect.off('change.tokenized').hide().empty().val('');
	    noneMessage.hide();
	    loadingIndicator.show();

            $.ajax({
                url:      this.customerTokensUrl,
                type:     'GET',
                dataType: 'json',
                data:     {
					customer_id: customerId,
					source_type: $('#' + this.sourceSelectId).val() || ''
				},
                success:  (tokens) => {
                    loadingIndicator.hide();
			if (tokens && typeof tokens === 'object' && !Array.isArray(tokens) && tokens.error) {
				noneMessage.text(tokens.error).show();
				return;
			}
                    if (!Array.isArray(tokens) || tokens.length === 0) {
                        noneMessage.text($t('No stored cards found for this customer.')).show();
                        return;
                    }
			if (!hashSelect.length || !hashSelect.is('select')) {
        noneMessage.text($t('Stored card field is unavailable in this form.')).show();
				return;
			}
      hashSelect.append($('<option>', { value: '', text: $t('-- Select a stored card --') }));
                    $.each(tokens, (_i, t) => {
          const label = t.label || $t('Stored card');
          const hash = t.public_hash || '';
			    if (!hash) {
				    return;
			    }

			    hashSelect.append(
            '<option value="' + this._esc(hash) + '">' + this._esc(label) + '</option>'
			    );
                    });
			if (hashSelect.children().length <= 1) {
        noneMessage.text($t('No valid stored cards available for this customer.')).show();
				hashSelect.hide().empty();
				return;
			}
			hashSelect.off('change.tokenized').on('change.tokenized', (e) => {
		if ($(e.currentTarget).val()) {
          noneMessage.hide();
        }
			});
			hashSelect.show();
                },
                error: () => {
                    loadingIndicator.hide();
          noneMessage.text($t('Failed to load stored cards. Please retry after selecting a customer.')).show();
                }
            });
        },

        _clearTokenDropdown: function () {
            $('#payment_token_hash').off('change.tokenized').hide().empty().val('');
	    $('#tokenized-loading').hide();
	    $('#tokenized-none').hide();
        },

        // ── Source type toggle ────────────────────────────────────────────────

        _bindSourceToggle: function () {
            $('#' + this.sourceSelectId).on('change', () => {
                $('#' + this.sessionInputId).val('');
                $('#' + this.tokenizedTokenInputId).val('');
                if (this._isHostedFieldsSourceSelected()) {
                    this._destroyIframe();
                    this._initSdk();
                } else {
                    this._destroyIframe();
                    this._setSubmitEnabled(true);
                }
            });
        },

        _isCardSourceSelected: function () {
            return $('#' + this.sourceSelectId).val() === 'HOSTED_FIELDS';
        },

        _isHostedFieldsSourceSelected: function () {
            return this._isCardSourceSelected();
        },

        _getActivePaymentConfig: function () {
            return this.paymentConfig;
        },

        // ── SDK init ──────────────────────────────────────────────────────────

        _initSdk: async function () {
            if (this.paymentInitiated) { return; }

            const activeConfig = this._getActivePaymentConfig();
            if (!activeConfig) {
                this._showError($t('Payment form configuration is unavailable for the selected source.'));
                return;
            }

            this._setSubmitEnabled(false);
            this._beginAsyncFlow();

            chIframe.initialize(
                activeConfig,
                () => { this._onIframeReady(); },
                (valid) => { this._onIframeValid(valid); },
                (brand) => { this._onCardBrand(brand); },
                (data) => { this._onFieldValidity(data); },
                (data) => { this._onFieldFocus(data); }
            );

            try {
                const credsResponse = await chSession({ storeUrl: activeConfig.storeUrl });
                const paymentSessionData = credsResponse && credsResponse.ch_credentials ? credsResponse.ch_credentials : null;

                if (!paymentSessionData) {
                    throw new Error($t('Unable to start payment session.'));
                }

                const contextId = (window.crypto && typeof window.crypto.randomUUID === 'function')
                    ? window.crypto.randomUUID()
                    : undefined;

                await chIframe.initSdk(activeConfig, paymentSessionData, contextId);
                this.paymentSessionData = paymentSessionData;

                await new Promise((resolve, reject) => {
                    chIframe.instantiateIframe(resolve, reject);
                });

                this.paymentInitiated = true;
            } catch (err) {
                this._endAsyncFlow();
                // AC: Hosted Fields Fallback — notify merchant + provide retry/manual path
                this._showFallback(err);
            }
        },

        _destroyIframe: function () {
            chIframe.destroyIframe();
            this.paymentInitiated = false;
            this.iframeValid      = false;
		this.paymentSessionData = null;
		this.submitInProgress = false;
		this.allowNativeSubmit = false;
        },

        // ── SDK callbacks ─────────────────────────────────────────────────────

        _onIframeReady: function () {
            this._endAsyncFlow();
        },

        _onIframeValid: function (valid) {
            this.iframeValid = valid;
            if (this._isHostedFieldsSourceSelected()) {
                this._setSubmitEnabled(valid);
            }
        },

        _onCardBrand: function (brand) {
            const brandMap = {
                'visa': 'sdc-card-brand-icon-visa', 'mastercard': 'sdc-card-brand-icon-mastercard',
                'american-express': 'sdc-card-brand-icon-amex', 'diners-club': 'sdc-card-brand-icon-diners',
                'discover': 'sdc-card-brand-icon-discover', 'jcb': 'sdc-card-brand-icon-jcb',
                'unionpay': 'sdc-card-brand-icon-union'
            };
            const icon = $('#or-sdc-card-brand-icon');
            icon.removeClass().addClass('sdc-card-brand-icon');
            if (brand && brandMap[brand]) { icon.addClass(brandMap[brand]); }
        },

        _onFieldValidity: function (data) {
            const frameMap = {
                cardNumber: '#or-sdc-card-number-frame', nameOnCard: '#or-sdc-card-name-frame',
                securityCode: '#or-sdc-security-code-frame', expirationMonth: '#or-sdc-exp-month-frame',
                expirationYear: '#or-sdc-exp-year-frame'
            };
            const msgMap = {
                cardNumber: '#or-sdc-card-number-msg', nameOnCard: '#or-sdc-card-name-msg',
                securityCode: '#or-sdc-security-code-msg', expirationMonth: '#or-sdc-exp-month-msg',
                expirationYear: '#or-sdc-exp-year-msg'
            };
            const messages = this.paymentConfig['invalidFields'] || {};

            const field  = data['field'];
            const $frame = $(frameMap[field]);
            const $msg   = $(msgMap[field]);
            if (!$frame.length) { return; }
            if (data['isValid'] === true) {
                $frame.removeClass('sdc-error-field').addClass('sdc-valid-field');
                $msg.addClass('sdc-hidden');
            } else if (data['shouldShowError'] === true) {
                $msg.text(messages[field] || '');
                $frame.removeClass('sdc-valid-field').addClass('sdc-error-field');
                $msg.removeClass('sdc-hidden');
            } else {
                $frame.removeClass('sdc-valid-field sdc-error-field');
                $msg.addClass('sdc-hidden');
            }
        },

        _onFieldFocus: function (data) {
            const frameMap = {
                cardNumber: '#or-sdc-card-number-frame', nameOnCard: '#or-sdc-card-name-frame',
                securityCode: '#or-sdc-security-code-frame', expirationMonth: '#or-sdc-exp-month-frame',
                expirationYear: '#or-sdc-exp-year-frame'
            };
            const $frame = $(frameMap[data]);
            if (!$frame.length) { return; }
            if ($frame[0].contains(document.activeElement)) {
                $frame.addClass('sdc-focused-field');
            } else {
                $frame.removeClass('sdc-focused-field');
            }
        },

        // ── Form submit intercept ─────────────────────────────────────────────

        _bindFormSubmit: function () {
		    $('#' + this.formId).on('submit', (e) => {
		if (this.allowNativeSubmit) {
			this.allowNativeSubmit = false;
			return;
		}
		// Validate that a customer is selected
		const customerId = $('#customer_id').val();
		if (!customerId) {
			e.preventDefault();
      this._showError($t('Please select a customer before submitting.'));
			return;
		}
                if (!this._isHostedFieldsSourceSelected()) {
                    // TOKEN path — require a selected stored token
                    const hash = $('#payment_token_hash').val();
                    if (!hash) {
                        e.preventDefault();
                        this._showError($t('Please select a stored payment card.'));
                    }
                    return;
                }
                // HOSTED_FIELDS path
                e.preventDefault();
		    if (this.submitInProgress) {
			    return;
		    }
                if (!this.iframeValid) {
                    this._showError($t('Please complete all card fields before submitting.'));
                    return;
                }
		    this.submitInProgress = true;
		    $('#' + this.sessionInputId).val('');
		    $('#' + this.tokenizedTokenInputId).val('');
                this._setSubmitEnabled(false);
                this._beginAsyncFlow();
                chIframe.submitIframeForm(
                    () => { this._onSubmitSuccess(); },
                    () => { this._onSubmitFailure(); }
                );
            });
        },

        _onSubmitSuccess: function (sessionId) {
            sessionId = sessionId || (this.paymentSessionData && this.paymentSessionData.sessionId) || '';
            if (!sessionId) {
                this._onSubmitFailure();
                this._showError($t('Payment session not established. Please try again.'));
                return;
            }
            $('#' + this.sessionInputId).val(sessionId);
    if (this._isCardSourceSelected()) {
      this._tokenizeCardSessionAndSubmit(sessionId);
      return;
    }
    this.submitInProgress = false;
            this._endAsyncFlow();
    this._submitNativeForm();
        },

        _onSubmitFailure: function () {
		this.submitInProgress = false;
            this._endAsyncFlow();
            this._setSubmitEnabled(true);
            chIframe.resetIframe();
            this._showError($t('Card capture failed. Please check card details and try again.'));
        },

    _tokenizeCardSessionAndSubmit: function (sessionId) {
      this._tokenizeCardSession(sessionId)
        .done((response) => {
          const tokenData = response && response.token_data ? String(response.token_data) : '';
          if (!tokenData) {
            this._onTokenizeFailure($t('Tokenized card data was not returned. Please retry.'));
            return;
          }
          $('#' + this.tokenizedTokenInputId).val(tokenData);
          this.submitInProgress = false;
          this._endAsyncFlow();
          this._submitNativeForm();
        })
        .fail((xhr) => {
          let message = $t('Card tokenization failed. Please retry.');
          if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
          }
          this._onTokenizeFailure(message);
        });
    },

    _tokenizeCardSession: function (sessionId) {
      if (!this.tokenizeSessionUrl) {
        return $.Deferred().reject({
          responseJSON: { message: $t('Card tokenization URL is not configured.') }
        }).promise();
      }

      return $.ajax({
        url: this.tokenizeSessionUrl,
        type: 'POST',
        dataType: 'json',
        data: {
          session_id: sessionId,
          form_key: $('input[name="form_key"]').val() || ''
        }
      });
    },

    _onTokenizeFailure: function (message) {
      this.submitInProgress = false;
      this._endAsyncFlow();
      this._setSubmitEnabled(true);
      this._showError(message);
    },

    _submitNativeForm: function () {
      this.allowNativeSubmit = true;
      const form = document.getElementById(this.formId);
      if (form && typeof form.submit === 'function') {
        form.submit();
      } else {
        this.allowNativeSubmit = false;
        this._showError($t('Unable to submit the form. Please try again.'));
      }
    },

        // ── Mask / unmask ─────────────────────────────────────────────────────

        unmaskCardNumber: function () {
            chIframe.unmask('cardNumber');
            $('#or-sdc-unmask-number').addClass('sdc-hidden');
            $('#or-sdc-mask-number').removeClass('sdc-hidden');
        },
        maskCardNumber: function () {
            chIframe.mask('cardNumber');
            $('#or-sdc-unmask-number').removeClass('sdc-hidden');
            $('#or-sdc-mask-number').addClass('sdc-hidden');
        },
        unmaskSecurity: function () {
            chIframe.unmask('securityCode');
            $('#or-sdc-unmask-security').addClass('sdc-hidden');
            $('#or-sdc-mask-security').removeClass('sdc-hidden');
        },
        maskSecurity: function () {
            chIframe.mask('securityCode');
            $('#or-sdc-unmask-security').removeClass('sdc-hidden');
            $('#or-sdc-mask-security').addClass('sdc-hidden');
        },

        // ── Helpers ───────────────────────────────────────────────────────────

        _setSubmitEnabled: function (enabled) {
            $('#' + this.submitBtnId)
                .prop('disabled', !enabled)
                .toggleClass('or-ready', !!enabled);
        },
        _beginAsyncFlow: function () { $('body').trigger('processStart'); },
        _endAsyncFlow:   function () { $('body').trigger('processStop'); },
        /** Generic error modal — uses imported alert directly, no inner require() */
        _showError: function (msg) { alert({ content: msg }); },
        /**
         * Hosted-fields fallback (Jira AC).
         * Shows an inline banner inside the card section AND a modal with retry/manual instructions.
         */
        _showFallback: function (err) {
            console.error('[OpenRefund] Hosted fields failed to load:', err);
            const $section = $('#' + this.cardSectionId);
            $section.find('#or-hosted-fields-error').remove();
            $('<div class="message message-error error" id="or-hosted-fields-error"><div>' +
              FALLBACK_MSG + '</div></div>').prependTo($section);
            alert({ content: FALLBACK_MSG });
        },
        _esc: function (str) { return $('<div>').text(str).html(); }
    });
});
