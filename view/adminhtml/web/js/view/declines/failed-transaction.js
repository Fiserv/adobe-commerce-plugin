define([
	    'jquery',
	    'mage/url'
], function ($, url) {

	    var currentPage = 1;
	    var rowsPerPage = 5;

	    function fetchOrders(page) {
		            $.ajax({
				                url: url.build('payments/declines/preview'),
				                type: 'GET',
				                data: {
							                page: page,
							                pageSize: rowsPerPage
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
		            var $tableBody = $('#tableBody');
		            $tableBody.empty();

		            orders.forEach(function (order) {
				                var row = '<tr>' +
					                    '<td>' + (order.date_time || 'N/A') + '</td>' +
					                    '<td>' + (order.order_increment_id || 'N/A') + '</td>' +
					                    '<td>' + (order.customer_name || 'N/A') + '</td>' +
					                    '<td>' + (order.order_state || 'N/A') + '</td>' +
					                    '<td>' + (order.grandTotal || 'N/A') + '</td>' +
					                    '<td>' + (order.remote_ip || 'N/A') + '</td>' +
					                    '<td><a href="' + order.viewUrl + '">View</a></td>' +
					                    '</tr>';
				                $tableBody.append(row);
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

	    function searchTable() {
		            var filter = $('#searchInput').val().toUpperCase();

		            $('#transactionsTable tr').each(function (index) {
				                if (index === 0) return; // Skip header row
				                var $row = $(this);
				                $row.attr('searchedRow', 'false');
				                $row.find('td').each(function () {
							                var txtValue = $(this).text();
							                if (txtValue.toUpperCase().indexOf(filter) > -1) {
										                    $row.attr('searchedRow', 'true');
										                    return false; // Stop searching further cells in this row
										                }
							            });
				            });

		            currentPage = 1; // Reset current page to 1 when search is applied

		            requestAnimationFrame(function () {
				                fetchOrders(currentPage);
				            });
		        }

	    $(document).ready(function () {
		            $('#searchInput').on('keyup', searchTable);
		            $('#rowsPerPage').on('change', changeRowsPerPage);
		            fetchOrders(currentPage);
		        });

});

