<?php
/**
 * Admin Enhancements
 *
 * New UI components and UX improvements for existing admin pages.
 *
 * @package ClassManagerPro
 */

/**
 * Render quick action buttons on existing pages.
 */
add_action( 'cmp_before_page_content', 'cmp_render_quick_actions_bar', 5 );

function cmp_render_quick_actions_bar() {
	if ( ! current_user_can( 'cmp_manage' ) ) {
		return;
	}

	$page = sanitize_key( $_GET['page'] ?? '' );
	?>
	<div class="cmp-quick-actions-bar">
		<div class="cmp-quick-actions-left">
			<?php if ( false !== strpos( $page, 'students' ) ) : ?>
				<button type="button" class="button button-primary cmp-quick-add-btn" data-type="student">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Quick Add Student', 'class-manager-pro' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( false !== strpos( $page, 'payments' ) ) : ?>
				<button type="button" class="button button-primary cmp-quick-add-btn" data-type="payment">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Quick Add Payment', 'class-manager-pro' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<div class="cmp-quick-actions-right">
			<div class="cmp-global-search">
				<span class="dashicons dashicons-search"></span>
				<input type="text" id="cmp-global-search-input" placeholder="<?php esc_attr_e( 'Quick search (Ctrl+K)...', 'class-manager-pro' ); ?>">
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render global search results dropdown.
 */
add_action( 'admin_footer', 'cmp_render_global_search_dropdown' );

function cmp_render_global_search_dropdown() {
	if ( ! cmp_is_cmp_admin_page() ) {
		return;
	}
	?>
	<div id="cmp-global-search-results" class="cmp-search-dropdown" style="display:none;">
		<div class="cmp-search-header">
			<span><?php esc_html_e( 'Results', 'class-manager-pro' ); ?></span>
			<kbd>Ctrl+K</kbd>
		</div>
		<div class="cmp-search-list"></div>
	</div>
	<?php
}

/**
 * Render quick add modals.
 */
add_action( 'admin_footer', 'cmp_render_quick_add_modals' );

function cmp_render_quick_add_modals() {
	if ( ! cmp_is_cmp_admin_page() || ! current_user_can( 'cmp_manage' ) ) {
		return;
	}
	?>
	<!-- Quick Add Student Modal -->
	<div id="cmp-modal-student" class="cmp-modal" style="display:none;">
		<div class="cmp-modal-content">
			<div class="cmp-modal-header">
				<h3><?php esc_html_e( 'Quick Add Student', 'class-manager-pro' ); ?></h3>
				<button type="button" class="cmp-modal-close">&times;</button>
			</div>
			<div class="cmp-modal-body">
				<form id="cmp-quick-student-form" class="cmp-quick-form">
					<div class="cmp-form-row">
						<label><?php esc_html_e( 'Name', 'class-manager-pro' ); ?> *</label>
						<input type="text" name="name" required>
					</div>
					<div class="cmp-form-row cmp-form-two-col">
						<div>
							<label><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?> *</label>
							<input type="tel" name="phone" required>
						</div>
						<div>
							<label><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></label>
							<input type="email" name="email">
						</div>
					</div>
					<div class="cmp-form-row cmp-form-two-col">
						<div>
							<label><?php esc_html_e( 'Class', 'class-manager-pro' ); ?> *</label>
							<select name="class_id" id="cmp-quick-class-select" required>
								<option value=""><?php esc_html_e( 'Select Class', 'class-manager-pro' ); ?></option>
								<?php foreach ( cmp_get_classes() as $class ) : ?>
									<option value="<?php echo esc_attr( $class->id ); ?>"><?php echo esc_html( $class->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div>
							<label><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?> *</label>
							<select name="batch_id" id="cmp-quick-batch-select" required>
								<option value=""><?php esc_html_e( 'Select Batch', 'class-manager-pro' ); ?></option>
							</select>
						</div>
					</div>
					<div class="cmp-form-row">
						<label><?php esc_html_e( 'Total Fee', 'class-manager-pro' ); ?></label>
						<input type="number" name="total_fee" step="0.01" min="0">
					</div>
					<div class="cmp-form-actions">
						<button type="submit" class="button button-primary">
							<span class="cmp-btn-text"><?php esc_html_e( 'Add Student', 'class-manager-pro' ); ?></span>
							<span class="cmp-spinner" style="display:none;"></span>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- Quick Add Payment Modal -->
	<div id="cmp-modal-payment" class="cmp-modal" style="display:none;">
		<div class="cmp-modal-content">
			<div class="cmp-modal-header">
				<h3><?php esc_html_e( 'Quick Add Payment', 'class-manager-pro' ); ?></h3>
				<button type="button" class="cmp-modal-close">&times;</button>
			</div>
			<div class="cmp-modal-body">
				<form id="cmp-quick-payment-form" class="cmp-quick-form">
					<div class="cmp-form-row">
						<label><?php esc_html_e( 'Student', 'class-manager-pro' ); ?> *</label>
						<?php cmp_render_smart_student_select( 'quick_student_id', 0, true ); ?>
					</div>
					<div class="cmp-form-row cmp-form-two-col">
						<div>
							<label><?php esc_html_e( 'Amount', 'class-manager-pro' ); ?> *</label>
							<input type="number" name="amount" step="0.01" min="0.01" required>
						</div>
						<div>
							<label><?php esc_html_e( 'Payment Mode', 'class-manager-pro' ); ?></label>
							<select name="payment_mode">
								<option value="Cash"><?php esc_html_e( 'Cash', 'class-manager-pro' ); ?></option>
								<option value="Online"><?php esc_html_e( 'Online', 'class-manager-pro' ); ?></option>
							</select>
						</div>
					</div>
					<div class="cmp-form-row">
						<label><?php esc_html_e( 'Transaction ID', 'class-manager-pro' ); ?></label>
						<input type="text" name="transaction_id">
					</div>
					<div class="cmp-form-row">
						<label><?php esc_html_e( 'Payment Date', 'class-manager-pro' ); ?></label>
						<input type="date" name="payment_date" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
					</div>
					<div class="cmp-form-actions">
						<button type="submit" class="button button-primary">
							<span class="cmp-btn-text"><?php esc_html_e( 'Record Payment', 'class-manager-pro' ); ?></span>
							<span class="cmp-spinner" style="display:none;"></span>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- Notification Center Dropdown -->
	<div id="cmp-notification-panel" class="cmp-notification-panel" style="display:none;">
		<div class="cmp-notification-header">
			<h4><?php esc_html_e( 'Notifications', 'class-manager-pro' ); ?></h4>
			<button type="button" id="cmp-mark-all-read"><?php esc_html_e( 'Mark all read', 'class-manager-pro' ); ?></button>
		</div>
		<div class="cmp-notification-list"></div>
	</div>
	<?php
}

/**
 * Enhanced page title with breadcrumbs.
 */
add_action( 'cmp_before_page_content', 'cmp_render_breadcrumbs', 1 );

function cmp_render_breadcrumbs() {
	if ( ! cmp_is_cmp_admin_page() ) {
		return;
	}

	$page = sanitize_key( $_GET['page'] ?? '' );
	$action = sanitize_key( $_GET['action'] ?? 'list' );

	$breadcrumbs = array(
		'cmp-dashboard'      => __( 'Dashboard', 'class-manager-pro' ),
		'cmp-classes'          => __( 'Classes', 'class-manager-pro' ),
		'cmp-batches'          => __( 'Batches', 'class-manager-pro' ),
		'cmp-students'         => __( 'Students', 'class-manager-pro' ),
		'cmp-payments'         => __( 'Payments', 'class-manager-pro' ),
		'cmp-payments-trash'   => __( 'Payment Trash', 'class-manager-pro' ),
		'cmp-analytics'        => __( 'Analytics', 'class-manager-pro' ),
		'cmp-teacher-console'  => __( 'Teacher Console', 'class-manager-pro' ),
		'cmp-razorpay-import'  => __( 'Razorpay Import', 'class-manager-pro' ),
		'cmp-settings'         => __( 'Settings', 'class-manager-pro' ),
		'cmp-add-new'          => __( 'Add New', 'class-manager-pro' ),
		'cmp-all-data'         => __( 'All Data', 'class-manager-pro' ),
		'cmp-health-check'     => __( 'Health Check', 'class-manager-pro' ),
		'cmp-notifications'    => __( 'Notifications', 'class-manager-pro' ),
		'cmp-backup'           => __( 'Backup & Export', 'class-manager-pro' ),
	);

	$current_label = $breadcrumbs[ $page ] ?? __( 'Class Manager Pro', 'class-manager-pro' );
	?>
	<nav class="cmp-breadcrumbs">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cmp-dashboard' ) ); ?>"><?php esc_html_e( 'CMP', 'class-manager-pro' ); ?></a>
		<span class="sep">/</span>
		<span class="current"><?php echo esc_html( $current_label ); ?></span>
		<?php if ( 'edit' === $action || 'view' === $action ) : ?>
			<span class="sep">/</span>
			<span class="current"><?php echo esc_html( ucfirst( $action ) ); ?></span>
		<?php endif; ?>
	</nav>
	<?php
}

/**
 * Add keyboard shortcut help.
 */
add_action( 'admin_footer', 'cmp_render_keyboard_shortcuts_help' );

function cmp_render_keyboard_shortcuts_help() {
	if ( ! cmp_is_cmp_admin_page() ) {
		return;
	}
	?>
	<div id="cmp-shortcuts-modal" class="cmp-modal" style="display:none;">
		<div class="cmp-modal-content cmp-modal-small">
			<div class="cmp-modal-header">
				<h3><?php esc_html_e( 'Keyboard Shortcuts', 'class-manager-pro' ); ?></h3>
				<button type="button" class="cmp-modal-close">&times;</button>
			</div>
			<div class="cmp-modal-body">
				<table class="cmp-shortcuts-table">
					<tr><td><kbd>Ctrl</kbd> + <kbd>K</kbd></td><td><?php esc_html_e( 'Quick Search', 'class-manager-pro' ); ?></td></tr>
					<tr><td><kbd>Ctrl</kbd> + <kbd>N</kbd></td><td><?php esc_html_e( 'Quick Add', 'class-manager-pro' ); ?></td></tr>
					<tr><td><kbd>Ctrl</kbd> + <kbd>/</kbd></td><td><?php esc_html_e( 'Show Shortcuts', 'class-manager-pro' ); ?></td></tr>
					<tr><td><kbd>Esc</kbd></td><td><?php esc_html_e( 'Close Modal / Clear Search', 'class-manager-pro' ); ?></td></tr>
				</table>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Enhanced filter forms with AJAX.
 */
add_action( 'cmp_filter_form_after', 'cmp_render_ajax_filter_indicator' );

function cmp_render_ajax_filter_indicator() {
	?>
	<div class="cmp-ajax-filter-status" style="display:none;">
		<span class="dashicons dashicons-update-alt cmp-spin"></span>
		<span><?php esc_html_e( 'Filtering...', 'class-manager-pro' ); ?></span>
	</div>
	<?php
}
