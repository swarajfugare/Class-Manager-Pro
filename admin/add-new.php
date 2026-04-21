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
		<p class="cmp-page-intro"><?php esc_html_e( 'Use this page as a clean top-to-bottom intake flow: create the class template, create the batch with live fee and due date, then add students manually only when you need to override the automated intake flow.', 'class-manager-pro' ); ?></p>
		<?php cmp_render_notice(); ?>

		<div class="cmp-add-new-stack">
			<section class="cmp-panel cmp-add-new-panel">
				<div class="cmp-panel-header">
					<div>
						<span class="cmp-step-badge">1</span>
						<h2><?php esc_html_e( 'Add Class', 'class-manager-pro' ); ?></h2>
					</div>
				</div>
				<?php cmp_render_class_form( null, 'cmp-add-new' ); ?>
			</section>
			<section class="cmp-panel cmp-add-new-panel">
				<div class="cmp-panel-header">
					<div>
						<span class="cmp-step-badge">2</span>
						<h2><?php esc_html_e( 'Add Batch', 'class-manager-pro' ); ?></h2>
					</div>
				</div>
				<?php cmp_render_batch_form( null, 'cmp-add-new' ); ?>
			</section>
			<section class="cmp-panel cmp-add-new-panel">
				<div class="cmp-panel-header">
					<div>
						<span class="cmp-step-badge">3</span>
						<h2><?php esc_html_e( 'Add Student', 'class-manager-pro' ); ?></h2>
					</div>
				</div>
				<?php cmp_render_student_form( null, 'cmp-add-new' ); ?>
			</section>
		</div>
	</div>
	<?php
}
