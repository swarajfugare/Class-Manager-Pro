<?php
/**
 * Operations Center admin pages.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the Operations Center section tabs.
 *
 * @return array
 */
function cmp_operations_center_tabs() {
	$is_admin = current_user_can( 'manage_options' );

	$tabs = array(
		array( 'key' => 'dashboard', 'label' => __( 'Dashboard', 'class-manager-pro' ), 'slug' => 'cmp-operations-center' ),
		array( 'key' => 'calendar', 'label' => __( 'Calendar', 'class-manager-pro' ), 'slug' => 'cmp-operations-center-calendar' ),
		array( 'key' => 'planner', 'label' => __( 'Monthly Planner', 'class-manager-pro' ), 'slug' => 'cmp-operations-center-planner' ),
		array( 'key' => 'batch_schedule', 'label' => __( 'Batch Schedule', 'class-manager-pro' ), 'slug' => 'cmp-operations-center-batch-schedule' ),
		array( 'key' => 'teacher_schedule', 'label' => __( 'Teacher Schedule', 'class-manager-pro' ), 'slug' => 'cmp-operations-center-teacher-schedule' ),
		array( 'key' => 'documents', 'label' => __( 'Documents', 'class-manager-pro' ), 'slug' => 'cmp-operations-center-documents' ),
		array( 'key' => 'holidays', 'label' => __( 'Holiday Manager', 'class-manager-pro' ), 'slug' => 'cmp-operations-center-holidays' ),
		array( 'key' => 'goals', 'label' => __( 'Goals', 'class-manager-pro' ), 'slug' => 'cmp-operations-center-goals' ),
	);

	if ( $is_admin ) {
		$tabs[] = array( 'key' => 'marketing', 'label' => __( 'Marketing Planner', 'class-manager-pro' ), 'slug' => 'cmp-operations-center-marketing' );
		$tabs[] = array( 'key' => 'business', 'label' => __( 'Business Planner', 'class-manager-pro' ), 'slug' => 'cmp-operations-center-business' );
		$tabs[] = array( 'key' => 'settings', 'label' => __( 'Settings', 'class-manager-pro' ), 'slug' => 'cmp-operations-center-settings' );
	}

	return $tabs;
}

/**
 * Renders the Operations Center navigation.
 *
 * @param string $active Active tab key.
 * @return void
 */
function cmp_render_operations_center_nav( $active = 'dashboard' ) {
	$active = sanitize_key( $active );
	?>
	<nav class="nav-tab-wrapper cmp-tab-nav cmp-operations-nav" aria-label="<?php esc_attr_e( 'Operations Center sections', 'class-manager-pro' ); ?>">
		<?php foreach ( cmp_operations_center_tabs() as $tab ) : ?>
			<?php $slug = sanitize_key( $tab['slug'] ); ?>
			<a class="nav-tab <?php echo esc_attr( $active === $tab['key'] ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( cmp_admin_url( $slug ) ); ?>"><?php echo esc_html( $tab['label'] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * Returns a wrapper class for a section card.
 *
 * @param string $status Status key.
 * @return string
 */
function cmp_operations_status_class( $status ) {
	return 'cmp-status-' . sanitize_key( $status );
}

/**
 * Returns a data refresh target for a section.
 *
 * @param string $section Section key.
 * @return string
 */
function cmp_operations_section_target_id( $section ) {
	$section = sanitize_key( $section );

	return 'cmp-operations-' . $section . '-root';
}

/**
 * Returns the page slug for a section.
 *
 * @param string $section Section key.
 * @return string
 */
function cmp_operations_section_page_slug( $section ) {
	$section = sanitize_key( $section );
	$map = array(
		'dashboard'        => 'cmp-operations-center',
		'calendar'         => 'cmp-operations-center-calendar',
		'planner'          => 'cmp-operations-center-planner',
		'batch_schedule'   => 'cmp-operations-center-batch-schedule',
		'teacher_schedule' => 'cmp-operations-center-teacher-schedule',
		'marketing'        => 'cmp-operations-center-marketing',
		'business'         => 'cmp-operations-center-business',
		'documents'        => 'cmp-operations-center-documents',
		'holidays'         => 'cmp-operations-center-holidays',
		'goals'            => 'cmp-operations-center-goals',
		'settings'         => 'cmp-operations-center-settings',
	);

	return isset( $map[ $section ] ) ? $map[ $section ] : 'cmp-operations-center';
}

/**
 * Renders the main Operations Center page.
 *
 * @return void
 */
function cmp_render_operations_center_page() {
	cmp_require_operations_center_access();
	echo cmp_render_operations_section_html( 'dashboard', wp_unslash( $_GET ) );
}

/**
 * Renders the calendar page.
 *
 * @return void
 */
function cmp_render_operations_calendar_page() {
	cmp_require_operations_center_access();
	echo cmp_render_operations_section_html( 'calendar', wp_unslash( $_GET ) );
}

/**
 * Renders the planner page.
 *
 * @return void
 */
function cmp_render_operations_planner_page() {
	cmp_require_operations_center_access();
	echo cmp_render_operations_section_html( 'planner', wp_unslash( $_GET ) );
}

/**
 * Renders the batch schedule page.
 *
 * @return void
 */
function cmp_render_operations_batch_schedule_page() {
	cmp_require_operations_center_access();
	echo cmp_render_operations_section_html( 'batch_schedule', wp_unslash( $_GET ) );
}

/**
 * Renders the teacher schedule page.
 *
 * @return void
 */
function cmp_render_operations_teacher_schedule_page() {
	cmp_require_operations_center_access();
	echo cmp_render_operations_section_html( 'teacher_schedule', wp_unslash( $_GET ) );
}

/**
 * Renders the marketing planner page.
 *
 * @return void
 */
function cmp_render_operations_marketing_planner_page() {
	cmp_require_operations_center_access();
	echo cmp_render_operations_section_html( 'marketing', wp_unslash( $_GET ) );
}

/**
 * Renders the business planner page.
 *
 * @return void
 */
function cmp_render_operations_business_planner_page() {
	cmp_require_operations_center_access();
	echo cmp_render_operations_section_html( 'business', wp_unslash( $_GET ) );
}

/**
 * Renders the documents page.
 *
 * @return void
 */
function cmp_render_operations_documents_page() {
	cmp_require_operations_center_access();
	echo cmp_render_operations_section_html( 'documents', wp_unslash( $_GET ) );
}

/**
 * Renders the holidays page.
 *
 * @return void
 */
function cmp_render_operations_holidays_page() {
	cmp_require_operations_center_access();
	echo cmp_render_operations_section_html( 'holidays', wp_unslash( $_GET ) );
}

/**
 * Renders the goals page.
 *
 * @return void
 */
function cmp_render_operations_goals_page() {
	cmp_require_operations_center_access();
	echo cmp_render_operations_section_html( 'goals', wp_unslash( $_GET ) );
}

/**
 * Renders the settings page.
 *
 * @return void
 */
function cmp_render_operations_settings_page() {
	cmp_require_manage_options();
	echo cmp_render_operations_section_html( 'settings', wp_unslash( $_GET ) );
}

/**
 * Returns the Operations Center heading copy for each section.
 *
 * @param string $section Section key.
 * @return array
 */
function cmp_operations_section_heading( $section ) {
	$map = array(
		'dashboard'       => array( __( 'Operations Center', 'class-manager-pro' ), __( 'Daily planning, live priorities, and the latest activity in one place.', 'class-manager-pro' ) ),
		'calendar'        => array( __( 'Smart Calendar', 'class-manager-pro' ), __( 'Month, week, day, and agenda views with live drag-and-drop scheduling.', 'class-manager-pro' ) ),
		'planner'         => array( __( 'Monthly Planner', 'class-manager-pro' ), __( 'Plan the month, duplicate plans, and keep the team aligned.', 'class-manager-pro' ) ),
		'batch_schedule'  => array( __( 'Batch Schedule', 'class-manager-pro' ), __( 'Track batch timing, teachers, links, documents, and status in one grid.', 'class-manager-pro' ) ),
		'teacher_schedule'=> array( __( 'Teacher Schedule', 'class-manager-pro' ), __( 'Show teachers only their own batches, classes, documents, and reminders.', 'class-manager-pro' ) ),
		'marketing'       => array( __( 'Marketing Planner', 'class-manager-pro' ), __( 'Plan campaigns, budgets, reminders, and performance notes.', 'class-manager-pro' ) ),
		'business'        => array( __( 'Business Planner', 'class-manager-pro' ), __( 'Track monthly goals, progress bars, and target-driven planning.', 'class-manager-pro' ) ),
		'documents'       => array( __( 'Document Center', 'class-manager-pro' ), __( 'Upload, preview, download, and version documents by batch.', 'class-manager-pro' ) ),
		'holidays'        => array( __( 'Holiday Manager', 'class-manager-pro' ), __( 'Manage institute, teacher, and batch holidays with automatic calendar updates.', 'class-manager-pro' ) ),
		'goals'           => array( __( 'Goals', 'class-manager-pro' ), __( 'Monitor admissions, revenue, attendance, and completion goals.', 'class-manager-pro' ) ),
		'settings'        => array( __( 'Settings', 'class-manager-pro' ), __( 'Configure defaults for calendar, reminders, and exports.', 'class-manager-pro' ) ),
	);

	return isset( $map[ $section ] ) ? $map[ $section ] : array( __( 'Operations Center', 'class-manager-pro' ), __( 'Daily planning and institute operations.', 'class-manager-pro' ) );
}

/**
 * Renders a section header and shell.
 *
 * @param string $section Section key.
 * @param string $inner_html Inner HTML.
 * @return string
 */
function cmp_operations_shell_markup( $section, $inner_html ) {
	list( $title, $subtitle ) = cmp_operations_section_heading( $section );
	$active = sanitize_key( $section );

	ob_start();
	?>
	<div class="wrap cmp-wrap cmp-operations-shell">
		<h1><?php echo esc_html( $title ); ?></h1>
		<p class="cmp-page-intro"><?php echo esc_html( $subtitle ); ?></p>
		<?php cmp_render_notice(); ?>
		<?php cmp_render_operations_center_nav( $active ); ?>
		<?php echo $inner_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Formats a human-readable date string.
 *
 * @param string $value Datetime string.
 * @param string $format Display format.
 * @return string
 */
function cmp_operations_format_datetime( $value, $format = 'M j, Y g:i a' ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return __( 'Not set', 'class-manager-pro' );
	}

	$timestamp = strtotime( $value );

	return false === $timestamp ? esc_html( $value ) : wp_date( $format, $timestamp );
}

/**
 * Renders the dashboard section.
 *
 * @param array $snapshot Dashboard data.
 * @return string
 */
function cmp_render_operations_dashboard_content( $snapshot ) {
	ob_start();
	?>
	<div id="<?php echo esc_attr( cmp_operations_section_target_id( 'dashboard' ) ); ?>">
		<section class="cmp-panel cmp-operations-hero">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Operations at a Glance', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Search any batch, teacher, class, event, goal, or document, then use the tabs above to move into the right section.', 'class-manager-pro' ); ?></p>
				</div>
			</div>
			<form method="get" class="cmp-inline-form cmp-operations-search-form cmp-operations-search-hero" data-cmp-action="cmp_search_operations_center" data-cmp-target="#cmp-operations-search-results">
				<input type="hidden" name="page" value="cmp-operations-center">
				<input type="search" name="search" class="regular-text" placeholder="<?php esc_attr_e( 'Search batch, teacher, class, event, goal, or document', 'class-manager-pro' ); ?>">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Search', 'class-manager-pro' ); ?></button>
			</form>
			<div class="cmp-inline-tools cmp-operations-hero-hint">
				<span class="cmp-inline-badge"><?php esc_html_e( 'Calendar', 'class-manager-pro' ); ?></span>
				<span class="cmp-inline-badge"><?php esc_html_e( 'Planner', 'class-manager-pro' ); ?></span>
				<span class="cmp-inline-badge"><?php esc_html_e( 'Goals', 'class-manager-pro' ); ?></span>
				<span class="cmp-inline-badge"><?php esc_html_e( 'Documents', 'class-manager-pro' ); ?></span>
			</div>
		</section>

		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card">
				<span><?php esc_html_e( "Today's Classes", 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) $snapshot['counts']['today_classes'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( "Tomorrow's Classes", 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) $snapshot['counts']['tomorrow_classes'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Upcoming Batches', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) $snapshot['counts']['upcoming_batches'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Pending Operations', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) $snapshot['counts']['pending_ops'] ) ); ?></strong>
			</div>
		</div>

		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Upcoming Holidays', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (int) $snapshot['counts']['holiday_count'] ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Goal Progress', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( (float) $snapshot['goal_completion'], 2 ) ); ?>%</strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Marketing Tasks Today', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( count( $snapshot['marketing_tasks_today'] ) ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Teacher Workload Today', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( count( $snapshot['teacher_schedule_today'] ) ) ); ?></strong>
			</div>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Today', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Classes, teacher tasks, marketing work, and reminders that need attention now.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Item', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Type', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Time', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $snapshot['pending_operations'] ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No pending operations for today.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( array_slice( $snapshot['pending_operations'], 0, 8 ) as $row ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $row->title ); ?></strong>
											<?php if ( ! empty( $row->batch_name ) ) : ?><br><span class="cmp-muted"><?php echo esc_html( $row->batch_name ); ?></span><?php endif; ?>
										</td>
										<td><span class="cmp-inline-badge"><?php echo esc_html( cmp_operations_event_type_label( $row->event_type ) ); ?></span></td>
										<td><?php echo esc_html( cmp_operations_format_datetime( $row->start_at ) ); ?></td>
										<td><span class="cmp-status <?php echo esc_attr( cmp_operations_status_class( $row->status ) ); ?>"><?php echo esc_html( cmp_operations_status_label( $row->status ) ); ?></span></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Recent Activities', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Recent admin actions and operational changes.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Activity', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'User', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'When', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $snapshot['recent_activities'] ) ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No activity yet.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $snapshot['recent_activities'] as $log ) : ?>
									<tr>
										<td><strong><?php echo esc_html( ucfirst( str_replace( '_', ' ', $log->action ) ) ); ?></strong><br><span class="cmp-muted"><?php echo esc_html( $log->message ); ?></span></td>
										<td><?php echo esc_html( ! empty( $log->admin_name ) ? $log->admin_name : __( 'System', 'class-manager-pro' ) ); ?></td>
										<td><?php echo esc_html( cmp_operations_format_datetime( $log->created_at ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		</div>

		<div id="cmp-operations-search-results" class="cmp-grid cmp-grid-2"></div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Upcoming Batches', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Batch start dates, occupancy, and seat pressure in one place.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Start', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $snapshot['upcoming_batches'] ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No upcoming batches found.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $snapshot['upcoming_batches'] as $batch ) : ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id ) ) ); ?>"><?php echo esc_html( $batch->batch_name ); ?></a>
											<br><span class="cmp-muted"><?php echo esc_html( $batch->class_name ); ?></span>
										</td>
										<td><?php echo esc_html( cmp_get_teacher_label( (int) $batch->teacher_user_id ) ); ?></td>
										<td><?php echo esc_html( ! empty( $batch->start_date ) ? wp_date( 'M j, Y', strtotime( $batch->start_date ) ) : __( 'Not set', 'class-manager-pro' ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $batch->student_count ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Business Goals', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Track progress against revenue, admissions, and other goals.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-stack">
					<?php if ( empty( $snapshot['business_goals'] ) ) : ?>
						<div class="cmp-callout"><p><?php esc_html_e( 'No goals have been created yet.', 'class-manager-pro' ); ?></p></div>
					<?php else : ?>
						<?php foreach ( array_slice( $snapshot['business_goals'], 0, 6 ) as $goal ) : ?>
							<div class="cmp-callout">
								<div class="cmp-panel-header">
									<div>
										<h3><?php echo esc_html( $goal->title ); ?></h3>
										<p class="cmp-muted"><?php echo esc_html( cmp_operations_goal_types()[ $goal->goal_type ] ?? $goal->goal_type ); ?> - <?php echo esc_html( ucfirst( $goal->period ) ); ?></p>
									</div>
									<strong><?php echo esc_html( number_format_i18n( cmp_operations_goal_progress( $goal ), 2 ) ); ?>%</strong>
								</div>
								<div class="cmp-progress"><span style="width:<?php echo esc_attr( min( 100, cmp_operations_goal_progress( $goal ) ) ); ?>%;"></span></div>
								<p class="cmp-muted"><?php echo esc_html( cmp_format_money( (float) $goal->current_value ) ); ?> / <?php echo esc_html( cmp_format_money( (float) $goal->target_value ) ); ?></p>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</section>
		</div>
	</div>
	<?php
	return cmp_operations_shell_markup( 'teacher_schedule', ob_get_clean() );
}

/**
 * Renders a generic event form.
 *
 * @param string $section Section key.
 * @param array  $defaults Defaults.
 * @return string
 */
function cmp_render_operations_event_form( $section, $defaults = array() ) {
	$defaults = wp_parse_args(
		$defaults,
		array(
			'id'                => 0,
			'title'             => '',
			'event_type'        => 'other',
			'status'            => 'planned',
			'priority'          => 'normal',
			'batch_id'          => 0,
			'class_id'          => 0,
			'teacher_user_id'   => 0,
			'assigned_user_id'  => 0,
			'start_at'          => '',
			'end_at'            => '',
			'all_day'           => 0,
			'recurrence_freq'   => 'none',
			'recurrence_interval'=> 1,
			'recurrence_until'  => '',
			'location'          => '',
			'meet_link'         => '',
			'notes'             => '',
			'reminder_at'       => '',
			'reminder_channel'  => 'email',
		)
	);

	$classes = cmp_get_classes();
	$batches = cmp_get_batches();
	$teachers = cmp_get_teacher_users();
	$event_types = cmp_operations_event_types();
	$status_options = cmp_operations_event_statuses();
	$priority_options = cmp_operations_event_priorities();
	$recurrence_options = cmp_operations_recurrence_frequencies();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form cmp-operations-event-form" data-cmp-ajax-form="cmp_save_operations_event_ajax">
		<input type="hidden" name="action" value="cmp_save_operations_event">
		<input type="hidden" name="operations_section" value="<?php echo esc_attr( $section ); ?>">
		<input type="hidden" name="id" value="<?php echo esc_attr( (int) $defaults['id'] ); ?>">
		<?php wp_nonce_field( 'cmp_save_operations_event' ); ?>
		<div class="cmp-grid cmp-grid-2">
			<div>
				<label for="cmp-operations-event-title"><?php esc_html_e( 'Title', 'class-manager-pro' ); ?></label>
				<input type="text" id="cmp-operations-event-title" name="title" class="regular-text" value="<?php echo esc_attr( $defaults['title'] ); ?>" required>
			</div>
			<div>
				<label for="cmp-operations-event-type"><?php esc_html_e( 'Event Type', 'class-manager-pro' ); ?></label>
				<select id="cmp-operations-event-type" name="event_type">
					<?php foreach ( $event_types as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $defaults['event_type'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="cmp-operations-event-batch"><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></label>
				<select id="cmp-operations-event-batch" name="batch_id" data-cmp-batches>
					<option value="0"><?php esc_html_e( 'No batch', 'class-manager-pro' ); ?></option>
					<?php foreach ( $batches as $batch ) : ?>
						<option value="<?php echo esc_attr( (int) $batch->id ); ?>" <?php selected( (int) $defaults['batch_id'], (int) $batch->id ); ?>><?php echo esc_html( $batch->batch_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="cmp-operations-event-teacher"><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></label>
				<select id="cmp-operations-event-teacher" name="teacher_user_id">
					<option value="0"><?php esc_html_e( 'No teacher', 'class-manager-pro' ); ?></option>
					<?php foreach ( $teachers as $teacher ) : ?>
						<option value="<?php echo esc_attr( (int) $teacher->ID ); ?>" <?php selected( (int) $defaults['teacher_user_id'], (int) $teacher->ID ); ?>><?php echo esc_html( $teacher->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="cmp-operations-event-start"><?php esc_html_e( 'Start', 'class-manager-pro' ); ?></label>
				<input type="datetime-local" id="cmp-operations-event-start" name="start_at" value="<?php echo esc_attr( $defaults['start_at'] ? str_replace( ' ', 'T', substr( $defaults['start_at'], 0, 16 ) ) : '' ); ?>">
			</div>
			<div>
				<label for="cmp-operations-event-end"><?php esc_html_e( 'End', 'class-manager-pro' ); ?></label>
				<input type="datetime-local" id="cmp-operations-event-end" name="end_at" value="<?php echo esc_attr( $defaults['end_at'] ? str_replace( ' ', 'T', substr( $defaults['end_at'], 0, 16 ) ) : '' ); ?>">
			</div>
			<div>
				<label for="cmp-operations-event-status"><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></label>
				<select id="cmp-operations-event-status" name="status">
					<?php foreach ( $status_options as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $defaults['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="cmp-operations-event-priority"><?php esc_html_e( 'Priority', 'class-manager-pro' ); ?></label>
				<select id="cmp-operations-event-priority" name="priority">
					<?php foreach ( $priority_options as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $defaults['priority'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="cmp-operations-event-recurrence"><?php esc_html_e( 'Recurring', 'class-manager-pro' ); ?></label>
				<div class="cmp-inline-tools">
					<select id="cmp-operations-event-recurrence" name="recurrence_freq">
						<?php foreach ( $recurrence_options as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $defaults['recurrence_freq'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="number" name="recurrence_interval" min="1" step="1" value="<?php echo esc_attr( (int) $defaults['recurrence_interval'] ); ?>" style="max-width: 100px;">
				</div>
			</div>
			<div>
				<label for="cmp-operations-event-until"><?php esc_html_e( 'Repeat Until', 'class-manager-pro' ); ?></label>
				<input type="date" id="cmp-operations-event-until" name="recurrence_until" value="<?php echo esc_attr( $defaults['recurrence_until'] ); ?>">
			</div>
			<div>
				<label for="cmp-operations-event-location"><?php esc_html_e( 'Location', 'class-manager-pro' ); ?></label>
				<input type="text" id="cmp-operations-event-location" name="location" value="<?php echo esc_attr( $defaults['location'] ); ?>">
			</div>
			<div>
				<label for="cmp-operations-event-meet"><?php esc_html_e( 'Meet Link', 'class-manager-pro' ); ?></label>
				<input type="url" id="cmp-operations-event-meet" name="meet_link" value="<?php echo esc_attr( $defaults['meet_link'] ); ?>">
			</div>
			<div>
				<label for="cmp-operations-event-reminder"><?php esc_html_e( 'Reminder At', 'class-manager-pro' ); ?></label>
				<input type="datetime-local" id="cmp-operations-event-reminder" name="reminder_at" value="<?php echo esc_attr( $defaults['reminder_at'] ? str_replace( ' ', 'T', substr( $defaults['reminder_at'], 0, 16 ) ) : '' ); ?>">
			</div>
			<div>
				<label for="cmp-operations-event-reminder-channel"><?php esc_html_e( 'Reminder Channel', 'class-manager-pro' ); ?></label>
				<select id="cmp-operations-event-reminder-channel" name="reminder_channel">
					<option value="email" <?php selected( $defaults['reminder_channel'], 'email' ); ?>><?php esc_html_e( 'Email', 'class-manager-pro' ); ?></option>
					<option value="whatsapp" <?php selected( $defaults['reminder_channel'], 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp', 'class-manager-pro' ); ?></option>
					<option value="admin" <?php selected( $defaults['reminder_channel'], 'admin' ); ?>><?php esc_html_e( 'Admin', 'class-manager-pro' ); ?></option>
				</select>
			</div>
		</div>
		<p>
			<label for="cmp-operations-event-notes"><?php esc_html_e( 'Notes', 'class-manager-pro' ); ?></label>
			<textarea id="cmp-operations-event-notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $defaults['notes'] ); ?></textarea>
		</p>
		<p>
			<label><input type="checkbox" name="all_day" value="1" <?php checked( ! empty( $defaults['all_day'] ) ); ?>> <?php esc_html_e( 'All day event', 'class-manager-pro' ); ?></label>
		</p>
		<?php submit_button( empty( $defaults['id'] ) ? __( 'Save Event', 'class-manager-pro' ) : __( 'Update Event', 'class-manager-pro' ) ); ?>
	</form>
	<?php
	return ob_get_clean();
}

/**
 * Renders the dashboard section wrapper.
 *
 * @param array $args Request args.
 * @return string
 */
function cmp_render_operations_dashboard_section( $args = array() ) {
	$snapshot = cmp_get_operations_dashboard_snapshot( $args );
	return cmp_operations_shell_markup( 'dashboard', cmp_render_operations_dashboard_content( $snapshot ) );
}

/**
 * Renders a calendar feed wrapper.
 *
 * @param array $args Request args.
 * @return string
 */
function cmp_render_operations_calendar_section( $args = array() ) {
	$settings = cmp_get_operations_center_settings();
	$selected_teacher = absint( cmp_field( $args, 'teacher_user_id', 0 ) );
	$selected_batch   = absint( cmp_field( $args, 'batch_id', 0 ) );
	$selected_type    = sanitize_key( cmp_field( $args, 'event_type', '' ) );
	$selected_status  = sanitize_key( cmp_field( $args, 'status', '' ) );
	$selected_priority= sanitize_key( cmp_field( $args, 'priority', '' ) );
	$selected_search  = sanitize_text_field( cmp_field( $args, 'search', '' ) );
	$default_view     = sanitize_key( cmp_field( $args, 'view', $settings['calendar_default_view'] ) );
	ob_start();
	?>
	<div id="<?php echo esc_attr( cmp_operations_section_target_id( 'calendar' ) ); ?>">
		<div class="cmp-toolbar cmp-operations-toolbar">
			<form method="get" class="cmp-inline-form cmp-operations-calendar-filters" data-cmp-operations-calendar-filters="1">
				<input type="hidden" name="page" value="cmp-operations-center-calendar">
				<select name="view" data-cmp-calendar-view>
					<option value="dayGridMonth" <?php selected( $default_view, 'dayGridMonth' ); ?>><?php esc_html_e( 'Month View', 'class-manager-pro' ); ?></option>
					<option value="timeGridWeek" <?php selected( $default_view, 'timeGridWeek' ); ?>><?php esc_html_e( 'Week View', 'class-manager-pro' ); ?></option>
					<option value="timeGridDay" <?php selected( $default_view, 'timeGridDay' ); ?>><?php esc_html_e( 'Day View', 'class-manager-pro' ); ?></option>
					<option value="listWeek" <?php selected( $default_view, 'listWeek' ); ?>><?php esc_html_e( 'Agenda View', 'class-manager-pro' ); ?></option>
				</select>
				<select name="batch_id" data-cmp-batches>
					<option value="0"><?php esc_html_e( 'All batches', 'class-manager-pro' ); ?></option>
					<?php foreach ( cmp_get_batches() as $batch ) : ?>
						<option value="<?php echo esc_attr( (int) $batch->id ); ?>" <?php selected( $selected_batch, (int) $batch->id ); ?>><?php echo esc_html( $batch->batch_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="teacher_user_id">
					<option value="0"><?php esc_html_e( 'All teachers', 'class-manager-pro' ); ?></option>
					<?php foreach ( cmp_get_teacher_users() as $teacher ) : ?>
						<option value="<?php echo esc_attr( (int) $teacher->ID ); ?>" <?php selected( $selected_teacher, (int) $teacher->ID ); ?>><?php echo esc_html( $teacher->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="event_type">
					<option value=""><?php esc_html_e( 'All types', 'class-manager-pro' ); ?></option>
					<?php foreach ( cmp_operations_event_types() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_type, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="status">
					<option value=""><?php esc_html_e( 'All statuses', 'class-manager-pro' ); ?></option>
					<?php foreach ( cmp_operations_event_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_status, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="priority">
					<option value=""><?php esc_html_e( 'All priorities', 'class-manager-pro' ); ?></option>
					<?php foreach ( cmp_operations_event_priorities() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_priority, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="search" name="search" value="<?php echo esc_attr( $selected_search ); ?>" placeholder="<?php esc_attr_e( 'Search events', 'class-manager-pro' ); ?>">
				<button type="button" class="button" data-cmp-calendar-refresh="1"><?php esc_html_e( 'Refresh', 'class-manager-pro' ); ?></button>
			</form>
			<div class="cmp-inline-tools">
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'cmp_export_operations_ics', 'scope' => 'calendar' ), admin_url( 'admin-post.php' ) ), 'cmp_export_operations_ics' ) ); ?>"><?php esc_html_e( 'Export ICS', 'class-manager-pro' ); ?></a>
			</div>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Calendar', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Drag and drop events to reschedule. Resize to change duration.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-calendar-shell">
					<div id="cmp-operations-calendar" class="cmp-operations-calendar" data-cmp-default-view="<?php echo esc_attr( $default_view ); ?>"></div>
				</div>
			</section>

			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Event Editor', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Click a calendar item to edit it or create a new one with the form below.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-callout cmp-callout-info">
					<p><?php esc_html_e( 'Use this form to create classes, teacher tasks, holidays, reminders, and planning items.', 'class-manager-pro' ); ?></p>
				</div>
				<?php echo cmp_render_operations_event_form( 'calendar', array( 'batch_id' => $selected_batch, 'teacher_user_id' => $selected_teacher ) ); ?>
			</section>
		</div>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Upcoming Events', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'A compact list of the next few items in the selected filter scope.', 'class-manager-pro' ); ?></p>
				</div>
			</div>
			<div id="cmp-operations-calendar-events-list">
				<?php echo cmp_render_operations_event_table( array( 'batch_id' => $selected_batch, 'teacher_user_id' => $selected_teacher, 'event_type' => $selected_type, 'status' => $selected_status, 'priority' => $selected_priority, 'search' => $selected_search, 'limit' => 12 ) ); ?>
			</div>
		</section>
	</div>
	<?php
	return cmp_operations_shell_markup( 'calendar', ob_get_clean() );
}

/**
 * Renders a generic operations event table.
 *
 * @param array $args Query args.
 * @return string
 */
function cmp_render_operations_event_table( $args = array() ) {
	$rows = cmp_get_operations_event_rows( $args );

	ob_start();
	?>
	<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Start', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Priority', 'class-manager-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No events found.', 'class-manager-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $row->title ); ?></strong>
								<br><span class="cmp-muted"><?php echo esc_html( cmp_operations_event_type_label( $row->event_type ) ); ?></span>
							</td>
							<td><?php echo esc_html( ! empty( $row->batch_name ) ? $row->batch_name : __( 'General', 'class-manager-pro' ) ); ?></td>
							<td><?php echo esc_html( ! empty( $row->teacher_name ) ? $row->teacher_name : __( 'Unassigned', 'class-manager-pro' ) ); ?></td>
							<td><?php echo esc_html( cmp_operations_format_datetime( $row->start_at ) ); ?></td>
							<td><span class="cmp-status <?php echo esc_attr( cmp_operations_status_class( $row->status ) ); ?>"><?php echo esc_html( cmp_operations_status_label( $row->status ) ); ?></span></td>
							<td><span class="cmp-inline-badge"><?php echo esc_html( cmp_operations_priority_label( $row->priority ) ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Renders the monthly planner page.
 *
 * @param array $args Request args.
 * @return string
 */
function cmp_render_operations_planner_section( $args = array() ) {
	$month = sanitize_text_field( cmp_field( $args, 'month', current_time( 'Y-m' ) ) );
	$start = $month . '-01 00:00:00';
	$end   = gmdate( 'Y-m-t 23:59:59', strtotime( $start ) );
	$rows  = cmp_get_operations_event_rows( array_merge( $args, array( 'start' => $start, 'end' => $end, 'limit' => 80 ) ) );

	return cmp_operations_shell_markup(
		'planner',
		cmp_render_operations_event_form( 'planner', array( 'start_at' => $start, 'event_type' => 'business' ) ) .
		'<section class="cmp-panel">' .
		'<div class="cmp-panel-header"><div><h2>' . esc_html__( 'Monthly Items', 'class-manager-pro' ) . '</h2><p class="cmp-muted">' . esc_html__( 'Create, edit, duplicate, move, and track the month in one list.', 'class-manager-pro' ) . '</p></div></div>' .
		cmp_render_operations_event_table( array_merge( $args, array( 'start' => $start, 'end' => $end, 'limit' => 100 ) ) ) .
		'</section>'
	);
}

/**
 * Renders the batch schedule page.
 *
 * @param array $args Request args.
 * @return string
 */
function cmp_render_operations_batch_schedule_section( $args = array() ) {
	$rows = cmp_get_operations_batch_schedule_rows( $args );

	ob_start();
	?>
	<div id="<?php echo esc_attr( cmp_operations_section_target_id( 'batch_schedule' ) ); ?>">
		<div class="cmp-toolbar">
			<form method="get" class="cmp-inline-form cmp-filter-form" data-cmp-action="cmp_filter_operations_section" data-cmp-target=".cmp-operations-shell">
				<input type="hidden" name="page" value="cmp-operations-center-batch-schedule">
				<input type="hidden" name="section" value="batch_schedule">
				<select name="status">
					<option value=""><?php esc_html_e( 'All statuses', 'class-manager-pro' ); ?></option>
					<option value="active" <?php selected( sanitize_key( cmp_field( $args, 'status', '' ) ), 'active' ); ?>><?php esc_html_e( 'Active', 'class-manager-pro' ); ?></option>
					<option value="completed" <?php selected( sanitize_key( cmp_field( $args, 'status', '' ) ), 'completed' ); ?>><?php esc_html_e( 'Completed', 'class-manager-pro' ); ?></option>
				</select>
				<select name="teacher_user_id">
					<option value="0"><?php esc_html_e( 'All teachers', 'class-manager-pro' ); ?></option>
					<?php foreach ( cmp_get_teacher_users() as $teacher ) : ?>
						<option value="<?php echo esc_attr( (int) $teacher->ID ); ?>" <?php selected( absint( cmp_field( $args, 'teacher_user_id', 0 ) ), (int) $teacher->ID ); ?>><?php echo esc_html( $teacher->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="search" name="search" value="<?php echo esc_attr( cmp_field( $args, 'search', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Search batch', 'class-manager-pro' ); ?>">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'class-manager-pro' ); ?></button>
			</form>
		</div>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Batch Schedule', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Quick access to start dates, teachers, meeting links, documents, and quick actions.', 'class-manager-pro' ); ?></p>
				</div>
			</div>
			<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Teacher', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Course', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Start Date', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Students', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Docs', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr><td colspan="7"><?php esc_html_e( 'No batches found.', 'class-manager-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $row->batch_name ); ?></strong><br><span class="cmp-muted"><?php echo esc_html( $row->class_name ); ?></span></td>
									<td><?php echo esc_html( cmp_get_teacher_label( (int) $row->teacher_user_id ) ); ?></td>
									<td><?php echo esc_html( $row->class_name ); ?></td>
									<td><?php echo esc_html( ! empty( $row->start_date ) ? wp_date( 'M j, Y', strtotime( $row->start_date ) ) : __( 'Not set', 'class-manager-pro' ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( (int) $row->student_count ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( (int) $row->document_count ) ); ?></td>
									<td>
										<a class="button button-small" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $row->id ) ) ); ?>"><?php esc_html_e( 'Open', 'class-manager-pro' ); ?></a>
										<?php if ( ! empty( $row->meet_link ) ) : ?>
											<a class="button button-small" href="<?php echo esc_url( $row->meet_link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Meet Link', 'class-manager-pro' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</div>
	<?php
	return cmp_operations_shell_markup( 'teacher_schedule', ob_get_clean() );
}

/**
 * Renders the teacher schedule page.
 *
 * @param array $args Request args.
 * @return string
 */
function cmp_render_operations_teacher_schedule_section( $args = array() ) {
	$teacher_id = current_user_can( 'manage_options' ) ? absint( cmp_field( $args, 'teacher_user_id', 0 ) ) : get_current_user_id();
	$payload    = cmp_get_operations_teacher_schedule_rows( array( 'teacher_user_id' => $teacher_id ) );

	ob_start();
	?>
	<div id="<?php echo esc_attr( cmp_operations_section_target_id( 'teacher_schedule' ) ); ?>">
		<div class="cmp-toolbar">
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<form method="get" class="cmp-inline-form cmp-filter-form" data-cmp-action="cmp_filter_operations_section" data-cmp-target=".cmp-operations-shell">
					<input type="hidden" name="page" value="cmp-operations-center-teacher-schedule">
					<input type="hidden" name="section" value="teacher_schedule">
					<select name="teacher_user_id">
						<?php foreach ( cmp_get_teacher_users() as $teacher ) : ?>
							<option value="<?php echo esc_attr( (int) $teacher->ID ); ?>" <?php selected( $teacher_id, (int) $teacher->ID ); ?>><?php echo esc_html( $teacher->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Load Teacher', 'class-manager-pro' ); ?></button>
				</form>
			<?php else : ?>
				<div class="cmp-inline-badge cmp-inline-badge-success"><?php esc_html_e( 'Teacher-scoped view only', 'class-manager-pro' ); ?></div>
			<?php endif; ?>
		</div>

		<div class="cmp-cards cmp-cards-4">
			<div class="cmp-card">
				<span><?php esc_html_e( 'Assigned Batches', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( count( $payload['batches'] ) ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( "Today's Work", 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( count( $payload['events_today'] ) ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Upcoming Classes', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( count( $payload['upcoming'] ) ) ); ?></strong>
			</div>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Documents', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( array_sum( array_map( 'count', $payload['documents'] ) ) ) ); ?></strong>
			</div>
		</div>

		<div class="cmp-grid cmp-grid-2">
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Today', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( "Only this teacher's batches and tasks appear here.", 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<?php echo cmp_render_operations_event_table( array( 'teacher_user_id' => $teacher_id, 'limit' => 8 ) ); ?>
			</section>
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php esc_html_e( 'Batches', 'class-manager-pro' ); ?></h2>
						<p class="cmp-muted"><?php esc_html_e( 'Teacher assignments, links, and docs.', 'class-manager-pro' ); ?></p>
					</div>
				</div>
				<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Start', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Docs', 'class-manager-pro' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $payload['batches'] ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No batches assigned.', 'class-manager-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $payload['batches'] as $batch ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $batch->batch_name ); ?></strong><br><span class="cmp-muted"><?php echo esc_html( $batch->class_name ); ?></span></td>
										<td><?php echo esc_html( ! empty( $batch->start_date ) ? wp_date( 'M j, Y', strtotime( $batch->start_date ) ) : __( 'Not set', 'class-manager-pro' ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( isset( $payload['documents'][ (int) $batch->id ] ) ? count( $payload['documents'][ (int) $batch->id ] ) : 0 ) ); ?></td>
										<td><a class="button button-small" href="<?php echo esc_url( cmp_admin_url( 'cmp-batches', array( 'action' => 'view', 'id' => (int) $batch->id ) ) ); ?>"><?php esc_html_e( 'Open', 'class-manager-pro' ); ?></a></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Renders the marketing planner page.
 *
 * @param array $args Request args.
 * @return string
 */
function cmp_render_operations_marketing_section( $args = array() ) {
	$rows = cmp_get_operations_event_rows( array_merge( $args, array( 'event_type' => 'marketing', 'limit' => 40 ) ) );

	return cmp_operations_shell_markup(
		'marketing',
		cmp_render_operations_event_form( 'marketing', array( 'event_type' => 'marketing', 'priority' => 'high' ) ) .
		'<section class="cmp-panel">' .
		'<div class="cmp-panel-header"><div><h2>' . esc_html__( 'Campaign Planner', 'class-manager-pro' ) . '</h2><p class="cmp-muted">' . esc_html__( 'Track ads, offers, staff ownership, and campaign reminders.', 'class-manager-pro' ) . '</p></div></div>' .
		cmp_render_operations_event_table( array_merge( $args, array( 'event_type' => 'marketing', 'limit' => 20 ) ) ) .
		'</section>'
	);
}

/**
 * Renders the business planner page.
 *
 * @param array $args Request args.
 * @return string
 */
function cmp_render_operations_business_section( $args = array() ) {
	$rows = cmp_get_operations_goal_rows( array_merge( $args, array( 'limit' => 40 ) ) );

	ob_start();
	?>
	<div id="<?php echo esc_attr( cmp_operations_section_target_id( 'business' ) ); ?>">
		<div class="cmp-cards cmp-cards-4">
			<?php foreach ( array( 'monthly', 'quarterly', 'yearly' ) as $period ) : ?>
				<?php
				$filtered = array_filter(
					$rows,
					static function ( $row ) use ( $period ) {
						return isset( $row->period ) && $period === $row->period;
					}
				);
				$total = 0;
				$current = 0;
				foreach ( $filtered as $goal ) {
					$total += (float) $goal->target_value;
					$current += (float) $goal->current_value;
				}
				?>
				<div class="cmp-card">
					<span><?php echo esc_html( ucfirst( $period ) ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $total > 0 ? ( $current / $total ) * 100 : 0, 2 ) ); ?>%</strong>
				</div>
			<?php endforeach; ?>
			<div class="cmp-card">
				<span><?php esc_html_e( 'Goals', 'class-manager-pro' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></strong>
			</div>
		</div>

		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Business Goals', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Create and monitor revenue, admissions, expense, and batch goals.', 'class-manager-pro' ); ?></p>
				</div>
			</div>
			<?php echo cmp_render_operations_goal_form(); ?>
			<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall cmp-space-top">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Goal', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Period', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Progress', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr><td colspan="4"><?php esc_html_e( 'No goals found.', 'class-manager-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $rows as $goal ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $goal->title ); ?></strong><br><span class="cmp-muted"><?php echo esc_html( cmp_operations_goal_types()[ $goal->goal_type ] ?? $goal->goal_type ); ?></span></td>
									<td><?php echo esc_html( ucfirst( $goal->period ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( cmp_operations_goal_progress( $goal ), 2 ) ); ?>%</td>
									<td><span class="cmp-inline-badge"><?php echo esc_html( ucfirst( $goal->status ) ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</div>
	<?php
	return cmp_operations_shell_markup( 'business', ob_get_clean() );
}

/**
 * Renders the documents page.
 *
 * @param array $args Request args.
 * @return string
 */
function cmp_render_operations_documents_section( $args = array() ) {
	$rows = cmp_get_operations_document_rows( array_merge( $args, array( 'limit' => 50 ) ) );

	ob_start();
	?>
	<div id="<?php echo esc_attr( cmp_operations_section_target_id( 'documents' ) ); ?>">
		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Document Center', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Upload files, keep version history, and share batch resources.', 'class-manager-pro' ); ?></p>
				</div>
			</div>
			<?php echo cmp_render_operations_document_form(); ?>
			<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall cmp-space-top">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Type', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Version', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'No documents found.', 'class-manager-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $row->title ); ?></strong><br><span class="cmp-muted"><?php echo esc_html( ! empty( $row->file_name ) ? $row->file_name : $row->teacher_name ); ?></span></td>
									<td><?php echo esc_html( ! empty( $row->batch_name ) ? $row->batch_name : __( 'General', 'class-manager-pro' ) ); ?></td>
									<td><?php echo esc_html( cmp_operations_document_types()[ $row->document_type ] ?? $row->document_type ); ?></td>
									<td><?php echo esc_html( 'v' . number_format_i18n( (int) $row->version_number ) ); ?></td>
									<td>
										<?php if ( ! empty( $row->file_url ) ) : ?>
											<a class="button button-small" href="<?php echo esc_url( $row->file_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview', 'class-manager-pro' ); ?></a>
											<a class="button button-small" href="<?php echo esc_url( $row->file_url ); ?>" download><?php esc_html_e( 'Download', 'class-manager-pro' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</div>
	<?php
	return cmp_operations_shell_markup( 'documents', ob_get_clean() );
}

/**
 * Renders the holidays page.
 *
 * @param array $args Request args.
 * @return string
 */
function cmp_render_operations_holidays_section( $args = array() ) {
	ob_start();
	?>
	<div id="<?php echo esc_attr( cmp_operations_section_target_id( 'holidays' ) ); ?>">
		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Holiday Manager', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Institute, teacher, and batch holidays automatically update the calendar.', 'class-manager-pro' ); ?></p>
				</div>
			</div>
			<?php echo cmp_render_operations_event_form( 'holidays', array( 'event_type' => 'holiday', 'all_day' => 1 ) ); ?>
			<div class="cmp-space-top">
				<?php echo cmp_render_operations_event_table( array_merge( $args, array( 'event_type' => 'holiday', 'limit' => 20 ) ) ); ?>
			</div>
		</section>
	</div>
	<?php
	return cmp_operations_shell_markup( 'holidays', ob_get_clean() );
}

/**
 * Renders the goals page.
 *
 * @param array $args Request args.
 * @return string
 */
function cmp_render_operations_goals_section( $args = array() ) {
	$rows = cmp_get_operations_goal_rows( array_merge( $args, array( 'limit' => 50 ) ) );

	ob_start();
	?>
	<div id="<?php echo esc_attr( cmp_operations_section_target_id( 'goals' ) ); ?>">
		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Goals Tracking', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Monthly, quarterly, and yearly progress for the business team.', 'class-manager-pro' ); ?></p>
				</div>
			</div>
			<?php echo cmp_render_operations_goal_form(); ?>
			<div class="cmp-table-scroll cmp-table-scroll-y cmp-table-scroll-tall cmp-space-top">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Goal', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Target', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Current', 'class-manager-pro' ); ?></th>
							<th><?php esc_html_e( 'Progress', 'class-manager-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr><td colspan="4"><?php esc_html_e( 'No goals found.', 'class-manager-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $rows as $goal ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $goal->title ); ?></strong><br><span class="cmp-muted"><?php echo esc_html( cmp_operations_goal_types()[ $goal->goal_type ] ?? $goal->goal_type ); ?></span></td>
									<td><?php echo esc_html( cmp_format_money( (float) $goal->target_value ) ); ?></td>
									<td><?php echo esc_html( cmp_format_money( (float) $goal->current_value ) ); ?></td>
									<td>
										<div class="cmp-progress"><span style="width:<?php echo esc_attr( min( 100, cmp_operations_goal_progress( $goal ) ) ); ?>%;"></span></div>
										<span class="cmp-muted"><?php echo esc_html( number_format_i18n( cmp_operations_goal_progress( $goal ), 2 ) ); ?>%</span>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</div>
	<?php
	return cmp_operations_shell_markup( 'goals', ob_get_clean() );
}

/**
 * Renders the settings page.
 *
 * @param array $args Request args.
 * @return string
 */
function cmp_render_operations_settings_section( $args = array() ) {
	$settings = cmp_get_operations_center_settings();

	ob_start();
	?>
	<div id="<?php echo esc_attr( cmp_operations_section_target_id( 'settings' ) ); ?>">
		<section class="cmp-panel">
			<div class="cmp-panel-header">
				<div>
					<h2><?php esc_html_e( 'Operations Settings', 'class-manager-pro' ); ?></h2>
					<p class="cmp-muted"><?php esc_html_e( 'Set the calendar default view, working hours, and ICS export label.', 'class-manager-pro' ); ?></p>
				</div>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form cmp-operations-settings-form" data-cmp-ajax-form="cmp_save_operations_settings_ajax">
				<input type="hidden" name="action" value="cmp_save_operations_settings">
				<input type="hidden" name="operations_section" value="settings">
				<?php wp_nonce_field( 'cmp_save_operations_settings' ); ?>
				<div class="cmp-grid cmp-grid-2">
					<div>
						<label for="cmp-operations-calendar-view"><?php esc_html_e( 'Default Calendar View', 'class-manager-pro' ); ?></label>
						<select id="cmp-operations-calendar-view" name="calendar_default_view">
							<option value="dayGridMonth" <?php selected( $settings['calendar_default_view'], 'dayGridMonth' ); ?>><?php esc_html_e( 'Month View', 'class-manager-pro' ); ?></option>
							<option value="timeGridWeek" <?php selected( $settings['calendar_default_view'], 'timeGridWeek' ); ?>><?php esc_html_e( 'Week View', 'class-manager-pro' ); ?></option>
							<option value="timeGridDay" <?php selected( $settings['calendar_default_view'], 'timeGridDay' ); ?>><?php esc_html_e( 'Day View', 'class-manager-pro' ); ?></option>
							<option value="listWeek" <?php selected( $settings['calendar_default_view'], 'listWeek' ); ?>><?php esc_html_e( 'Agenda View', 'class-manager-pro' ); ?></option>
						</select>
					</div>
					<div>
						<label for="cmp-operations-ics-title"><?php esc_html_e( 'ICS Feed Title', 'class-manager-pro' ); ?></label>
						<input type="text" id="cmp-operations-ics-title" name="ics_feed_title" value="<?php echo esc_attr( $settings['ics_feed_title'] ); ?>">
					</div>
					<div>
						<label for="cmp-operations-working-start"><?php esc_html_e( 'Working Hours Start', 'class-manager-pro' ); ?></label>
						<input type="time" id="cmp-operations-working-start" name="working_hours_start" value="<?php echo esc_attr( $settings['working_hours_start'] ); ?>">
					</div>
					<div>
						<label for="cmp-operations-working-end"><?php esc_html_e( 'Working Hours End', 'class-manager-pro' ); ?></label>
						<input type="time" id="cmp-operations-working-end" name="working_hours_end" value="<?php echo esc_attr( $settings['working_hours_end'] ); ?>">
					</div>
				</div>
				<p>
					<label for="cmp-operations-reminder-subject"><?php esc_html_e( 'Reminder Subject', 'class-manager-pro' ); ?></label>
					<input type="text" id="cmp-operations-reminder-subject" name="reminder_subject" class="large-text" value="<?php echo esc_attr( $settings['reminder_subject'] ); ?>">
				</p>
				<p>
					<label for="cmp-operations-reminder-email"><?php esc_html_e( 'Reminder Email Body', 'class-manager-pro' ); ?></label>
					<textarea id="cmp-operations-reminder-email" name="reminder_email" rows="4" class="large-text"><?php echo esc_textarea( $settings['reminder_email'] ); ?></textarea>
				</p>
				<?php submit_button( __( 'Save Settings', 'class-manager-pro' ) ); ?>
			</form>
		</section>
	</div>
	<?php
	return cmp_operations_shell_markup( 'settings', ob_get_clean() );
}

/**
 * Renders a goal form.
 *
 * @param object|null $goal Goal row.
 * @return string
 */
function cmp_render_operations_goal_form( $goal = null ) {
	$goal = is_object( $goal ) ? $goal : null;
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form cmp-operations-goal-form" data-cmp-ajax-form="cmp_save_operations_goal_ajax">
		<input type="hidden" name="action" value="cmp_save_operations_goal">
		<input type="hidden" name="operations_section" value="goals">
		<input type="hidden" name="id" value="<?php echo esc_attr( $goal ? (int) $goal->id : 0 ); ?>">
		<?php wp_nonce_field( 'cmp_save_operations_goal' ); ?>
		<div class="cmp-grid cmp-grid-2">
			<div>
				<label><?php esc_html_e( 'Goal Type', 'class-manager-pro' ); ?></label>
				<select name="goal_type">
					<?php foreach ( cmp_operations_goal_types() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $goal ? $goal->goal_type : 'other', $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label><?php esc_html_e( 'Period', 'class-manager-pro' ); ?></label>
				<select name="period">
					<option value="monthly" <?php selected( $goal ? $goal->period : 'monthly', 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'class-manager-pro' ); ?></option>
					<option value="quarterly" <?php selected( $goal ? $goal->period : 'monthly', 'quarterly' ); ?>><?php esc_html_e( 'Quarterly', 'class-manager-pro' ); ?></option>
					<option value="yearly" <?php selected( $goal ? $goal->period : 'monthly', 'yearly' ); ?>><?php esc_html_e( 'Yearly', 'class-manager-pro' ); ?></option>
				</select>
			</div>
			<div>
				<label><?php esc_html_e( 'Title', 'class-manager-pro' ); ?></label>
				<input type="text" name="title" value="<?php echo esc_attr( $goal ? $goal->title : '' ); ?>" required>
			</div>
			<div>
				<label><?php esc_html_e( 'Target Value', 'class-manager-pro' ); ?></label>
				<input type="number" name="target_value" min="0" step="0.01" value="<?php echo esc_attr( $goal ? (float) $goal->target_value : '' ); ?>">
			</div>
			<div>
				<label><?php esc_html_e( 'Current Value', 'class-manager-pro' ); ?></label>
				<input type="number" name="current_value" min="0" step="0.01" value="<?php echo esc_attr( $goal ? (float) $goal->current_value : '' ); ?>">
			</div>
			<div>
				<label><?php esc_html_e( 'Status', 'class-manager-pro' ); ?></label>
				<select name="status">
					<option value="active" <?php selected( $goal ? $goal->status : 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'class-manager-pro' ); ?></option>
					<option value="paused" <?php selected( $goal ? $goal->status : 'active', 'paused' ); ?>><?php esc_html_e( 'Paused', 'class-manager-pro' ); ?></option>
					<option value="completed" <?php selected( $goal ? $goal->status : 'active', 'completed' ); ?>><?php esc_html_e( 'Completed', 'class-manager-pro' ); ?></option>
					<option value="cancelled" <?php selected( $goal ? $goal->status : 'active', 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'class-manager-pro' ); ?></option>
				</select>
			</div>
		</div>
		<p>
			<label><?php esc_html_e( 'Notes', 'class-manager-pro' ); ?></label>
			<textarea name="notes" rows="3" class="large-text"><?php echo esc_textarea( $goal ? $goal->notes : '' ); ?></textarea>
		</p>
		<?php submit_button( $goal ? __( 'Update Goal', 'class-manager-pro' ) : __( 'Save Goal', 'class-manager-pro' ) ); ?>
	</form>
	<?php
	return ob_get_clean();
}

/**
 * Renders a document form.
 *
 * @param object|null $document Document row.
 * @return string
 */
function cmp_render_operations_document_form( $document = null ) {
	$document = is_object( $document ) ? $document : null;
	$batches   = cmp_get_batches();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form cmp-operations-document-form" enctype="multipart/form-data" data-cmp-ajax-form="cmp_save_operations_document_ajax">
		<input type="hidden" name="action" value="cmp_save_operations_document">
		<input type="hidden" name="operations_section" value="documents">
		<input type="hidden" name="id" value="<?php echo esc_attr( $document ? (int) $document->id : 0 ); ?>">
		<?php wp_nonce_field( 'cmp_save_operations_document' ); ?>
		<div class="cmp-grid cmp-grid-2">
			<div>
				<label><?php esc_html_e( 'Batch', 'class-manager-pro' ); ?></label>
				<select name="batch_id" required>
					<option value="0"><?php esc_html_e( 'Select batch', 'class-manager-pro' ); ?></option>
					<?php foreach ( $batches as $batch ) : ?>
						<option value="<?php echo esc_attr( (int) $batch->id ); ?>" <?php selected( $document ? (int) $document->batch_id : 0, (int) $batch->id ); ?>><?php echo esc_html( $batch->batch_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label><?php esc_html_e( 'Document Type', 'class-manager-pro' ); ?></label>
				<select name="document_type">
					<?php foreach ( cmp_operations_document_types() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $document ? $document->document_type : 'other', $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label><?php esc_html_e( 'Title', 'class-manager-pro' ); ?></label>
				<input type="text" name="title" required value="<?php echo esc_attr( $document ? $document->title : '' ); ?>">
			</div>
			<div>
				<label><?php esc_html_e( 'Version', 'class-manager-pro' ); ?></label>
				<input type="number" name="version_number" min="1" step="1" value="<?php echo esc_attr( $document ? (int) $document->version_number + 1 : 1 ); ?>">
			</div>
		</div>
		<p>
			<label><?php esc_html_e( 'Upload File', 'class-manager-pro' ); ?></label>
			<input type="file" name="document_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.zip">
		</p>
		<p>
			<label><?php esc_html_e( 'Notes', 'class-manager-pro' ); ?></label>
			<textarea name="notes" rows="3" class="large-text"><?php echo esc_textarea( $document ? $document->notes : '' ); ?></textarea>
		</p>
		<p>
			<label><input type="checkbox" name="is_teacher_visible" value="1" <?php checked( ! $document || ! empty( $document->is_teacher_visible ) ); ?>> <?php esc_html_e( 'Visible to teachers', 'class-manager-pro' ); ?></label>
		</p>
		<?php submit_button( $document ? __( 'Add New Version', 'class-manager-pro' ) : __( 'Save Document', 'class-manager-pro' ) ); ?>
	</form>
	<?php
	return ob_get_clean();
}

/**
 * Renders the current operations section HTML.
 *
 * @param string $section Section key.
 * @param array  $args Request args.
 * @return string
 */
function cmp_render_operations_section_html( $section, $args = array() ) {
	$section = sanitize_key( $section );
	$html    = '';

	if ( 'calendar' === $section ) {
		$html = cmp_render_operations_calendar_section( $args );
	} elseif ( 'planner' === $section ) {
		$html = cmp_render_operations_planner_section( $args );
	} elseif ( 'batch_schedule' === $section ) {
		$html = cmp_render_operations_batch_schedule_section( $args );
	} elseif ( 'teacher_schedule' === $section ) {
		$html = cmp_render_operations_teacher_schedule_section( $args );
	} elseif ( 'marketing' === $section ) {
		$html = cmp_render_operations_marketing_section( $args );
	} elseif ( 'business' === $section ) {
		$html = cmp_render_operations_business_section( $args );
	} elseif ( 'documents' === $section ) {
		$html = cmp_render_operations_documents_section( $args );
	} elseif ( 'holidays' === $section ) {
		$html = cmp_render_operations_holidays_section( $args );
	} elseif ( 'goals' === $section ) {
		$html = cmp_render_operations_goals_section( $args );
	} elseif ( 'settings' === $section ) {
		$html = cmp_render_operations_settings_section( $args );
	} else {
		$html = cmp_render_operations_dashboard_section( $args );
	}

	return $html;
}

/**
 * AJAX filter handler for Operations Center sections.
 */
function cmp_ajax_filter_operations_section() {
	cmp_require_operations_center_access_ajax();
	check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

	$section = sanitize_key( cmp_field( $_POST, 'section', 'dashboard' ) );
	$args    = wp_unslash( $_POST );

	wp_send_json_success(
		array(
			'html'    => cmp_render_operations_section_html( $section, $args ),
			'section' => $section,
		)
	);
}

/**
 * AJAX handler for the operations calendar feed.
 */
function cmp_ajax_get_operations_calendar_events() {
	cmp_require_operations_center_access_ajax();
	check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

	$events = cmp_get_operations_calendar_events( wp_unslash( $_POST ) );

	wp_send_json_success(
		array(
			'events' => $events,
		)
	);
}

/**
 * Handles event save via admin-post.
 */
function cmp_handle_save_operations_event() {
	cmp_require_operations_center_access();
	check_admin_referer( 'cmp_save_operations_event' );

	$result = cmp_save_operations_event_record( wp_unslash( $_POST ) );
	$section = sanitize_key( cmp_field( $_POST, 'operations_section', 'dashboard' ) );
	$page    = cmp_operations_section_page_slug( $section );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( $page, $result->get_error_message(), 'error' );
	}

	cmp_redirect( $page, $result['message'] );
}

/**
 * Handles event save via AJAX.
 */
function cmp_ajax_save_operations_event() {
	cmp_require_operations_center_access_ajax();
	check_ajax_referer( 'cmp_save_operations_event', 'nonce' );

	$result = cmp_save_operations_event_record( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			),
			400
		);
	}

	wp_send_json_success(
		array(
			'message' => $result['message'],
			'id'      => $result['id'],
			'refresh'  => array(
				'action' => 'cmp_filter_operations_section',
				'section'=> sanitize_key( cmp_field( $_POST, 'operations_section', 'calendar' ) ),
				'target' => '.cmp-operations-shell',
			),
		)
	);
}

/**
 * Handles event delete via admin-post.
 */
function cmp_handle_delete_operations_event() {
	cmp_require_operations_center_access();

	$id = absint( cmp_field( $_GET, 'id', 0 ) );
	$section = sanitize_key( cmp_field( $_GET, 'operations_section', 'dashboard' ) );
	$page    = cmp_operations_section_page_slug( $section );
	check_admin_referer( 'cmp_delete_operations_event_' . $id );

	$result = cmp_delete_operations_event_record( $id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( $page, $result->get_error_message(), 'error' );
	}

	cmp_redirect( $page, __( 'Event deleted successfully.', 'class-manager-pro' ) );
}

/**
 * Handles event delete via AJAX.
 */
function cmp_ajax_delete_operations_event() {
	cmp_require_operations_center_access_ajax();
	check_ajax_referer( 'cmp_delete_operations_event', 'nonce' );

	$id = absint( cmp_field( $_POST, 'id', 0 ) );
	$result = cmp_delete_operations_event_record( $id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			),
			400
		);
	}

	wp_send_json_success(
		array(
			'message' => __( 'Event deleted successfully.', 'class-manager-pro' ),
			'deleted_row' => 'operations-event-' . $id,
			'refresh'  => array(
				'action' => 'cmp_filter_operations_section',
				'section'=> sanitize_key( cmp_field( $_POST, 'operations_section', 'calendar' ) ),
				'target' => '.cmp-operations-shell',
			),
		)
	);
}

/**
 * Handles document save via admin-post.
 */
function cmp_handle_save_operations_document() {
	cmp_require_operations_center_access();
	check_admin_referer( 'cmp_save_operations_document' );

	$result = cmp_save_operations_document_record( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-operations-center-documents', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-operations-center-documents', $result['message'] );
}

/**
 * Handles document save via AJAX.
 */
function cmp_ajax_save_operations_document() {
	cmp_require_operations_center_access_ajax();
	check_ajax_referer( 'cmp_save_operations_document', 'nonce' );

	$result = cmp_save_operations_document_record( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			),
			400
		);
	}

	wp_send_json_success(
		array(
			'message' => $result['message'],
			'id'      => $result['id'],
			'refresh'  => array(
				'action' => 'cmp_filter_operations_section',
				'section'=> 'documents',
				'target' => '.cmp-operations-shell',
			),
		)
	);
}

/**
 * Handles document delete via admin-post.
 */
function cmp_handle_delete_operations_document() {
	cmp_require_operations_center_access();

	$id = absint( cmp_field( $_GET, 'id', 0 ) );
	check_admin_referer( 'cmp_delete_operations_document_' . $id );

	$result = cmp_delete_operations_document_record( $id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-operations-center-documents', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-operations-center-documents', __( 'Document deleted successfully.', 'class-manager-pro' ) );
}

/**
 * Handles document delete via AJAX.
 */
function cmp_ajax_delete_operations_document() {
	cmp_require_operations_center_access_ajax();
	check_ajax_referer( 'cmp_delete_operations_document', 'nonce' );

	$id = absint( cmp_field( $_POST, 'id', 0 ) );
	$result = cmp_delete_operations_document_record( $id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			),
			400
		);
	}

	wp_send_json_success(
		array(
			'message' => __( 'Document deleted successfully.', 'class-manager-pro' ),
			'deleted_row' => 'operations-document-' . $id,
			'refresh'  => array(
				'action' => 'cmp_filter_operations_section',
				'section'=> 'documents',
				'target' => '.cmp-operations-shell',
			),
		)
	);
}

/**
 * Handles goal save via admin-post.
 */
function cmp_handle_save_operations_goal() {
	cmp_require_operations_center_access();
	check_admin_referer( 'cmp_save_operations_goal' );

	$result = cmp_save_operations_goal_record( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-operations-center-goals', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-operations-center-goals', $result['message'] );
}

/**
 * Handles goal save via AJAX.
 */
function cmp_ajax_save_operations_goal() {
	cmp_require_operations_center_access_ajax();
	check_ajax_referer( 'cmp_save_operations_goal', 'nonce' );

	$result = cmp_save_operations_goal_record( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			),
			400
		);
	}

	wp_send_json_success(
		array(
			'message' => $result['message'],
			'id'      => $result['id'],
			'refresh'  => array(
				'action' => 'cmp_filter_operations_section',
				'section'=> sanitize_key( cmp_field( $_POST, 'operations_section', 'goals' ) ),
				'target' => '.cmp-operations-shell',
			),
		)
	);
}

/**
 * Handles goal delete via admin-post.
 */
function cmp_handle_delete_operations_goal() {
	cmp_require_operations_center_access();

	$id = absint( cmp_field( $_GET, 'id', 0 ) );
	check_admin_referer( 'cmp_delete_operations_goal_' . $id );

	$result = cmp_delete_operations_goal_record( $id );

	if ( is_wp_error( $result ) ) {
		cmp_redirect( 'cmp-operations-center-goals', $result->get_error_message(), 'error' );
	}

	cmp_redirect( 'cmp-operations-center-goals', __( 'Goal deleted successfully.', 'class-manager-pro' ) );
}

/**
 * Handles goal delete via AJAX.
 */
function cmp_ajax_delete_operations_goal() {
	cmp_require_operations_center_access_ajax();
	check_ajax_referer( 'cmp_delete_operations_goal', 'nonce' );

	$id = absint( cmp_field( $_POST, 'id', 0 ) );
	$result = cmp_delete_operations_goal_record( $id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			),
			400
		);
	}

	wp_send_json_success(
		array(
			'message' => __( 'Goal deleted successfully.', 'class-manager-pro' ),
			'deleted_row' => 'operations-goal-' . $id,
			'refresh'  => array(
				'action' => 'cmp_filter_operations_section',
				'section'=> sanitize_key( cmp_field( $_POST, 'operations_section', 'goals' ) ),
				'target' => '.cmp-operations-shell',
			),
		)
	);
}

/**
 * Handles ICS export.
 */
function cmp_handle_export_operations_ics() {
	cmp_require_operations_center_access();
	check_admin_referer( 'cmp_export_operations_ics' );

	$ics = cmp_generate_operations_ics( wp_unslash( $_GET ) );
	$filename = 'operations-center-' . gmdate( 'Y-m-d' ) . '.ics';

	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}

/**
 * Handles a search request for Operations Center.
 */
function cmp_ajax_search_operations_center() {
	cmp_require_operations_center_access_ajax();
	check_ajax_referer( 'cmp_admin_nonce', 'nonce' );

	$search = sanitize_text_field( cmp_field( $_POST, 'search', '' ) );
	$results = cmp_get_operations_search_results( $search );

	ob_start();
	?>
	<div class="cmp-grid cmp-grid-2">
		<?php foreach ( array(
			'events'    => __( 'Events', 'class-manager-pro' ),
			'batches'   => __( 'Batches', 'class-manager-pro' ),
			'documents' => __( 'Documents', 'class-manager-pro' ),
			'goals'     => __( 'Goals', 'class-manager-pro' ),
			'teachers'  => __( 'Teachers', 'class-manager-pro' ),
		) as $key => $label ) : ?>
			<section class="cmp-panel">
				<div class="cmp-panel-header">
					<div>
						<h2><?php echo esc_html( $label ); ?></h2>
						<p class="cmp-muted"><?php echo esc_html( sprintf( __( '%d results found', 'class-manager-pro' ), count( $results[ $key ] ) ) ); ?></p>
					</div>
				</div>
				<?php if ( empty( $results[ $key ] ) ) : ?>
					<div class="cmp-callout"><p><?php esc_html_e( 'No results found.', 'class-manager-pro' ); ?></p></div>
				<?php else : ?>
					<div class="cmp-stack">
						<?php foreach ( array_slice( $results[ $key ], 0, 5 ) as $row ) : ?>
							<div class="cmp-callout">
								<strong><?php echo esc_html( cmp_operations_search_item_label( $row, $key ) ); ?></strong>
								<?php if ( 'events' === $key && ! empty( $row->start_at ) ) : ?>
									<br><span class="cmp-muted"><?php echo esc_html( cmp_operations_format_datetime( $row->start_at ) ); ?></span>
								<?php elseif ( 'batches' === $key && ! empty( $row->class_name ) ) : ?>
									<br><span class="cmp-muted"><?php echo esc_html( $row->class_name ); ?></span>
								<?php elseif ( 'documents' === $key && ! empty( $row->batch_name ) ) : ?>
									<br><span class="cmp-muted"><?php echo esc_html( $row->batch_name ); ?></span>
								<?php elseif ( 'teachers' === $key && ! empty( $row->user_email ) ) : ?>
									<br><span class="cmp-muted"><?php echo esc_html( $row->user_email ); ?></span>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>
	<?php

	wp_send_json_success(
		array(
			'html' => ob_get_clean(),
		)
	);
}
