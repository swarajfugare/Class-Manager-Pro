(function ($) {
	'use strict';

	const palette = ['#2271b1', '#00a32a', '#dba617', '#d63638', '#8c5fbf', '#008a8a', '#c94f7c'];

	function updateBatchOptions($classSelect) {
		const selectedClass = String($classSelect.val() || '0');
		const $scope = $classSelect.closest('form, .cmp-wrap');

		$scope.find('select[data-cmp-batches]').each(function () {
			const $batchSelect = $(this);
			let currentHidden = false;

			$batchSelect.find('option').each(function () {
				const $option = $(this);
				const optionClass = String($option.data('class-id') || '0');
				const optionValue = String($option.attr('value') || '');
				const shouldHide = optionValue !== '' && optionValue !== '0' && selectedClass !== '0' && optionClass !== selectedClass;

				$option.prop('hidden', shouldHide).prop('disabled', shouldHide);

				if ($option.is(':selected') && shouldHide) {
					currentHidden = true;
				}
			});

			if (currentHidden) {
				$batchSelect.val($batchSelect.find('option:not(:disabled)').first().val());
			}
		});
	}

	function maybeFillBatchFee($batchSelect) {
		const targetSelector = $batchSelect.data('cmp-batch-fee-target');

		if (!targetSelector) {
			return;
		}

		const $target = $(targetSelector);
		const selectedFee = $batchSelect.find('option:selected').data('batch-fee');

		if (!$target.length || selectedFee === undefined || selectedFee === '') {
			return;
		}

		if (!$target.val() || $target.data('cmp-autofilled')) {
			$target.val(selectedFee);
			$target.data('cmp-autofilled', true);
		}
	}

	function updateFreeBatchState($checkbox) {
		const $form = $checkbox.closest('form');
		const $fee = $form.find('#cmp-batch-fee');
		const isFree = $checkbox.is(':checked');

		$fee.prop('disabled', isFree);

		if (isFree) {
			$fee.val('0');
		}
	}

	function updateExportLink($form) {
		const $link = $form.find('.cmp-export-link');

		if (!$link.length || !$link.data('base-url')) {
			return;
		}

		const url = new URL($link.data('base-url'), window.location.href);
		const params = new URLSearchParams($form.serialize());

		params.forEach(function (value, key) {
			if (key !== 'page') {
				url.searchParams.set(key, value);
			}
		});

		$link.attr('href', url.toString());
	}

	function runAjaxFilter($form) {
		const action = $form.data('cmp-action');
		const target = $form.data('cmp-target');

		if (!action || !target) {
			return;
		}

		updateExportLink($form);

		const data = $form.serializeArray();
		data.push({ name: 'action', value: action });
		data.push({ name: 'nonce', value: CMPAdmin.nonce });

		$form.addClass('cmp-loading');

		$.post(CMPAdmin.ajaxUrl, data)
			.done(function (response) {
				if (response && response.success && response.data && response.data.html) {
					$(target).html(response.data.html);
				}
			})
			.always(function () {
				$form.removeClass('cmp-loading');
			});
	}

	function debounce(fn, wait) {
		let timeout;

		return function () {
			const context = this;
			const args = arguments;

			clearTimeout(timeout);
			timeout = setTimeout(function () {
				fn.apply(context, args);
			}, wait);
		};
	}

	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}

		return new Promise(function (resolve, reject) {
			const $temp = $('<input type="text" class="cmp-copy-temp">').val(text).appendTo('body');
			$temp[0].select();

			try {
				document.execCommand('copy');
				resolve();
			} catch (error) {
				reject(error);
			}

			$temp.remove();
		});
	}

	function renderChart(id, config) {
		const canvas = document.getElementById(id);

		if (!canvas || typeof Chart === 'undefined') {
			return;
		}

		return new Chart(canvas, config);
	}

	function renderCharts() {
		const charts = window.CMPCharts || {};

		if (charts.dashboardRevenue) {
			renderChart('cmp-dashboard-revenue', {
				type: 'bar',
				data: {
					labels: charts.dashboardRevenue.labels,
					datasets: [{
						label: 'Revenue',
						data: charts.dashboardRevenue.values,
						backgroundColor: palette[0]
					}]
				},
				options: {
					maintainAspectRatio: false,
					scales: { y: { beginAtZero: true } }
				}
			});
		}

		if (charts.studentStatus) {
			renderChart('cmp-dashboard-status', {
				type: 'doughnut',
				data: {
					labels: charts.studentStatus.labels,
					datasets: [{
						data: charts.studentStatus.values,
						backgroundColor: [palette[1], palette[0], palette[3]]
					}]
				},
				options: { maintainAspectRatio: false }
			});
		}

		if (charts.monthlyRevenue) {
			renderChart('cmp-analytics-monthly-revenue', {
				type: 'bar',
				data: {
					labels: charts.monthlyRevenue.labels,
					datasets: [{
						label: 'Revenue',
						data: charts.monthlyRevenue.values,
						backgroundColor: palette[0]
					}]
				},
				options: {
					maintainAspectRatio: false,
					scales: { y: { beginAtZero: true } }
				}
			});
		}

		if (charts.classRevenue) {
			renderChart('cmp-analytics-class-revenue', {
				type: 'pie',
				data: {
					labels: charts.classRevenue.labels,
					datasets: [{
						data: charts.classRevenue.values,
						backgroundColor: palette
					}]
				},
				options: { maintainAspectRatio: false }
			});
		}

		if (charts.studentGrowth) {
			renderChart('cmp-analytics-student-growth', {
				type: 'line',
				data: {
					labels: charts.studentGrowth.labels,
					datasets: [{
						label: 'Students',
						data: charts.studentGrowth.values,
						borderColor: palette[1],
						backgroundColor: 'rgba(0, 163, 42, 0.15)',
						fill: true,
						tension: 0.25
					}]
				},
				options: {
					maintainAspectRatio: false,
					scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
				}
			});
		}
	}

	$(function () {
		const delayedFilter = debounce(function () {
			runAjaxFilter($(this).closest('.cmp-filter-form'));
		}, 250);

		$('[data-cmp-class-select]').each(function () {
			const $select = $(this);
			updateBatchOptions($select);
		});

		$(document).on('change', '[data-cmp-class-select]', function () {
			const $select = $(this);
			updateBatchOptions($select);
			$select.closest('form, .cmp-wrap').find('[data-cmp-batches]').each(function () {
				maybeFillBatchFee($(this));
			});
		});

		$('[data-cmp-batches]').each(function () {
			maybeFillBatchFee($(this));
		});

		$(document).on('change', '[data-cmp-batches]', function () {
			maybeFillBatchFee($(this));
		});

		$(document).on('input', '#cmp-student-total-fee', function () {
			$(this).data('cmp-autofilled', false);
		});

		$('#cmp-batch-is-free').each(function () {
			updateFreeBatchState($(this));
		});

		$(document).on('change', '#cmp-batch-is-free', function () {
			updateFreeBatchState($(this));
		});

		$('.cmp-filter-form').each(function () {
			updateExportLink($(this));
		});

		$(document).on('submit', '.cmp-filter-form', function (event) {
			event.preventDefault();
			runAjaxFilter($(this));
		});

		$(document).on('input change', '.cmp-filter-form input, .cmp-filter-form select', delayedFilter);

		$(document).on('click', '.cmp-delete-link', function (event) {
			if (!window.confirm('Delete this record?')) {
				event.preventDefault();
			}
		});

		$(document).on('submit', 'form[data-cmp-confirm]', function (event) {
			const message = $(this).data('cmp-confirm');

			if (message && !window.confirm(message)) {
				event.preventDefault();
			}
		});

		$(document).on('click', '[data-cmp-copy-target]', function () {
			const $button = $(this);
			const selector = $button.data('cmp-copy-target');
			const $target = $(selector);
			const text = $target.length ? $target.val() || $target.text() : '';
			const originalText = $button.text();

			if (!text) {
				return;
			}

			copyText(text).then(function () {
				$button.text('Copied');
				window.setTimeout(function () {
					$button.text(originalText);
				}, 1200);
			});
		});

		renderCharts();
	});
})(jQuery);
