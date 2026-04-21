<?php
/**
 * Settings admin page.
 *
 * @package ClassManagerPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the settings page.
 */
function cmp_render_settings_page() {
	cmp_require_manage_options();

	$key_id         = (string) get_option( 'cmp_razorpay_key_id', '' );
	$secret         = (string) get_option( 'cmp_razorpay_secret', '' );
	$webhook_secret = (string) get_option( 'cmp_razorpay_webhook_secret', '' );
	$webhook_url    = rest_url( 'cmp/v1/razorpay-webhook' );
	?>
	<div class="wrap cmp-wrap">
		<h1><?php esc_html_e( 'Settings', 'class-manager-pro' ); ?></h1>
		<?php cmp_render_notice(); ?>

		<section class="cmp-panel">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
				<input type="hidden" name="action" value="cmp_save_settings">
				<?php wp_nonce_field( 'cmp_save_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cmp-razorpay-key-id"><?php esc_html_e( 'Razorpay Key ID', 'class-manager-pro' ); ?></label></th>
						<td><input type="text" id="cmp-razorpay-key-id" name="razorpay_key_id" class="regular-text" value="<?php echo esc_attr( $key_id ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-razorpay-secret"><?php esc_html_e( 'Razorpay Secret', 'class-manager-pro' ); ?></label></th>
						<td><input type="password" id="cmp-razorpay-secret" name="razorpay_secret" class="regular-text" value="<?php echo esc_attr( $secret ); ?>" autocomplete="new-password"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-webhook-secret"><?php esc_html_e( 'Webhook Secret', 'class-manager-pro' ); ?></label></th>
						<td><input type="password" id="cmp-webhook-secret" name="razorpay_webhook_secret" class="regular-text" value="<?php echo esc_attr( $webhook_secret ); ?>" autocomplete="new-password"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmp-webhook-url"><?php esc_html_e( 'Webhook URL', 'class-manager-pro' ); ?></label></th>
						<td><input type="url" id="cmp-webhook-url" class="large-text code" value="<?php echo esc_url( $webhook_url ); ?>" readonly></td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'class-manager-pro' ) ); ?>
			</form>
		</section>
	</div>
	<?php
}
