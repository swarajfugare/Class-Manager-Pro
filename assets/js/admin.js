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
		data.push({ name: 'nonce', value: getAjaxNonce() });

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

		if (charts.courseDemandHeatmap) {
			renderChart('cmp-analytics-course-demand', {
				type: 'bar',
				data: {
					labels: charts.courseDemandHeatmap.labels,
					datasets: [{
						label: 'Students',
						data: charts.courseDemandHeatmap.values,
						backgroundColor: palette[3]
					}]
				},
				options: {
					indexAxis: 'y',
					maintainAspectRatio: false,
					scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
				}
			});
		}
	}

	function downloadCsv(filename, content) {
		const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
		const url = URL.createObjectURL(blob);
		const link = document.createElement('a');

		link.href = url;
		link.download = filename;
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		URL.revokeObjectURL(url);
	}

	function getAjaxNonce() {
		return String($('input[name="cmp_admin_ajax_nonce"]').first().val() || CMPAdmin.nonce || '');
	}

	function updateFeedback($feedback, message, isError) {
		if (!$feedback || !$feedback.length) {
			return;
		}

		$feedback.text(message || '');
		$feedback.toggleClass('cmp-feedback-error', !!isError);
		$feedback.toggleClass('cmp-feedback-success', !!message && !isError);
	}

	function showAdminNotice(message, type) {
		const noticeType = type === 'error' ? 'notice-error' : 'notice-success';
		const $wrap = $('.cmp-wrap').first();
		const $notice = $('<div class="notice is-dismissible cmp-ajax-notice"><p></p></div>');

		if (!$wrap.length || !message) {
			return;
		}

		$wrap.find('.cmp-ajax-notice').remove();
		$notice.addClass(noticeType);
		$notice.find('p').text(message);
		$wrap.prepend($notice);
		window.scrollTo({ top: 0, behavior: 'smooth' });
	}

	function collectIds(selector) {
		return $(selector).filter(':checked').map(function () {
			return $(this).val();
		}).get();
	}

	function requestEntityAction(payload) {
		return $.post(CMPAdmin.ajaxUrl, $.extend({
			action: 'cmp_admin_entity_action',
			nonce: getAjaxNonce()
		}, payload));
	}

	function requestDelete(payload) {
		return $.post(CMPAdmin.ajaxUrl, $.extend({
			action: 'cmp_delete_item',
			nonce: String(CMPAdmin.deleteNonce || '')
		}, payload));
	}

	function removeDeletedRows(rows) {
		if (!Array.isArray(rows)) {
			return;
		}

		rows.forEach(function (rowId) {
			$('[data-cmp-row-id="' + rowId + '"]').remove();
		});
	}

	function handleEntityActionFailure(xhr, $feedback) {
		const message = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Request failed.';

		updateFeedback($feedback, message, true);
		showAdminNotice(message, 'error');
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
			if (!$(this).data('cmp-action')) {
				return;
			}

			event.preventDefault();
			runAjaxFilter($(this));
		});

		$(document).on('input change', '.cmp-filter-form input, .cmp-filter-form select', function () {
			if (!$(this).closest('.cmp-filter-form').data('cmp-action')) {
				return;
			}

			delayedFilter.call(this);
		});

		$(document).on('click', '.cmp-delete-link', function (event) {
			const $link = $(this);
			const message = 'Are you sure you want to delete?';

			if (!$link.data('cmp-ajax-delete')) {
				if (!window.confirm(message)) {
					event.preventDefault();
				}

				return;
			}

			event.preventDefault();

			if (!window.confirm(message)) {
				return;
			}

			const entityType = String($link.data('type') || $link.data('cmp-entity-type') || '');
			const entityId = String($link.data('id') || $link.data('cmp-entity-id') || '');
			const $feedback = $($link.data('cmp-feedback') || '');
			const originalText = $.trim($link.text());

			if (!entityType || !entityId) {
				return;
			}

			$link.addClass('is-busy').attr('aria-disabled', 'true').text('Deleting...');
			updateFeedback($feedback, 'Deleting...', false);

			requestDelete({
				id: entityId,
				type: entityType
			}).done(function (response) {
				if (!response || !response.success || !response.data) {
					updateFeedback($feedback, 'Delete failed.', true);
					showAdminNotice('Delete failed.', 'error');
					return;
				}

				removeDeletedRows([response.data.deleted_row || (entityType + '-' + entityId)]);
				updateFeedback($feedback, response.data.message || 'Deleted.', false);
				showAdminNotice(response.data.message || 'Deleted.', 'success');
			}).fail(function (xhr) {
				handleEntityActionFailure(xhr, $feedback);
			}).always(function () {
				$link.removeClass('is-busy').removeAttr('aria-disabled').text(originalText);
			});
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

		$(document).on('change', '[data-cmp-select-all]', function () {
			const checked = $(this).is(':checked');
			const target = $(this).data('cmp-select-all');

			if (!target) {
				return;
			}

			$(target).prop('checked', checked);
		});

		$(document).on('click', '[data-cmp-bulk-apply="1"]', function () {
			const $button = $(this);
			const entityType = String($button.data('cmp-entity-type') || '');
			const checkboxSelector = String($button.data('cmp-checkbox') || '');
			const actionSelector = String($button.data('cmp-action-select') || '');
			const $feedback = $($button.data('cmp-feedback') || '');
			const task = String($(actionSelector).val() || '');
			const ids = collectIds(checkboxSelector);
			const confirmMessage = entityType === 'class' ? 'Delete the selected classes?' : 'Delete the selected batches?';

			if (!task) {
				updateFeedback($feedback, 'Choose a bulk action.', true);
				return;
			}

			if (!ids.length) {
				updateFeedback($feedback, 'Select at least one record.', true);
				return;
			}

			if (task === 'delete' && !window.confirm(confirmMessage)) {
				return;
			}

			$button.prop('disabled', true).addClass('is-busy');
			updateFeedback($feedback, 'Working...', false);

			requestEntityAction({
				entity_type: entityType,
				task: task,
				ids: ids
			}).done(function (response) {
				if (!response || !response.success || !response.data) {
					updateFeedback($feedback, 'Bulk action failed.', true);
					showAdminNotice('Bulk action failed.', 'error');
					return;
				}

				if (response.data.csv && response.data.filename) {
					downloadCsv(response.data.filename, response.data.csv);
				}

				removeDeletedRows(response.data.deleted_rows || []);
				updateFeedback($feedback, response.data.message || 'Done.', false);
				showAdminNotice(response.data.message || 'Done.', 'success');

				if (response.data.reload) {
					window.location.reload();
				}
			}).fail(function (xhr) {
				handleEntityActionFailure(xhr, $feedback);
			}).always(function () {
				$button.prop('disabled', false).removeClass('is-busy');
			});
		});

		$(document).on('change', '#cmp-student-select-all', function () {
			const checked = $(this).is(':checked');
			$('.cmp-student-select').prop('checked', checked);
		});

		$(document).on('click', '#cmp-student-bulk-apply', function () {
			const $button = $(this);
			const $feedback = $('#cmp-student-bulk-feedback');
			const action = String($('#cmp-student-bulk-action').val() || '');
			const studentIds = $('.cmp-student-select:checked').map(function () {
				return $(this).val();
			}).get();

			if (!action) {
				updateFeedback($feedback, 'Choose a bulk action.', true);
				return;
			}

			if (!studentIds.length) {
				updateFeedback($feedback, 'Select at least one student.', true);
				return;
			}

			if (action === 'change_batch' && (!$('#cmp-student-bulk-class').val() || $('#cmp-student-bulk-class').val() === '0' || !$('#cmp-student-bulk-batch').val() || $('#cmp-student-bulk-batch').val() === '0')) {
				updateFeedback($feedback, 'Choose the target class and batch.', true);
				return;
			}

			if (action === 'delete' && !window.confirm('Delete the selected students?')) {
				return;
			}

			$button.prop('disabled', true).addClass('is-busy');
			updateFeedback($feedback, 'Working...', false);

			requestEntityAction({
				entity_type: 'student',
				task: action,
				ids: studentIds,
				target_class_id: $('#cmp-student-bulk-class').val(),
				target_batch_id: $('#cmp-student-bulk-batch').val()
			}).done(function (response) {
				if (!response || !response.success || !response.data) {
					updateFeedback($feedback, 'Bulk action failed.', true);
					showAdminNotice('Bulk action failed.', 'error');
					return;
				}

				if (response.data.csv && response.data.filename) {
					downloadCsv(response.data.filename, response.data.csv);
				}

				updateFeedback($feedback, response.data.message || 'Done.', false);
				showAdminNotice(response.data.message || 'Done.', 'success');

				if (response.data.reload) {
					window.location.reload();
				}
			}).fail(function (xhr) {
				handleEntityActionFailure(xhr, $feedback);
			}).always(function () {
				$button.prop('disabled', false).removeClass('is-busy');
			});
		});

		$(document).on('submit', 'form[data-cmp-attendance-form="1"]', function (event) {
			const $form = $(this);
			const $feedback = $form.find('[data-cmp-attendance-feedback]');
			const $button = $form.find('input[type="submit"], button[type="submit"]').first();
			const isInputButton = $button.is('input');
			const originalText = isInputButton ? String($button.val() || '') : String($button.text() || '');
			const data = $form.serializeArray();

			event.preventDefault();

			data.push({ name: 'action', value: 'cmp_save_attendance_quick' });
			data.push({ name: 'nonce', value: String(CMPAdmin.attendanceNonce || '') });

			$button.prop('disabled', true).addClass('is-busy');
			if (isInputButton) {
				$button.val('Saving...');
			} else {
				$button.text('Saving...');
			}
			updateFeedback($feedback, 'Saving attendance...', false);

			$.post(CMPAdmin.ajaxUrl, data).done(function (response) {
				if (!response || !response.success || !response.data) {
					updateFeedback($feedback, 'Attendance save failed.', true);
					showAdminNotice('Attendance save failed.', 'error');
					return;
				}

				if (response.data.summary) {
					Object.keys(response.data.summary).forEach(function (key) {
						$form.closest('.cmp-panel').find('[data-cmp-attendance-summary="' + key + '"]').text(response.data.summary[key]);
					});
				}

				updateFeedback($feedback, response.data.message || 'Attendance saved.', false);
				showAdminNotice(response.data.message || 'Attendance saved.', 'success');
			}).fail(function (xhr) {
				handleEntityActionFailure(xhr, $feedback);
			}).always(function () {
				$button.prop('disabled', false).removeClass('is-busy');
				if (isInputButton) {
					$button.val(originalText);
				} else {
					$button.text(originalText);
				}
			});
		});

		renderCharts();
	});
})(jQuery);
