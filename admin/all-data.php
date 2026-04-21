<?php
/**
 * All Data admin page.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the combined all-data page.
 */
function cmp_render_all_data_page() {
	cmp_require_manage_options();

	$filters = cmp_read_student_filters( $_GET );
	$classes = cmp_get_classes();
	$batches = cmp_get_batches();
	$export_url = wp_nonce_url(
		add_query_arg(
			array_merge(
				array(
					'page'       => 'cmp-all-data',
					'cmp_export' => 'all-data',
				),
				$filters
			),
			admin_url( 'admin.php' )
		),
		'cmp_export_all-data'
	);
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'All Data', 'class-manager-pro' ); ?></h1>
		<?php cmp_render_notice(); ?>

		<form method="get" class="cmp-filter-form cmp-toolbar" data-cmp-action="cmp_filter_all_data" data-cmp-target=".cmp-all-data-results">
			<input type="hidden" name="page" value="cmp-all-data">
			<input type="search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search name or phone', 'class-manager-pro' ); ?>">
			<select name="class_id" data-cmp-class-select>
				<option value="0"><?php esc_html_e( 'All classes', 'class-manager-pro' ); ?></option>
				<?php foreach ( $classes as $class ) : ?>
					<option value="<?php echo esc_attr( (int) $class->id ); ?>" <?php selected( $filters['class_id'], (int) $class->id ); ?>><?php echo esc_html( $class->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="batch_id" data-cmp-batches>
				<option value="0"><?php esc_html_e( 'All batches', 'class-manager-pro' ); ?></option>
				<?php foreach ( $batches as $batch ) : ?>
					<option value="<?php echo esc_attr( (int) $batch->id ); ?>" data-class-id="<?php echo esc_attr( (int) $batch->class_id ); ?>" <?php selected( $filters['batch_id'], (int) $batch->id ); ?>><?php echo esc_html( $batch->batch_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'class-manager-pro' ); ?></button>
			<a class="button cmp-export-link" data-base-url="<?php echo esc_url( $export_url ); ?>" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'class-manager-pro' ); ?></a>
		</form>

		<section class="cmp-panel">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Student Name', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Class', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Total Fee', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Paid Fee', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Remaining Fee', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody class="cmp-all-data-results">
					<?php echo cmp_render_all_data_rows( $filters ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</tbody>
			</table>
		</section>
	</div>
	<?php
}
