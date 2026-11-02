<?php
/**
 * Classes admin page.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a class form.
 *
 * @param object|null $class Class row.
 * @param string      $return_page Return page slug.
 */
function cmp_render_class_form( $class = null, $return_page = 'cmp-classes' ) {
	$is_edit = $class && ! empty( $class->id );
	$courses = cmp_get_tutor_courses();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form" data-cmp-ajax-form="cmp_save_class_ajax">
		<input type="hidden" name="action" value="cmp_save_class">
		<input type="hidden" name="return_page" value="<?php echo esc_attr( $return_page ); ?>">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( (int) $class->id ); ?>">
		<?php endif; ?>
		<?php wp_nonce_field( 'cmp_save_class' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cmp-class-name"><?php esc_html_e( 'Name', 'class-manager-pro' ); ?></label></th>
				<td><input type="text" id="cmp-class-name" name="name" class="regular-text" value="<?php echo esc_attr( $is_edit ? $class->name : '' ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-class-description"><?php esc_html_e( 'Description', 'class-manager-pro' ); ?></label></th>
				<td><textarea id="cmp-class-description" name="description" rows="4" class="large-text"><?php echo esc_textarea( $is_edit ? $class->description : '' ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-class-total-fee"><?php esc_html_e( 'Default Fee', 'class-manager-pro' ); ?></label></th>
				<td>
					<input type="number" id="cmp-class-total-fee" name="total_fee" min="0" step="0.01" value="<?php echo esc_attr( $is_edit ? $class->total_fee : '' ); ?>">
					<p class="description"><?php esc_html_e( 'This is a fallback only. New students should normally inherit the fee from their batch.', 'class-manager-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cmp-class-next-course"><?php esc_html_e( 'Next Course', 'class-manager-pro' ); ?></label></th>
				<td>
					<select id="cmp-class-next-course" name="next_course_id">
						<option value="0"><?php esc_html_e( 'No upsell course', 'class-manager-pro' ); ?></option>
						<?php foreach ( $courses as $course ) : ?>
							<option value="<?php echo esc_attr( (int) $course->ID ); ?>" <?php selected( $is_edit ? (int) $class->next_course_id : 0, (int) $course->ID ); ?>><?php echo esc_html( $course->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'When a student completes this class, the student profile can enroll them into this next Tutor LMS course.', 'class-manager-pro' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Class', 'class-manager-pro' ) : __( 'Add Class', 'class-manager-pro' ) ); ?>
	</form>
	<?php
}

/**
 * Renders the classes page.
 */
function cmp_render_classes_page() {
	cmp_require_manage_options();

	$action = sanitize_key( cmp_field( $_GET, 'action' ) );
	$id     = absint( cmp_field( $_GET, 'id', 0 ) );
	$class  = ( 'edit' === $action && $id ) ? cmp_get_class( $id ) : null;
	$rows   = cmp_get_classes();
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Classes & Batches', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Manage classes as templates, then expand into batches for students, attendance, payments, announcements, teachers, and Tutor LMS links.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>
		<?php cmp_render_admin_workflow_nav( 'cmp-classes' ); ?>
		<?php
		cmp_render_workflow_tabs(
			array(
				array( 'key' => 'classes', 'label' => __( 'Classes', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-classes' ) ),
				array( 'key' => 'batches', 'label' => __( 'Batches', 'class-manager-pro' ), 'url' => cmp_admin_url( 'cmp-batches' ) ),
			),
			'classes',
			__( 'Class and batch sections', 'class-manager-pro' )
		);

		global $wpdb;
		$class_batch_stats = $wpdb->get_results(
			"SELECT class_id, COUNT(id) as total_batches, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_batches FROM " . cmp_table( 'batches' ) . " GROUP BY class_id",
			OBJECT_K
		);
		$class_student_stats = $wpdb->get_results(
			"SELECT class_id, COUNT(id) as total_students FROM " . cmp_table( 'students' ) . " GROUP BY class_id",
			OBJECT_K
		);
		$overview = cmp_get_batch_overview_metrics();
		?>

		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Course Classes', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Active Batches', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $overview['active_batches'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Enrolled Students', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $overview['total_students'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Total Revenue Collected', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( cmp_format_money( $overview['total_revenue'] ) ); ?></strong>
			</div>
		</div>

		<section class="cmp-panel">
			<h2><?php echo esc_html( $class ? __( 'Edit Class', 'class-manager-pro' ) : __( 'Add Class', 'class-manager-pro' ) ); ?></h2>
			<?php cmp_render_class_form( $class, 'cmp-classes' ); ?>
		</section>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'All Classes', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'View master class templates, associated batches, student counts, and create new batches instantly.', 'class-manager-pro' ); ?></p>
				</div>
				<div class="cmp-toolbar">
					<input type="search" id="cmp-quick-class-filter" placeholder="<?php esc_attr_e( 'Search classes...', 'class-manager-pro' ); ?>" style="min-width:200px;padding:4px 10px;border-radius:6px;font-size:13px;" onkeyup="var val = this.value.toLowerCase(); document.querySelectorAll('#cmp-class-table-body tr').forEach(function(r){ r.style.display = r.innerText.toLowerCase().indexOf(val) > -1 ? '' : 'none'; });">
				</div>
			</div>

			<div class="cmp-toolbar cmp-bulk-toolbar">
				<?php wp_nonce_field( 'cmp_admin_nonce', 'cmp_admin_ajax_nonce' ); ?>
				<select id="cmp-class-bulk-action">
					<option value=""><?php esc_html_e( 'Bulk actions', 'class-manager-pro' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete selected', 'class-manager-pro' ); ?></option>
					<option value="export"><?php esc_html_e( 'Export selected', 'class-manager-pro' ); ?></option>
				</select>
				<button
					type="button"
					class="button button-secondary"
					data-cmp-bulk-apply="1"
					data-cmp-entity-type="class"
					data-cmp-action-select="#cmp-class-bulk-action"
					data-cmp-checkbox=".cmp-class-select"
					data-cmp-feedback="#cmp-class-bulk-feedback"
				><?php esc_html_e( 'Apply', 'class-manager-pro' ); ?></button>
				<span class="cmp-muted" id="cmp-class-bulk-feedback"></span>
			</div>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><input type="checkbox" id="cmp-class-select-all" data-cmp-select-all=".cmp-class-select"></th>
						<th><?php esc_html_e( 'Class Name', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Batches', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Enrolled Students', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Default Fee', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Next Course', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody id="cmp-class-table-body">
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No classes found.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$class_id   = (int) $row->id;
							$edit_url   = cmp_admin_url( 'cmp-classes', array( 'action' => 'edit', 'id' => $class_id ) );
							$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=cmp_delete_class&id=' . $class_id ), 'cmp_delete_class_' . $class_id );
							$add_batch_url = cmp_admin_url( 'cmp-batches', array( 'action' => 'add', 'class_id' => $class_id ) );
							$batches_url   = cmp_admin_url( 'cmp-batches', array( 'class_id' => $class_id ) );
							$students_url  = cmp_admin_url( 'cmp-students', array( 'class_id' => $class_id ) );

							$total_b  = isset( $class_batch_stats[ $class_id ] ) ? (int) $class_batch_stats[ $class_id ]->total_batches : 0;
							$active_b = isset( $class_batch_stats[ $class_id ] ) ? (int) $class_batch_stats[ $class_id ]->active_batches : 0;
							$st_count = isset( $class_student_stats[ $class_id ] ) ? (int) $class_student_stats[ $class_id ]->total_students : 0;
							?>
							<tr data-cmp-row-id="class-<?php echo esc_attr( $class_id ); ?>">
								<td><input type="checkbox" class="cmp-class-select" value="<?php echo esc_attr( $class_id ); ?>"></td>
								<td>
									<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $row->name ); ?></a></strong>
									<?php if ( ! empty( $row->description ) ) : ?>
										<div class="cmp-muted" style="font-size:12px;margin-top:2px;"><?php echo esc_html( wp_trim_words( $row->description, 12 ) ); ?></div>
									<?php endif; ?>
								</td>
								<td>
									<a href="<?php echo esc_url( $batches_url ); ?>" class="button button-small" style="font-size:12px;">
										📁 <?php echo esc_html( sprintf( __( '%1$d Batches (%2$d active)', 'class-manager-pro' ), $total_b, $active_b ) ); ?>
									</a>
								</td>
								<td>
									<a href="<?php echo esc_url( $students_url ); ?>" style="font-weight:600;text-decoration:none;">
										👤 <?php echo esc_html( sprintf( _n( '%s Student', '%s Students', $st_count, 'class-manager-pro' ), number_format_i18n( $st_count ) ) ); ?>
									</a>
								</td>
								<td><?php echo esc_html( cmp_format_money( $row->total_fee ) ); ?></td>
								<td><?php echo esc_html( ! empty( $row->next_course_id ) ? cmp_get_tutor_course_title( (int) $row->next_course_id ) : __( 'Not configured', 'class-manager-pro' ) ); ?></td>
								<td class="cmp-actions" style="white-space:nowrap;">
									<a href="<?php echo esc_url( $add_batch_url ); ?>" class="button button-small button-primary" title="<?php esc_attr_e( 'Create a new batch for this class', 'class-manager-pro' ); ?>">+ <?php esc_html_e( 'New Batch', 'class-manager-pro' ); ?></a>
									<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'class-manager-pro' ); ?></a>
									<a
										class="cmp-delete-link button button-small"
										style="color:#dc2626;"
										href="<?php echo esc_url( $delete_url ); ?>"
										data-id="<?php echo esc_attr( $class_id ); ?>"
										data-type="class"
										data-cmp-ajax-delete="1"
										data-cmp-entity-type="class"
										data-cmp-entity-id="<?php echo esc_attr( $class_id ); ?>"
										data-cmp-confirm="<?php esc_attr_e( 'Delete this class and all its settings?', 'class-manager-pro' ); ?>"
										data-cmp-feedback="#cmp-class-bulk-feedback"
									><?php esc_html_e( 'Delete', 'class-manager-pro' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>
	</div>
	<?php
}
