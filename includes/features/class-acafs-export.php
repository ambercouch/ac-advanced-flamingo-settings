<?php
defined( 'ABSPATH' ) || exit;

class ACAFS_Export {

	public function __construct() {
		add_action( 'admin_post_acafs_export_flamingo_messages', array( $this, 'acafs_export_flamingo_messages' ) );
		add_action( 'acafs_render_import_export_page', array( $this, 'acafs_render_export_section' ) );
		add_action( 'admin_notices', array( $this, 'acafs_show_export_notice' ) );

		add_action( 'admin_post_acafs_get_message_count', array( $this, 'acafs_get_message_count' ) );
		add_action( 'admin_post_nopriv_acafs_get_message_count', array( $this, 'acafs_get_message_count' ) );
	}

	/**
	 * Export Flamingo messages to a JSON file, optionally filtered by date range.
	 */
	public function acafs_export_flamingo_messages() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'ac-advanced-flamingo-settings' ) );
		}

		check_admin_referer( 'acafs_export_flamingo_messages' );

		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
		$export_all = isset( $_GET['export_all'] ) ? (int) $_GET['export_all'] : 0;

		$is_filtered = ( ! $export_all && ! empty( $start_date ) && ! empty( $end_date ) );

		// COUNT query (no SQL fragments).
		if ( $is_filtered ) {
			$total_query  = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_date BETWEEN %s AND %s";
			$total_params = array(
				'flamingo_inbound',
				'publish',
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
			);
		} else {
			$total_query  = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s";
			$total_params = array( 'flamingo_inbound', 'publish' );
		}

		$total_prepared = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $total_query ), $total_params ) );
		$total          = (int) $wpdb->get_var( $total_prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$batch    = 500;
		$offset   = 0;
		$messages = array();

		while ( $offset < $total ) {

			// SELECT query (no SQL fragments).
			if ( $is_filtered ) {
				$select_query  = "SELECT * FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_date BETWEEN %s AND %s LIMIT %d OFFSET %d";
				$select_params = array(
					'flamingo_inbound',
					'publish',
					$start_date . ' 00:00:00',
					$end_date . ' 23:59:59',
					$batch,
					$offset,
				);
			} else {
				$select_query  = "SELECT * FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s LIMIT %d OFFSET %d";
				$select_params = array(
					'flamingo_inbound',
					'publish',
					$batch,
					$offset,
				);
			}

			$select_prepared = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $select_query ), $select_params ) );

			$results = $wpdb->get_results( $select_prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			foreach ( $results as &$msg ) {
				$msg['meta']       = get_post_meta( (int) $msg['ID'] );
				$terms             = wp_get_post_terms( (int) $msg['ID'], 'flamingo_inbound_channel', array( 'fields' => 'ids' ) );
				$msg['channel_id'] = ! empty( $terms ) ? (int) $terms[0] : 0;
			}

			$messages = array_merge( $messages, $results );
			$offset  += $batch;
		}

		$filename = 'flamingo-messages';
		if ( $is_filtered ) {
			$filename .= "-{$start_date}_to_{$end_date}";
		}
		$filename .= '-' . time() . '.json';

		$upload_dir = wp_upload_dir();
		$file_path  = trailingslashit( $upload_dir['basedir'] ) . $filename;

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		$json = wp_json_encode( $messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		if ( ! $wp_filesystem || ! $wp_filesystem->put_contents( $file_path, $json, FS_CHMOD_FILE ) ) {
			wp_die( esc_html__( 'Export failed: could not write file.', 'ac-advanced-flamingo-settings' ) );
		}

		set_transient( 'acafs_export_file', trailingslashit( $upload_dir['baseurl'] ) . $filename, 30 );
		set_transient( 'acafs_export_success', $total, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'admin.php?page=acafs-message-sync&export_success=1' ) );
		exit;
	}




	/**
	 * Show a notice after export
	 */
	public function acafs_show_export_notice() {
		$count = get_transient( 'acafs_export_success' );
		if ( ! $count ) {
			return;
		}

		$file_url = get_transient( 'acafs_export_file' );
		?>
		<div class="notice notice-success is-dismissible">
			<h2 style="margin-bottom: 5px;"><?php esc_html_e( 'Export Complete', 'ac-advanced-flamingo-settings' ); ?></h2>
			<p>
				<?php
				/* translators: %d is the number of exported messages */
				printf( esc_html__( '%d messages exported successfully.', 'ac-advanced-flamingo-settings' ), esc_html( $count ) );
				?>
			</p>
			<?php if ( ! empty( $file_url ) ) : ?>
				<p><a href="<?php echo esc_url( $file_url ); ?>" class="button button-primary" download>
						<?php esc_html_e( 'Download Exported File', 'ac-advanced-flamingo-settings' ); ?>
					</a></p>
			<?php endif; ?>
		</div>
		<?php
		delete_transient( 'acafs_export_success' );
		delete_transient( 'acafs_export_file' );
	}

	/**
	 * Return the number of messages that match the selected export filters
	 */
	public function acafs_get_message_count() {
		global $wpdb;

		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
		$export_all = isset( $_GET['export_all'] ) ? (int) $_GET['export_all'] : 0;

		$is_filtered = ( ! $export_all && ! empty( $start_date ) && ! empty( $end_date ) );

		if ( $is_filtered ) {
			$query  = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_date BETWEEN %s AND %s";
			$params = array(
				'flamingo_inbound',
				'publish',
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
			);
		} else {
			$query  = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s";
			$params = array( 'flamingo_inbound', 'publish' );
		}

		$prepared = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $query ), $params ) );
		$count    = (int) $wpdb->get_var( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		echo (int) $count;
		exit;
	}



	/**
	 * Render the export section of the import/export page
	 */
	public function acafs_render_export_section() {
		?>
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Export Messages', 'ac-advanced-flamingo-settings' ); ?></h2>
			</div>
			<div class="inside">
				<p><?php esc_html_e( 'Download Flamingo messages as a JSON file. You can select a date range or download all messages.', 'ac-advanced-flamingo-settings' ); ?></p>

				<form id="acafs-export-form">

					<input type="hidden" name="action" value="acafs_export_flamingo_messages">
			<?php wp_nonce_field( 'acafs_export_flamingo_messages' ); ?>

					<label>
						<input type="checkbox" id="export_all" name="export_all" value="1">
						<?php esc_html_e( 'Export all messages', 'ac-advanced-flamingo-settings' ); ?>
					</label>

					<div id="date-filters">
					<label for="start_date"><?php esc_html_e( 'Start Date:', 'ac-advanced-flamingo-settings' ); ?></label>
					<input type="date" name="start_date" id="start_date" value="<?php echo esc_attr( wp_date( 'Y-m-01', current_time( 'timestamp' ) ) ); ?>">

					<label for="end_date"><?php esc_html_e( 'End Date:', 'ac-advanced-flamingo-settings' ); ?></label>
					<input type="date" name="end_date" id="end_date" value="<?php echo esc_attr( wp_date( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>">
					</div>

					<p id="message-count"><?php esc_html_e( 'Messages to be exported: 0', 'ac-advanced-flamingo-settings' ); ?></p>

					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Export Messages', 'ac-advanced-flamingo-settings' ); ?>
					</button>
				</form>

				<div id="acafs-export-feedback" style="margin-top: 10px;"></div>
			</div>
		</div>

		<script>
			document.addEventListener("DOMContentLoaded", function () {
				const startDate = document.getElementById("start_date");
				const endDate = document.getElementById("end_date");
				const exportAll = document.getElementById("export_all");
				const dateFilters = document.getElementById("date-filters");
				const countDisplay = document.getElementById("message-count");
				const form = document.getElementById("acafs-export-form");
				const feedback = document.getElementById("acafs-export-feedback");

				function updateCount() {
					const sd = startDate.value;
					const ed = endDate.value;
					const all = exportAll.checked ? 1 : 0;

					fetch(`<?php echo esc_url( admin_url( 'admin-post.php?action=acafs_get_message_count' ) ); ?>&start_date=${sd}&end_date=${ed}&export_all=${all}`)
						.then(res => res.text())
						.then(count => {
							countDisplay.textContent = "<?php esc_html_e( 'Messages to be exported:', 'ac-advanced-flamingo-settings' ); ?> " + count;
						});
				}

				form.addEventListener("submit", function (e) {
					e.preventDefault();

					const nonce = (form.querySelector('input[name="_wpnonce"]') || {}).value || '';

					feedback.innerHTML = "<p><strong><?php esc_html_e( 'Exporting messages... Please wait.', 'ac-advanced-flamingo-settings' ); ?></strong></p>";
					form.action = "<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" +
						"?action=acafs_export_flamingo_messages&start_date=" + encodeURIComponent(startDate.value) +
						"&end_date=" + encodeURIComponent(endDate.value) +
						"&export_all=" + (exportAll.checked ? 1 : 0);
					form.submit();
				});

				exportAll.addEventListener("change", function () {
					dateFilters.style.display = this.checked ? "none" : "block";
					updateCount();
				});

				startDate.addEventListener("change", updateCount);
				endDate.addEventListener("change", updateCount);

				updateCount();
			});
		</script>
		<?php
	}
}
