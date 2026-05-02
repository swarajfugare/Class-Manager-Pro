/**
 * Class Manager Pro v2 - Admin JavaScript
 * Enhanced UX: AJAX filtering, smart search, inline editing, modals, keyboard shortcuts
 */

(function($) {
    'use strict';

    var CMPAdmin = {
        init: function() {
            this.setupSmartSearch();
            this.setupAJAXFilters();
            this.setupQuickAddModals();
            this.setupInlineEditing();
            this.setupGlobalSearch();
            this.setupNotifications();
            this.setupKeyboardShortcuts();
            this.setupAutoRefresh();
            this.setupBulkActions();
            this.setupHealthCheck();
            this.setupBackupManager();
            this.setupTooltips();
        },

        // ===== Smart Search (replaces large dropdowns) =====
        setupSmartSearch: function() {
            var self = this;
            $(document).on('focus', '.cmp-smart-search', function() {
                var $input = $(this);
                var type = $input.data('type');
                self.initSmartSearch($input, type);
            });
        },

        initSmartSearch: function($input, type) {
            if ($input.data('autocomplete-initialized')) return;
            $input.data('autocomplete-initialized', true);

            var ajaxUrl = CMPAdminV2.ajaxUrl;
            var nonce = CMPAdminV2.nonce;

            $input.autocomplete({
                source: function(request, response) {
                    var data = {
                        action: 'cmp_smart_search_' + type + 's',
                        term: request.term,
                        nonce: nonce
                    };
                    if ($input.data('class-id')) {
                        data.class_id = $input.data('class-id');
                    }
                    $.getJSON(ajaxUrl, data, function(res) {
                        response(res || []);
                    }).fail(function() {
                        response([]);
                    });
                },
                minLength: 1,
                select: function(event, ui) {
                    var targetId = $input.data('target');
                    $('#' + targetId).val(ui.item.id);
                    $input.val(ui.item.label);

                    // Trigger change for dependent fields
                    if (type === 'student') {
                        $(document).trigger('cmp:studentSelected', [ui.item]);
                    }
                    return false;
                },
                open: function() {
                    $(this).autocomplete('widget').addClass('cmp-autocomplete-dropdown');
                }
            }).autocomplete('instance')._renderItem = function(ul, item) {
                return $('<li>')
                    .append('<div class="cmp-ac-item"><strong>' + self.escapeHtml(item.label) + '</strong><br><small>' + self.escapeHtml(item.subtitle || '') + '</small></div>')
                    .appendTo(ul);
            };
        },

        // ===== AJAX Filtering =====
        setupAJAXFilters: function() {
            var self = this;

            // Students filter
            $(document).on('submit', '.cmp-filter-form[data-page="students"]', function(e) {
                e.preventDefault();
                self.filterStudents($(this));
            });

            // Payments filter
            $(document).on('submit', '.cmp-filter-form[data-page="payments"]', function(e) {
                e.preventDefault();
                self.filterPayments($(this));
            });

            // Batches filter
            $(document).on('submit', '.cmp-filter-form[data-page="batches"]', function(e) {
                e.preventDefault();
                self.filterBatches($(this));
            });

            // Debounced live search on filter forms
            var searchTimeout;
            $(document).on('input', '.cmp-filter-form input[name="search"]', function() {
                clearTimeout(searchTimeout);
                var $form = $(this).closest('form');
                searchTimeout = setTimeout(function() {
                    $form.trigger('submit');
                }, 400);
            });
        },

        filterStudents: function($form) {
            var self = this;
            self.showFilterLoading();

            $.post(CMPAdminV2.ajaxUrl, {
                action: 'cmp_filter_students',
                nonce: CMPAdminV2.nonce,
                search: $form.find('input[name="search"]').val(),
                class_id: $form.find('select[name="class_id"]').val(),
                batch_id: $form.find('select[name="batch_id"]').val(),
                status: $form.find('select[name="status"]').val(),
                page: 1
            }, function(res) {
                self.hideFilterLoading();
                if (res.success) {
                    $('#cmp-student-list-container').html(res.data.html);
                    if (res.data.pagination) {
                        $('.cmp-tablenav').html(res.data.pagination);
                    }
                    self.showToast(res.data.total + ' ' + CMPAdminV2.labels.resultsFound || 'results found');
                }
            }, 'json');
        },

        filterPayments: function($form) {
            var self = this;
            self.showFilterLoading();

            $.post(CMPAdminV2.ajaxUrl, {
                action: 'cmp_filter_payments',
                nonce: CMPAdminV2.nonce,
                search: $form.find('input[name="search"]').val(),
                payment_mode: $form.find('select[name="payment_mode"]').val(),
                balance_status: $form.find('select[name="balance_status"]').val(),
                assignment_status: $form.find('select[name="assignment_status"]').val(),
                deleted_status: $form.find('select[name="deleted_status"]').val() || 'active',
                page: 1
            }, function(res) {
                self.hideFilterLoading();
                if (res.success) {
                    $('#cmp-payment-list-container').html(res.data.html);
                }
            }, 'json');
        },

        filterBatches: function($form) {
            var self = this;
            self.showFilterLoading();

            $.post(CMPAdminV2.ajaxUrl, {
                action: 'cmp_filter_batches',
                nonce: CMPAdminV2.nonce,
                class_id: $form.find('select[name="class_id"]').val(),
                status: $form.find('select[name="status"]').val()
            }, function(res) {
                self.hideFilterLoading();
                if (res.success) {
                    $('#cmp-batch-list-container').html(res.data.html);
                }
            }, 'json');
        },

        showFilterLoading: function() {
            $('.cmp-ajax-filter-status').show();
        },

        hideFilterLoading: function() {
            $('.cmp-ajax-filter-status').hide();
        },

        // ===== Quick Add Modals =====
        setupQuickAddModals: function() {
            var self = this;

            $(document).on('click', '.cmp-quick-add-btn', function() {
                var type = $(this).data('type');
                $('#cmp-modal-' + type).fadeIn(150).find('input:first').focus();
            });

            $(document).on('click', '.cmp-modal-close', function() {
                $(this).closest('.cmp-modal').fadeOut(150);
            });

            $(document).on('click', '.cmp-modal', function(e) {
                if (e.target === this) {
                    $(this).fadeOut(150);
                }
            });

            // Quick student form
            $(document).on('submit', '#cmp-quick-student-form', function(e) {
                e.preventDefault();
                self.submitQuickAdd('student', $(this));
            });

            // Quick payment form
            $(document).on('submit', '#cmp-quick-payment-form', function(e) {
                e.preventDefault();
                self.submitQuickAdd('payment', $(this));
            });

            // Load batches when class changes in quick add
            $(document).on('change', '#cmp-quick-class-select', function() {
                var classId = $(this).val();
                var $batchSelect = $('#cmp-quick-batch-select');
                $batchSelect.html('<option value="">' + CMPAdminV2.labels.loading + '</option>');

                $.getJSON(CMPAdminV2.ajaxUrl, {
                    action: 'cmp_get_batches',
                    nonce: CMPAdminV2.nonce,
                    class_id: classId
                }, function(res) {
                    if (res.success && res.data.batches) {
                        var options = '<option value="">' + CMPAdminV2.labels.selectBatch + '</option>';
                        $.each(res.data.batches, function(i, batch) {
                            options += '<option value="' + batch.id + '">' + self.escapeHtml(batch.batch_name) + '</option>';
                        });
                        $batchSelect.html(options);
                    }
                });
            });
        },

        submitQuickAdd: function(type, $form) {
            var self = this;
            var $btn = $form.find('button[type="submit"]');
            var $text = $btn.find('.cmp-btn-text');
            var $spinner = $btn.find('.cmp-spinner');

            $text.hide();
            $spinner.show();
            $btn.prop('disabled', true);

            var data = $form.serialize();
            data += '&action=cmp_quick_add_' + type + '&nonce=' + CMPAdminV2.nonce;

            $.post(CMPAdminV2.ajaxUrl, data, function(res) {
                $text.show();
                $spinner.hide();
                $btn.prop('disabled', false);

                if (res.success) {
                    self.showToast(res.data.message, 'success');
                    $form[0].reset();
                    $('#cmp-modal-' + type).fadeOut(150);
                    // Refresh the list if on the relevant page
                    location.reload();
                } else {
                    self.showToast(res.data.message || CMPAdminV2.labels.error, 'error');
                }
            }, 'json');
        },

        // ===== Inline Editing =====
        setupInlineEditing: function() {
            var self = this;

            $(document).on('dblclick', '.cmp-inline-editable', function() {
                var $cell = $(this);
                if ($cell.find('input, select').length) return;

                var value = $cell.data('value') || $cell.text().trim();
                var field = $cell.data('field');
                var entity = $cell.data('entity');
                var id = $cell.data('id');

                var $input;
                if (field === 'status') {
                    $input = $('<select>').html(
                        '<option value="Active">Active</option>' +
                        '<option value="Inactive">Inactive</option>' +
                        '<option value="Drop">Drop</option>'
                    ).val(value);
                } else {
                    $input = $('<input type="text">').val(value);
                }

                $cell.html($input);
                $input.focus().select();

                var saveEdit = function() {
                    var newValue = $input.val();
                    $cell.html($cell.data('original-html') || newValue).data('value', newValue);

                    $.post(CMPAdminV2.ajaxUrl, {
                        action: 'cmp_inline_edit_' + entity,
                        nonce: CMPAdminV2.nonce,
                        entity_id: id,
                        field: field,
                        value: newValue
                    }, function(res) {
                        if (res.success) {
                            self.showToast(CMPAdminV2.labels.saved || 'Saved!', 'success');
                        } else {
                            self.showToast(res.data.message || CMPAdminV2.labels.error, 'error');
                        }
                    }, 'json');
                };

                $input.on('blur', saveEdit).on('keydown', function(e) {
                    if (e.which === 13) { // Enter
                        saveEdit();
                    } else if (e.which === 27) { // Escape
                        $cell.html($cell.data('original-html') || value);
                    }
                });

                $cell.data('original-html', $cell.html());
            });
        },

        // ===== Global Search =====
        setupGlobalSearch: function() {
            var self = this;
            var $input = $('#cmp-global-search-input');
            var $results = $('#cmp-global-search-results');
            var searchTimeout;

            $input.on('input', function() {
                var query = $(this).val().trim();
                if (query.length < 2) {
                    $results.hide();
                    return;
                }

                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    $.post(CMPAdminV2.ajaxUrl, {
                        action: 'cmp_quick_search',
                        nonce: CMPAdminV2.nonce,
                        q: query
                    }, function(res) {
                        if (res.success) {
                            self.renderSearchResults(res.data.results);
                        }
                    }, 'json');
                }, 200);
            });

            $input.on('focus', function() {
                if ($(this).val().trim().length >= 2) {
                    $results.show();
                }
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('.cmp-global-search, .cmp-search-dropdown').length) {
                    $results.hide();
                }
            });
        },

        renderSearchResults: function(results) {
            var $list = $('#cmp-global-search-results .cmp-search-list');
            $list.empty();

            if (!results.length) {
                $list.html('<div class="cmp-search-no-results">' + CMPAdminV2.labels.noResults + '</div>');
                $('#cmp-global-search-results').show();
                return;
            }

            $.each(results, function(i, item) {
                var $item = $('<div class="cmp-search-item">')
                    .append('<span class="dashicons ' + item.icon + '"></span>')
                    .append('<div class="cmp-search-item-info"><strong>' + item.title + '</strong><br><small>' + item.subtitle + '</small></div>');

                $item.on('click', function() {
                    window.location.href = item.url;
                });

                $list.append($item);
            });

            $('#cmp-global-search-results').show();
        },

        // ===== Notifications =====
        setupNotifications: function() {
            var self = this;

            // Floating badge click
            $(document).on('click', '#cmp-notification-badge', function() {
                $('#cmp-notification-panel').toggle();
                self.loadNotifications();
            });

            // Mark all read
            $(document).on('click', '#cmp-mark-all-read', function() {
                $.post(CMPAdminV2.ajaxUrl, {
                    action: 'cmp_mark_all_read',
                    nonce: CMPAdminV2.nonce
                }, function(res) {
                    if (res.success) {
                        self.loadNotifications();
                        $('#cmp-notification-badge').fadeOut();
                    }
                }, 'json');
            });

            // Dismiss single
            $(document).on('click', '.cmp-dismiss-note', function() {
                var $btn = $(this);
                $.post(CMPAdminV2.ajaxUrl, {
                    action: 'cmp_dismiss_notification',
                    nonce: CMPAdminV2.nonce,
                    id: $btn.data('id')
                }, function() {
                    $btn.closest('.cmp-notification-item').fadeOut(200);
                }, 'json');
            });

            // Periodic check for new notifications
            if (CMPAdminV2.features.notifications) {
                setInterval(function() {
                    self.checkNewNotifications();
                }, 60000);
            }
        },

        loadNotifications: function() {
            var $list = $('#cmp-notification-panel .cmp-notification-list');
            $list.html('<div class="cmp-loading">' + CMPAdminV2.labels.loading + '</div>');

            $.post(CMPAdminV2.ajaxUrl, {
                action: 'cmp_get_notifications',
                nonce: CMPAdminV2.nonce
            }, function(res) {
                if (res.success && res.data.notifications) {
                    $list.empty();
                    $.each(res.data.notifications, function(i, note) {
                        var typeClass = 'cmp-notification-' + note.type;
                        var icon = note.type === 'success' ? 'yes' : (note.type === 'error' ? 'no' : 'info');
                        $list.append(
                            '<div class="cmp-notification-item ' + typeClass + ' ' + (note.read ? 'cmp-read' : 'cmp-unread') + '">' +
                            '<span class="dashicons dashicons-' + icon + '"></span>' +
                            '<p>' + note.message + '</p>' +
                            '</div>'
                        );
                    });
                }
            }, 'json');
        },

        checkNewNotifications: function() {
            $.post(CMPAdminV2.ajaxUrl, {
                action: 'cmp_get_notifications',
                nonce: CMPAdminV2.nonce
            }, function(res) {
                if (res.success && res.data.unread_count > 0) {
                    var $badge = $('#cmp-notification-badge');
                    $badge.find('.cmp-notification-count').text(res.data.unread_count);
                    $badge.show().addClass('cmp-pulse');
                    setTimeout(function() { $badge.removeClass('cmp-pulse'); }, 2000);
                }
            }, 'json');
        },

        // ===== Keyboard Shortcuts =====
        setupKeyboardShortcuts: function() {
            var self = this;

            $(document).on('keydown', function(e) {
                // Ctrl+K: Global search
                if (e.ctrlKey && e.which === 75) {
                    e.preventDefault();
                    $('#cmp-global-search-input').focus().select();
                }

                // Ctrl+N: Quick add
                if (e.ctrlKey && e.which === 78) {
                    e.preventDefault();
                    $('.cmp-quick-add-btn:first').trigger('click');
                }

                // Ctrl+/: Show shortcuts
                if (e.ctrlKey && e.which === 191) {
                    e.preventDefault();
                    $('#cmp-shortcuts-modal').fadeIn(150);
                }

                // Escape: Close modals
                if (e.which === 27) {
                    $('.cmp-modal:visible').fadeOut(150);
                    $('#cmp-global-search-results').hide();
                    $('#cmp-notification-panel').hide();
                }
            });
        },

        // ===== Auto Refresh =====
        setupAutoRefresh: function() {
            if (!CMPAdminV2.features.realtime || !CMPAdminV2.dashboardAutoRefresh) {
                return;
            }

            var interval = (CMPAdminV2.dashboardRefreshInterval || 60) * 1000;
            var $toggle = $('#cmp-realtime-enabled');

            if (!$toggle.length) return;

            var refreshTimer;

            var startRefresh = function() {
                refreshTimer = setInterval(function() {
                    if (document.visibilityState === 'visible') {
                        $.post(CMPAdminV2.ajaxUrl, {
                            action: 'cmp_dashboard_realtime_data',
                            nonce: CMPAdminV2.nonce,
                            range: $('#dashboard-range').val() || 'today'
                        }, function(res) {
                            if (res.success && res.data.metrics) {
                                self.updateDashboardMetrics(res.data.metrics);
                                $('.cmp-last-updated').text('Updated: ' + res.data.time);
                            }
                        }, 'json');
                    }
                }, interval);
            };

            var stopRefresh = function() {
                clearInterval(refreshTimer);
            };

            if ($toggle.is(':checked')) {
                startRefresh();
            }

            $toggle.on('change', function() {
                if ($(this).is(':checked')) {
                    startRefresh();
                    CMPAdmin.showToast('Auto-refresh enabled', 'success');
                } else {
                    stopRefresh();
                    CMPAdmin.showToast('Auto-refresh disabled', 'info');
                }
            });
        },

        updateDashboardMetrics: function(metrics) {
            $('[data-metric="total_students"]').text(metrics.total_students);
            $('[data-metric="total_revenue"]').text(this.formatMoney(metrics.total_revenue));
            $('[data-metric="pending_fees"]').text(this.formatMoney(metrics.pending_fees));
            $('[data-metric="new_students"]').text(metrics.new_students);
            $('[data-metric="new_payments"]').text(this.formatMoney(metrics.new_payments));
        },

        // ===== Bulk Actions =====
        setupBulkActions: function() {
            var self = this;

            // Select all visible
            $(document).on('change', '.cmp-select-all', function() {
                $('.cmp-item-checkbox').prop('checked', $(this).is(':checked'));
            });

            // Enhanced bulk action submit with confirmation
            $(document).on('click', '.cmp-bulk-submit', function(e) {
                var action = $(this).closest('.cmp-bulk-actions').find('select[name="bulk_action"]').val();
                var checked = $('.cmp-item-checkbox:checked').length;

                if (!action) {
                    self.showToast('Please select an action', 'warning');
                    e.preventDefault();
                    return false;
                }

                if (checked === 0) {
                    self.showToast('Please select at least one item', 'warning');
                    e.preventDefault();
                    return false;
                }

                if (action.indexOf('delete') !== -1 || action === 'bulk_delete') {
                    if (!confirm(CMPAdminV2.labels.bulkConfirm.replace('{count}', checked))) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        },

        // ===== Health Check =====
        setupHealthCheck: function() {
            var self = this;

            $(document).on('click', '#cmp-run-health-scan', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).find('.dashicons').addClass('cmp-spin');

                $.post(CMPAdminV2.ajaxUrl, {
                    action: 'cmp_run_health_check',
                    nonce: CMPAdminV2.nonce
                }, function(res) {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('cmp-spin');
                    if (res.success) {
                        self.showToast('Scan complete!', 'success');
                        location.reload();
                    }
                }, 'json');
            });

            $(document).on('click', '.cmp-repair-btn', function() {
                var $btn = $(this);
                var $row = $btn.closest('tr');
                var type = $row.data('type');
                var details = $btn.data('details');

                $btn.prop('disabled', true).text('Repairing...');

                $.post(CMPAdminV2.ajaxUrl, {
                    action: 'cmp_repair_health_issue',
                    nonce: CMPAdminV2.nonce,
                    issue_type: type,
                    details: details
                }, function(res) {
                    if (res.success) {
                        $row.fadeOut(300);
                        self.showToast(res.data.message, 'success');
                    } else {
                        $btn.prop('disabled', false).text('Repair');
                        self.showToast(res.data.message || 'Repair failed', 'error');
                    }
                }, 'json');
            });
        },

        // ===== Backup Manager =====
        setupBackupManager: function() {
            var self = this;

            $(document).on('click', '#cmp-create-backup', function() {
                var $btn = $(this);
                $btn.prop('disabled', true);

                $.post(CMPAdminV2.ajaxUrl, {
                    action: 'cmp_create_backup',
                    nonce: CMPAdminV2.nonce
                }, function(res) {
                    $btn.prop('disabled', false);
                    if (res.success) {
                        self.showToast(res.data.message, 'success');
                        setTimeout(function() { location.reload(); }, 1000);
                    }
                }, 'json');
            });

            $(document).on('click', '.cmp-delete-backup', function() {
                var filename = $(this).data('filename');
                if (!confirm('Delete this backup?')) return;

                $.post(CMPAdminV2.ajaxUrl, {
                    action: 'cmp_delete_backup',
                    nonce: CMPAdminV2.nonce,
                    filename: filename
                }, function() {
                    location.reload();
                }, 'json');
            });

            $(document).on('click', '.cmp-download-backup', function() {
                var filename = $(this).data('filename');
                $.post(CMPAdminV2.ajaxUrl, {
                    action: 'cmp_download_backup',
                    nonce: CMPAdminV2.nonce,
                    filename: filename
                }, function(res) {
                    if (res.success && res.data.content) {
                        var blob = new Blob([res.data.content], {type: 'application/json'});
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = filename;
                        a.click();
                        URL.revokeObjectURL(url);
                    }
                }, 'json');
            });
        },

        // ===== Tooltips =====
        setupTooltips: function() {
            $(document).on('mouseenter', '[data-cmp-tooltip]', function() {
                var text = $(this).data('cmp-tooltip');
                var $tip = $('<div class="cmp-tooltip">' + text + '</div>').appendTo('body');
                var offset = $(this).offset();
                $tip.css({
                    top: offset.top - $tip.outerHeight() - 8,
                    left: offset.left + $(this).outerWidth() / 2 - $tip.outerWidth() / 2
                }).fadeIn(100);
                $(this).data('tooltip-el', $tip);
            }).on('mouseleave', '[data-cmp-tooltip]', function() {
                var $tip = $(this).data('tooltip-el');
                if ($tip) {
                    $tip.fadeOut(100, function() { $(this).remove(); });
                }
            });
        },

        // ===== Utilities =====
        showToast: function(message, type) {
            type = type || 'info';
            var $toast = $('<div class="cmp-toast cmp-toast-' + type + '">')
                .html('<span class="dashicons dashicons-' + (type === 'success' ? 'yes' : (type === 'error' ? 'no' : 'info')) + '"></span> ' + this.escapeHtml(message))
                .appendTo('body')
                .hide()
                .fadeIn(200);

            setTimeout(function() {
                $toast.fadeOut(300, function() { $(this).remove(); });
            }, 3000);
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        },

        formatMoney: function(amount) {
            return '₹' + parseFloat(amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    };

    // Initialize when DOM is ready
    $(function() {
        CMPAdmin.init();
    });

    // Expose globally
    window.CMPAdmin = CMPAdmin;

})(jQuery);
