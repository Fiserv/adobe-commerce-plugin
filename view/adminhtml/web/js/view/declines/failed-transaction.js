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

	function fetchData(page) {

		if( jQuery('.search-pagination-container.failed-transaction').length ) {
			actionUrl = url.build('payments/declines/order');
		} else {
			actionUrl = url.build('payments/declines/preview');
		}

		const filters = [searchFilter, approvalStatus, orderState, transactionState, fromDate, toDate];
		$('#resetFilter').toggle(!filters.every(val => val === ''));

		$.ajax({
			url: actionUrl,
			type: 'GET',
			data: {
				page: page,
				pageSize: rowsPerPage,
				searchFilter: searchFilter,
				approvalStatus: encodeURIComponent(approvalStatus),
				orderState: encodeURIComponent(orderState),
				transactionState: encodeURIComponent(transactionState),
				fromDate: fromDate,
				toDate: toDate
			},
			success: function (data) {
				if( jQuery('.search-pagination-container.failed-transaction').length ) {
					renderTransactionTable(data.transactions);
				} else {
					renderOrderTable(data.orders);
				}

				setupPagination(data.totalPages);
			},
			error: function () {
				console.error('Failed to fetch orders');
			}
		});
	}

	function renderOrderTable(orders) {
		var tableBody = $('#tableBody');
		var noRecordsMessage = $('#noRecordsMessage');
		var paginationInfoTop = $('#paginationInfoTop');
		var paginationControls = $('#paginationControls');

		tableBody.empty();

		if (!orders || orders.length === 0) {
			noRecordsMessage.show(); // Show message if no orders
			paginationInfoTop.hide();
			paginationControls.hide();
			return;
		} else {
			noRecordsMessage.hide(); // Hide message if orders exist
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
			'<td><a href="' + order.orderViewUrl + '">View</a></td>' +
			'</tr>';
			tableBody.append(row);
		});
	}

	function renderTransactionTable(transactions) {
		var tableBody = $('#tableBody');
		var noRecordsMessage = $('#noRecordsMessage');
		var paginationInfoTop = $('#paginationInfoTop');
		var paginationControls = $('#paginationControls');

		tableBody.empty();

		if (!transactions || transactions.length === 0) {
			noRecordsMessage.show(); // Show message if no orders
			paginationInfoTop.hide();
			paginationControls.hide();
			return;
		} else {
			noRecordsMessage.hide(); // Hide message if orders exist
			paginationInfoTop.show();
			paginationControls.show();
		}

		transactions.forEach(function (transaction) {
			actionURL = '';
			if(transaction.transaction_url) {
				actionURL = '<td><a href="' + transaction.transaction_url + '">View</a></td>';
			}
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

		$('#paginationInfoTop').text(currentPage + ' of ' + totalPages); // Update the pagination info at the top
	}

	function changeRowsPerPage() {
		rowsPerPage = parseInt($('#rowsPerPage').val());
		currentPage = 1; // Reset current page to 1 when rows per page is changed
		fetchData(currentPage);
	}

	function filterByApprovalStatus() {
		approvalStatus = $('#approvalStatus').val();
		currentPage = 1; // Reset current page to 1 when filter applied
		fetchData(currentPage);
	}

	function filterByOrderState() {
		orderState = $('#orderState').val();
		currentPage = 1; // Reset current page to 1 when filter applied
		fetchData(currentPage);
	}

	function filterByTransactionState() {
		transactionState = $('#transactionStateFilter').val();
		currentPage = 1; // Reset current page to 1 when filter applied
		fetchData(currentPage);
	}

	function filterByDateRange() {
		fromDate = $('#fromDate').val();
		toDate = $('#toDate').val();
		currentPage = 1; // Reset to first page
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

		$(this).hide();

		currentPage = 1;
		fetchData(currentPage);
	}

	function searchTable() {
		searchFilter = $('#searchInput').val().toLowerCase();
		currentPage = 1; // Reset current page to 1 when search is applied
		fetchData(currentPage);
	}

	$(document).ready(function () {
		$('#searchInput').on('keypress', function(event) {
			if (event.which === 13) { // 13 is the Enter key code
				searchTable();
			}
		});
		$('#rowsPerPage').on('change', changeRowsPerPage);
		$('#approvalStatus').on('change', filterByApprovalStatus);
		$('#fromDate, #toDate').on('change', filterByDateRange);
		$('#resetFilter').on('click', resetFilter);

		// Tooltip functionality
		$(document).on('mouseenter', '.tooltip-icon', function() {
			const tooltipText = $(this).find('.tooltip-text');
			tooltipText.css('visibility', 'visible').css('opacity', '1');
		}).on('mouseleave', '.tooltip-icon', function() {
			const tooltipText = $(this).find('.tooltip-text');
			tooltipText.css('visibility', 'hidden').css('opacity', '0');
		});

		setupPagination($('#paginationControls').data('totalpage'));

		if( $('.search-pagination-container.failed-transaction').length ) {
			$('#transactionStateFilter').on('change', filterByTransactionState);
		} else {
			$('#orderState').on('change', filterByOrderState);
		}

		$('#toggleFilterBtn').on('click', function () {
			$('#filterSection').toggleClass('open');
			$(this).toggleClass('open');
		});
	});
});
