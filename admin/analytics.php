<?php
/**
 * Analytics admin page.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the analytics page.
 */
function cmp_render_analytics_page() {
	cmp_require_manage_options();

	$chart_data = array(
		'monthlyRevenue' => cmp_get_monthly_revenue( 12 ),
		'classRevenue'   => cmp_get_class_revenue(),
		'studentGrowth'  => cmp_get_student_growth( 12 ),
	);

	wp_add_inline_script( 'cmp-admin', 'window.CMPCharts = ' . wp_json_encode( $chart_data ) . ';', 'before' );
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Analytics', 'class-manager-pro' ); ?></h1>
		<?php cmp_render_notice(); ?>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel cmp-panel-wide">
				<h2><?php esc_html_e( 'Monthly Revenue', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-monthly-revenue"></canvas></div>
			</section>
			<section class="cmp-panel">
				<h2><?php esc_html_e( 'Class-wise Revenue', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart"><canvas id="cmp-analytics-class-revenue"></canvas></div>
			</section>
			<section class="cmp-panel cmp-panel-wide">
				<h2><?php esc_html_e( 'Student Growth', 'class-manager-pro' ); ?></h2>
				<div class="cmp-chart cmp-chart-large"><canvas id="cmp-analytics-student-growth"></canvas></div>
			</section>
		</div>
	</div>
	<?php
}
