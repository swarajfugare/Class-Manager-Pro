<?php
/**
 * Interested students admin page.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the interested students workspace.
 *
 * @param bool $embedded Whether the workspace is shown inside Students.
 * @return void
 */
function cmp_render_interested_students_workspace( $embedded = false ) {
	cmp_require_manage_options();

	$filters = array(
		'search'           => sanitize_text_field( cmp_field( $_GET, 'search' ) ),
		'class_id'         => absint( cmp_field( $_GET, 'class_id', 0 ) ),
		'batch_id'         => absint( cmp_field( $_GET, 'batch_id', 0 ) ),
		'follow_up_status' => sanitize_key( cmp_field( $_GET, 'follow_up_status', '' ) ),
		'email_status'     => sanitize_key( cmp_field( $_GET, 'email_status', '' ) ),
		'whatsapp_status'  => sanitize_key( cmp_field( $_GET, 'whatsapp_status', '' ) ),
	);
	$paged          = max( 1, absint( cmp_field( $_GET, 'paged', 1 ) ) );
	$per_page       = cmp_get_default_per_page();
	$total          = cmp_get_interested_students_count( $filters );
	$pagination     = cmp_get_pagination_data( $total, $paged, $per_page );
	$rows       = cmp_get_interested_students(
		array_merge(
			$filters,
			array(
				'limit'  => $pagination['per_page'],
				'offset' => $pagination['offset'],
			)
		)
	);
	$classes = cmp_get_classes();
	$batches = cmp_get_batches();
	$follow_up_statuses = cmp_get_interested_student_follow_up_statuses();
	$crm_total         = cmp_get_interested_students_count();
	$crm_pending       = cmp_get_interested_students_count( array( 'follow_up_status' => 'follow_up_pending' ) );
	$crm_contacted     = cmp_get_interested_students_count( array( 'follow_up_status' => 'contacted' ) );
	$crm_converted     = cmp_get_interested_students_count( array( 'follow_up_status' => 'converted' ) );
	$crm_not_interest  = cmp_get_interested_students_count( array( 'follow_up_status' => 'not_interested' ) );
	$email_status_options = array(
		''        => __( 'All email statuses', 'class-manager-pro' ),
		'sent'    => __( 'Email sent', 'class-manager-pro' ),
		'failed'  => __( 'Email failed', 'class-manager-pro' ),
		'pending' => __( 'Email pending', 'class-manager-pro' ),
	);
	$whatsapp_status_options = array(
		''        => __( 'All WhatsApp statuses', 'class-manager-pro' ),
		'sent'    => __( 'WhatsApp sent', 'class-manager-pro' ),
		'failed'  => __( 'WhatsApp failed', 'class-manager-pro' ),
		'pending' => __( 'WhatsApp pending', 'class-manager-pro' ),
	);
	$table_context = array(
		'return_page' => $embedded ? 'cmp-students' : 'cmp-interested-students',
		'return_args' => array(
			'tab'      => $embedded ? 'interested' : '',
			'search'   => $filters['search'],
			'class_id' => $filters['class_id'],
			'batch_id' => $filters['batch_id'],
			'follow_up_status' => $filters['follow_up_status'],
			'email_status'    => $filters['email_status'],
			'whatsapp_status' => $filters['whatsapp_status'],
			'paged'    => $paged,
		),
	);
	?>
	<?php if ( ! $embedded ) : ?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Interested Students', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Track failed payment attempts separately so follow-up stays focused without polluting the active student list.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>
		<?php cmp_render_admin_workflow_nav( 'cmp-interested-students' ); ?>
	<?php endif; ?>

		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Leads', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $crm_total ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Follow-up Pending', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $crm_pending ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Contacted', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $crm_contacted ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Converted / Not Interested', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $crm_converted + $crm_not_interest ) ); ?></strong>
			</div>
		</div>

		<div class="cmp-cards cmp-cards-3">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Interested Students', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Linked to Batches', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( count( array_filter( $rows, static function ( $row ) { return ! empty( $row->batch_id ); } ) ) ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Attempted Amount', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( array_sum( array_map( static function ( $row ) { return isset( $row->attempt_amount ) ? (float) $row->attempt_amount : 0; }, $rows ) ) ) ); ?></strong>
			</div>
		</div>

		<section class="cmp-panel">
			<form method="get" class="cmp-toolbar cmp-filter-form" data-cmp-action="cmp_filter_interested_students" data-cmp-target="#cmp-interested-table-wrap">
				<input type="hidden" name="page" value="<?php echo esc_attr( $embedded ? 'cmp-students' : 'cmp-interested-students' ); ?>">
				<?php if ( $embedded ) : ?>
					<input type="hidden" name="tab" value="interested">
				<?php endif; ?>
				<input type="search" name="search" class="regular-text" placeholder="<?php esc_attr_e( 'Search name, phone, email, batch', 'class-manager-pro' ); ?>" value="<?php echo esc_attr( $filters['search'] ); ?>">
				<select name="class_id" data-cmp-class-select data-cmp-searchable="1">
					<option value="0"><?php esc_html_e( 'All classes', 'class-manager-pro' ); ?></option>
					<?php foreach ( $classes as $class ) : ?>
						<option value="<?php echo esc_attr( (int) $class->id ); ?>" <?php selected( $filters['class_id'], (int) $class->id ); ?>><?php echo esc_html( $class->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="batch_id" data-cmp-batches data-cmp-searchable="1">
					<option value="0"><?php esc_html_e( 'All batches', 'class-manager-pro' ); ?></option>
					<?php foreach ( $batches as $batch ) : ?>
						<option value="<?php echo esc_attr( (int) $batch->id ); ?>" data-class-id="<?php echo esc_attr( (int) $batch->class_id ); ?>" <?php selected( $filters['batch_id'], (int) $batch->id ); ?>><?php echo esc_html( sprintf( '%1$s / %2$s', $batch->class_name, $batch->batch_name ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="follow_up_status">
					<?php foreach ( $follow_up_statuses as $status_key => $status_label ) : ?>
						<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $filters['follow_up_status'], $status_key ); ?>><?php echo esc_html( $status_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="email_status">
					<?php foreach ( $email_status_options as $status_key => $status_label ) : ?>
						<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $filters['email_status'], $status_key ); ?>><?php echo esc_html( $status_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="whatsapp_status">
					<?php foreach ( $whatsapp_status_options as $status_key => $status_label ) : ?>
						<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $filters['whatsapp_status'], $status_key ); ?>><?php echo esc_html( $status_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'class-manager-pro' ); ?></button>
				<a class="button" href="<?php echo esc_url( cmp_admin_url( $embedded ? 'cmp-students' : 'cmp-interested-students', $embedded ? array( 'tab' => 'interested' ) : array() ) ); ?>"><?php esc_html_e( 'Reset', 'class-manager-pro' ); ?></a>
			</form>
		</section>

		<?php if ( ! $embedded ) : ?>
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Export CRM', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Export all leads, filtered leads, selected rows, or follow-up status groups in CSV or Excel format.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-toolbar cmp-interested-export-form" data-cmp-interested-export-form="1">
					<input type="hidden" name="action" value="cmp_export_interested_students">
					<input type="hidden" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>">
					<input type="hidden" name="class_id" value="<?php echo esc_attr( $filters['class_id'] ); ?>">
					<input type="hidden" name="batch_id" value="<?php echo esc_attr( $filters['batch_id'] ); ?>">
					<input type="hidden" name="follow_up_status" value="<?php echo esc_attr( $filters['follow_up_status'] ); ?>">
					<input type="hidden" name="email_status" value="<?php echo esc_attr( $filters['email_status'] ); ?>">
					<input type="hidden" name="whatsapp_status" value="<?php echo esc_attr( $filters['whatsapp_status'] ); ?>">
					<input type="hidden" name="selected_ids" value="">
					<?php wp_nonce_field( 'cmp_export_interested_students' ); ?>
					<select name="scope">
						<option value="all"><?php esc_html_e( 'All Records', 'class-manager-pro' ); ?></option>
						<option value="filtered" selected><?php esc_html_e( 'Current Filters', 'class-manager-pro' ); ?></option>
						<option value="selected"><?php esc_html_e( 'Selected Rows', 'class-manager-pro' ); ?></option>
						<option value="follow_up_pending"><?php esc_html_e( 'Follow-up Pending', 'class-manager-pro' ); ?></option>
						<option value="contacted"><?php esc_html_e( 'Contacted', 'class-manager-pro' ); ?></option>
						<option value="converted"><?php esc_html_e( 'Converted', 'class-manager-pro' ); ?></option>
						<option value="not_interested"><?php esc_html_e( 'Not Interested', 'class-manager-pro' ); ?></option>
					</select>
					<div class="cmp-export-actions">
						<button type="submit" name="format" value="csv" class="button"><?php esc_html_e( 'Export CSV', 'class-manager-pro' ); ?></button>
						<button type="submit" name="format" value="xlsx" class="button button-primary"><?php esc_html_e( 'Export Excel', 'class-manager-pro' ); ?></button>
					</div>
					<span class="cmp-muted" data-cmp-export-feedback><?php esc_html_e( 'Use the row checkboxes below to export selected leads.', 'class-manager-pro' ); ?></span>
				</form>
			</section>
		<?php endif; ?>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Follow-up List', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Use the quick actions to call, message, or email students whose payment did not complete.', 'class-manager-pro' ); ?></p>
				</div>
			</div>

			<div id="cmp-interested-table-wrap">
				<?php echo cmp_render_interested_students_table( $rows, __( 'No interested students match the current filters.', 'class-manager-pro' ), $table_context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php cmp_render_pagination( $pagination, $embedded ? array_merge( $filters, array( 'tab' => 'interested' ) ) : $filters ); ?>
			</div>
		</section>
	<?php if ( ! $embedded ) : ?>
	</div>
	<?php endif; ?>
	<?php
}

/**
 * Renders the interested students page.
 *
 * @return void
 */
function cmp_render_interested_students_page() {
	cmp_render_interested_students_workspace( false );
}
