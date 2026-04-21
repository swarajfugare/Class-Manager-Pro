<?php
/**
 * Add New admin hub.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the add-new page.
 */
function cmp_render_add_new_page() {
	cmp_require_manage_options();
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Add New', 'class-manager-pro' ); ?></h1>
		<?php cmp_render_notice(); ?>

		<section class="cmp-panel">
			<h2><?php esc_html_e( 'Add Class', 'class-manager-pro' ); ?></h2>
			<?php cmp_render_class_form( null, 'cmp-add-new' ); ?>
		</section>

		<section class="cmp-panel">
			<h2><?php esc_html_e( 'Add Batch', 'class-manager-pro' ); ?></h2>
			<?php cmp_render_batch_form( null, 'cmp-add-new' ); ?>
		</section>

		<section class="cmp-panel">
			<h2><?php esc_html_e( 'Add Student', 'class-manager-pro' ); ?></h2>
			<?php cmp_render_student_form( null, 'cmp-add-new' ); ?>
		</section>
	</div>
	<?php
}
