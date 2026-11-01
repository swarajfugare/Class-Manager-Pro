(function ($) {
	'use strict';

	const palette = ['#2271b1', '#00a32a', '#dba617', '#d63638', '#8c5fbf', '#008a8a', '#c94f7c'];
	const ajaxEndpoint = window.ajaxurl || CMPAdmin.ajaxUrl;

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

				$option.attr('data-cmp-class-hidden', shouldHide ? '1' : '0');

				if ($option.is(':selected') && shouldHide) {
					currentHidden = true;
				}
			});

			if (currentHidden) {
				$batchSelect.val($batchSelect.find('option').filter(function () {
					return String($(this).attr('data-cmp-class-hidden') || '0') !== '1';
				}).first().val());
			}

			applySelectSearchFilter($batchSelect);
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

	function syncInterestedExportForm() {
		const $exportForm = $('.cmp-interested-export-form').first();
		const $filterForm = $('.cmp-filter-form').filter(function () {
			return String($(this).data('cmp-action') || '') === 'cmp_filter_interested_students';
		}).first();

		if (!$exportForm.length || !$filterForm.length) {
			return;
		}

		['search', 'class_id', 'batch_id', 'follow_up_status', 'email_status', 'whatsapp_status'].forEach(function (name) {
			const $field = $exportForm.find('[name="' + name + '"]').first();
			const $source = $filterForm.find('[name="' + name + '"]').first();

			if ($field.length && $source.length) {
				$field.val($source.val());
			}
		});
	}

	function runAjaxFilter($form) {
		const action = $form.data('cmp-action');
		const target = $form.data('cmp-target');

		if (!action || !target) {
			return;
		}

		const previousXhr = $form.data('cmp-active-xhr');
		if (previousXhr && typeof previousXhr.abort === 'function') {
			previousXhr.abort();
		}

		updateExportLink($form);
		syncInterestedExportForm();

		const data = $form.serializeArray();
		data.push({ name: 'action', value: action });
		data.push({ name: 'nonce', value: getAjaxNonce() });

		$form.addClass('cmp-loading');

		const xhr = $.post(ajaxEndpoint, data)
			.done(function (response) {
				if (response && response.success && response.data && response.data.html) {
					injectAjaxHtml(target, response.data.html);
				}
			})
			.always(function () {
				$form.removeClass('cmp-loading');
				$form.removeData('cmp-active-xhr');
			});

		$form.data('cmp-active-xhr', xhr);
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

	function escapeHtml(text) {
		return $('<div>').text(String(text || '')).html();
	}

	function readTemplateSource(sourceId) {
		if (window.tinymce && window.tinymce.get(sourceId)) {
			return window.tinymce.get(sourceId).getContent();
		}

		const $source = $('#' + sourceId);

		return $source.length ? String($source.val() || $source.text() || '') : '';
	}

	function renderTemplatePreviewHtml(template) {
		const sampleValues = {
			'{{name}}': 'Aarav Patil',
			'{{amount}}': '4,500.00',
			'{{course}}': 'Mathematics',
			'{{batch}}': 'Evening Batch',
			'{{due_date}}': '2026-04-30',
			'{{payment_link}}': 'https://example.com/pay',
			'{student_name}': 'Aarav Patil',
			'{class_name}': 'Mathematics',
			'{batch_name}': 'Evening Batch',
			'{pending_fee}': '4,500.00',
			'{due_date}': '2026-04-30',
			'{payment_link}': 'https://example.com/pay'
		};
		let output = String(template || '');

		Object.keys(sampleValues).forEach(function (key) {
			output = output.split(key).join(sampleValues[key]);
		});

		if (!/[<][a-z!/][\s\S]*[>]/i.test(output)) {
			output = escapeHtml(output).replace(/\n/g, '<br>');
		}

		return output;
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

		if (charts.monthlyAdmissions) {
			renderChart('cmp-analytics-monthly-admissions', {
				type: 'bar',
				data: {
					labels: charts.monthlyAdmissions.labels,
					datasets: [{
						label: 'Admissions',
						data: charts.monthlyAdmissions.values,
						backgroundColor: palette[2]
					}]
				},
				options: {
					maintainAspectRatio: false,
					scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
				}
			});
		}

		if (charts.paymentTrends) {
			renderChart('cmp-analytics-payment-trends', {
				type: 'line',
				data: {
					labels: charts.paymentTrends.labels,
					datasets: [{
						label: 'Payments',
						data: charts.paymentTrends.values,
						borderColor: palette[4],
						backgroundColor: 'rgba(140, 95, 191, 0.15)',
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

		if (charts.registrationTrends) {
			renderChart('cmp-analytics-registration-trends', {
				type: 'bar',
				data: {
					labels: charts.registrationTrends.labels,
					datasets: [{
						label: 'Registrations',
						data: charts.registrationTrends.values,
						backgroundColor: palette[5]
					}]
				},
				options: {
					maintainAspectRatio: false,
					scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
				}
			});
		}

		if (charts.leadSource) {
			renderChart('cmp-analytics-lead-source', {
				type: 'doughnut',
				data: {
					labels: charts.leadSource.labels,
					datasets: [{
						data: charts.leadSource.values,
						backgroundColor: palette
					}]
				},
				options: { maintainAspectRatio: false }
			});
		}

		if (charts.repeatStudents) {
			renderChart('cmp-analytics-repeat-students', {
				type: 'bar',
				data: {
					labels: charts.repeatStudents.labels,
					datasets: [{
						label: 'Repeat Students',
						data: charts.repeatStudents.values,
						backgroundColor: palette[6]
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

	function getElementText($element) {
		return $element.is('input') ? String($element.val() || '') : String($element.text() || '');
	}

	function setElementText($element, value) {
		if ($element.is('input')) {
			$element.val(value);
			return;
		}

		$element.text(value);
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

	function setFormBusyState($form) {
		if (!$form || !$form.length || $form.data('cmp-busy')) {
			return;
		}

		$form.data('cmp-busy', true).attr('aria-busy', 'true');

		$form.find('button[type="submit"], input[type="submit"]').each(function () {
			const $control = $(this);
			const label = $control.is('input') ? String($control.val() || '') : String($control.text() || '');

			if (!$control.data('cmp-original-label')) {
				$control.data('cmp-original-label', label);
			}

			$control.prop('disabled', true).addClass('is-busy');
		});
	}

	function formatProgressMessage(message, progress) {
		if (!progress || !progress.total) {
			return message || '';
		}

		const processed = Number(progress.processed || 0);
		const total = Number(progress.total || 0);
		const percentage = Number(progress.percentage || 0);
		const prefix = message ? String(message) + ' ' : '';

		return prefix + '(' + processed + '/' + total + ' - ' + percentage + '%)';
	}

	function formatCountdown(expiryText) {
		if (!expiryText) {
			return '';
		}

		const expiry = new Date(String(expiryText).replace(' ', 'T'));

		if (Number.isNaN(expiry.getTime())) {
			return String(expiryText);
		}

		const diff = expiry.getTime() - Date.now();

		if (diff <= 0) {
			return 'Expired';
		}

		const totalSeconds = Math.floor(diff / 1000);
		const days = Math.floor(totalSeconds / 86400);
		const hours = Math.floor((totalSeconds % 86400) / 3600);
		const minutes = Math.floor((totalSeconds % 3600) / 60);
		const seconds = totalSeconds % 60;

		if (days > 0) {
			return days + 'd ' + hours + 'h ' + minutes + 'm';
		}

		if (hours > 0) {
			return hours + 'h ' + minutes + 'm ' + seconds + 's';
		}

		return minutes + 'm ' + seconds + 's';
	}

	function updateRegistrationCountdowns() {
		$('[data-cmp-expiry-at]').each(function () {
			const $node = $(this);
			const expiryText = String($node.data('cmp-expiry-at') || '');

			$node.text(formatCountdown(expiryText));
		});
	}

	function requestStudentImportChunk(sessionId, nonce) {
		return $.post(ajaxEndpoint, {
			action: 'cmp_process_student_import_chunk',
			_ajax_nonce: nonce,
			session_id: sessionId
		});
	}

	function runStudentImportSession(sessionId, nonce, $feedback, onComplete, retryCount) {
		const attempts = Number(retryCount || 0);

		requestStudentImportChunk(sessionId, nonce).done(function (response) {
			if (!response || !response.success || !response.data) {
				if (attempts < 3) {
					updateFeedback($feedback, 'Import connection dropped. Retrying...', false);
					window.setTimeout(function () {
						runStudentImportSession(sessionId, nonce, $feedback, onComplete, attempts + 1);
					}, (attempts + 1) * 700);
					return;
				}

				onComplete(new Error('Import failed.'));
				return;
			}

			updateFeedback($feedback, formatProgressMessage(response.data.message || 'Importing...', response.data.progress), false);

			if (response.data.stage === 'completed') {
				onComplete(null, response.data);
				return;
			}

			window.setTimeout(function () {
				runStudentImportSession(sessionId, nonce, $feedback, onComplete, 0);
			}, 50);
		}).fail(function (xhr) {
			if (attempts < 3) {
				updateFeedback($feedback, 'Import connection dropped. Retrying...', false);
				window.setTimeout(function () {
					runStudentImportSession(sessionId, nonce, $feedback, onComplete, attempts + 1);
				}, (attempts + 1) * 700);
				return;
			}

			onComplete(xhr || new Error('Import failed.'));
		});
	}

	function requestRazorpayImportChunk(sessionId, nonce, chunkSize) {
		return $.post(ajaxEndpoint, {
			action: 'cmp_process_razorpay_page_import_chunk',
			_ajax_nonce: nonce,
			session_id: sessionId,
			chunk_size: chunkSize || 25
		});
	}

	function runRazorpayImportSession(sessionId, nonce, $feedback, onComplete, retryCount) {
		const attempts = Number(retryCount || 0);

		requestRazorpayImportChunk(sessionId, nonce, 25).done(function (response) {
			if (!response || !response.success || !response.data) {
				if (attempts < 3) {
					updateFeedback($feedback, 'Import connection dropped. Retrying...', false);
					window.setTimeout(function () {
						runRazorpayImportSession(sessionId, nonce, $feedback, onComplete, attempts + 1);
					}, (attempts + 1) * 700);
					return;
				}

				onComplete(new Error('Import failed.'));
				return;
			}

			updateFeedback($feedback, formatProgressMessage(response.data.message || 'Importing...', response.data.progress), false);

			if (response.data.stage === 'completed') {
				onComplete(null, response.data);
				return;
			}

			window.setTimeout(function () {
				runRazorpayImportSession(sessionId, nonce, $feedback, onComplete, 0);
			}, 150);
		}).fail(function (xhr) {
			if (attempts < 3) {
				updateFeedback($feedback, 'Import connection dropped. Retrying...', false);
				window.setTimeout(function () {
					runRazorpayImportSession(sessionId, nonce, $feedback, onComplete, attempts + 1);
				}, (attempts + 1) * 700);
				return;
			}

			onComplete(xhr || new Error('Import failed.'));
		});
	}

	function selectShouldBeSearchable($select) {
		if (!$select.length || $select.is('[multiple]') || $select.is('[data-cmp-searchable="0"]')) {
			return false;
		}

		return true;
	}

	function getEnhancedSelectWrapper($select) {
		return $select.closest('.cmp-enhanced-select');
	}

	function getSearchQuery($select) {
		const $input = getEnhancedSelectWrapper($select).find('.cmp-enhanced-select-search').first();

		return $input.length ? String($input.val() || '').trim().toLowerCase() : '';
	}

	function updateEnhancedSelectDisplay($select) {
		const $wrapper = getEnhancedSelectWrapper($select);
		const $trigger = $wrapper.find('.cmp-enhanced-select-trigger').first();
		const $label = $wrapper.find('.cmp-enhanced-select-trigger-label').first();
		const $selected = $select.find('option:selected').first();
		const selectedText = $.trim(String($selected.text() || ''));
		const fallbackText = $.trim(String($select.find('option').first().text() || 'Select option'));
		const value = String($select.val() || '');
		const labelText = selectedText || fallbackText || 'Select option';

		if (!$wrapper.length || !$trigger.length || !$label.length) {
			return;
		}

		$label.text(labelText);
		$wrapper.toggleClass('is-placeholder', value === '');
		$trigger.prop('disabled', $select.prop('disabled'));
		$trigger.attr('aria-expanded', $wrapper.hasClass('is-open') ? 'true' : 'false');
	}

	function applySelectSearchFilter($select) {
		const $wrapper = getEnhancedSelectWrapper($select);
		const query = getSearchQuery($select);
		const currentValue = String($select.val() || '');
		const $list = $wrapper.find('.cmp-enhanced-select-options').first();
		let visibleCount = 0;

		if (!$wrapper.length || !$list.length) {
			return;
		}

		$list.empty();

		$select.find('option').each(function () {
			const $option = $(this);
			const value = String($option.attr('value') || '');
			const optionText = $.trim(String($option.text() || ''));
			const normalizedText = optionText.toLowerCase();
			const classHidden = String($option.attr('data-cmp-class-hidden') || '0') === '1';
			const originalDisabled = String($option.attr('data-cmp-original-disabled') || '0') === '1';
			const isSelected = value === currentValue;
			const isPlaceholder = value === '';
			const matchesSearch = query === '' || normalizedText.indexOf(query) !== -1 || isSelected;
			const shouldRender = !classHidden && (matchesSearch || isSelected) && !(query !== '' && isPlaceholder && !isSelected);
			const $optionButton = $('<button type="button" class="cmp-enhanced-select-option"></button>');

			$option.prop('hidden', classHidden).prop('disabled', classHidden ? value !== '' : originalDisabled);

			if (!shouldRender) {
				return;
			}

			$optionButton.attr('data-cmp-option-value', value);
			$optionButton.append($('<span class="cmp-enhanced-select-option-label"></span>').text(optionText || 'Select option'));

			if (originalDisabled && !isSelected) {
				$optionButton.prop('disabled', true);
			}

			if (isSelected) {
				$optionButton.addClass('is-selected');
				$optionButton.append($('<span class="cmp-enhanced-select-option-check" aria-hidden="true"></span>').text('Selected'));
			}

			if (isPlaceholder) {
				$optionButton.addClass('is-placeholder');
			}

			$list.append($optionButton);
			visibleCount++;
		});

		if (!visibleCount) {
			$list.append($('<div class="cmp-enhanced-select-empty"></div>').text('No results found.'));
		}

		updateEnhancedSelectDisplay($select);
	}

	function closeEnhancedSelect($scope) {
		const $wrappers = $scope && $scope.length ? $scope : $('.cmp-enhanced-select.is-open');

		$wrappers.each(function () {
			const $wrapper = $(this);
			const $select = $wrapper.find('select').first();
			const $dropdown = $wrapper.find('.cmp-enhanced-select-dropdown').first();
			const $search = $wrapper.find('.cmp-enhanced-select-search').first();

			$wrapper.removeClass('is-open');
			$dropdown.prop('hidden', true);

			if ($search.length && $search.val()) {
				$search.val('');
			}

			if ($select.length) {
				applySelectSearchFilter($select);
			}
		});
	}

	function openEnhancedSelect($select) {
		const $wrapper = getEnhancedSelectWrapper($select);
		const $dropdown = $wrapper.find('.cmp-enhanced-select-dropdown').first();
		const $search = $wrapper.find('.cmp-enhanced-select-search').first();

		if (!$wrapper.length || $select.prop('disabled')) {
			return;
		}

		closeEnhancedSelect($('.cmp-enhanced-select').not($wrapper));
		$wrapper.addClass('is-open');
		$dropdown.prop('hidden', false);
		applySelectSearchFilter($select);

		window.setTimeout(function () {
			$search.trigger('focus');
		}, 0);
	}

	function refreshSearchableSelect($select) {
		if (!selectShouldBeSearchable($select)) {
			return;
		}

		let $wrapper = getEnhancedSelectWrapper($select);
		let $trigger;
		let $dropdown;
		let $search;
		let $list;

		if (!$wrapper.length) {
			$select.find('option').each(function () {
				const $option = $(this);

				if ($option.attr('data-cmp-original-disabled') === undefined) {
					$option.attr('data-cmp-original-disabled', $option.prop('disabled') && String($option.attr('value') || '') !== '' ? '1' : '0');
				}
			});

			$select.wrap('<div class="cmp-enhanced-select"></div>');
			$wrapper = getEnhancedSelectWrapper($select);
			$select.addClass('cmp-enhanced-select-native');

			$trigger = $('<button type="button" class="cmp-enhanced-select-trigger" aria-haspopup="listbox" aria-expanded="false"></button>');
			$trigger.append('<span class="cmp-enhanced-select-trigger-label"></span>');
			$trigger.append('<span class="cmp-enhanced-select-caret" aria-hidden="true"></span>');

			$dropdown = $('<div class="cmp-enhanced-select-dropdown" hidden></div>');
			$search = $('<input type="search" class="cmp-enhanced-select-search" autocomplete="off" spellcheck="false">');
			$search.attr('placeholder', String($select.data('cmp-search-placeholder') || 'Type to search'));
			$list = $('<div class="cmp-enhanced-select-options" role="listbox"></div>');

			$dropdown.append($search);
			$dropdown.append($list);
			$select.after($trigger);
			$trigger.after($dropdown);
		}

		$select.find('option').each(function () {
			const $option = $(this);

			if ($option.attr('data-cmp-original-disabled') === undefined) {
				$option.attr('data-cmp-original-disabled', $option.prop('disabled') && String($option.attr('value') || '') !== '' ? '1' : '0');
			}
		});

		updateEnhancedSelectDisplay($select);
		applySelectSearchFilter($select);
	}

	function refreshSearchableSelects($scope) {
		const $root = $scope && $scope.length ? $scope : $(document);

		$root.find('.cmp-wrap select, .cmp-form select, .cmp-toolbar select').each(function () {
			refreshSearchableSelect($(this));
		});
	}

	function setAttendanceOptionState($group, status) {
		const nextStatus = String(status || '');
		const $input = $group.find('[data-cmp-attendance-status-input]').first();

		if (!$input.length || !nextStatus) {
			return;
		}

		$input.val(nextStatus);
		$group.find('[data-cmp-attendance-set]').each(function () {
			const $button = $(this);
			const buttonStatus = String($button.data('cmp-attendance-set') || '');
			const isSelected = buttonStatus === nextStatus;
			const classes = String($button.attr('class') || '')
				.split(/\s+/)
				.filter(function (className) {
					return className && className.indexOf('is-') !== 0;
				});

			if (classes.indexOf('button') === -1) {
				classes.push('button');
			}

			if (classes.indexOf('cmp-attendance-option') === -1) {
				classes.push('cmp-attendance-option');
			}

			if (isSelected) {
				classes.push('is-selected');
				classes.push('is-' + buttonStatus);
			}

			$button.attr('class', Array.from(new Set(classes)).join(' '));
			$button.attr('aria-pressed', isSelected ? 'true' : 'false');
		});
	}

	function collectIds(selector) {
		return $(selector).filter(':checked').map(function () {
			return $(this).val();
		}).get();
	}

	function requestEntityAction(payload) {
		return $.post(ajaxEndpoint, $.extend({
			action: 'cmp_admin_entity_action',
			nonce: getAjaxNonce()
		}, payload));
	}

	function requestDelete(payload) {
		return $.post(ajaxEndpoint, $.extend({
			action: 'cmp_delete_item',
			nonce: String(CMPAdmin.deleteNonce || '')
		}, payload));
	}

	function requestStudentEmail(studentId, nonce) {
		return $.post(ajaxEndpoint, {
			action: 'cmp_send_student_follow_up_email',
			nonce: nonce || getAjaxNonce(),
			student_id: studentId
		});
	}

	function requestInterestedStudentEmail(leadId, nonce) {
		return $.post(ajaxEndpoint, {
			action: 'cmp_send_interested_student_email_ajax',
			nonce: nonce || getAjaxNonce(),
			lead_id: leadId
		});
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

	function refreshStudentResults() {
		const $wrap = $('#cmp-student-results-wrap');
		const $form = $('.cmp-filter-form').first();
		const data = $form.length ? $form.serializeArray() : [];

		if (!$wrap.length) {
			return $.Deferred().resolve().promise();
		}

		data.push({ name: 'action', value: 'cmp_filter_students' });
		data.push({ name: 'nonce', value: getAjaxNonce() });

		return $.post(ajaxEndpoint, data).done(function (response) {
			if (response && response.success && response.data && response.data.html) {
				$wrap.html(response.data.html);
			}
		});
	}

	function refreshClassResults() {
		const $tbody = $('#cmp-class-table-body');

		if (!$tbody.length) {
			return $.Deferred().resolve().promise();
		}

		return $.post(ajaxEndpoint, {
			action: 'cmp_filter_classes',
			nonce: getAjaxNonce()
		}).done(function (response) {
			if (response && response.success && response.data && response.data.html) {
				$tbody.html(response.data.html);
			}
		});
	}

	function refreshBatchResults() {
		const $tbody = $('#cmp-batch-table-body');

		if (!$tbody.length) {
			return $.Deferred().resolve().promise();
		}

		return $.post(ajaxEndpoint, {
			action: 'cmp_filter_batches',
			nonce: getAjaxNonce()
		}).done(function (response) {
			if (response && response.success && response.data && response.data.html) {
				$tbody.html(response.data.html);
			}
		});
	}

	function refreshPaymentResults() {
		const $section = $('#cmp-payment-list-section');
		const $form = $section.find('.cmp-filter-form').first();
		const data = $form.length ? $form.serializeArray() : [];

		if (!$section.length) {
			return $.Deferred().resolve().promise();
		}

		data.push({ name: 'action', value: 'cmp_filter_payments' });
		data.push({ name: 'nonce', value: getAjaxNonce() });

		return $.post(ajaxEndpoint, data).done(function (response) {
			if (response && response.success && response.data && response.data.html) {
				$section.replaceWith(response.data.html);
			}
		});
	}

	function refreshInterestedStudentsResults() {
		const $wrap = $('#cmp-interested-table-wrap');
		const $form = $('.cmp-filter-form').filter(function () {
			return String($(this).data('cmp-action') || '') === 'cmp_filter_interested_students';
		}).first();
		const data = $form.length ? $form.serializeArray() : [];

		if (!$wrap.length) {
			return $.Deferred().resolve().promise();
		}

		data.push({ name: 'action', value: 'cmp_filter_interested_students' });
		data.push({ name: 'nonce', value: getAjaxNonce() });

		return $.post(ajaxEndpoint, data).done(function (response) {
			if (response && response.success && response.data && response.data.html) {
				$wrap.html(response.data.html);
			}
		});
	}

	function handleSectionRefresh(refresh) {
		const action = refresh && refresh.action ? String(refresh.action) : '';
		const target = refresh && refresh.target ? String(refresh.target) : '';

		if (action === 'cmp_filter_students') {
			return refreshStudentResults();
		}

		if (action === 'cmp_filter_classes') {
			return refreshClassResults();
		}

		if (action === 'cmp_filter_batches') {
			return refreshBatchResults();
		}

		if (action === 'cmp_filter_payments') {
			return refreshPaymentResults();
		}

		if (action === 'cmp_filter_interested_students') {
			return refreshInterestedStudentsResults();
		}

		if (refresh && refresh.html && target) {
			injectAjaxHtml(target, refresh.html);
		}

		return $.Deferred().resolve().promise();
	}

	function submitAjaxForm($form) {
		const action = String($form.data('cmp-ajax-form') || '');
		const $button = $form.find('input[type="submit"], button[type="submit"]').first();
		const originalText = getElementText($button);
		const formData = new window.FormData($form[0]);
		const formNonce = String(formData.get('_wpnonce') || $form.find('input[name="_wpnonce"]').val() || '');
		const $feedback = $form.find('[data-cmp-feedback]').first();

		if (!action) {
			return false;
		}

		if (formNonce && !formData.get('nonce')) {
			formData.append('nonce', formNonce);
		}

		formData.append('action', action);

		$button.prop('disabled', true).addClass('is-busy');
		setElementText($button, 'Saving...');
		updateFeedback($feedback, 'Saving...', false);

		$.ajax({
			url: ajaxEndpoint,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				updateFeedback($feedback, 'Save failed.', true);
				showAdminNotice('Save failed.', 'error');
				return;
			}

			updateFeedback($feedback, response.data.message || 'Saved successfully.', false);
			showAdminNotice(response.data.message || 'Saved successfully.', 'success');
			handleSectionRefresh(response.data.refresh);

			const shouldReset = !$form.find('[name="id"]').length || !String($form.find('[name="id"]').val() || '').trim();

			if ((shouldReset || $form.is('[data-cmp-reset-on-success="1"]')) && $form[0].reset) {
				$form[0].reset();
			}

			refreshSearchableSelects($form.closest('.cmp-wrap'));
		}).fail(function (xhr) {
			handleEntityActionFailure(xhr, $feedback);
		}).always(function () {
			$button.prop('disabled', false).removeClass('is-busy');
			setElementText($button, originalText);
		});

		return false;
	}

	function updateStatusBadges(rowIds, statusKey, statusLabel) {
		if (!Array.isArray(rowIds) || !statusKey || !statusLabel) {
			return;
		}

		rowIds.forEach(function (rowId) {
			const $badge = $('[data-cmp-status-badge="' + rowId + '"]');

			if (!$badge.length) {
				return;
			}

			const classes = String($badge.attr('class') || '')
				.split(/\s+/)
				.filter(function (className) {
					return className && className.indexOf('cmp-status-') !== 0;
				});

			if (classes.indexOf('cmp-status') === -1) {
				classes.push('cmp-status');
			}

			classes.push('cmp-status-' + statusKey);
			$badge.attr('class', Array.from(new Set(classes)).join(' '));
			$badge.text(statusLabel);
		});
	}

	function enhanceResponsiveTables($scope) {
		const $root = $scope && $scope.length ? $scope : $(document);

		$root.find('.cmp-table-scroll table.widefat, .cmp-panel table.widefat, .cmp-wrap table.widefat').each(function () {
			const $table = $(this);

			if ($table.data('cmp-responsive-ready')) {
				return;
			}

			const headers = [];

			$table.find('thead th').each(function () {
				headers.push($.trim(String($(this).text() || '')));
			});

			$table.find('tbody tr').each(function () {
				const $row = $(this);
				const bodyLabels = [];

				$row.children('th').each(function () {
					bodyLabels.push($.trim(String($(this).text() || '')));
				});

				$row.children('td, th').each(function (index) {
					const $cell = $(this);
					const label = $.trim(String($cell.attr('data-cmp-label') || headers[index] || ''));

					if (label) {
						$cell.attr('data-cmp-label', label);
					}

					if (index === 0) {
						$cell.addClass('cmp-table-primary');
					}
				});

				if (!headers.length && bodyLabels.length) {
					$row.children('td').each(function (index) {
						const $cell = $(this);
						const label = $.trim(String($cell.attr('data-cmp-label') || bodyLabels[index] || ''));

						if (label) {
							$cell.attr('data-cmp-label', label);
						}
					});
				}
			});

			$table.attr('data-cmp-responsive-table', '1');
			$table.data('cmp-responsive-ready', true);
		});
	}

	function injectAjaxHtml(target, html) {
		const content = String(html || '');
		const $target = $(target);

		if (!$target.length) {
			return;
		}

		if ($target.is('section') && /^\s*<section\b/i.test(content)) {
			$target.replaceWith(content);
			window.setTimeout(function () {
				refreshSearchableSelects($('.cmp-wrap'));
				enhanceResponsiveTables($('.cmp-wrap'));
			}, 0);
			return;
		}

		$target.html(content);
		window.setTimeout(function () {
			refreshSearchableSelects($target.closest('.cmp-wrap').length ? $target.closest('.cmp-wrap') : $('.cmp-wrap'));
			enhanceResponsiveTables($target.closest('.cmp-wrap').length ? $target.closest('.cmp-wrap') : $('.cmp-wrap'));
		}, 0);
	}

	$(function () {
		const delayedFilter = debounce(function () {
			runAjaxFilter($(this).closest('.cmp-filter-form'));
		}, 500);

		refreshSearchableSelects($('.cmp-wrap'));
		enhanceResponsiveTables($('.cmp-wrap'));

		// Run registration countdown timers efficiently only when elements exist on page
		if ($('[data-cmp-countdown], .cmp-countdown-target').length > 0) {
			updateRegistrationCountdowns();
			const countdownTimer = window.setInterval(function () {
				if (document.hidden) {
					return;
				}
				if (!$('[data-cmp-countdown], .cmp-countdown-target').length) {
					clearInterval(countdownTimer);
					return;
				}
				updateRegistrationCountdowns();
			}, 1000);
		}

		$('[data-cmp-class-select]').each(function () {
			const $select = $(this);
			updateBatchOptions($select);
		});

		$(document).on('change', '[data-cmp-class-select]', function () {
			const $select = $(this);
			updateBatchOptions($select);
			$select.closest('form, .cmp-wrap').find('[data-cmp-batches]').each(function () {
				maybeFillBatchFee($(this));
				refreshSearchableSelect($(this));
			});
		});

		$('[data-cmp-batches]').each(function () {
			maybeFillBatchFee($(this));
		});

		$(document).on('change', '[data-cmp-batches]', function () {
			maybeFillBatchFee($(this));
		});

		$(document).on('change', '.cmp-enhanced-select-native', function () {
			refreshSearchableSelect($(this));
		});

		$(document).on('click', '.cmp-enhanced-select-trigger', function (event) {
			const $trigger = $(this);
			const $select = $trigger.closest('.cmp-enhanced-select').find('select').first();

			event.preventDefault();

			if (!$select.length) {
				return;
			}

			if ($trigger.closest('.cmp-enhanced-select').hasClass('is-open')) {
				closeEnhancedSelect($trigger.closest('.cmp-enhanced-select'));
				return;
			}

			openEnhancedSelect($select);
		});

		$(document).on('input', '.cmp-enhanced-select-search', function () {
			const $input = $(this);
			const $select = $input.closest('.cmp-enhanced-select').find('select').first();

			if (!$select.length) {
				return;
			}

			applySelectSearchFilter($select);
		});

		$(document).on('keydown', '.cmp-enhanced-select-trigger', function (event) {
			const $trigger = $(this);
			const $select = $trigger.closest('.cmp-enhanced-select').find('select').first();

			if (!$select.length) {
				return;
			}

			if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				openEnhancedSelect($select);
			}

			if (event.key === 'Escape') {
				closeEnhancedSelect($trigger.closest('.cmp-enhanced-select'));
			}
		});

		$(document).on('keydown', '.cmp-enhanced-select-search', function (event) {
			if (event.key !== 'Escape') {
				return;
			}

			const $wrapper = $(this).closest('.cmp-enhanced-select');
			const $trigger = $wrapper.find('.cmp-enhanced-select-trigger').first();

			closeEnhancedSelect($wrapper);
			$trigger.trigger('focus');
		});

		$(document).on('click', '.cmp-enhanced-select-option', function (event) {
			const $option = $(this);
			const $wrapper = $option.closest('.cmp-enhanced-select');
			const $select = $wrapper.find('select').first();
			const nextValue = String($option.attr('data-cmp-option-value') || '');

			event.preventDefault();

			if (!$select.length || $option.prop('disabled')) {
				return;
			}

			$select.val(nextValue).trigger('change');
			closeEnhancedSelect($wrapper);
		});

		$(document).on('mousedown', function (event) {
			const $target = $(event.target);

			if ($target.closest('.cmp-enhanced-select').length) {
				return;
			}

			closeEnhancedSelect();
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

		syncInterestedExportForm();

		$(document).on('submit', '.cmp-filter-form', function (event) {
			if (!$(this).data('cmp-action')) {
				return;
			}

			event.preventDefault();
			runAjaxFilter($(this));
		});

		$(document).on('submit', 'form[data-cmp-ajax-form]', function (event) {
			event.preventDefault();
			submitAjaxForm($(this));
		});

		$(document).on('submit', 'form[action*="admin-post.php"]:not([data-cmp-confirm])', function (event) {
			if (event.isDefaultPrevented()) {
				return;
			}

			if ($(this).data('cmp-ajax-form')) {
				return;
			}

			setFormBusyState($(this));
		});

		$(document).on('input change', '.cmp-filter-form input, .cmp-filter-form select', function () {
			if ($(this).hasClass('cmp-enhanced-select-search')) {
				return;
			}

			const $form = $(this).closest('.cmp-filter-form');
			if (!$form.data('cmp-action')) {
				return;
			}

			// Reduce server requests: for text/search inputs, debounce and only query if >= 2 characters or cleared
			if ($(this).is('input[type="search"], input[type="text"]')) {
				const queryVal = String($(this).val() || '').trim();
				if (queryVal.length > 0 && queryVal.length < 2) {
					return;
				}
			}

			delayedFilter.call(this);
		});

		$(document).on('click', '.cmp-delete-link', function (event) {
			const $link = $(this);
			const message = String($link.data('cmp-confirm') || 'Are you sure you want to delete?');

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
			const isPaymentDelete = entityType === 'payment';

			if (!entityType || !entityId) {
				return;
			}

			const busyLabel = isPaymentDelete ? 'Moving to Trash...' : 'Deleting...';
			const failureMessage = isPaymentDelete ? 'Move to Trash failed.' : 'Delete failed.';
			const successMessage = isPaymentDelete ? 'Moved to Trash.' : 'Deleted.';

			$link.addClass('is-busy').attr('aria-disabled', 'true').text(busyLabel);
			updateFeedback($feedback, busyLabel, false);

			requestDelete({
				id: entityId,
				type: entityType
			}).done(function (response) {
				if (!response || !response.success || !response.data) {
					updateFeedback($feedback, failureMessage, true);
					showAdminNotice(failureMessage, 'error');
					return;
				}

				removeDeletedRows([response.data.deleted_row || (entityType + '-' + entityId)]);
				updateFeedback($feedback, response.data.message || successMessage, false);
				showAdminNotice(response.data.message || successMessage, 'success');

				if ($link.data('cmp-refresh-page')) {
					window.location.reload();
					return;
				}

				if (entityType === 'student') {
					refreshStudentResults();
				}
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
				return;
			}

			setFormBusyState($(this));
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

		$(document).on('click', '[data-cmp-template-preview]', function () {
			const sourceId = String($(this).data('cmp-template-preview') || '');
			const targetSelector = String($(this).data('cmp-preview-target') || '');
			const $target = $(targetSelector);

			if (!sourceId || !$target.length) {
				return;
			}

			$target.html(renderTemplatePreviewHtml(readTemplateSource(sourceId))).addClass('is-visible');
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
			const statusSelector = String($button.data('cmp-status-select') || '');
			const $feedback = $($button.data('cmp-feedback') || '');
			const task = String($(actionSelector).val() || '');
			const targetStatus = statusSelector ? String($(statusSelector).val() || '') : '';
			const ids = collectIds(checkboxSelector);
			const confirmMessages = {
				class: 'Delete the selected classes?',
				batch: 'Delete the selected batches?',
				payment: 'Move the selected payments to Trash?'
			};
			const confirmMessage = confirmMessages[entityType] || 'Delete the selected records?';

			if (!task) {
				updateFeedback($feedback, 'Choose a bulk action.', true);
				return;
			}

			if (!ids.length) {
				updateFeedback($feedback, 'Select at least one record.', true);
				return;
			}

			if (task === 'change_status' && !targetStatus) {
				updateFeedback($feedback, 'Choose a status first.', true);
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
				ids: ids,
				target_status: targetStatus
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

				if (entityType === 'batch') {
					updateStatusBadges(response.data.updated_rows || [], response.data.updated_status || '', response.data.updated_status_label || '');
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

		$(document).on('submit', '.cmp-interested-export-form', function () {
			const $form = $(this);
			const scope = String($form.find('[name="scope"]').val() || '');
			const $selectedField = $form.find('[name="selected_ids"]').first();
			const selectedIds = $('.cmp-interested-select:checked').map(function () {
				return $(this).val();
			}).get();
			const $feedback = $form.find('[data-cmp-export-feedback]').first();

			$selectedField.val(scope === 'selected' ? selectedIds.join(',') : '');

			if (scope === 'selected' && !selectedIds.length) {
				if ($feedback.length) {
					updateFeedback($feedback, 'Select at least one interested student before exporting selected rows.', true);
				}
				return false;
			}

			if ($feedback.length) {
				updateFeedback($feedback, '', false);
			}

			return true;
		});

		$(document).on('click', '#cmp-student-bulk-apply', function () {
			const $button = $(this);
			const $feedback = $('#cmp-student-bulk-feedback');
			const action = String($('#cmp-student-bulk-action').val() || '');
			const targetStatus = String($('#cmp-student-bulk-status').val() || '');
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

			if (action === 'change_status' && !targetStatus) {
				updateFeedback($feedback, 'Choose a status first.', true);
				return;
			}

			if (action === 'move_to_batch' && (!$('#cmp-student-bulk-class').val() || $('#cmp-student-bulk-class').val() === '0' || !$('#cmp-student-bulk-batch').val() || $('#cmp-student-bulk-batch').val() === '0')) {
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
				target_status: targetStatus,
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

				removeDeletedRows(response.data.deleted_rows || []);
				updateFeedback($feedback, response.data.message || 'Done.', false);
				showAdminNotice(response.data.message || 'Done.', 'success');
				if (action !== 'export') {
					refreshStudentResults();
				}
			}).fail(function (xhr) {
				handleEntityActionFailure(xhr, $feedback);
			}).always(function () {
				$button.prop('disabled', false).removeClass('is-busy');
			});
		});

		$(document).on('click', '[data-cmp-send-email="1"]', function (event) {
			const $button = $(this);
			const studentId = String($button.data('cmp-student-id') || '');
			const $feedback = $($button.data('cmp-feedback') || '');
			const href = String($button.attr('href') || '');
			const parsedUrl = href ? new URL(href, window.location.href) : null;
			const linkNonce = parsedUrl ? String(parsedUrl.searchParams.get('_wpnonce') || '') : '';
			const originalText = getElementText($button);

			if (!studentId) {
				return;
			}

			event.preventDefault();
			$button.prop('disabled', true).attr('aria-disabled', 'true').addClass('is-busy');
			setElementText($button, 'Sending...');
			updateFeedback($feedback, 'Sending email...', false);

			requestStudentEmail(studentId, linkNonce).done(function (response) {
				if (!response || !response.success || !response.data) {
					updateFeedback($feedback, 'Email could not be sent.', true);
					showAdminNotice('Email could not be sent.', 'error');
					return;
				}

				updateFeedback($feedback, response.data.message || 'Email sent successfully.', false);
				showAdminNotice(response.data.message || 'Email sent successfully.', 'success');
			}).fail(function (xhr) {
				handleEntityActionFailure(xhr, $feedback);
			}).always(function () {
				$button.prop('disabled', false).removeAttr('aria-disabled').removeClass('is-busy');
				setElementText($button, originalText);
			});
		});

		$(document).on('click', '[data-cmp-send-wa="1"]', function (event) {
			const $button = $(this);
			const studentId = String($button.data('cmp-student-id') || '');
			const $feedback = $($button.data('cmp-feedback') || '');
			const originalText = getElementText($button);

			if (!studentId) {
				return;
			}

			event.preventDefault();
			$button.prop('disabled', true).attr('aria-disabled', 'true').addClass('is-busy');
			setElementText($button, 'Sending...');
			updateFeedback($feedback, 'Sending WhatsApp message...', false);

			$.post(ajaxEndpoint, {
				action: 'cmp_send_student_direct_whatsapp',
				student_id: studentId,
				_wpnonce: getAjaxNonce()
			}).done(function (response) {
				if (!response || !response.success || !response.data) {
					const msg = response && response.data && response.data.message ? response.data.message : 'WhatsApp delivery failed.';
					updateFeedback($feedback, msg, true);
					showAdminNotice(msg, 'error');
					return;
				}

				updateFeedback($feedback, response.data.message || 'WhatsApp message delivered!', false);
				showAdminNotice(response.data.message || 'WhatsApp message delivered!', 'success');
			}).fail(function (xhr) {
				handleEntityActionFailure(xhr, $feedback);
			}).always(function () {
				$button.prop('disabled', false).removeAttr('aria-disabled').removeClass('is-busy');
				setElementText($button, originalText);
			});
		});

		$(document).on('click', '[data-cmp-interested-email="1"]', function (event) {
			const $button = $(this);
			const leadId = String($button.data('cmp-lead-id') || '');
			const $feedback = $($button.data('cmp-feedback') || '');
			const href = String($button.attr('href') || '');
			const parsedUrl = href ? new URL(href, window.location.href) : null;
			const linkNonce = parsedUrl ? String(parsedUrl.searchParams.get('_wpnonce') || '') : '';
			const originalText = getElementText($button);

			if (!leadId) {
				return;
			}

			event.preventDefault();
			$button.prop('disabled', true).attr('aria-disabled', 'true').addClass('is-busy');
			setElementText($button, 'Sending...');
			updateFeedback($feedback, 'Sending email...', false);

			requestInterestedStudentEmail(leadId, linkNonce).done(function (response) {
				if (!response || !response.success || !response.data) {
					updateFeedback($feedback, 'Email could not be sent.', true);
					showAdminNotice('Email could not be sent.', 'error');
					return;
				}

				updateFeedback($feedback, response.data.message || 'Email sent successfully.', false);
				showAdminNotice(response.data.message || 'Email sent successfully.', 'success');
			}).fail(function (xhr) {
				handleEntityActionFailure(xhr, $feedback);
			}).always(function () {
				$button.prop('disabled', false).removeAttr('aria-disabled').removeClass('is-busy');
				setElementText($button, originalText);
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

			$.post(ajaxEndpoint, data).done(function (response) {
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

				if (response.data.date) {
					const $dot = $('[data-cmp-attendance-date-dot="' + response.data.date + '"]');

					$dot.addClass('is-visible');
					$dot.closest('.cmp-attendance-date-pill').addClass('is-recorded');
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

		$(document).on('click', '[data-cmp-attendance-set]', function () {
			const $button = $(this);
			const $group = $button.closest('[data-cmp-attendance-toggle="1"]');
			const status = String($button.data('cmp-attendance-set') || '');

			if (!$group.length || !status) {
				return;
			}

			setAttendanceOptionState($group, status);
		});

		$(document).on('submit', 'form[data-cmp-ajax-import]', function (event) {
			const $form = $(this);
			const $feedback = $($form.data('cmp-feedback') || '');
			const $button = $form.find('input[type="submit"], button[type="submit"]').first();
			const originalText = getElementText($button);
			const formData = new window.FormData($form[0]);
			const importType = String($form.data('cmp-ajax-import') || '');
			const formNonce = String($form.find('input[name="_wpnonce"]').val() || '');
			const busyLabel = String($form.data('cmp-busy-label') || 'Importing...');

			function restoreButton() {
				$button.prop('disabled', false).removeClass('is-busy');
				setElementText($button, originalText);
				refreshSearchableSelects($form.closest('.cmp-wrap'));
			}

			event.preventDefault();

			$button.prop('disabled', true).addClass('is-busy');
			setElementText($button, busyLabel);
			updateFeedback($feedback, busyLabel, false);

			$.ajax({
				url: ajaxEndpoint,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false
			}).done(function (response) {
				if (!response || !response.success || !response.data) {
					updateFeedback($feedback, 'Import failed.', true);
					showAdminNotice('Import failed.', 'error');
					restoreButton();
					return;
				}

				if (importType === 'student-file' && response.data.session_id) {
					updateFeedback($feedback, formatProgressMessage(response.data.message || 'Importing...', response.data.progress), false);

					runStudentImportSession(response.data.session_id, formNonce, $feedback, function (error, finalData) {
						if (error) {
							if (error.responseJSON) {
								handleEntityActionFailure(error, $feedback);
							} else {
								updateFeedback($feedback, 'Import failed.', true);
								showAdminNotice('Import failed.', 'error');
							}

							restoreButton();
							return;
						}

						updateFeedback($feedback, finalData && finalData.message ? finalData.message : 'Import completed.', false);
						showAdminNotice(finalData && finalData.message ? finalData.message : 'Import completed.', 'success');
						$form.find('input[type="file"]').val('');
						restoreButton();
					});

					return;
				}

				if (importType === 'razorpay-page' && response.data.session_id) {
					updateFeedback($feedback, formatProgressMessage(response.data.message || 'Importing...', response.data.progress), false);

					runRazorpayImportSession(response.data.session_id, formNonce, $feedback, function (error, finalData) {
						if (error) {
							if (error.responseJSON) {
								handleEntityActionFailure(error, $feedback);
							} else {
								updateFeedback($feedback, 'Import failed.', true);
								showAdminNotice('Import failed.', 'error');
							}

							restoreButton();
							return;
						}

						updateFeedback($feedback, finalData && finalData.message ? finalData.message : 'Import completed.', false);
						showAdminNotice(finalData && finalData.message ? finalData.message : 'Import completed.', 'success');
						restoreButton();
					});

					return;
				}

				if (importType === 'razorpay-sync-all') {
					var syncData = response.data || {};
					var feedbackHtml = '<div class="notice notice-success inline" style="margin:12px 0;padding:12px 14px;border-left-color:#10b981;background:#f0fdf4;border-radius:4px;">' +
						'<strong style="display:block;color:#065f46;font-size:14px;margin-bottom:6px;">' + (syncData.message || 'Payment pages synced successfully.') + '</strong>';

					if (syncData.batch_stats && syncData.batch_stats.length > 0) {
						feedbackHtml += '<ul style="margin:8px 0 0 16px;list-style:disc;color:#1e3a8a;font-size:13px;line-height:1.6;">';
						$.each(syncData.batch_stats, function(idx, item) {
							feedbackHtml += '<li><strong>' + item.batch_name + ':</strong> ' + item.captured + ' enrolled, ' + item.failed + ' leads, ' + item.duplicates + ' skipped</li>';
						});
						feedbackHtml += '</ul>';
					}
					feedbackHtml += '</div>';

					$feedback.html(feedbackHtml);
					showAdminNotice(syncData.message || 'Sync completed successfully.', 'success');
					restoreButton();
					return;
				}

				updateFeedback($feedback, response.data.message || 'Import completed.', false);
				showAdminNotice(response.data.message || 'Import completed.', 'success');

				$form.find('input[type="file"]').val('');
				restoreButton();
			}).fail(function (xhr) {
				handleEntityActionFailure(xhr, $feedback);
				restoreButton();
			});
		});

		$(document).on('click', '#cmp-wa-test-btn', function (event) {
			event.preventDefault();
			const $button = $(this);
			const $phoneInput = $('#cmp-wa-test-phone');
			const $feedback = $('#cmp-wa-test-feedback');
			const phoneVal = String($phoneInput.val() || '').trim();

			if (!phoneVal) {
				updateFeedback($feedback, 'Please enter a test mobile number.', true);
				return;
			}

			$button.prop('disabled', true).text('Sending...');
			updateFeedback($feedback, 'Dispatching test message...', false);

			$.post(ajaxEndpoint, {
				action: 'cmp_test_whatsapp_connection',
				_ajax_nonce: String($('input[name="cmp_admin_ajax_nonce"]').first().val() || CMPAdmin.nonce || ''),
				phone: phoneVal
			}).done(function (res) {
				$button.prop('disabled', false).text('Send Test Message');
				if (res && res.success) {
					if (res.data && res.data.mode === 'link' && res.data.action_url) {
						updateFeedback($feedback, res.data.message || 'Direct link ready.', false);
						window.open(res.data.action_url, '_blank');
					} else {
						updateFeedback($feedback, res.data && res.data.message ? res.data.message : 'Message sent successfully!', false);
					}
				} else {
					updateFeedback($feedback, res && res.data && res.data.message ? res.data.message : 'Delivery failed.', true);
				}
			}).fail(function (xhr) {
				$button.prop('disabled', false).text('Send Test Message');
				updateFeedback($feedback, 'Connection or server error during WhatsApp test.', true);
			});
		});

		renderCharts();
	});
})(jQuery);
