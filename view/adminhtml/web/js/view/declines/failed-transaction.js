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
		var statusFilter = $('#approvalStatusFilter').val().toUpperCase();
		var searchInput = $('#searchInput').val().toUpperCase();
		var startDate = $('#startDate').datepicker("getDate");
		var endDate = $('#endDate').datepicker("getDate");

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
			var dateInRange = (!startDate || rowDate >= startDate) && (!endDate || rowDate <= endDate);
			return (stateFilter === "" || rowState === stateFilter) && (statusFilter === "" || rowStatus === statusFilter) && (searchInput === "" || containsSearch) && dateInRange;
		});

		updateTableBody(filteredData);
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
		setupPagination();
		displayTable(1);
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
		$('#approvalStatusFilter').val('');
		$('.dropdown-menu').removeClass('show');
		currentPage = 1;
		applyFiltersAndSort();
	}

	$(document).ready(function () {
		storeOriginalData();

		var dateOptions = {
			dateFormat: "yy-mm-dd",
			beforeShow: function(input, inst) {
				if (input.id === 'startDate') {
					var endDate = $('#endDate').datepicker("getDate");
					$(this).datepicker("option", "maxDate", endDate);
				} else if (input.id === 'endDate') {
					var startDate = $('#startDate').datepicker("getDate");
					$(this).datepicker("option", "minDate", startDate);
				}
			},
			onSelect: function(selectedDate) {
				if (this.id === 'startDate') {
					$('#endDate').datepicker('option', 'minDate', selectedDate);
				} else if (this.id === 'endDate') {
					$('#startDate').datepicker('option', 'maxDate', selectedDate);
				}
			}
		};

		$('#startDate').datepicker(dateOptions);
		$('#endDate').datepicker(dateOptions);

		$('#searchInput').on('keyup', searchTable);
		$('#rowsPerPage').on('change', changeRowsPerPage);

		$('.dropdown-header').on('click', function () {
			var $menu = $(this).find('.dropdown-menu');
			$('.dropdown-menu').not($menu).removeClass('show');
			$menu.toggleClass('show');
		});

		$('#transactionStateMenu div').on('click', function () {
			var filterValue = $(this).data('value');
			$('#transactionStateFilter').val(filterValue);
			$('#transactionStateMenu').removeClass('show');

			currentPage = 1;
			applyFiltersAndSort();
		});

		$('#approvalStatusMenu div').on('click', function () {
			var filterValue = $(this).data('value');
			$('#approvalStatusFilter').val(filterValue);
			$('#approvalStatusMenu').removeClass('show');

			currentPage = 1;
			applyFiltersAndSort();
		});

		// Close dropdowns when clicking outside
		$(document).on('click', function (event) {
			if (!$(event.target).closest('.dropdown-header').length) {
				$('.dropdown-menu').removeClass('show');
			}
		});

		// Apply date filter when button is clicked
		$('#applyDateFilter').on('click', function () {
			currentPage = 1;
			applyFiltersAndSort();
		});

		// Reset filters when the reset button is clicked
		$('#resetFilters').on('click', resetFilters);

		// Initialize pagination and display the first page
		setupPagination();
		displayTable(1);
	});
});
