define([
	'jquery'
], function ($) {
	'use strict';

	return function (config) {
		const recordsFoundLabel = (config && config.recordsFoundLabel) || 'records found';
		const ajaxUrl = (config && config.ajaxUrl) || '';

		$(() => {
			const $search = $('#or-search');
			const $statusFilter = $('#or-status-filter');
			const $count = $('#or-record-count');
			const $rowsPerPage = $('#or-rows-per-page');
			const $paginationControls = $('#or-pagination-controls');
			const $paginationInfoTop = $('#or-pagination-info-top');
			const $tableWrapper = $('#or-table-wrapper');
			const $emptyState = $('#or-empty');
			const $tbody = $('#or-table-body');

			if (!ajaxUrl || !$paginationControls.length) {
				return;
			}

			let currentPage = parseInt($paginationControls.data('current-page'), 10) || 1;
			let rowsPerPage = parseInt($rowsPerPage.val(), 10) || parseInt($paginationControls.data('page-size'), 10) || 20;
			let totalPages = parseInt($paginationControls.data('total-pages'), 10) || 0;
			let searchFilter = '';
			let statusFilter = '';
			let searchTimer = null;

			const sortState = {
				col: 'created_at',
				dir: 'desc'
			};

			const setLoading = (isLoading) => {
				$tbody.css('opacity', isLoading ? '0.55' : '1');
			};

			const updatePaginationInfo = () => {
				if (totalPages > 0) {
					$paginationInfoTop.text(currentPage + ' of ' + totalPages).show();
				} else {
					$paginationInfoTop.hide();
				}
			};

			const setupPagination = () => {
				$paginationControls.empty();

				if (totalPages <= 0) {
					$paginationControls.hide();
					return;
				}

				$paginationControls.show();

				const $prevButton = $('<span>')
					.text('<')
					.addClass('or-pagination-button')
					.toggleClass('disabled', currentPage <= 1)
					.on('click', function () {
						if (currentPage > 1) {
							currentPage--;
							fetchData();
						}
					});

				const $pageInfo = $('<span>')
					.text(currentPage + ' of ' + totalPages)
					.addClass('or-pagination-info');

				const $nextButton = $('<span>')
					.text('>')
					.addClass('or-pagination-button')
					.toggleClass('disabled', currentPage >= totalPages)
					.on('click', function () {
						if (currentPage < totalPages) {
							currentPage++;
							fetchData();
						}
					});

				$paginationControls.append($prevButton, $pageInfo, $nextButton);
			};

			const renderRows = (rows) => {
				$tbody.empty();

				$.each(rows || [], function (_index, row) {
					const tr = $('<tr>').addClass('or-row')
						.attr('data-search', row.search_data || '')
						.attr('data-status', row.status || '');

					tr.append($('<td>').text(row.created_at || ''));
					tr.append($('<td>').text(row.amount || ''));
					tr.append($('<td>').text(row.currency_code || ''));
					tr.append($('<td>').text(row.customer_display || ''));

					const $code = $('<code>').text(row.open_refund_id || '');
					tr.append($('<td>').append($code));

					const $badge = $('<span>')
						.addClass('or-status-badge')
						.addClass(row.status_class || '')
						.text(row.status || '');
					tr.append($('<td>').append($badge));

					const $actionLink = $('<a>')
						.attr('href', row.view_url || '#')
						.text('View');
					tr.append($('<td>').append($actionLink));

					$tbody.append(tr);
				});
			};

			const toggleEmptyState = (hasRows) => {
				if (hasRows) {
					$emptyState.hide();
					$tableWrapper.show();
				} else {
					$tableWrapper.hide();
					$emptyState.show();
				}
			};

			const fetchData = () => {
				setLoading(true);

				$.ajax({
					url: ajaxUrl,
					type: 'GET',
					data: {
						page: currentPage,
						pageSize: rowsPerPage,
						searchFilter: searchFilter,
						statusFilter: statusFilter,
						sortCol: sortState.col,
						sortDir: sortState.dir
					},
					success: function (data) {
						const rows = data && data.rows ? data.rows : [];
						totalPages = data && data.totalPages ? parseInt(data.totalPages, 10) : 0;
						currentPage = data && data.currentPage ? parseInt(data.currentPage, 10) : 1;

						renderRows(rows);
						toggleEmptyState(rows.length > 0);
						$count.text((data.totalRecords || 0) + ' ' + recordsFoundLabel);
						updatePaginationInfo();
						setupPagination();
					},
					error: function () {
						$count.text('0 ' + recordsFoundLabel);
						toggleEmptyState(false);
						totalPages = 0;
						updatePaginationInfo();
						setupPagination();
					},
					complete: function () {
						setLoading(false);
					}
				});
			};

			$search.on('input', function () {
				searchFilter = (($search.val() || '') + '').trim();
				currentPage = 1;
				clearTimeout(searchTimer);
				searchTimer = setTimeout(fetchData, 250);
			});

			$statusFilter.on('change', function () {
				statusFilter = ($statusFilter.val() || '') + '';
				currentPage = 1;
				fetchData();
			});

			$rowsPerPage.on('change', function () {
				rowsPerPage = parseInt($rowsPerPage.val(), 10) || 20;
				currentPage = 1;
				fetchData();
			});

			$('th.or-sortable').on('click', function (event) {
				const col = ($(event.currentTarget).data('col') || '') + '';
				if (!col) {
					return;
				}

				if (sortState.col === col) {
					sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
				} else {
					sortState.col = col;
					sortState.dir = 'asc';
				}

				$('th.or-sortable').removeClass('sort-asc sort-desc');
				$(event.currentTarget).addClass('sort-' + sortState.dir);

				currentPage = 1;
				fetchData();
			});

			$count.text('0 ' + recordsFoundLabel);
			updatePaginationInfo();
			setupPagination();
			fetchData();
		});
	};
});
