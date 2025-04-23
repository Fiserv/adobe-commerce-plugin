define([
	'jquery',
], function ($) {
	var currentPage = 1;
	var rowsPerPage = 20;
	var reverseDateOrder = false; // Determines the direction of Date/Time sorting
	var originalData = []; // Stores original data for sorting purposes

	function storeOriginalData() {
		$('#transactionsTable tbody tr').each(function () {
			originalData.push($(this).clone());
		});
	}

	function applyFiltersAndSort() {
		var stateFilter = $('#transactionStateFilter').val().toUpperCase(); // Get the selected transaction state filter
		var statusFilter = $('#approvalStatusFilter').val().toUpperCase(); // Get the selected approval status filter
		var searchInput = $('#searchInput').val().toUpperCase();
		var filteredData = originalData.filter(function ($row) {
			var rowState = $row.find('td').eq(2).text().toUpperCase(); // Extract the transaction state
			var rowStatus = $row.find('td').eq(3).text().toUpperCase(); // Extract the approval status
			var containsSearch = false;
			$row.find('td').each(function () {
				var txtValue = $(this).text();
				if (txtValue.toUpperCase().indexOf(searchInput) > -1) {
					containsSearch = true;
					return false; // Stop searching further cells in this row
				}
			});
			return (stateFilter === "" || rowState === stateFilter) && (statusFilter === "" || rowStatus === statusFilter) && (searchInput === "" || containsSearch);
		});

		var sortedData = filterDataBySortDirection(filteredData);
		updateTableBody(sortedData);
	}

	function filterDataBySortDirection(data) {
		if (reverseDateOrder) {
			data.sort(function (rowA, rowB) {
				const keyA = rowA.find('td').eq(0).text();
				const keyB = rowB.find('td').eq(0).text();
				if (!isNaN(Date.parse(keyA)) && !isNaN(Date.parse(keyB))) {
					const dateA = new Date(keyA);
					const dateB = new Date(keyB);
					return dateB - dateA;
				}
				return keyB.localeCompare(keyA);
			});
		} else {
			data.sort(function (rowA, rowB) {
				const keyA = rowA.find('td').eq(0).text();
				const keyB = rowB.find('td').eq(0).text();
				if (!isNaN(Date.parse(keyA)) && !isNaN(Date.parse(keyB))) {
					const dateA = new Date(keyA);
					const dateB = new Date(keyB);
					return dateA - dateB;
				}
				return keyA.localeCompare(keyB);
			});
		}
		return data;
	}

	function updateTableBody(data) {
		$('#transactionsTable tbody').empty().append(data);
		setupPagination();
		displayTable(currentPage);
	}

	function displayTable(page) {
		var start = (page - 1) * rowsPerPage;
		var end = page * rowsPerPage;
		var visibleRowIndex = 0;
		var noRecordsFound = true;

		$('#transactionsTable tbody tr').each(function (index) {
			if (visibleRowIndex >= start && visibleRowIndex < end) {
				$(this).show();
				noRecordsFound = false;
			} else {
				$(this).hide();
			}
			visibleRowIndex++;
		});

		if (noRecordsFound) {
			$('#noRecordsMessage').show();
		} else {
			$('#noRecordsMessage').hide();
		}
	}

	function setupPagination() {
		var visibleRowCount = $('#transactionsTable tbody tr').length;
		var pageCount = Math.ceil(visibleRowCount / rowsPerPage);
		var $paginationControls = $('#paginationControls');
		$paginationControls.empty();

		var $prevButton = $('<span>').text('<').addClass('pagination-button').on('click', function () {
			if (currentPage > 1) {
				changePage(currentPage - 1);
			}
		});
		if (currentPage === 1) {
			$prevButton.addClass('disabled');
		}
		$paginationControls.append($prevButton);

		var $pageInfo = $('<span>').text(currentPage + ' of ' + pageCount).addClass('pagination-info');
		$paginationControls.append($pageInfo);

		var $nextButton = $('<span>').text('>').addClass('pagination-button').on('click', function () {
			if (currentPage < pageCount) {
				changePage(currentPage + 1);
			}
		});
		if (currentPage === pageCount) {
			$nextButton.addClass('disabled');
		}
		$paginationControls.append($nextButton);

		// Update the pagination info at the top
		$('#paginationInfoTop').text(currentPage + ' of ' + pageCount);
	}

	function changePage(page) {
		currentPage = page;
		displayTable(page);
		setupPagination(); // Update pagination info
	}

	function changeRowsPerPage() {
		rowsPerPage = parseInt($('#rowsPerPage').val());
		currentPage = 1;
		setupPagination();
		displayTable(1);
	}

	function searchTable() {
		currentPage = 1; // Reset current page to 1 when search is applied
		requestAnimationFrame(function () {
			applyFiltersAndSort();
		});
	}

	$(document).ready(function () {
		storeOriginalData(); // Store original data on page load

		$('#searchInput').on('keyup', searchTable);
		$('#rowsPerPage').on('change', changeRowsPerPage);

		$('.transaction-state-filter-arrow').on('click', function () {
			$('#transactionStateMenu').toggleClass('show');
		});

		$('#transactionStateMenu div').on('click', function () {
			var filterValue = $(this).data('value');
			$('#transactionStateFilter').val(filterValue);
			$('#transactionStateMenu').removeClass('show');

			currentPage = 1;
			applyFiltersAndSort();
		});

		$('.approval-status-filter-arrow').on('click', function () {
			$('#approvalStatusMenu').toggleClass('show');
		});

		$('#approvalStatusMenu div').on('click', function () {
			var filterValue = $(this).data('value');
			$('#approvalStatusFilter').val(filterValue);
			$('#approvalStatusMenu').removeClass('show');

			currentPage = 1;
			applyFiltersAndSort();
		});

		$(document).on('click', function (event) {
			if (!$(event.target).closest('.transaction-state-filter-arrow, #transactionStateMenu').length) {
				$('#transactionStateMenu').removeClass('show');
			}
			if (!$(event.target).closest('.approval-status-filter-arrow, #approvalStatusMenu').length) {
				$('#approvalStatusMenu').removeClass('show');
			}
		});

		// Attach click event only to the sort arrow for Date/Time
		$('#dateSortArrow').on('click', function () {
			reverseDateOrder = !reverseDateOrder;
			applyFiltersAndSort();

			// Toggle arrow direction
			$('#dateSortArrow').html(reverseDateOrder ? '&#9650;' : '&#9660;'); // Up arrow on reverse sort, down arrow on normal sort
		});

		setupPagination();
		displayTable(1);
	});
});
