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
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cmp-form">
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
				<th scope="row"><label for="cmp-class-total-fee"><?php esc_html_e( 'Total Fee', 'class-manager-pro' ); ?></label></th>
				<td><input type="number" id="cmp-class-total-fee" name="total_fee" min="0" step="0.01" value="<?php echo esc_attr( $is_edit ? $class->total_fee : '' ); ?>"></td>
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
		<h1><?php esc_html_e( 'Classes', 'class-manager-pro' ); ?></h1>
		<?php cmp_render_notice(); ?>

		<section class="cmp-panel">
			<h2><?php echo esc_html( $class ? __( 'Edit Class', 'class-manager-pro' ) : __( 'Add Class', 'class-manager-pro' ) ); ?></h2>
			<?php cmp_render_class_form( $class, 'cmp-classes' ); ?>
		</section>

		<section class="cmp-panel">
			<h2><?php esc_html_e( 'All Classes', 'class-manager-pro' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Description', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Total Fee', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Created', 'class-manager-pro' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No classes found.', 'class-manager-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$edit_url   = cmp_admin_url( 'cmp-classes', array( 'action' => 'edit', 'id' => (int) $row->id ) );
							$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=cmp_delete_class&id=' . (int) $row->id ), 'cmp_delete_class_' . (int) $row->id );
							?>
							<tr>
								<td><?php echo esc_html( $row->name ); ?></td>
								<td><?php echo esc_html( wp_trim_words( $row->description, 16 ) ); ?></td>
								<td><?php echo esc_html( cmp_format_money( $row->total_fee ) ); ?></td>
								<td><?php echo esc_html( $row->created_at ); ?></td>
								<td class="cmp-actions">
									<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'class-manager-pro' ); ?></a>
									<a class="cmp-delete-link" href="<?php echo esc_url( $delete_url ); ?>"><?php esc_html_e( 'Delete', 'class-manager-pro' ); ?></a>
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
