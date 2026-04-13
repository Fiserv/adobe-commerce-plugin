define(['jquery', 'mage/translate'], function ($, $t) {
    'use strict';

    return function (config) {
        config = config || {};

        var cancelUrl = config.cancelUrl || '';
        var updateUrl = config.updateUrl || '';
        var dataUrl = config.dataUrl || '';


        var MSG = {
            confirmCancelLatest: config.msgConfirmCancelLatest || $t('Cancel the latest renewal transaction? Recurring will continue.'),
            confirmCancelChain: config.msgConfirmCancelChain || $t('Cancel this subscription? Future renewals will stop.'),
            cancelling: config.msgCancelling || $t('Cancelling...'),
            cancelFailedPrefixLatest: config.msgCancelFailedPrefix || ($t('Failed to cancel:') + ' '),
            cancelFailedPrefixChain: config.msgCancelFailedPrefix2 || ($t('Failed to cancel subscription:') + ' '),

            selectCard: config.msgSelectCard || $t('Please select a saved card.'),
            saving: config.msgSaving || $t('Saving...'),
            updated: config.msgUpdated || $t('Updated. Future renewals will use this card.'),
            vaultLoadFailed: config.msgVaultLoadFailed || $t('Unable to load saved cards.')
        };


        var searchInputId = config.searchInputId || 'searchInput';
        var rowsPerPageId = config.rowsPerPageId || 'rowsPerPage';
        var tableBodyId = config.tableBodyId || 'tableBody';
        var noMatchesId = config.noMatchesId || 'noMatches';
        var pagerId = config.pagerId || 'paginationControls';
        var pageInfoId = config.pageInfoId || 'paginationInfo';

        function getFormKey() {
            if (typeof window.FORM_KEY !== 'undefined') { return window.FORM_KEY; }
            var fk = document.querySelector('input[name="form_key"]');
            return fk ? fk.value : '';
        }

        function safeJson(res) {
            return res.text().then(function (txt) {
                try { return JSON.parse(txt || '{}'); }
                catch (e) { return { success: false, error: 'Invalid JSON response' }; }
            });
        }

        function postForm(url, payload) {
            var params = new URLSearchParams();
            Object.keys(payload || {}).forEach(function (k) {
                params.append(k, payload[k] == null ? '' : String(payload[k]));
            });

            return fetch(url, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            }).then(function (res) {
                return safeJson(res).then(function (json) {
                    if (!res.ok) {
                        var msg = (json && (json.error || json.message))
                            ? (json.error || json.message)
                            : ('HTTP ' + res.status);
                        throw new Error(msg);
                    }
                    return json;
                });
            });
        }

        function postCancel(subscriptionId) {
            if (!cancelUrl) return Promise.reject(new Error('missing cancelUrl'));
            if (!subscriptionId) return Promise.reject(new Error('missing subscription_id'));

            return postForm(cancelUrl, {
                subscription_id: subscriptionId,
                form_key: getFormKey() || ''
            }).then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && (json.error || json.message)) ? (json.error || json.message) : 'Cancel failed');
                }
                return json;
            });
        }

        // UPDATED: include customer_id for Adminhtml UpdatePayment controller
        function postUpdatePayment(subscriptionId, publicHash) {
            if (!updateUrl) return Promise.reject(new Error('missing updateUrl'));
            if (!subscriptionId) return Promise.reject(new Error('missing subscription_id'));
            if (!publicHash) return Promise.reject(new Error('missing public_hash'));

            var selectEl= document.querySelector('.subscription-payment-select[data-subscription-id="' + subscriptionId + '"]');
            var customerId= selectEl ? (selectEl.getAttribute('data-customer-id') || '') : '';

            return postForm(updateUrl, {
                subscription_id: subscriptionId,
                public_hash: publicHash,
                customer_id: customerId,
                form_key: getFormKey() || ''
            }).then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && (json.error || json.message)) ? (json.error || json.message) : 'Update failed');
                }
                return json;
            });
        }

        function setFeedback(subId, msg, kind) {
            var el= document.querySelector('.subscription-update-feedback[data-subscription-id="' + subId + '"]');
            if (!el) return;
            el.textContent = msg || '';
            el.style.color = (kind === 'error') ? '#b00020' : (kind === 'success' ? '#0a7a0a' : '#555');
        }

        function setPaymentControlsDisabled(subId, disabled) {
            var select = document.querySelector('.subscription-payment-select[data-subscription-id="' + subId + '"]');
            var toggleBtn = document.querySelector('.subscription-update-btn[data-subscription-id="' + subId + '"]');
            var saveBtn = document.querySelector('.subscription-save-btn[data-subscription-id="' + subId + '"]');
            var cancelBtn = document.querySelector('.subscription-cancel-edit-btn[data-subscription-id="' + subId + '"]');

            if (select) select.disabled = !!disabled;
            if (toggleBtn) toggleBtn.disabled = !!disabled;
            if (saveBtn) saveBtn.disabled = !!disabled;
            if (cancelBtn) cancelBtn.disabled = !!disabled;
        }

        function togglePaymentEditor(subId, open) {
            var editor = document.querySelector('.subscription-payment-editor[data-subscription-id="' + subId + '"]');
            if (!editor) return;

            var shouldOpen = (typeof open === 'boolean')
                ? open
                : (editor.style.display === 'none' || editor.style.display === '');

            editor.style.display = shouldOpen ? 'flex' : 'none';
        }

        function populateVaultSelectFromData(selectEl, tokens) {
            if (!selectEl) return;

            var curValue= selectEl.value || '';
            while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);

            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = $t('Select a saved card');
            selectEl.appendChild(placeholder);

            if (Array.isArray(tokens)) {
                tokens.forEach(function (t) {
                    if (!t || !t.public_hash) return;
                    var opt = document.createElement('option');
                    opt.value = String(t.public_hash);
                    opt.textContent = String(t.label || t.public_hash);
                    if (curValue && curValue === opt.value) opt.selected = true;
                    selectEl.appendChild(opt);
                });
            }

            selectEl.setAttribute('data-vault-loaded', 'true');
        }


        function onStatusChange(ev) {
            var sel = ev.target;
            if (!sel || !sel.classList || !sel.classList.contains('subscription-status-select')) return;
            if (sel.value !== 'cancelled') return;

            var subId = sel.getAttribute('data-subscription-id');
            if (!subId) return;

            if (!confirm(MSG.confirmCancelLatest)) {
                sel.value = 'active';
                return;
            }

            var allSelects = Array.prototype.slice.call(document.querySelectorAll('.subscription-status-select'));
            allSelects.forEach(function (s) { s.disabled = true; });

            var originalParent = sel.parentNode;
            var savingLabel = document.createElement('span');
            savingLabel.className = 'subscription-status-saving';
            savingLabel.textContent = MSG.cancelling;

            try { originalParent.replaceChild(savingLabel, sel); } catch (e) {}

            postCancel(subId).then(function () {
                window.location.reload();
            }).catch(function (err) {
                alert(MSG.cancelFailedPrefixLatest + ((err && err.message) ? err.message : String(err)));

                var restoreSelect = document.createElement('select');
                restoreSelect.className = 'subscription-status-select';
                restoreSelect.setAttribute('data-subscription-id', subId);
                restoreSelect.setAttribute('aria-label', $t('Subscription status'));

                var optActive = document.createElement('option');
                optActive.value = 'active';
                optActive.text = $t('Active');
                optActive.selected = true;

                var optCancel = document.createElement('option');
                optCancel.value = 'cancelled';
                optCancel.text = $t('Cancel');

                restoreSelect.appendChild(optActive);
                restoreSelect.appendChild(optCancel);

                try { originalParent.replaceChild(restoreSelect, savingLabel); } catch (ignore) {}
            }).finally(function () {
                var all2 = Array.prototype.slice.call(document.querySelectorAll('.subscription-status-select'));
                all2.forEach(function (s) { s.disabled = false; });
            });
        }

        function onCancelChainClick(ev) {
            var btn = ev.target;
            if (!btn || !btn.classList || !btn.classList.contains('subscription-cancel-chain-btn')) return;

            var rootId = btn.getAttribute('data-root-subscription-id');
            if (!rootId) return;

            if (!confirm(MSG.confirmCancelChain)) return;

            btn.disabled = true;

            postCancel(rootId).then(function () {
                window.location.reload();
            }).catch(function (err) {
                btn.disabled = false;
                alert(MSG.cancelFailedPrefixChain + ((err && err.message) ? err.message : String(err)));
            });
        }

        function onCancelLatestClick(ev) {
            var btn = ev.target;
            if (!btn || !btn.classList || !btn.classList.contains('subscription-cancel-latest-btn')) return;

            var subId = btn.getAttribute('data-subscription-id');
            if (!subId) return;

            if (!confirm(MSG.confirmCancelLatest)) return;

            btn.disabled = true;
            btn.textContent = MSG.cancelling;

            postCancel(subId).then(function () {
                window.location.reload();
            }).catch(function (err) {
                btn.disabled = false;
                btn.textContent = $t('Void transaction');
                alert(MSG.cancelFailedPrefixLatest + ((err && err.message) ? err.message : String(err)));
            });
        }

        function onUpdateCardClick(ev) {
            var btn = ev.target;
            if (!btn || !btn.classList || !btn.classList.contains('subscription-update-btn')) return;

            var subId = btn.getAttribute('data-subscription-id');
            if (!subId) return;

            setFeedback(subId, '', 'info');
            togglePaymentEditor(subId);

            // Option B: usually already loaded; this is harmless.
            var selectEl = document.querySelector('.subscription-payment-select[data-subscription-id="' + subId + '"]');
            if (!selectEl) return;

            // Vault tokens are server-rendered into data-vault-loaded="true" on initial page load
            // and rebuilt from r.vault_tokens on every AJAX refresh — no additional fetch needed.
        }

        function onSaveCardClick(ev) {
            var btn = ev.target;
            if (!btn || !btn.classList || !btn.classList.contains('subscription-save-btn')) return;

            var subId = btn.getAttribute('data-subscription-id');
            if (!subId) return;

            var selectEl = document.querySelector('.subscription-payment-select[data-subscription-id="' + subId + '"]');
            var publicHash = selectEl ? (selectEl.value || '') : '';

            if (!publicHash) {
                setFeedback(subId, MSG.selectCard, 'error');
                return;
            }

            setFeedback(subId, MSG.saving, 'info');
            setPaymentControlsDisabled(subId, true);

            postUpdatePayment(subId, publicHash).then(function (json) {
                var cardLabel = (json && json.card_label) ? String(json.card_label) : '';
                var feedbackMsg = MSG.updated
                    + (cardLabel ? (' Card number: ' + cardLabel) : '');

                setFeedback(subId, feedbackMsg, 'success');
                togglePaymentEditor(subId, false);

                // Do NOT overwrite "Card used" — that always reflects the card on the transaction.
                // The server has persisted change_payment_card to the DB so the customer page
                // will also see this message on its next AJAX refresh without any localStorage.
            }).catch(function (err) {
                setFeedback(subId, (err && err.message) ? err.message : String(err), 'error');
            }).finally(function () {
                setPaymentControlsDisabled(subId, false);
            });
        }

        function onCancelEditClick(ev) {
            var btn = ev.target;
            if (!btn || !btn.classList || !btn.classList.contains('subscription-cancel-edit-btn')) return;

            var subId = btn.getAttribute('data-subscription-id');
            if (!subId) return;

            setFeedback(subId, '', 'info');
            togglePaymentEditor(subId, false);
        }

        function initSearchAndPaging() {
            var searchInput = document.getElementById(searchInputId);
            var rowsPerPage = document.getElementById(rowsPerPageId);
            var tbody = document.getElementById(tableBodyId);
            var noMatches = document.getElementById(noMatchesId);
            var pager = document.getElementById(pagerId);
            var info = document.getElementById(pageInfoId);

            if (!tbody) return;

            var currentPage = 1;

            function getRows() {
                return Array.prototype.slice.call(tbody.querySelectorAll('tr'));
            }

            function getLimit() {
                return parseInt(rowsPerPage ? rowsPerPage.value : 20, 10) || 20;
            }

            function renderPager(totalMatches, limit) {
                if (!pager) return;

                var totalPages = Math.max(1, Math.ceil(totalMatches / limit));
                if (currentPage > totalPages) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;

                pager.innerHTML = '';

                if (totalMatches === 0) {
                    pager.style.display = 'none';
                    if (info) info.textContent = '0 of 0';
                    return;
                }
                pager.style.display = '';

                function makeBtn(label, page, disabled, active) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = label;
                    b.disabled = !!disabled;
                    b.setAttribute('data-page', String(page));
                    b.className = 'action-default';
                    b.style.marginRight = '6px';
                    b.style.padding = '6px 10px';
                    b.style.border = '1px solid #ddd';
                    b.style.background = active ? '#eee' : '#fff';
                    b.style.cursor = disabled ? 'not-allowed' : 'pointer';
                    return b;
                }

                pager.appendChild(makeBtn('Prev', currentPage - 1, currentPage === 1, false));

                var windowSize = 7;
                var start = Math.max(1, currentPage - Math.floor(windowSize / 2));
                var end = Math.min(totalPages, start + windowSize - 1);
                start = Math.max(1, end - windowSize + 1);

                for (var p = start; p <= end; p++) {
                    pager.appendChild(makeBtn(String(p), p, false, p === currentPage));
                }

                pager.appendChild(makeBtn('Next', currentPage + 1, currentPage === totalPages, false));

                if (info) info.textContent = currentPage + ' of ' + totalPages;
            }

            function refresh() {
                var q = (searchInput ? (searchInput.value || '') : '').trim().toLowerCase();
                var limit = getLimit();
                var rows = getRows();

                var matches = [];
                rows.forEach(function (r) {
                    var text = (r.textContent || '').toLowerCase();
                    var match = (q === '') || (text.indexOf(q) !== -1);
                    if (match) matches.push(r);
                    r.style.display = 'none';
                });

                if (noMatches) noMatches.style.display = (matches.length === 0) ? '' : 'none';

                renderPager(matches.length, limit);

                var startIdx = (currentPage - 1) * limit;
                var endIdx = startIdx + limit;
                matches.slice(startIdx, endIdx).forEach(function (r) { r.style.display = ''; });
            }

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    currentPage = 1;
                    refresh();
                });
            }

            if (rowsPerPage) {
                rowsPerPage.addEventListener('change', function () {
                    currentPage = 1;
                    refresh();
                });
            }

            if (pager) {
                pager.addEventListener('click', function (ev) {
                    var btn = ev.target;
                    if (!btn || btn.tagName !== 'BUTTON') return;
                    var page = parseInt(btn.getAttribute('data-page'), 10);
                    if (!page || isNaN(page)) return;
                    currentPage = page;
                    refresh();
                });
            }

            refresh();
        }

        document.addEventListener('change', onStatusChange, false);
        document.addEventListener('click', onCancelChainClick, false);
        document.addEventListener('click', onCancelLatestClick, false);
        document.addEventListener('click', onUpdateCardClick, false);
        document.addEventListener('click', onSaveCardClick, false);
        document.addEventListener('click', onCancelEditClick, false);

        // ---- AJAX table refresh (decline-dashboard pattern) ----
        var pollEnabled = !!config.pollEnabled;
        var pollMs = parseInt(config.pollMs, 10) || 60000;

        function fetchDataAndRender() {
            if (!dataUrl) return;

            $.ajax({
                url: dataUrl,
                type: 'GET',
                dataType: 'json',
                cache: false,
                success: function (data) {
                    if (!data || !data.success || !Array.isArray(data.rows)) return;
                    renderAdminTable(data.rows);
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error('Admin recurring data fetch failed:', textStatus, errorThrown);
                }
            });
        }

        function renderAdminTable(rows) {
            var $tbody = $('#' + tableBodyId);
            if (!$tbody.length) return;

            var $noMatches = $('#' + noMatchesId);

            if (!rows || rows.length === 0) {
                $tbody.empty();
                if ($noMatches.length) $noMatches.show();
                return;
            }
            if ($noMatches.length) $noMatches.hide();

            var html = '';
            rows.forEach(function (r) {
                var cardLabel = r.card_last4 ? ('************' + r.card_last4) : 'N/A';

                // Status display
                // The builder guarantees: only the latest row in a chain returns 'active'.
                // All older rows (including the parent once children exist) return 'processed'.
                // We never render a raw 'processing' DB state to the user.
                // The cancel dropdown is shown only on the latest active row so the
                // admin can void that specific transaction.
                var statusHtml = '';
                if (r.status === 'cancelled') {
                    statusHtml = '<span class="subscription-status-label" style="color:#c00;">Canceled</span>';
                } else if (r.status === 'active' && r.is_latest) {
                    // Latest row: show the void/cancel dropdown
                    statusHtml = '<select class="subscription-status-select" data-subscription-id="' + r.subscription_id + '" aria-label="Subscription status">'
                        + '<option value="active" selected>Active</option>'
                        + '<option value="cancelled">Cancel</option>'
                        + '</select>';
                } else if (r.status === 'processed') {
                    statusHtml = '<span class="subscription-status-label" style="color:#28a745; font-weight:bold;">Processed</span>';
                } else {
                    // Normalize any unexpected raw state (e.g. mid-cycle 'processing') to Processed
                    statusHtml = '<span class="subscription-status-label" style="color:#28a745; font-weight:bold;">Processed</span>';
                }

                // Payment method — for the latest active row, show "Card used" plus
                // change_payment_card feedback if the admin or customer has updated the card.
                // change_payment_card is server-persisted so it reflects changes made on
                // either page without any localStorage.
                var paymentHtml = '';
                var pendingLabel = (r.change_payment_card && r.is_latest) ? String(r.change_payment_card) : '';
                if (cardLabel !== 'N/A') {
                    paymentHtml = '<span class="subscription-payment-current"'
                        + ' data-subscription-id="' + r.subscription_id + '"'
                        + ' style="font-size:12px; color:#333;">Card used: ' + cardLabel + '</span>';
                    if (pendingLabel) {
                        paymentHtml += '<br><span style="color:#0a7a0a; font-size:11px;">'
                            + MSG.updated + ' Card number: ' + pendingLabel
                            + '</span>';
                    }
                    if (r.status === 'active' && r.is_latest) {
                        // Show Update-card controls inline.
                        // vault_tokens is sent by SubscriptionDataBuilder only for the
                        // latest active row, so the dropdown is always populated here.
                        var custId = r.customer_id || '';
                        var vaultTokens = Array.isArray(r.vault_tokens) ? r.vault_tokens : [];
                        var vaultOptions = '<option value="">' + $t('Select a saved card') + '</option>';
                        vaultTokens.forEach(function (t) {
                            if (!t || !t.public_hash) return;
                            vaultOptions += '<option value="' + t.public_hash + '">'
                                + (t.label || t.public_hash) + '</option>';
                        });

                        paymentHtml += '<button type="button"'
                            + ' class="subscription-update-btn action secondary"'
                            + ' data-subscription-id="' + r.subscription_id + '"'
                            + ' style="margin-left:6px;">'
                            + $t('Update card') + '</button>'
                            + '<div class="subscription-payment-editor"'
                            + ' data-subscription-id="' + r.subscription_id + '"'
                            + ' style="display:none; gap:8px; align-items:center; flex-wrap:wrap;">'
                            + '<select class="subscription-payment-select"'
                            + ' data-subscription-id="' + r.subscription_id + '"'
                            + ' data-customer-id="' + custId + '"'
                            + ' data-vault-loaded="true">'
                            + vaultOptions
                            + '</select>'
                            + '<button type="button"'
                            + ' class="subscription-save-btn action secondary"'
                            + ' data-subscription-id="' + r.subscription_id + '">'
                            + $t('Save') + '</button>'
                            + '<button type="button"'
                            + ' class="subscription-cancel-edit-btn action secondary"'
                            + ' data-subscription-id="' + r.subscription_id + '">'
                            + $t('Cancel') + '</button>'
                            + '</div>'
                            + '<span class="subscription-update-feedback"'
                            + ' data-subscription-id="' + r.subscription_id + '"'
                            + ' style="font-size:12px;"></span>';
                    }
                } else {
                    paymentHtml = '<span style="color:#333; font-size:12px;">—</span>';
                }

                // Cancel column
                var cancelHtml = '';
                if (r.chain_cancelled) {
                    cancelHtml = '<span style="color:#c00; font-size:12px; font-weight:bold;">Subscription canceled</span>';
                } else if (r.root_sub_id > 0) {
                    cancelHtml = '<button type="button" class="subscription-cancel-chain-btn action secondary" data-root-subscription-id="' + r.root_sub_id + '">Cancel subscription</button>';
                } else {
                    cancelHtml = '<span style="color:#777; font-size:12px;">—</span>';
                }

                // Next billing
                var nextBillingHtml = r.next_billing || 'N/A';

                html += '<tr style="border-top:1px solid #eee;"'
                    + ' data-subscription-id="' + r.subscription_id + '"'
                    + ' data-root-sub-id="' + (r.root_sub_id || r.subscription_id) + '">'
                    + '<td>' + (r.customer_name || 'Guest') + '</td>'
                    + '<td>' + (r.increment_id || 'N/A') + '</td>'
                    + '<td>' + (r.amount || 'N/A') + '</td>'
                    + '<td>' + (r.customer_email || 'N/A') + '</td>'
                    + '<td>' + (r.interval || 'N/A') + '</td>'
                    + '<td>' + statusHtml + '</td>'
                    + '<td>' + paymentHtml + '</td>'
                    + '<td>' + nextBillingHtml + '</td>'
                    + '<td>' + cancelHtml + '</td>'
                    + '</tr>';
            });

            $tbody.html(html);


            initSearchAndPaging();
        }

        // ---- Change-detector: watch for new transactions and refresh the table ----
        //
        // Polls RecurringData every 5 seconds. fetchDataAndRender() delegates to
        // SubscriptionDataBuilder — a lightweight indexed query — cheap enough to
        // call directly. The table updates within ~5 seconds of a new recurring
        // transaction landing without any manual page refresh.
        if (dataUrl && !window.FISERV_ADMIN_RECURRING_WATCHER_INITIALIZED) {
            window.FISERV_ADMIN_RECURRING_WATCHER_INITIALIZED = true;

            setInterval(function () {
                if (!document.hidden) {
                    fetchDataAndRender();
                }
            }, 5000);
        }
    };
});