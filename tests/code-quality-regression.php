<?php
/**
 * Code-quality regressions using real WordPress APIs and transactional fixtures.
 *
 * @package AC_Advanced_Flamingo_Settings
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}
require_once dirname( __DIR__ ) . '/includes/core/class-acafs-settings.php';
require_once dirname( __DIR__ ) . '/includes/core/class-acafs-divi-migration-safety.php';
require_once dirname( __DIR__ ) . '/includes/features/class-acafs-columns.php';
require_once dirname( __DIR__ ) . '/includes/features/class-acafs-uploaded-files.php';
require_once dirname( __DIR__ ) . '/includes/services/class-acafs-channel-hierarchy.php';
require_once WP_PLUGIN_DIR . '/flamingo/flamingo.php';
Flamingo_Inbound_Message::register_post_type();

/**
 * Assert a quality regression expectation.
 *
 * @param bool   $condition Expected condition.
 * @param string $message Description.
 */
function acafs_quality_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	WP_CLI::log( 'PASS: ' . $message );
}

function acafs_quality_run_regression() {
	global $wpdb;
	$wpdb->query( 'START TRANSACTION' );
	try {
		$prefix  = 'acafs-quality-' . wp_generate_uuid4();
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'flamingo_inbound',
				'post_title'  => $prefix,
				'post_status' => 'publish',
			)
		);
		add_post_meta( $post_id, '_field_' . $prefix, 'included' );
		add_post_meta( $post_id, 'XfieldY' . $prefix, 'excluded' );
		wp_cache_delete( 'acafs_flamingo_meta_keys', 'acafs_cache' );
		$settings = new ACAFS_Settings();
		$discover = new ReflectionMethod( 'ACAFS_Settings', 'acafs_get_all_flamingo_fields' );
		$discover->setAccessible( true );
		$keys = $discover->invoke( $settings );
		acafs_quality_assert( in_array( $prefix, $keys, true ), 'Literal _field_ prefix is discovered.' );
		acafs_quality_assert( ! in_array( 'XfieldY' . $prefix, $keys, true ), 'LIKE underscores do not expose unrelated metadata keys.' );
		acafs_quality_assert( $keys === wp_cache_get( 'acafs_flamingo_meta_keys', 'acafs_cache' ), 'Distinct metadata discovery remains object-cached.' );

		$email = $prefix . '@example.test';
		foreach ( array(
			'2001-02-03 12:00:00' => 'Older preview',
			'2001-02-04 12:00:00' => 'Newest preview',
		) as $date => $preview ) {
			$message_id = wp_insert_post(
				array(
					'post_type'   => 'flamingo_inbound',
					'post_status' => 'publish',
					'post_title'  => $prefix,
					'post_date'   => $date,
				)
			);
			add_post_meta( $message_id, '_from_email', $email );
			add_post_meta( $message_id, '_field_your-message', $preview );
		}
		add_post_meta( $post_id, '_email', $email );
		$columns = new ACAFS_Columns();
		ob_start();
		$columns->acafs_render_address_book_column( 'last_message', $post_id );
		$html = ob_get_clean();
		acafs_quality_assert( false !== strpos( $html, 'Newest preview' ) && false === strpos( $html, 'Older preview' ), 'Bounded ID-only lookup retains the newest message preview.' );
		acafs_quality_assert( false !== strpos( $html, 'post=' . $message_id ), 'Address-book link retains the correct message ID.' );

		$taxonomy = ACAFS_Channel_Hierarchy::TAXONOMY;
		$term     = wp_insert_term( $prefix, $taxonomy );
		acafs_quality_assert( ! is_wp_error( $term ), 'Taxonomy failure fixture created.' );
		wp_set_object_terms( $post_id, array( (int) $term['term_id'] ), $taxonomy );
		$uncached   = static function ( $args ) {
			$args['cache_results'] = false;
			return $args;
		};
		$fail_query = static function ( $sql ) use ( $wpdb ) {
			return 0 === stripos( ltrim( $sql ), 'SELECT' ) && false !== strpos( $sql, $wpdb->terms ) ? 'SELECT * FROM acafs_quality_nonexistent_terms' : $sql;
		};
		$old_errors = $wpdb->suppress_errors( true );
		add_filter( 'get_terms_args', $uncached );
		add_filter( 'query', $fail_query );
		try {
			$hierarchy = new ACAFS_Channel_Hierarchy();
			$error     = $hierarchy->export_channels( $post_id );
			acafs_quality_assert( is_wp_error( $error ) && 'acafs_export_channel_failed' === $error->get_error_code() && '' !== $wpdb->last_error, 'Real taxonomy SQL failure mutates last_error and fails export.' );
			$error = $hierarchy->resolve_channels(
				array(
					'channel_paths' => array(
						array(
							array(
								'name' => $prefix,
								'slug' => $prefix,
							),
						),
					),
				)
			);
			acafs_quality_assert( is_wp_error( $error ) && 'acafs_import_channel_lookup_failed' === $error->get_error_code() && '' !== $wpdb->last_error, 'Real parent-path SQL failure is reported rather than creating a channel.' );
			$error = $hierarchy->resolve_channels(
				array(
					'channel_slug' => $prefix,
					'channel_name' => $prefix,
				)
			);
			acafs_quality_assert( is_wp_error( $error ) && 'acafs_import_channel_lookup_failed' === $error->get_error_code() && '' !== $wpdb->last_error, 'Real legacy-channel SQL failure is reported rather than assigned incorrectly.' );
		} finally {
			remove_filter( 'get_terms_args', $uncached );
			remove_filter( 'query', $fail_query );
			$wpdb->suppress_errors( $old_errors );
		}
		update_option( ACAFS_Divi_Migration_Safety::OPTION, '1' );
		delete_option( ACAFS_Divi_Migration_Safety::FAILURE_OPTION );
		$monitor = new ACAFS_Divi_Migration_Safety();
		$monitor->record_failure( array( 'unexpected' => 'integration value' ) );
		acafs_quality_assert( false === get_option( ACAFS_Divi_Migration_Safety::FAILURE_OPTION ), 'Mixed integration error input remains safely rejected.' );
		$original_user    = get_current_user_id();
		$original_request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve request state around a nonce-verified fixture.
		$original_get     = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve request state for a nonce-verified security fixture.
		$admin_ids        = get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'ID',
				'number' => 1,
			)
		);
		wp_set_current_user( (int) $admin_ids[0] );
		$die_handler = static function () {
			return static function ( $message ) {
				throw new RuntimeException( esc_html( $message ) );
			};
		};
		add_filter( 'wp_die_handler', $die_handler, 999 );
		try {
			$_GET     = array(
				'_wpnonce' => wp_create_nonce( ACAFS_Uploaded_Files::DOWNLOAD_NONCE ),
				'file'     => array( 'unexpected' ),
			);
			$_REQUEST = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Synchronize the synthetic GET request for check_admin_referer.
			$denial   = '';
			try {
				( new ACAFS_Uploaded_Files() )->acafs_download_uploaded_file();
			} catch ( RuntimeException $exception ) {
				$denial = $exception->getMessage();
			}
			acafs_quality_assert( 'Invalid download request.' === $denial, 'Array-shaped download input is denied without a PHP type error.' );
		} finally {
			remove_filter( 'wp_die_handler', $die_handler, 999 );
			$_GET     = $original_get;
			$_REQUEST = $original_request;
			wp_set_current_user( $original_user );
		}
	} finally {
		$wpdb->query( 'ROLLBACK' );
		wp_cache_delete( 'acafs_flamingo_meta_keys', 'acafs_cache' );
	}
	WP_CLI::success( 'Code-quality regressions passed; fixture data rolled back.' );
}
acafs_quality_run_regression();
