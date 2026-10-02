/**
 * PayByLink Dashboard JavaScript
 *
 * Interaction flow (search/filter/paginate all via AJAX against the same
 * controller action, no full page reloads)
 */

define(['jquery', 'mage/translate', 'Magento_Ui/js/modal/modal'], function ($, $t, modal) {
    'use strict';

    let currentPage = 1;
    let rowsPerPage = 20;
    let ajaxUrl = '';
    let formKey = '';
    let icons = { retry: '', copy: '' };
    let currentLink = '';

    let filters = {
        search: '',
        order_number: '',
        status: '',
        amount_from: '',
        amount_to: '',
        created_from: '',
        created_to: '',
        expires_from: '',
        expires_to: ''
    };

    function clearStalePageLoader() {
        $('[data-container="body"]')
            .removeClass('ajax-loading')
            .attr('aria-busy', 'false')
            .trigger('processStop');
    }

    function hasActiveFilters() {
        return Object.keys(filters).some(function (key) {
            return filters[key] !== '';
        });
    }

    function fetchData(page) {
        currentPage = page;

        $('#resetFilter').toggle(hasActiveFilters());

        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: $.extend({ page: page, pageSize: rowsPerPage }, filters),
            success: function (data) {
                renderRows(data.rows || []);
                setupPagination(data.totalPages || 1);
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('Failed to fetch payment links', textStatus, errorThrown);
            }
        });
    }

    function escapeHtml(value) {
        if (value === undefined || value === null) {
            return '';
        }
        return $('<div>').text(value).html();
    }

    function capitalize(value) {
        value = (value || '') + '';
        return value.charAt(0).toUpperCase() + value.slice(1).toLowerCase();
    }

    function pad(number) {
        return number < 10 ? '0' + number : '' + number;
    }

    function formatDateParts(unixTs, rawFallback) {
        if (!unixTs) {
            return { date: rawFallback || '', time: '' };
        }
        let d = new Date(unixTs * 1000);
        let dateStr = (d.getMonth() + 1) + '/' + d.getDate() + '/' + pad(d.getFullYear() % 100);
        let hours = d.getHours();
        let hr12 = hours % 12 || 12;
        let timeStr = hr12 + ':' + pad(d.getMinutes()) + (hours >= 12 ? 'pm' : 'am');
        return { date: dateStr, time: timeStr };
    }

    function dateWrapHtml(unixTs, rawFallback) {
        let parts = formatDateParts(unixTs, rawFallback);
        let html = '<div class="date-wrap"><div class="date-main">' + escapeHtml(parts.date) + '</div>';
        if (parts.time) {
            html += '<div class="date-time">' + escapeHtml(parts.time) + '</div>';
        }
        html += '</div>';
        return html;
    }

    function viewOrderLinkHtml(row) {
        return '<a href="' + escapeHtml(row.view_order_url) + '" title="' + escapeHtml($t('View Order')) + '" class="action-view-order" style="display:inline-flex;align-items:center;">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' +
            '</a>';
    }

    function actionsHtml(row) {
        let statusCode = ((row.status_code || row.status || '') + '').toLowerCase();
        let html = '<div class="action-icons" style="display:flex;gap:8px;align-items:center;">';

        if (statusCode === 'unpaid') {
            let copyText = row.paybylink_url || row.order || '';
            html += '<button type="button" class="copy-link-btn" data-link="' + escapeHtml(copyText) + '" onclick="openCopyModal(this)" title="' + escapeHtml($t('Copy Link')) + '">' +
                '<img src="' + escapeHtml(icons.copy) + '" alt="' + escapeHtml($t('Copy Link')) + '" width="16" height="16" /></button>';
            html += viewOrderLinkHtml(row);
        } else if (statusCode === 'expired') {
            if (row.retry_url) {
                html += '<form action="' + escapeHtml(row.retry_url) + '" method="post" style="display:inline-flex;align-items:center;margin:0;">' +
                    '<input type="hidden" name="form_key" value="' + escapeHtml(formKey) + '" />' +
                    '<button type="submit" title="' + escapeHtml($t('Refresh Payment Link')) + '" class="action-retry" style="display:inline-flex;align-items:center;background:none;border:none;padding:0;cursor:pointer;">' +
                    '<img src="' + escapeHtml(icons.retry) + '" alt="' + escapeHtml($t('Refresh Payment Link')) + '" width="16" height="16" /></button></form>';
            } else {
                html += '<span class="action-retry disabled" title="' + escapeHtml($t('Refresh Payment Link')) + '">' +
                    '<img src="' + escapeHtml(icons.retry) + '" alt="' + escapeHtml($t('Refresh Payment Link')) + '" width="16" height="16" /></span>';
            }
            html += viewOrderLinkHtml(row);
        } else if (statusCode === 'paid') {
            html += viewOrderLinkHtml(row);
        }

        html += '</div>';
        return html;
    }

    function renderRows(rows) {
        let $tbody = $('#tableBody');
        let $table = $('#transactionsTable');
        let $noRecords = $('#noRecordsMessage');
        let $paginationInfoTop = $('#paginationInfoTop');
        let $paginationControls = $('#paginationControls');

        $tbody.empty();

        if (!rows || rows.length === 0) {
            $table.hide();
            $noRecords.show();
            $paginationInfoTop.hide();
            $paginationControls.hide();
            return;
        }

        $table.show();
        $noRecords.hide();
        $paginationInfoTop.show();
        $paginationControls.show();

        rows.forEach(function (row) {
            let statusCode = ((row.status_code || row.status || '') + '').toLowerCase();
            let statusLabel = escapeHtml(capitalize(statusCode));
            let statusHtml = statusCode === 'expired' ? '<span class="status-expired">' + statusLabel + '</span>' : statusLabel;
            let orderCell = row.entity_id
                ? '<a href="' + escapeHtml(row.view_order_url) + '">' + escapeHtml(row.order) + '</a>'
                : escapeHtml(row.order);

            let tr = '<tr>' +
                '<td>' + orderCell + '</td>' +
                '<td>' + escapeHtml(row.amount_formatted || row.amount) + '</td>' +
                '<td>' + dateWrapHtml(row.created_ts, row.created) + '</td>' +
                '<td>' + dateWrapHtml(row.expires_ts, row.expires) + '</td>' +
                '<td>' + statusHtml + '</td>' +
                '<td class="actions-cell">' + actionsHtml(row) + '</td>' +
                '</tr>';

            $tbody.append(tr);
        });
    }

    function setupPagination(totalPages) {
        let $paginationControls = $('#paginationControls');
        $paginationControls.empty();

        let $prevButton = $('<span>').text('<').addClass('pagination-button').on('click', function () {
            if (currentPage > 1) {
                fetchData(currentPage - 1);
            }
        });
        if (currentPage === 1) {
            $prevButton.addClass('disabled');
        }
        $paginationControls.append($prevButton);

        let $pageInfo = $('<span>').text(currentPage + ' of ' + totalPages).addClass('pagination-info');
        $paginationControls.append($pageInfo);

        let $nextButton = $('<span>').text('>').addClass('pagination-button').on('click', function () {
            if (currentPage < totalPages) {
                fetchData(currentPage + 1);
            }
        });
        if (currentPage >= totalPages) {
            $nextButton.addClass('disabled');
        }
        $paginationControls.append($nextButton);

        $('#paginationInfoTop').text(currentPage + ' ' + $t('of') + ' ' + totalPages);
    }

    function syncFiltersFromInputs() {
        filters.search = $('#searchInput').val() || '';
        filters.order_number = $('#filter_order_number').val() || '';
        filters.status = $('#filter_status').val() || '';
        filters.amount_from = $('#filter_amount_from').val() || '';
        filters.amount_to = $('#filter_amount_to').val() || '';
        filters.created_from = $('#filter_created_from').val() || '';
        filters.created_to = $('#filter_created_to').val() || '';
        filters.expires_from = $('#filter_expires_from').val() || '';
        filters.expires_to = $('#filter_expires_to').val() || '';
    }

    function resetFilters() {
        $('#searchInput').val('');
        $('#filter_order_number').val('');
        $('#filter_status').val('');
        $('#filter_amount_from').val('');
        $('#filter_amount_to').val('');
        $('#filter_created_from').val('');
        $('#filter_created_to').val('');
        $('#filter_expires_from').val('');
        $('#filter_expires_to').val('');

        Object.keys(filters).forEach(function (key) {
            filters[key] = '';
        });

        $('#resetFilter').hide();
        fetchData(1);
    }

    function bindEvents() {
        $('#searchInput').on('keypress', function (event) {
            if (event.which === 13) {
                event.preventDefault();
                syncFiltersFromInputs();
                fetchData(1);
            }
        });

        $('#filter_order_number').on('keypress', function (event) {
            if (event.which === 13) {
                event.preventDefault();
                syncFiltersFromInputs();
                fetchData(1);
            }
        });

        $('#filter_status, #filter_amount_from, #filter_amount_to, #filter_created_from, #filter_created_to, #filter_expires_from, #filter_expires_to')
            .on('change', function () {
                syncFiltersFromInputs();
                fetchData(1);
            });

        $('#resetFilter').on('click', function (event) {
            event.preventDefault();
            resetFilters();
        });

        $('#rowsPerPage').on('change', function () {
            rowsPerPage = parseInt($(this).val(), 10) || 20;
            fetchData(1);
        });

        $('#toggleFilterBtn').on('click', function () {
            $('#filterSection').toggleClass('open');
            $(this).toggleClass('open');
        });
    }

    let modalOptions = {
        type: 'popup',
        modalClass: 'copy-link-modal',
        title: '',
        autoOpen: false,
        clickableOverlay: true,
        buttons: [],
        closed: function () {
            $('#modal-success').removeClass('show');
            $('#modal-copy-btn').show();
        }
    };

    function copyToClipboard(text, callback) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                if (callback) callback(true);
            }).catch(function () {
                fallbackCopy(text, callback);
            });
        } else {
            fallbackCopy(text, callback);
        }
    }

    function fallbackCopy(text, callback) {
        let textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();

        let successful = false;
        try {
            successful = document.execCommand('copy');
        } catch (err) {
            successful = false;
        }

        document.body.removeChild(textArea);
        if (callback) callback(successful);
    }

    window.openCopyModal = function (button) {
        currentLink = $(button).data('link');
        $('#modal-link-text').text(currentLink);
        $('#modal-success').removeClass('show');
        $('#modal-copy-btn').show();
        $('#copy-link-modal').modal('openModal');

        copyToClipboard(currentLink, function (success) {
            if (success) {
                $('#modal-copy-btn').hide();
                $('#modal-success').addClass('show');
            }
        });
    };

    window.copyModalLink = function () {
        copyToClipboard(currentLink, function (success) {
            if (success) {
                $('#modal-copy-btn').hide();
                $('#modal-success').addClass('show');
            }
        });
    };

    window.closeCopyModal = function () {
        $('#copy-link-modal').modal('closeModal');
    };

    return function (config) {
        ajaxUrl = config.ajaxUrl;
        formKey = config.formKey;
        icons = config.icons || icons;

        $(document).ready(function () {
            clearStalePageLoader();

            $('#copy-link-modal').modal(modalOptions);

            syncFiltersFromInputs();
            $('#resetFilter').toggle(hasActiveFilters());

            setupPagination($('#paginationControls').data('totalpage') || 1);

            bindEvents();
        });
    };
});
