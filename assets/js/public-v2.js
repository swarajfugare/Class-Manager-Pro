/**
 * Class Manager Pro v2 - Public JavaScript
 * Enhanced registration form UX
 */

(function($) {
    'use strict';

    var CMPPublic = {
        init: function() {
            this.setupFormEnhancements();
            this.setupPhoneValidation();
            this.setupAutoPopulate();
        },

        setupFormEnhancements: function() {
            // Add loading state to submit button
            $('.cmp-registration-form').on('submit', function() {
                var $btn = $(this).find('button[type="submit"]');
                $btn.prop('disabled', true).text(CMPPublicV2.messages.submitting);
            });

            // Smooth scroll to errors
            if ($('.cmp-form-error').length) {
                $('html, body').animate({
                    scrollTop: $('.cmp-form-error:first').offset().top - 100
                }, 300);
            }
        },

        setupPhoneValidation: function() {
            $(document).on('input', 'input[name="phone"]', function() {
                var val = $(this).val().replace(/[^0-9+]/g, '');
                if (val.length > 15) {
                    val = val.substring(0, 15);
                }
                $(this).val(val);
            });
        },

        setupAutoPopulate: function() {
            // Auto-populate batch fee when batch changes
            $(document).on('change', 'select[name="batch_id"]', function() {
                var batchId = $(this).val();
                if (!batchId) return;

                $.getJSON(CMPPublicV2.ajaxUrl, {
                    action: 'cmp_get_batch_fee',
                    nonce: CMPPublicV2.nonce,
                    batch_id: batchId
                }, function(res) {
                    if (res.success && res.data.fee) {
                        $('input[name="total_fee"]').val(res.data.fee);
                    }
                });
            });
        }
    };

    $(function() {
        CMPPublic.init();
    });

    window.CMPPublic = CMPPublic;

})(jQuery);
