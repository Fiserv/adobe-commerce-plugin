define(['jquery', 'mage/translate'], function ($, $t) {
    'use strict';

    return function (config) {
        config = config || {};

        var cancelUrl = config.cancelUrl || '';
        var updateUrl = config.updateUrl || '';

        var initialVaultTokens = Array.isArray(config.initialVaultTokens) ? config.initialVaultTokens : [];

        var searchInputId = config.searchInputId || 'searchInput';
        var rowsPerPageId = config.rowsPerPageId || 'rowsPerPage';
        var tableBodyId = config.tableBodyId || 'tableBody';
        var noRecordsId = config.noRecordsId || 'noRecordsMessage';
        var paginationId = config.paginationId || 'paginationControls';

        var MSG = {
            selectCard: config.msgSelectCard || $t('Please select a saved card.'),
            saving: config.msgSaving || $t('Saving...'),
            updated: config.msgUpdated || $t('Updated. Future renewals will use this card.'),
            cancelLatestConfirm: config.msgConfirmCancelLatest || $t('Cancel the latest renewal transaction? Recurring will continue.'),
            cancelling: config.msgCancelling || $t('Cancelling...'),
            cancelFailedPrefix: config.msgCancelFailedPrefix || ($t('Failed to cancel:') + ' '),
            cancelChainConfirm: config.msgConfirmCancelChain || $t('Cancel this subscription? Future renewals will stop.'),
            cancelChainFailedPrefix: config.msgCancelFailedPrefix2 || ($t('Failed to cancel subscription:') + ' ')
        };


        function getFormKey() {
            if (typeof window.FORM_KEY !== 'undefined') return window.FORM_KEY;
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

        function findRowBySubscriptionId(subId) {
            return document.querySelector('tr.recurring-row[data-subscription-id="' + subId + '"]');
        }

        // ---- UI helpers ----
        function setFeedback(subId, msg, kind) {
            var el = document.querySelector('.subscription-update-feedback[data-subscription-id="' + subId + '"]');
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

            var shouldOpen = (typeof open === 'boolean') ? open : (editor.style.display === 'none' || editor.style.display === '');
            editor.style.display = shouldOpen ? 'flex' : 'none';
        }

        // ---- Requests ----
        function postCancel(subscriptionId) {
            if (!cancelUrl) return Promise.reject(new Error('missing cancelUrl'));
            if (!subscriptionId) return Promise.reject(new Error('missing subscription_id'));

            return postForm(cancelUrl, {
                subscription_id: subscriptionId,
                form_key: getFormKey()
            }).then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && (json.error || json.message)) ? (json.error || json.message) : 'Cancel failed');
                }
                return json;
            });
        }

        function postUpdatePayment(subscriptionId, publicHash) {
            if (!updateUrl) return Promise.reject(new Error('missing updateUrl'));
            if (!subscriptionId) return Promise.reject(new Error('missing subscription_id'));
            if (!publicHash) return Promise.reject(new Error('missing public_hash'));

            var row = findRowBySubscriptionId(subscriptionId);
            var orderInc = row ? (row.getAttribute('data-order-inc') || '') : '';

            return postForm(updateUrl, {
                subscription_id: subscriptionId,
                order_increment_id: orderInc,
                public_hash: publicHash,
                form_key: getFormKey()
            }).then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && (json.error || json.message)) ? (json.error || json.message) : 'Update failed');
                }
                return json;
            });
        }

        // -----------------------------
        // Vault token refresh (unchanged)
        // -----------------------------
        function populateVaultSelectFromData(selectEl, tokens) {
            if (!selectEl || !Array.isArray(tokens)) return;

            var curValue = selectEl.value || '';
            while (selectEl.firstChild) selectEl.removeChild(selectEl.firstChild);

            // Keep your placeholder if present in markup; if not, we just add tokens
            tokens.forEach(function (t, i) {
                if (!t || !t.public_hash) return;
                var opt = document.createElement('option');
                opt.value = String(t.public_hash);
                opt.text = String(t.label || t.public_hash);

                if (curValue && curValue === opt.value) opt.selected = true;
                else if (!curValue && i === 0) opt.selected = true;

                selectEl.appendChild(opt);
            });

            selectEl.setAttribute('data-vault-loaded', 'true');

            try { selectEl.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
        }

        function fetchVaultTokensThenPopulate(selectEl) {
            if (!selectEl) return;
            // Vault tokens are server-rendered into initialVaultTokens.
            populateVaultSelectFromData(selectEl, initialVaultTokens);
        }

        // -----------------------------
        // Search + Pagination (unchanged)
        // -----------------------------
        function initSearchAndPaging() {
            var searchInput = document.getElementById(searchInputId);
            var rowsPerPage = document.getElementById(rowsPerPageId);
            var tableBody = document.getElementById(tableBodyId);
            var noRecords = document.getElementById(noRecordsId);
            var pager = document.getElementById(paginationId);

            if (!tableBody) return;

            var currentPage = 1;

            function getRows() {
                return Array.prototype.slice.call(tableBody.querySelectorAll('tr.recurring-row'));
            }

            function buildSearchText(tr) {
                var tds = tr.querySelectorAll('td');
                function cell(i) { return (tds[i] ? (tds[i].textContent || '') : '').trim(); }

                var order = cell(0);
                var amount = cell(2);
                var interval = cell(3);

                var statusSel = tr.querySelector('.subscription-status-select');
                var status = statusSel ? (statusSel.value || '') : cell(4);

                // NOTE: dropdown might be hidden; still ok for search
                var paySel = tr.querySelector('.subscription-payment-select');
                var payment = paySel
                    ? ((paySel.options[paySel.selectedIndex] && paySel.options[paySel.selectedIndex].text) || '')
                    : cell(5);

                var nextBilling = cell(6);
                var sequence = cell(7);
                var originalOrder = cell(8);

                return (order + ' ' + amount + ' ' + interval + ' ' + status + ' ' + payment + ' ' +
                    nextBilling + ' ' + sequence + ' ' + originalOrder).toLowerCase();
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
                    return;
                }
                pager.style.display = '';

                function makeBtn(label, page, disabled, active) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = label;
                    b.disabled = !!disabled;
                    b.setAttribute('data-page', String(page));
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

                var info = document.createElement('span');
                info.textContent = ' Page ' + currentPage + ' of ' + totalPages;
                info.style.marginLeft = '8px';
                info.style.color = '#666';
                pager.appendChild(info);
            }

            function applyAll() {
                var q = (searchInput ? (searchInput.value || '') : '').toLowerCase().trim();
                var limit = getLimit();
                var rows = getRows();

                var matches = [];
                rows.forEach(function (r) {
                    var match = (q === '') || (buildSearchText(r).indexOf(q) !== -1);
                    r.setAttribute('data-match', match ? '1' : '0');
                    if (match) matches.push(r);
                });

                if (noRecords) {
                    noRecords.style.display = (matches.length === 0) ? '' : 'none';
                }

                renderPager(matches.length, limit);

                rows.forEach(function (r) { r.style.display = 'none'; });

                var startIdx = (currentPage - 1) * limit;
                var endIdx = startIdx + limit;
                matches.slice(startIdx, endIdx).forEach(function (r) {
                    r.style.display = '';
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    currentPage = 1;
                    applyAll();
                });
            }

            if (rowsPerPage) {
                rowsPerPage.addEventListener('change', function () {
                    currentPage = 1;
                    applyAll();
                });
            }

            tableBody.addEventListener('change', function (ev) {
                var el = ev.target;
                if (!el || !el.classList) return;
                if (el.classList.contains('subscription-status-select') ||
                    el.classList.contains('subscription-payment-select')) {
                    applyAll();
                }
            });

            if (pager) {
                pager.addEventListener('click', function (ev) {
                    var btn = ev.target;
                    if (!btn || btn.tagName !== 'BUTTON') return;
                    var page = parseInt(btn.getAttribute('data-page'), 10);
                    if (!page || isNaN(page)) return;
                    currentPage = page;
                    applyAll();
                });
            }

            applyAll();
        }

        // ---- Event handlers ----

        // lazy-load vault tokens
        document.addEventListener('focus', function (ev) {
            var el = ev.target;
            if (!el || !el.classList || !el.classList.contains('subscription-payment-select')) return;
            if (el.getAttribute('data-vault-loaded') === 'true') return;
            fetchVaultTokensThenPopulate(el);
        }, true);

        document.addEventListener('click', function (ev) {
            var el = ev.target;
            if (!el || !el.classList || !el.classList.contains('subscription-payment-select')) return;
            if (el.getAttribute('data-vault-loaded') === 'true') return;
            fetchVaultTokensThenPopulate(el);
        }, false);

        // UPDATED: Update card button now toggles editor (no API call)
        document.addEventListener('click', function (ev) {
            var btn = ev.target;
            if (!btn || !btn.classList || !btn.classList.contains('subscription-update-btn')) return;

            var subId = btn.getAttribute('data-subscription-id');
            if (!subId) return;

            setFeedback(subId, '', 'info');
            togglePaymentEditor(subId);
        }, false);

        // NEW: Save button performs the API call
        document.addEventListener('click', function (ev) {
            var btn = ev.target;
            if (!btn || !btn.classList || !btn.classList.contains('subscription-save-btn')) return;

            var subId = btn.getAttribute('data-subscription-id');
            if (!subId) return;

            var select = document.querySelector('.subscription-payment-select[data-subscription-id="' + subId + '"]');
            var publicHash = select ? (select.value || '') : '';

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

                // close editor
                togglePaymentEditor(subId, false);

                // Do NOT overwrite "Card used" — that always reflects the card on the transaction.
                // The feedback message (above) shows the new pending card.
                // The server has persisted change_payment_card to the DB so the admin page
                // will also see this message on its next AJAX refresh without any localStorage.
            }).catch(function (err) {
                setFeedback(subId, (err && err.message) ? err.message : String(err), 'error');
            }).finally(function () {
                setPaymentControlsDisabled(subId, false);
            });
        }, false);

        // NEW: Cancel button closes editor, no API call
        document.addEventListener('click', function (ev) {
            var btn = ev.target;
            if (!btn || !btn.classList || !btn.classList.contains('subscription-cancel-edit-btn')) return;

            var subId = btn.getAttribute('data-subscription-id');
            if (!subId) return;

            setFeedback(subId, '', 'info');
            togglePaymentEditor(subId, false);
        }, false);

        // cancel latest renewal (unchanged)
        document.addEventListener('change', function (ev) {
            var sel = ev.target;
            if (!sel || !sel.classList || !sel.classList.contains('subscription-status-select')) return;
            if (sel.value !== 'cancelled') return;

            var subId = sel.getAttribute('data-subscription-id');
            if (!subId) return;

            if (!confirm(MSG.cancelLatestConfirm)) {
                sel.value = 'active';
                return;
            }

            var all = Array.prototype.slice.call(document.querySelectorAll('.subscription-status-select'));
            all.forEach(function (s) { s.disabled = true; });

            var originalParent = sel.parentNode;
            var savingLabel = document.createElement('span');
            savingLabel.className = 'subscription-status-saving';
            savingLabel.textContent = MSG.cancelling;
            try { originalParent.replaceChild(savingLabel, sel); } catch (e) {}

            postCancel(subId).then(function () {
                window.location.reload();
            }).catch(function (err) {
                alert(MSG.cancelFailedPrefix + ((err && err.message) ? err.message : String(err)));

                var restore = document.createElement('select');
                restore.className = 'subscription-status-select';
                restore.setAttribute('data-subscription-id', subId);

                var optActive = document.createElement('option');
                optActive.value = 'active';
                optActive.text = $t('Active');
                optActive.selected = true;

                var optCancel = document.createElement('option');
                optCancel.value = 'cancelled';
                optCancel.text = $t('Cancel');

                restore.appendChild(optActive);
                restore.appendChild(optCancel);

                try { originalParent.replaceChild(restore, savingLabel); } catch (ignore) {}
            }).finally(function () {
                var all2 = Array.prototype.slice.call(document.querySelectorAll('.subscription-status-select'));
                all2.forEach(function (s) { s.disabled = false; });
            });
        }, false);

        // void/cancel a specific child renewal transaction (button)
        document.addEventListener('click', function (ev) {
            var btn = ev.target;
            if (!btn || !btn.classList || !btn.classList.contains('subscription-cancel-latest-btn')) return;

            var subId = btn.getAttribute('data-subscription-id');
            if (!subId) return;

            if (!confirm(MSG.cancelLatestConfirm)) return;

            btn.disabled = true;
            btn.textContent = MSG.cancelling;

            postCancel(subId).then(function () {
                window.location.reload();
            }).catch(function (err) {
                btn.disabled = false;
                btn.textContent = $t('Void transaction');
                alert(MSG.cancelFailedPrefix + ((err && err.message) ? err.message : String(err)));
            });
        }, false);

        // cancel subscription chain (unchanged)
        document.addEventListener('click', function (ev) {
            var btn = ev.target;
            if (!btn || !btn.classList || !btn.classList.contains('subscription-cancel-chain-btn')) return;

            var rootId = btn.getAttribute('data-root-subscription-id');
            if (!rootId) return;

            if (!confirm(MSG.cancelChainConfirm)) return;

            btn.disabled = true;

            postCancel(rootId).then(function () {
                window.location.reload();
            }).catch(function (err) {
                btn.disabled = false;
                alert(MSG.cancelChainFailedPrefix + ((err && err.message) ? err.message : String(err)));
            });
        }, false);

        // -----------------------------
        // INIT
        // -----------------------------
        initSearchAndPaging();


        // ---- AJAX table refresh (decline-dashboard pattern) ----
        var dataUrl = config.dataUrl || '';

        function fetchDataAndRender() {
            if (!dataUrl) return;

            $.ajax({
                url: dataUrl,
                type: 'GET',
                dataType: 'json',
                cache: false,
                success: function (data) {
                    if (!data || !data.success || !Array.isArray(data.rows)) return;
                    renderFrontendTable(data.rows);
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error('Recurring data fetch failed:', textStatus, errorThrown);
                }
            });
        }

        function renderFrontendTable(rows) {
            var $tbody = $('#' + tableBodyId);
            if (!$tbody.length) return;

            var $noRecords = $('#' + noRecordsId);

            if (!rows || rows.length === 0) {
                $tbody.empty();
                if ($noRecords.length) $noRecords.show();
                return;
            }
            if ($noRecords.length) $noRecords.hide();

            // Build vault token options HTML once (reused for every active row)
            var vaultOptionsHtml = '<option value="">' + $t('Select a saved card') + '</option>';
            initialVaultTokens.forEach(function (tok) {
                if (!tok || !tok.public_hash) return;
                vaultOptionsHtml += '<option value="' + escHtml(tok.public_hash) + '">'
                    + escHtml(tok.label || tok.public_hash) + '</option>';
            });
            var hasVaultTokens = initialVaultTokens.length > 0;

            // Build new HTML rows
            var html = '';
            rows.forEach(function (r) {
                var subId = r.subscription_id;
                var cardLabel = r.card_last4 ? ('************' + r.card_last4) : '—';

                // ---- Status cell ----
                // Rules mirror the PHP block:
                //   cancelled  → red "Canceled" label
                //   active (latest row, chain not ended) → void dropdown
                //   processed / everything else → green "Processed" label
                var statusHtml = '';
                if (r.status === 'cancelled') {
                    statusHtml = '<span class="subscription-status-label" style="color:#c00;">Canceled</span>';
                } else if (r.status === 'active' && r.is_latest && !r.chain_cancelled) {
                    statusHtml = '<select class="subscription-status-select" data-subscription-id="' + subId + '">'
                        + '<option value="active" selected>' + $t('Active') + '</option>'
                        + '<option value="cancelled">' + $t('Cancel') + '</option>'
                        + '</select>';
                } else {
                    // Normalize any unexpected raw state (e.g. mid-cycle 'processing') to Processed
                    statusHtml = '<span class="subscription-status-label" style="color:#28a745; font-weight:bold;">Processed</span>';
                }

                // ---- Payment method cell ----
                // Latest/active row with vault tokens → full Update-card editor (matches phtml)
                // change_payment_card (from server) drives the "Updated" feedback message —
                // server-persisted so both customer and admin pages reflect it without localStorage.
                var paymentHtml = '';
                var pendingLabel = (r.change_payment_card && r.is_latest) ? String(r.change_payment_card) : '';
                if (r.status === 'active' && r.is_latest && !r.chain_cancelled && hasVaultTokens) {
                    var feedbackText = pendingLabel
                        ? (MSG.updated + ' Card number: ' + pendingLabel)
                        : '';
                    paymentHtml = '<div class="subscription-payment-wrapper"'
                        + ' style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;"'
                        + ' data-subscription-id="' + subId + '">'
                        + '<span class="subscription-payment-current"'
                        + '  data-subscription-id="' + subId + '"'
                        + '  style="font-size:12px; color:#333;">'
                        + 'Card used: ' + cardLabel
                        + '</span>'
                        + '<button type="button"'
                        + '  class="subscription-update-btn action primary"'
                        + '  data-subscription-id="' + subId + '">'
                        + $t('Update card')
                        + '</button>'
                        + '<div class="subscription-payment-editor"'
                        + '  data-subscription-id="' + subId + '"'
                        + '  style="display:none; gap:8px; align-items:center; flex-wrap:wrap;">'
                        + '<select class="subscription-payment-select"'
                        + '  data-subscription-id="' + subId + '"'
                        + '  data-vault-loaded="true">'
                        + vaultOptionsHtml
                        + '</select>'
                        + '<button type="button"'
                        + '  class="subscription-save-btn action secondary"'
                        + '  data-subscription-id="' + subId + '">'
                        + $t('Save')
                        + '</button>'
                        + '<button type="button"'
                        + '  class="subscription-cancel-edit-btn action secondary"'
                        + '  data-subscription-id="' + subId + '">'
                        + $t('Cancel')
                        + '</button>'
                        + '</div>'
                        + '<span class="subscription-update-feedback"'
                        + '  data-subscription-id="' + subId + '"'
                        + '  style="font-size:12px;' + (feedbackText ? ' color:#0a7a0a;' : '') + '">'
                        + escHtml(feedbackText)
                        + '</span>'
                        + '</div>';
                } else {
                    paymentHtml = '<span style="font-size:12px; color:#333;">Card used: ' + cardLabel + '</span>';
                }

                // ---- Cancel subscription cell ----
                var cancelHtml = '';
                if (r.chain_cancelled) {
                    cancelHtml = '<span style="color:#c00; font-size:12px; font-weight:bold;">'
                        + $t('Subscription canceled') + '</span>';
                } else if (r.root_sub_id > 0) {
                    cancelHtml = '<button type="button"'
                        + ' class="subscription-cancel-chain-btn action secondary"'
                        + ' data-root-subscription-id="' + r.root_sub_id + '">'
                        + $t('Cancel subscription')
                        + '</button>';
                } else {
                    cancelHtml = '<span style="color:#777; font-size:12px;">—</span>';
                }

                // ---- Order link ----
                var orderHtml = r.increment_id || '—';
                if (r.order_entity_id) {
                    var orderViewUrl = config.orderViewBaseUrl
                        ? config.orderViewBaseUrl.replace('ORDER_ID_PLACEHOLDER', String(r.order_entity_id))
                        : '';
                    if (orderViewUrl) {
                        orderHtml = '<a href="' + orderViewUrl + '">' + escHtml(r.increment_id) + '</a>';
                    }
                }

                var tdStyle = 'padding:8px; border-bottom:1px solid #f5f5f5;';
                html += '<tr class="recurring-row"'
                    + ' data-subscription-id="' + subId + '"'
                    + ' data-order-inc="' + escHtml(r.increment_id || '') + '"'
                    + ' data-root-sub-id="' + escHtml(String(r.root_sub_id || subId)) + '"'
                    + ' data-root="' + escHtml(r.increment_id ? r.increment_id.replace(/-\d+$/, '') : '') + '">'
                    + '<td style="' + tdStyle + '">' + orderHtml + '</td>'
                    + '<td style="' + tdStyle + '">' + escHtml(r.date || 'Unknown') + '</td>'
                    + '<td style="' + tdStyle + '">' + escHtml(r.amount || 'N/A') + '</td>'
                    + '<td style="' + tdStyle + '">' + escHtml(r.interval || 'N/A') + '</td>'
                    + '<td style="' + tdStyle + '">' + statusHtml + '</td>'
                    + '<td style="' + tdStyle + '">' + paymentHtml + '</td>'
                    + '<td style="' + tdStyle + '">' + escHtml(r.next_billing || 'N/A') + '</td>'
                    + '<td style="' + tdStyle + '">' + cancelHtml + '</td>'
                    + '</tr>';
            });

            $tbody.html(html);


            // Re-run search + pagination after replacing rows
            initSearchAndPaging();
        }

        // Minimal HTML escape for values injected into the rendered table rows
        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // ---- Change-detector: watch for new transactions and refresh the table ----
        //
        // Polls RecurringData every 5 seconds. fetchDataAndRender() is a lightweight
        // indexed query via SubscriptionDataBuilder — cheap enough to call directly.
        // The table updates within ~5 seconds of a new recurring transaction landing
        // without any manual page refresh.
        if (dataUrl && !window.FISERV_RECURRING_WATCHER_INITIALIZED) {
            window.FISERV_RECURRING_WATCHER_INITIALIZED = true;

            setInterval(function () {
                if (!document.hidden) {
                    fetchDataAndRender();
                }
            }, 5000);
        }
    };
});