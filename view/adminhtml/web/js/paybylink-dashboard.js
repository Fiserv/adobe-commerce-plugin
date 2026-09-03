/**
 * PayByLink Dashboard JavaScript
 * Separated from manage_payment_links.phtml for better maintainability
 */

define(['jquery', 'Magento_Ui/js/modal/modal'], function($, modal) {
    'use strict';

    var currentLink = '';

    function clearStalePageLoader() {
        $('[data-container="body"]')
            .removeClass('ajax-loading')
            .attr('aria-busy', 'false')
            .trigger('processStop');
    }

    // Toggle Filter Panel
    window.toggleFilterPanel = function() {
        var $panel = $('#filter-panel');
        if ($panel.length) {
            $panel.slideToggle(200);
        }
    };

    // Check if any filters are active and show panel
    $(document).ready(function() {
        clearStalePageLoader();

        var hasFilters = false;
        var filterParams = ['order_number', 'amount_from', 'amount_to', 'created_from', 'created_to', 'expires_from', 'expires_to', 'status'];

        filterParams.forEach(function(param) {
            if ($('#filter_' + param).length && $('#filter_' + param).val() !== '') {
                hasFilters = true;
            }
        });

        if (hasFilters) {
            $('#filter-panel').show();
        }
    });

    var modalOptions = {
        type: 'popup',
        modalClass: 'copy-link-modal',
        title: '',
        autoOpen: false,
        clickableOverlay: true,
        buttons: [],
        closed: function() {
            $('#modal-success').removeClass('show');
            $('#modal-copy-btn').show();
        }
    };

    $('#copy-link-modal').modal(modalOptions);

    function copyToClipboard(text, callback) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                if (callback) callback(true);
            }).catch(function() {
                fallbackCopy(text, callback);
            });
        } else {
            fallbackCopy(text, callback);
        }
    }

    function fallbackCopy(text, callback) {
        var textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();

        var successful = false;
        try {
            successful = document.execCommand('copy');
        } catch (err) {
            successful = false;
        }

        document.body.removeChild(textArea);
        if (callback) callback(successful);
    }

    window.openCopyModal = function(button) {
        currentLink = $(button).data('link');
        $('#modal-link-text').text(currentLink);
        $('#modal-success').removeClass('show');
        $('#modal-copy-btn').show();
        $('#copy-link-modal').modal('openModal');

        copyToClipboard(currentLink, function(success) {
            if (success) {
                $('#modal-copy-btn').hide();
                $('#modal-success').addClass('show');
            }
        });
    };

    window.copyModalLink = function() {
        copyToClipboard(currentLink, function(success) {
            if (success) {
                $('#modal-copy-btn').hide();
                $('#modal-success').addClass('show');
            }
        });
    };

    window.closeCopyModal = function() {
        $('#copy-link-modal').modal('closeModal');
    };
});

