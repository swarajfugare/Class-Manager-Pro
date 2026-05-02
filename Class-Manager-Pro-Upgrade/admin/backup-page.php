<?php
/**
 * Backup & Export Page
 *
 * @package ClassManagerPro
 */

function cmp_render_backup_page() {
	if ( ! current_user_can( 'cmp_manage_backups' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'class-manager-pro' ) );
	}

	$backups = CMP_Backup_Manager::list_backups();
	?>
	<div class="wrap cmp-admin cmp-backup-page">
		<h1><?php esc_html_e( 'Backup & Export', 'class-manager-pro' ); ?></h1>

		<div class="cmp-backup-actions">
			<button type="button" id="cmp-create-backup" class="button button-primary">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Create New Backup', 'class-manager-pro' ); ?>
			</button>
			<button type="button" id="cmp-export-csv" class="button" onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=cmp-students&cmp_export=csv' ) ); ?>'">
				<span class="dashicons dashicons-media-spreadsheet"></span>
				<?php esc_html_e( 'Export Students CSV', 'class-manager-pro' ); ?>
			</button>
		</div>

		<h2><?php esc_html_e( 'Existing Backups', 'class-manager-pro' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Filename', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Date', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Size', 'class-manager-pro' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'class-manager-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $backups ) ) : ?>
					<tr>
						<td colspan="4"><?php esc_html_e( 'No backups found.', 'class-manager-pro' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $backups as $backup ) : ?>
						<tr>
							<td><?php echo esc_html( $backup['name'] ); ?></td>
							<td><?php echo esc_html( $backup['date'] ); ?></td>
							<td><?php echo esc_html( $backup['size'] ); ?></td>
							<td>
								<button type="button" class="button button-small cmp-download-backup" data-filename="<?php echo esc_attr( $backup['name'] ); ?>">
									<?php esc_html_e( 'Download', 'class-manager-pro' ); ?>
								</button>
								<button type="button" class="button button-small cmp-delete-backup" data-filename="<?php echo esc_attr( $backup['name'] ); ?>">
									<?php esc_html_e( 'Delete', 'class-manager-pro' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
