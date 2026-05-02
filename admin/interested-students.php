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
 * Renders the interested students page.
 *
 * @return void
 */
function cmp_render_interested_students_page() {
	cmp_require_manage_options();

	$filters = array(
		'search'   => sanitize_text_field( cmp_field( $_GET, 'search' ) ),
		'class_id' => absint( cmp_field( $_GET, 'class_id', 0 ) ),
		'batch_id' => absint( cmp_field( $_GET, 'batch_id', 0 ) ),
	);
	$paged   = max( 1, absint( cmp_field( $_GET, 'paged', 1 ) ) );
	$per_page = cmp_get_default_per_page();
	$total    = cmp_get_interested_students_count( $filters );
	$pagination = cmp_get_pagination_data( $total, $paged, $per_page );
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
	$table_context = array(
		'return_page' => 'cmp-interested-students',
		'return_args' => array(
			'search'   => $filters['search'],
			'class_id' => $filters['class_id'],
			'batch_id' => $filters['batch_id'],
			'paged'    => $paged,
		),
	);
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Interested Students', 'class-manager-pro' ); ?></h1>
		<p class="cmp-page-intro"><?php esc_html_e( 'Track failed payment attempts separately so follow-up stays focused without polluting the active student list.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>

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
			<form method="get" class="cmp-toolbar">
				<input type="hidden" name="page" value="cmp-interested-students">
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
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'class-manager-pro' ); ?></button>
				<a class="button" href="<?php echo esc_url( cmp_admin_url( 'cmp-interested-students' ) ); ?>"><?php esc_html_e( 'Reset', 'class-manager-pro' ); ?></a>
			</form>
		</section>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Follow-up List', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Use the quick actions to call, message, or email students whose payment did not complete.', 'class-manager-pro' ); ?></p>
				</div>
			</div>

			<?php cmp_render_interested_students_table( $rows, __( 'No interested students match the current filters.', 'class-manager-pro' ), $table_context ); ?>
			<?php cmp_render_pagination( $pagination, $filters ); ?>
		</section>
	</div>
	<?php
}
