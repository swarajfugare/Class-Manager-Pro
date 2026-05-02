<?php
/**
 * Notifications Page
 *
 * @package ClassManagerPro
 */

function cmp_render_notifications_page() {
	if ( ! current_user_can( 'cmp_manage' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'class-manager-pro' ) );
	}

	$notifications = CMP_Admin_Notifications::get_for_user();
	CMP_Admin_Notifications::mark_all_read();
	?>
	<div class="wrap cmp-admin cmp-notifications-page">
		<h1><?php esc_html_e( 'Notifications', 'class-manager-pro' ); ?></h1>

		<?php if ( empty( $notifications ) ) : ?>
			<div class="cmp-notice cmp-notice-info">
				<p><?php esc_html_e( 'No notifications.', 'class-manager-pro' ); ?></p>
			</div>
		<?php else : ?>
			<div class="cmp-notification-list-full">
				<?php foreach ( $notifications as $note ) : ?>
					<div class="cmp-notification-item cmp-notification-<?php echo esc_attr( $note['type'] ); ?> <?php echo $note['read'] ? 'cmp-read' : 'cmp-unread'; ?>">
						<div class="cmp-notification-icon">
							<span class="dashicons dashicons-<?php
								echo esc_attr( 'success' === $note['type'] ? 'yes' : ( 'error' === $note['type'] ? 'no' : ( 'warning' === $note['type'] ? 'flag' : 'info' ) ) );
							?>"></span>
						</div>
						<div class="cmp-notification-content">
							<p><?php echo esc_html( $note['message'] ); ?></p>
							<span class="cmp-notification-meta">
								<?php echo esc_html( human_time_diff( strtotime( $note['time'] ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'ago', 'class-manager-pro' ); ?>
								<?php if ( $note['context'] ) : ?>
									| <?php echo esc_html( ucfirst( $note['context'] ) ); ?>
								<?php endif; ?>
							</span>
						</div>
						<div class="cmp-notification-actions">
							<button type="button" class="button button-small cmp-dismiss-note" data-id="<?php echo esc_attr( $note['id'] ); ?>">
								<?php esc_html_e( 'Dismiss', 'class-manager-pro' ); ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
