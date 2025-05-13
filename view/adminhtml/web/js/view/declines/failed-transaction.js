define([
	'jquery',
	'mage/url'
], function ($, url) {

	var currentPage = 1;
	var rowsPerPage = 20;
	var searchFilter = '';
	var approvalStatus = '';
	var orderState = '';
	var fromDate = '';
	var toDate = '';

	function fetchOrders(page) {

		const filters = [searchFilter, approvalStatus, orderState, fromDate, toDate];
		$('#resetFilter').toggle(!filters.every(val => val === ''));

		$.ajax({
			url: url.build('payments/declines/preview'),
			type: 'GET',
			data: {
				page: page,
				pageSize: rowsPerPage,
				searchFilter: searchFilter,
				approvalStatus: encodeURIComponent(approvalStatus),
				orderState: encodeURIComponent(orderState),
				fromDate: fromDate,
				toDate: toDate
			},
			success: function (data) {
				renderTable(data.orders);
				setupPagination(data.totalPages);
			},
			error: function () {
				console.error('Failed to fetch orders');
			}
		});
	}

	function renderTable(orders) {
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

	function setupPagination(totalPages) {
		var $paginationControls = $('#paginationControls');
		$paginationControls.empty();

		var $prevButton = $('<span>').text('<').addClass('pagination-button').on('click', function () {
			if (currentPage > 1) {
				currentPage--;
				fetchOrders(currentPage);
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
				fetchOrders(currentPage);
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
		fetchOrders(currentPage);
	}

	function filterByApprovalStatus() {
		approvalStatus = $('#approvalStatus').val();
		currentPage = 1; // Reset current page to 1 when filter applied
		fetchOrders(currentPage);
	}

	function filterByOrderState() {
		orderState = $('#orderState').val();
		currentPage = 1; // Reset current page to 1 when filter applied
		fetchOrders(currentPage);
	}

	function filterByDateRange() {
		fromDate = $('#fromDate').val();
		toDate = $('#toDate').val();
		currentPage = 1; // Reset to first page
		fetchOrders(currentPage);
	}

	function resetFilter() {

		$('#searchInput').val('');
		$('#approvalStatus').val('');
		$('#orderState').val('');
		$('#fromDate').val('');
		$('#toDate').val('');

		searchFilter = '';
		approvalStatus = '';
		orderState = '';
		fromDate = '';
		toDate = '';

		$(this).hide();

		currentPage = 1;
		fetchOrders(currentPage);
	}

	function searchTable() {
		searchFilter = $('#searchInput').val().toLowerCase();
		currentPage = 1; // Reset current page to 1 when search is applied
		fetchOrders(currentPage);
	}

	$(document).ready(function () {
		$('#searchInput').on('keypress', function(event) {
			if (event.which === 13) { // 13 is the Enter key code
				searchTable();
			}
		});
		$('#rowsPerPage').on('change', changeRowsPerPage);
		$('#approvalStatus').on('change', filterByApprovalStatus);
		$('#orderState').on('change', filterByOrderState);
		$('#fromDate, #toDate').on('change', filterByDateRange);
		$('#resetFilter').on('click', resetFilter);
		setupPagination($('#paginationControls').data('totalpage'));
	});
});
