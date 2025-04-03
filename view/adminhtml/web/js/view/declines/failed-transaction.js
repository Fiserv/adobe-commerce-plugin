define([
	'jquery',
], function ($) {

	var currentPage = 1;
	var rowsPerPage = 5;

	function displayTable(page) {
		var start = (page - 1) * rowsPerPage;
		var end = page * rowsPerPage;
		var visibleRowIndex = 0;
		var searchInput = $('#searchInput').val().toUpperCase();

		$('#transactionsTable tr').each(function(index) {
			if (index === 0) return; // Skip header row
			var $row = $(this);
			if (searchInput === "" || $row.attr('searchedRow') === 'true') {
				if (visibleRowIndex >= start && visibleRowIndex < end) {
					$row.addClass('visible').removeClass('hidden');
				} else {
					$row.addClass('hidden').removeClass('visible');
				}
				visibleRowIndex++;
			} else {
				$row.addClass('hidden').removeClass('visible');
			}
		});
	}

	function setupPagination() {
		var visibleRowCount = 0;

		$('#transactionsTable tr').each(function(index) {
			if (index === 0) return; // Skip header row
			if ($(this).attr('searchedRow') === 'true' || $('#searchInput').val() === "") {
				visibleRowCount++;
			}
		});

		var pageCount = Math.ceil(visibleRowCount / rowsPerPage);
		var $paginationControls = $('#paginationControls');
		$paginationControls.empty();

		var $prevButton = $('<span>').text('<').addClass('pagination-button').on('click', function() {
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

		var $nextButton = $('<span>').text('>').addClass('pagination-button').on('click', function() {
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
		currentPage = 1; // Reset current page to 1 when rows per page is changed
		setupPagination();
		displayTable(1);
	}

	function searchTable() {
		var filter = $('#searchInput').val().toUpperCase();

		$('#transactionsTable tr').each(function(index) {
			if (index === 0) return; // Skip header row
			var $row = $(this);
			$row.attr('searchedRow', 'false');
			$row.find('td').each(function() {
				var txtValue = $(this).text();
				if (txtValue.toUpperCase().indexOf(filter) > -1) {
					$row.attr('searchedRow', 'true');
					return false; // Stop searching further cells in this row
				}
			});
		});

		currentPage = 1; // Reset current page to 1 when search is applied

		requestAnimationFrame(function() {
			setupPagination();
			displayTable(1);
		});
	}

	$(document).ready(function() {
		$('#searchInput').on('keyup', searchTable);
		$('#rowsPerPage').on('change', changeRowsPerPage);
		setupPagination();
		displayTable(1);
	});

});
