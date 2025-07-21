define([
	'jquery',
	'mage/url'
], function ($, url) {
	var currentPage = 1;
	var rowsPerPage = 20;
	var searchFilter = '';
	var approvalStatus = '';
	var orderState = '';
	var transactionState = '';
	var fromDate = '';
	var toDate = '';
	let exportUrl = '';
	let exportType = '';
	let orderIncrementId = '';

	function initializeExportType() {
		if ($('.search-pagination-container.failed-transaction').length) {
			exportType = 'transactions';
			console.log('Export type set to transactions');
		} else {
			exportType = 'orders';
			console.log('Export type set to orders');
		}
	}

	function fetchData(page) {
		let actionUrl;

		if (exportType === 'transactions') {
			actionUrl = url.build('payments/declines/order');
		} else {
			actionUrl = url.build('payments/declines/preview');
		}

		const filters = [searchFilter, approvalStatus, orderState, transactionState, fromDate, toDate];

		$('#resetFilter').toggle(!filters.every(val => val === ''));

		console.log('Fetching data with URL:', actionUrl, ' and filters:', filters);

		$.ajax({
			url: actionUrl,
			type: 'GET',
			data: {
				page: page,
				pageSize: rowsPerPage,
				orderIncrementId: orderIncrementId,
				searchFilter: searchFilter,
				approvalStatus: encodeURIComponent(approvalStatus),
				orderState: encodeURIComponent(orderState),
				transactionState: encodeURIComponent(transactionState),
				fromDate: fromDate,
				toDate: toDate
			},
			success: function (data) {
				if (exportType === 'transactions') {
					renderTransactionTable(data.transactions);
				} else {
					renderOrderTable(data.orders);
				}

				setupPagination(data.totalPages);
			},
			error: function (jqXHR, textStatus, errorThrown) {
				console.error('Failed to fetch data', textStatus, errorThrown);
			}
		});
	}

	function renderOrderTable(orders) {
		var tableBody = $('#tableBody');
		tableBody.empty();

		var noRecordsMessage = $('#noRecordsMessage');
		var paginationInfoTop = $('#paginationInfoTop');
		var paginationControls = $('#paginationControls');

		if (!orders || orders.length === 0) {
			noRecordsMessage.show();
			paginationInfoTop.hide();
			paginationControls.hide();
			return;
		} else {
			noRecordsMessage.hide();
			paginationInfoTop.show();
			paginationControls.show();
		}

		orders.forEach(function (order) {
			var row = '<tr>' +
				'<td>' + (order.date_time || 'N/A') + '</td>' +
				'<td>' + (order.order_increment_id || 'N/A') + '</td>' +
				'<td>' + (order.customer_name || 'N/A') + '</td>' +
				'<td>' + (order.order_state || 'N/A') + '</td>' +
				'<td>' + (order.grandTotal || 'N/A') + '</td>' +
				'<td>' + (order.approval_status || 'N/A') + '</td>' +
				'<td>' + (order.remote_ip || 'N/A') + '</td>' +
				'<td><a href="' + (order.orderViewUrl || '#') + '">View</a></td>' +
				'</tr>';
			tableBody.append(row);
		});
	}

	function renderTransactionTable(transactions) {
		var tableBody = $('#tableBody');
		tableBody.empty();

		var noRecordsMessage = $('#noRecordsMessage');
		var paginationInfoTop = $('#paginationInfoTop');
		var paginationControls = $('#paginationControls');

		if (!transactions || transactions.length === 0) {
			noRecordsMessage.show();
			paginationInfoTop.hide();
			paginationControls.hide();
			return;
		} else {
			noRecordsMessage.hide();
			paginationInfoTop.show();
			paginationControls.show();
		}

		transactions.forEach(function (transaction) {
			var actionURL = transaction.transaction_url ? '<td><a href="' + transaction.transaction_url + '">View</a></td>' : '<td>N/A</td>';
			var row = '<tr>' +
				'<td>' + (transaction.date_time || 'N/A') + '</td>' +
				'<td>' + (transaction.transaction_id || 'N/A') + '</td>' +
				'<td>' + (transaction.transaction_state || 'N/A') + '</td>' +
				'<td>' + (transaction.approval_status || 'N/A') + '</td>' +
				'<td>' + (transaction.total_amount || 'N/A') + '</td>' +
				'<td>' + (transaction.remote_ip || 'N/A') + '</td>' +
				actionURL +
				'</tr>';
			tableBody.append(row);
		});
	}

	function setupPagination(totalPages) {
		var $paginationControls = $('#paginationControls');
		$paginationControls.empty();

		var $prevButton = $('<span>').text('<').addClass('pagination-button').on('click', function () {
			if (currentPage > 1) {
				currentPage--;
				fetchData(currentPage);
			}
		});
		if (currentPage === 1) {
			$prevButton.addClass('disabled');
		}
		$paginationControls.append($prevButton);

		var $pageInfo = $('<span>').text(currentPage + ' of ' + totalPages).addClass('pagination-info');
		$paginationControls.append($pageInfo);

		var $nextButton = $('<span>').text('>').addClass('pagination-button').on('click', function () {
			if (currentPage < totalPages) {
				currentPage++;
				fetchData(currentPage);
			}
		});
		if (currentPage === totalPages) {
			$nextButton.addClass('disabled');
		}
		$paginationControls.append($nextButton);

		$('#paginationInfoTop').text(currentPage + ' of ' + totalPages);
	}

	function changeRowsPerPage() {
		rowsPerPage = parseInt($('#rowsPerPage').val());
		currentPage = 1;
		fetchData(currentPage);
	}

	function filterByApprovalStatus() {
		approvalStatus = $('#approvalStatus').val();
		currentPage = 1;
		fetchData(currentPage);
	}

	function filterByOrderState() {
		orderState = $('#orderState').val();
		currentPage = 1;
		fetchData(currentPage);
	}

	function filterByTransactionState() {
		transactionState = $('#transactionStateFilter').val();
		currentPage = 1;
		fetchData(currentPage);
	}

	function filterByDateRange() {
		fromDate = $('#fromDate').val();
		toDate = $('#toDate').val();
		currentPage = 1;
		fetchData(currentPage);
	}

	function resetFilter() {
		$('#searchInput').val('');
		$('#approvalStatus').val('');
		$('#orderState').val('');
		$('#transactionStateFilter').val('');
		$('#fromDate').val('');
		$('#toDate').val('');

		searchFilter = '';
		approvalStatus = '';
		orderState = '';
		transactionState = '';
		fromDate = '';
		toDate = '';

		$('#resetFilter').hide();
		currentPage = 1;
		fetchData(currentPage);
	}

	function searchTable() {
		searchFilter = $('#searchInput').val().toLowerCase();
		currentPage = 1;
		fetchData(currentPage);
	}

	$(document).ready(function () {
		// Fetch order increment ID from the page
		orderIncrementId = $('.order-id').text().replace('Order ID: ', '').trim();
		console.log('Order Increment ID:', orderIncrementId);

		$('#searchInput').on('keypress', function (event) {
			if (event.which === 13) {
				searchTable();
			}
		});
		$('#rowsPerPage').on('change', changeRowsPerPage);
		$('#approvalStatus').on('change', filterByApprovalStatus);
		$('#fromDate, #toDate').on('change', filterByDateRange);
		$('#resetFilter').on('click', resetFilter);

		$(document).on('mouseenter', '.tooltip-icon', function () {
			const tooltipText = $(this).find('.tooltip-text');
			tooltipText.css('visibility', 'visible').css('opacity', '1');
		}).on('mouseleave', '.tooltip-icon', function () {
			const tooltipText = $(this).find('.tooltip-text');
			tooltipText.css('visibility', 'hidden').css('opacity', '0');
		});

		setupPagination($('#paginationControls').data('totalpage'));

		if ($('.search-pagination-container.failed-transaction').length) {
			$('#transactionStateFilter').on('change', filterByTransactionState);
		} else {
			$('#orderState').on('change', filterByOrderState);
		}

		$('#toggleFilterBtn').on('click', function () {
			$('#filterSection').toggleClass('open');
			$(this).toggleClass('open');
		});

		$('#exportDropdownButton').on('click', function () {
			$('#exportDropdownMenu').toggle();
			$('#exportArrow').toggle();
			$('#exportArrowUp').toggle();
		});

		$('#cancelButton').on('click', function () {
			$('#exportDropdownMenu').hide();
			$('#exportArrow').show();
			$('#exportArrowUp').hide();
		});

		$('#exportButton').on('click', function () {
			var format = $('input[name="exportFormat"]:checked').val();
			if (format) {
				exportData(format);
			}
		});
		// Initialize exportType on page load
		initializeExportType();
	});

	async function exportData(format) {
		const filters = {
			searchFilter: searchFilter,
			approvalStatus: approvalStatus,
			orderState: orderState,
			transactionState: transactionState,
			fromDate: fromDate,
			toDate: toDate,
			format: format,
			type: exportType,
			orderIncrementId: orderIncrementId // Added
		};

		let fileNamePrefix = $('.search-pagination-container.failed-transaction').length ? 'failed_transactions' : 'failed_orders';
		let fileName = `${fileNamePrefix}.${format}`;

		$.ajax({
			url: exportUrl,
			type: 'GET',
			data: filters,
			xhrFields: {
				responseType: 'blob'
			},
			dataType: 'binary',
			processData: true,
			contentType: false,
			converters: {
				'* binary': function (response) {
					return response;
				}
			},
			success: function (blob, status, xhr) {
				const disposition = xhr.getResponseHeader('Content-Disposition');
				if (disposition && disposition.indexOf('filename=') !== -1) {
					const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
					if (matches != null && matches[1]) {
						fileName = decodeURIComponent(matches[1].replace(/['"]/g, ''));
					}
				}

				const downloadUrl = window.URL.createObjectURL(blob);
				const a = document.createElement('a');
				a.href = downloadUrl;
				a.download = fileName;
				document.body.appendChild(a);
				a.click();
				window.URL.revokeObjectURL(downloadUrl);
				document.body.removeChild(a);
			},
			error: function (jqXHR, textStatus, errorThrown) {
				console.error('Failed to export file:', textStatus, errorThrown);
				alert('Export failed. Please try again.');
			}
		});
	};

	return function(config)
	{
		exportUrl = config.exportUrl;
	}
});
