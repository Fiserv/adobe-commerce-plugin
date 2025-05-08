define([
	'jquery',
	'jquery/ui'
], function ($) {
	var currentPage = 1;
	var rowsPerPage = 20;
	var originalData = [];

	function storeOriginalData() {
		$('#transactionsTable tbody tr').each(function () {
			originalData.push($(this).clone());
		});
	}

	function applyFiltersAndSort() {
		var stateFilter = $('#transactionStateFilter').val().toUpperCase();
		var statusFilter = $('#approvalStatus').val().toUpperCase();
		var searchInput = $('#searchInput').val().toUpperCase();
		var startDate = $('#startDate').val();
		var endDate = $('#endDate').val();

		var filteredData = originalData.filter(function ($row) {
			var rowState = $row.find('td').eq(2).text().toUpperCase();
			var rowStatus = $row.find('td').eq(3).text().toUpperCase();
			var dateText = $row.find('td').eq(0).text();
			var rowDate = new Date(dateText);
			var containsSearch = false;
			$row.find('td').each(function () {
				var txtValue = $(this).text();
				if (txtValue.toUpperCase().indexOf(searchInput) > -1) {
					containsSearch = true;
					return false;
				}
			});
			var dateInRange = (!startDate || new Date(startDate) <= rowDate) && (!endDate || rowDate <= new Date(endDate));
			return (stateFilter === "" || rowState === stateFilter) && (statusFilter === "" || rowStatus === statusFilter) && (searchInput === "" || containsSearch) && dateInRange;
		});

		updateTableBody(filteredData);
		setupPagination();
		toggleResetButton();
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

		$('#paginationInfoTop').text(currentPage + ' of ' + pageCount);
	}

	function changePage(page) {
		currentPage = page;
		displayTable(page);
		setupPagination();
	}

	function changeRowsPerPage() {
		rowsPerPage = parseInt($('#rowsPerPage').val());
		currentPage = 1;
		applyFiltersAndSort();
	}

	function searchTable() {
		currentPage = 1;
		requestAnimationFrame(function () {
			applyFiltersAndSort();
		});
	}

	function resetFilters() {
		$('#startDate').val('');
		$('#endDate').val('');
		$('#searchInput').val('');
		$('#transactionStateFilter').val('');
		$('#approvalStatus').val('');
		currentPage = 1;
		applyFiltersAndSort();
		hideResetButton();
	}

	function toggleResetButton() {
		if ($('#searchInput').val() || $('#transactionStateFilter').val() || $('#approvalStatus').val() || $('#startDate').val() || $('#endDate').val()) {
			$('#resetFilters').show();
		} else {
			$('#resetFilters').hide();
		}
	}

	function hideResetButton() {
		$('#resetFilters').hide();
	}

	function handleDatePickers() {
		var startDatePicker = $('#startDate').datepicker({
			dateFormat: 'yy-mm-dd',
			onSelect: function (selectedDate) {
				var endDatePicker = $('#endDate');
				endDatePicker.datepicker('option', 'minDate', selectedDate);
				applyFiltersAndSort();
			}
		});
		var endDatePicker = $('#endDate').datepicker({
			dateFormat: 'yy-mm-dd',
			onSelect: function (selectedDate) {
				var startDatePicker = $('#startDate');
				startDatePicker.datepicker('option', 'maxDate', selectedDate);
				applyFiltersAndSort();
			}
		});
	}

	$(document).ready(function () {
		storeOriginalData();

		handleDatePickers();

		$('#searchInput').on('keyup', searchTable);
		$('#rowsPerPage').on('change', changeRowsPerPage);

		$('#transactionStateFilter').on('change', function () {
			$('option.selected-dropdown').removeClass('selected-dropdown');
			$(this).find('option:selected').addClass('selected-dropdown');
			applyFiltersAndSort();
		});

		$('#approvalStatus').on('change', function () {
			$('option.selected-dropdown').removeClass('selected-dropdown');
			$(this).find('option:selected').addClass('selected-dropdown');
			applyFiltersAndSort();
		});

		$('#resetFilters').on('click', resetFilters);

		toggleResetButton();
		setupPagination();
		displayTable(1);
	});
});
