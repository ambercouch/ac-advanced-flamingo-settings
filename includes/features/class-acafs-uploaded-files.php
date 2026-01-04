<?php
/**
 * AC Advanced Flamingo Settings – Uploaded files admin page.
 */

defined( 'ABSPATH' ) || exit;

class ACAFS_Uploaded_Files {

	const DELETE_ACTION     = 'acafs_delete_upload';
	const DELETE_ALL_ACTION = 'acafs_delete_all_uploads';

	public function __construct() {
		add_action( 'acafs_render_uploaded_files_page', array( $this, 'render_page' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Handle delete actions for uploaded files.
	 */
	public function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'acafs-uploaded-files' !== $page ) {
			return;
		}

		$paths = $this->get_upload_paths();
		if ( ! $paths ) {
			return;
		}

		if ( isset( $_POST['acafs_delete_all'] ) ) {
			check_admin_referer( self::DELETE_ALL_ACTION );
			$deleted = $this->delete_all_files( $paths['basedir'] );
			$status  = $deleted ? 'deleted_all' : 'delete_all_failed';
			wp_safe_redirect( add_query_arg( 'acafs_status', $status, admin_url( 'admin.php?page=acafs-uploaded-files' ) ) );
			exit;
		}

		if ( isset( $_GET['acafs_action'], $_GET['file'] ) && 'delete' === $_GET['acafs_action'] ) {
			check_admin_referer( self::DELETE_ACTION );
			$relative = sanitize_text_field( wp_unslash( $_GET['file'] ) );
			$deleted  = $this->delete_single_file( $paths['basedir'], $relative );
			$status   = $deleted ? 'deleted' : 'delete_failed';
			wp_safe_redirect( add_query_arg( 'acafs_status', $status, admin_url( 'admin.php?page=acafs-uploaded-files' ) ) );
			exit;
		}
	}

	/**
	 * Render the uploaded files page.
	 */
	public function render_page() {
		$paths = $this->get_upload_paths();
		if ( ! $paths ) {
			echo '<p>' . esc_html__( 'Upload paths are not available.', 'ac-advanced-flamingo-settings' ) . '</p>';
			return;
		}

		$this->render_notices();

		$files = $this->get_files( $paths['basedir'], $paths['baseurl'] );
		?>
		<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Delete all uploaded files?', 'ac-advanced-flamingo-settings' ) ); ?>');">
			<?php wp_nonce_field( self::DELETE_ALL_ACTION ); ?>
			<p>
				<button class="button button-secondary" type="submit" name="acafs_delete_all" value="1">
					<?php esc_html_e( 'Delete All Files', 'ac-advanced-flamingo-settings' ); ?>
				</button>
			</p>
		</form>

		<?php if ( empty( $files ) ) : ?>
			<p><?php esc_html_e( 'No uploaded files found.', 'ac-advanced-flamingo-settings' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'File Name', 'ac-advanced-flamingo-settings' ); ?></th>
						<th><?php esc_html_e( 'File Link', 'ac-advanced-flamingo-settings' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'ac-advanced-flamingo-settings' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $files as $file ) : ?>
						<tr>
							<td><?php echo esc_html( $file['name'] ); ?></td>
							<td>
								<a href="<?php echo esc_url( $file['url'] ); ?>" target="_blank" rel="noopener">
									<?php echo esc_html( $file['url'] ); ?>
								</a>
							</td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( $file['delete_url'] ); ?>">
									<?php esc_html_e( 'Delete', 'ac-advanced-flamingo-settings' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Build upload base paths.
	 *
	 * @return array|false
	 */
	private function get_upload_paths() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return false;
		}

		return array(
			'basedir' => trailingslashit( $uploads['basedir'] ) . 'acafs-cf7/',
			'baseurl' => trailingslashit( $uploads['baseurl'] ) . 'acafs-cf7/',
		);
	}

	/**
	 * Get list of uploaded files.
	 *
	 * @param string $base_dir Base directory.
	 * @param string $base_url Base URL.
	 * @return array
	 */
	private function get_files( $base_dir, $base_url ) {
		$files = array();

		if ( ! is_dir( $base_dir ) ) {
			return $files;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base_dir, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file_info ) {
			if ( ! $file_info->isFile() ) {
				continue;
			}

			$full_path = wp_normalize_path( $file_info->getPathname() );
			$relative  = ltrim( str_replace( wp_normalize_path( $base_dir ), '', $full_path ), '/' );
			$relative  = str_replace( '\\', '/', $relative );

			$delete_url = wp_nonce_url(
				add_query_arg(
					array(
						'page'         => 'acafs-uploaded-files',
						'acafs_action' => 'delete',
						'file'         => $relative,
					),
					admin_url( 'admin.php' )
				),
				self::DELETE_ACTION
			);

			$files[] = array(
				'name'       => $file_info->getFilename(),
				'url'        => trailingslashit( $base_url ) . $relative,
				'delete_url' => $delete_url,
			);
		}

		usort(
			$files,
			static function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return $files;
	}

	/**
	 * Delete a single file.
	 *
	 * @param string $base_dir Base directory.
	 * @param string $relative Relative path.
	 * @return bool
	 */
	private function delete_single_file( $base_dir, $relative ) {
		if ( '' === $relative ) {
			return false;
		}

		$base_dir = trailingslashit( $base_dir );
		$base     = realpath( $base_dir );
		if ( ! $base ) {
			return false;
		}

		$relative_path = ltrim( $relative, '/\\' );
		$full_path     = realpath( $base_dir . $relative_path );
		if ( ! $full_path ) {
			return false;
		}

		$base = trailingslashit( $base );
		if ( 0 !== strpos( $full_path, $base ) ) {
			return false;
		}

		if ( ! is_file( $full_path ) ) {
			return false;
		}

		return (bool) unlink( $full_path );
	}

	/**
	 * Delete all files in the upload directory.
	 *
	 * @param string $base_dir Base directory.
	 * @return bool
	 */
	private function delete_all_files( $base_dir ) {
		if ( ! is_dir( $base_dir ) ) {
			return false;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base_dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file_info ) {
			if ( $file_info->isDir() ) {
				@rmdir( $file_info->getPathname() );
				continue;
			}

			@unlink( $file_info->getPathname() );
		}

		return true;
	}

	/**
	 * Render status notices.
	 */
	private function render_notices() {
		if ( empty( $_GET['acafs_status'] ) ) {
			return;
		}

		$status  = sanitize_text_field( wp_unslash( $_GET['acafs_status'] ) );
		$message = '';
		$class   = 'notice notice-success';

		switch ( $status ) {
			case 'deleted':
				$message = __( 'File deleted.', 'ac-advanced-flamingo-settings' );
				break;
			case 'delete_failed':
				$message = __( 'File could not be deleted.', 'ac-advanced-flamingo-settings' );
				$class   = 'notice notice-error';
				break;
			case 'deleted_all':
				$message = __( 'All uploaded files deleted.', 'ac-advanced-flamingo-settings' );
				break;
			case 'delete_all_failed':
				$message = __( 'Uploaded files could not be deleted.', 'ac-advanced-flamingo-settings' );
				$class   = 'notice notice-error';
				break;
		}

		if ( '' === $message ) {
			return;
		}

		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}
}
