<?php
/**
 * Channel transport regression against real WordPress/Flamingo taxonomy APIs.
 *
 * Run with plugins/themes skipped on a local transactional database.
 *
 * @package AC_Advanced_Flamingo_Settings
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}
require_once dirname( __DIR__ ) . '/includes/services/class-acafs-import-service.php';
require_once dirname( __DIR__ ) . '/includes/services/class-acafs-export-service.php';
require_once WP_PLUGIN_DIR . '/flamingo/flamingo.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/includes/background/class-acafs-background-import.php';
require_once dirname( __DIR__ ) . '/includes/features/class-acafs-import.php';
require_once __DIR__ . '/fixtures/class-acafs-channel-import-test-worker.php';
Flamingo_Inbound_Message::register_post_type();

/**
 * Assert a transport expectation.
 *
 * @param bool   $condition Expected condition.
 * @param string $message   Description.
 */
function acafs_channel_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	WP_CLI::log( 'PASS: ' . $message );
}

/**
 * Get the post created for a unique fixture title.
 *
 * @param string $title Fixture title.
 * @return int Post ID, or zero when absent.
 */
function acafs_channel_message_id( $title ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s", 'flamingo_inbound', $title ) );
}

function acafs_channel_run_regression() {
	global $wpdb;
	$wpdb->query( 'START TRANSACTION' );
	$file          = wp_tempnam( 'acafs-channel-roundtrip' );
	$original_user = get_current_user_id();
	try {
		$taxonomy   = ACAFS_Channel_Hierarchy::TAXONOMY;
		$prefix     = 'acafs-channel-' . wp_generate_uuid4();
		$service    = new ACAFS_Import_Service();
		$root       = wp_insert_term( $prefix . ' Root', $taxonomy );
		$middle     = wp_insert_term( $prefix . ' Middle', $taxonomy, array( 'parent' => $root['term_id'] ) );
		$leaf       = wp_insert_term( $prefix . ' Leaf', $taxonomy, array( 'parent' => $middle['term_id'] ) );
		$other      = wp_insert_term( $prefix . ' Other', $taxonomy );
		$other_leaf = wp_insert_term( $prefix . ' Leaf', $taxonomy, array( 'parent' => $other['term_id'] ) );
		$source     = wp_insert_post(
			array(
				'post_type'    => 'flamingo_inbound',
				'post_status'  => 'publish',
				'post_title'   => $prefix . ' roundtrip',
				'post_content' => 'Hierarchy fixture',
				'post_date'    => '2001-02-03 12:00:00',
			),
			true
		);
		wp_set_object_terms( $source, array( (int) $leaf['term_id'], (int) $other_leaf['term_id'] ), $taxonomy );
		$export = ( new ACAFS_Export_Service() )->export_to_file(
			$file,
			array(
				'from' => '2001-02-03',
				'to'   => '2001-02-03',
			)
		);
		acafs_channel_assert( ! is_wp_error( $export ), 'Real JSON export succeeds.' );
		$records = $service->read_file( $file );
		acafs_channel_assert( ! is_wp_error( $records ), 'CLI/service JSON reader accepts the additive path format.' );
		$record = null;
		foreach ( $records as $candidate ) {
			if ( (int) $source === (int) $candidate['ID'] ) {
				$record = $candidate;
			}
		}
		acafs_channel_assert( is_array( $record ) && 2 === count( $record['channel_paths'] ), 'Export retains every explicitly assigned channel.' );
		$lengths = array_map( 'count', $record['channel_paths'] );
		sort( $lengths );
		acafs_channel_assert( array( 2, 3 ) === $lengths, 'Unassigned ancestors are exported as complete root-to-leaf paths.' );
		acafs_channel_assert( isset( $record['channel_id'], $record['channel_slug'], $record['channel_name'] ), 'Legacy flat export fields are retained for older readers.' );
		foreach ( $record['channel_paths'] as $path ) {
			acafs_channel_assert( array( 'name', 'slug' ) === array_keys( $path[0] ), 'Portable paths contain names/slugs, never source IDs.' );
		}
		wp_delete_post( $source, true );
		foreach ( array( $leaf, $other_leaf, $middle, $root, $other ) as $term ) {
			wp_delete_term( $term['term_id'], $taxonomy );
		}
		$unrelated            = wp_insert_term( $prefix . ' Unrelated ID', $taxonomy );
		$record['channel_id'] = $unrelated['term_id'];
		$result               = $service->import_batch( array( $record ) );
		acafs_channel_assert( 1 === $result['inserted'] && 0 === $result['failed'], 'Fresh destination recreates both trees parent-first.' );
		$imported = acafs_channel_message_id( $record['post_title'] );
		$assigned = wp_get_object_terms( $imported, $taxonomy );
		acafs_channel_assert( 2 === count( $assigned ), 'Only the two exported leaves are assigned, not their ancestors.' );
		$parents = array();
		foreach ( $assigned as $term ) {
			$parents[] = $term->parent;
			acafs_channel_assert( 0 !== (int) $term->parent && (int) $unrelated['term_id'] !== $term->term_id, 'Conflicting source term ID cannot redirect a channel.' );
		}
		acafs_channel_assert( 2 === count( array_unique( $parents ) ), 'Identical leaf names under different parents remain separate.' );
		$terms_before = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		$repeat       = $service->import_batch( array( $record ) );
		acafs_channel_assert(
			1 === $repeat['skipped'] && 0 === $repeat['failed'] && $terms_before === get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			),
			'Repeated imports create neither duplicate messages nor channel trees.'
		);
		$record['post_title'] .= ' existing-tree';
		$result                = $service->import_batch( array( $record ) );
		acafs_channel_assert(
			1 === $result['inserted'] && $terms_before === get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			),
			'New messages reuse existing complete destination hierarchies.'
		);

		$destination_root         = wp_insert_term( $prefix . ' Existing root', $taxonomy, array( 'slug' => $prefix . '-destination-only' ) );
		$wrong_parent             = wp_insert_term( $prefix . ' Wrong parent', $taxonomy );
		$wrong_leaf               = wp_insert_term(
			$prefix . ' Same child',
			$taxonomy,
			array(
				'slug'   => $prefix . '-source-child',
				'parent' => $wrong_parent['term_id'],
			)
		);
		$message                  = $record;
		$message['post_title']    = $prefix . ' collision';
		$message['channel_paths'] = array(
			array(
				array(
					'name' => $prefix . ' Existing root',
					'slug' => $prefix . '-source-root',
				),
				array(
					'name' => $prefix . ' Same child',
					'slug' => $prefix . '-source-child',
				),
			),
		);
		$result                   = $service->import_batch( array( $message ) );
		$correct                  = wp_get_object_terms( acafs_channel_message_id( $message['post_title'] ), $taxonomy );
		acafs_channel_assert( 1 === $result['inserted'] && 1 === count( $correct ) && (int) $destination_root['term_id'] === (int) $correct[0]->parent && (int) $wrong_leaf['term_id'] !== (int) $correct[0]->term_id, 'Slug/name collisions under another parent cannot hijack mapping.' );
		$message['post_title'] .= ' reuse';
		$result                 = $service->import_batch( array( $message ) );
		$reused                 = wp_get_object_terms( acafs_channel_message_id( $message['post_title'] ), $taxonomy, array( 'fields' => 'ids' ) );
		acafs_channel_assert( 1 === $result['inserted'] && array( (int) $correct[0]->term_id ) === $reused, 'Destination-generated slug suffixes still reuse the correct named child.' );

		$legacy = $record;
		unset( $legacy['channel_paths'] );
		$legacy['channel_slug'] = $correct[0]->slug;
		$legacy['post_title']   = $prefix . ' legacy slug';
		$result                 = $service->import_batch( array( $legacy ) );
		acafs_channel_assert( 1 === $result['inserted'] && array( (int) $correct[0]->term_id ) === wp_get_object_terms( acafs_channel_message_id( $legacy['post_title'] ), $taxonomy, array( 'fields' => 'ids' ) ), 'Legacy flat-slug JSON reuses an existing channel including its destination ancestry.' );
		$legacy['post_title'] = $prefix . ' legacy numeric';
		unset( $legacy['channel_slug'], $legacy['channel_name'] );
		$result = $service->import_batch( array( $legacy ) );
		acafs_channel_assert( 1 === $result['inserted'] && array() === wp_get_object_terms( acafs_channel_message_id( $legacy['post_title'] ), $taxonomy ), 'Legacy numeric-only channel IDs remain safely unassigned.' );
		$legacy['post_title'] = $prefix . ' no channel';
		unset( $legacy['channel_id'] );
		acafs_channel_assert( 1 === $service->import_batch( array( $legacy ) )['inserted'], 'Old exports without any channel fields still import.' );
		$legacy['post_title']   = $prefix . ' legacy new flat';
		$legacy['channel_slug'] = $prefix . '-legacy-new';
		$legacy['channel_name'] = $prefix . ' Legacy new';
		$result                 = $service->import_batch( array( $legacy ) );
		$flat                   = wp_get_object_terms( acafs_channel_message_id( $legacy['post_title'] ), $taxonomy );
		acafs_channel_assert( 1 === $result['inserted'] && 0 === (int) $flat[0]->parent, 'Legacy flat channels without ancestry still create a root channel.' );
		foreach ( array(
			null,
			'bad',
			array( array() ),
			array(
				array(
					array(
						'name' => '<b></b>',
						'slug' => 'x',
					),
				),
			),
		) as $bad ) {
			$invalid                  = $message;
			$invalid['post_title']    = $prefix . ' invalid';
			$invalid['channel_paths'] = $bad;
			$result                   = $service->import_batch( array( $invalid ) );
			acafs_channel_assert( 1 === $result['failed'] && 0 === $result['inserted'], 'Malformed paths fail instead of silently falling back to flat channels.' );
		}

		$failed_message                  = $message;
		$failed_message['post_title']    = $prefix . ' parent failure';
		$failed_message['channel_paths'] = array(
			array(
				array(
					'name' => $prefix . ' Failing root',
					'slug' => $prefix . '-failing-root',
				),
				array(
					'name' => 'Child',
					'slug' => $prefix . '-failing-child',
				),
			),
		);
		$fail_creation                   = static function ( $name ) use ( $prefix ) {
			return $prefix . ' Failing root' === wp_unslash( $name ) ? new WP_Error( 'fixture', 'Private error' ) : $name;
		};
		add_filter( 'pre_insert_term', $fail_creation );
		try {
			$result = $service->import_batch( array( $failed_message ) );
			acafs_channel_assert( 1 === $result['failed'] && 0 === $result['inserted'] && 'acafs_import_channel_creation_failed' === $result['errors'][0]->get_error_code() && 0 === acafs_channel_message_id( $failed_message['post_title'] ), 'Parent creation failures are explicit and do not insert unchannelled messages.' );
		} finally {
			remove_filter( 'pre_insert_term', $fail_creation );
		}
		acafs_channel_assert( 1 === $service->import_batch( array( $failed_message ) )['inserted'], 'A failed channel creation remains safely retryable.' );

		$assignment               = $message;
		$assignment['post_title'] = $prefix . ' assignment failure';
		$fail_relationship        = static function ( $query ) use ( $wpdb ) {
			return false !== strpos( $query, 'INSERT INTO `' . $wpdb->term_relationships . '`' ) ? 'INSERT INTO acafs_nonexistent_channel_table VALUES (1)' : $query;
		};
		$previous_suppression     = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail_relationship );
		try {
			$result = $service->import_batch( array( $assignment ) );
			acafs_channel_assert( 1 === $result['failed'] && 0 === $result['inserted'] && 'acafs_import_channel_assignment_failed' === $result['errors'][0]->get_error_code() && 0 === acafs_channel_message_id( $assignment['post_title'] ), 'Actual relationship INSERT failure is detected despite WordPress returning term IDs; incomplete message is removed.' );
		} finally {
			remove_filter( 'query', $fail_relationship );
			$wpdb->suppress_errors( $previous_suppression );
		}
		acafs_channel_assert( 1 === $service->import_batch( array( $assignment ) )['inserted'], 'Assignment failure retry is not skipped as a duplicate.' );

		$child_failure                  = $message;
		$child_failure['post_title']    = $prefix . ' child failure';
		$child_failure['channel_paths'] = array(
			array(
				array(
					'name' => $prefix . ' Partial root',
					'slug' => $prefix . '-partial-root',
				),
				array(
					'name' => $prefix . ' Blocked child',
					'slug' => $prefix . '-blocked-child',
				),
			),
		);
		$fail_child                     = static function ( $name ) use ( $prefix ) {
			return $prefix . ' Blocked child' === wp_unslash( $name ) ? new WP_Error( 'fixture', 'Private child error' ) : $name;
		};
		add_filter( 'pre_insert_term', $fail_child );
		try {
			$result = $service->import_batch( array( $child_failure ) );
			acafs_channel_assert( 1 === $result['failed'] && 0 === $result['inserted'] && get_term_by( 'slug', $prefix . '-partial-root', $taxonomy ) instanceof WP_Term, 'Child-creation failure leaves only a reusable parent, not a successful message.' );
		} finally {
			remove_filter( 'pre_insert_term', $fail_child );
		}
		acafs_channel_assert( 1 === $service->import_batch( array( $child_failure ) )['inserted'], 'Retry after child failure reuses the missing-child hierarchy safely.' );

		$escaped_name             = $prefix . ' O\'Neil "café" \\Bridal 日本語';
		$escaped                  = $message;
		$escaped['post_title']    = $prefix . ' escaped channel';
		$escaped['channel_paths'] = array(
			array(
				array(
					'name' => $escaped_name,
					'slug' => $prefix . '-escaped',
				),
			),
		);
		$result                   = $service->import_batch( array( $escaped ) );
		$escaped_terms            = wp_get_object_terms( acafs_channel_message_id( $escaped['post_title'] ), $taxonomy );
		acafs_channel_assert( 1 === $result['inserted'] && $escaped_name === $escaped_terms[0]->name, 'Apostrophes, quotes, literal backslashes and Unicode survive channel creation.' );

		$race_parent                   = wp_insert_term( $prefix . ' Race root', $taxonomy, array( 'slug' => $prefix . '-race-root' ) );
		$race_message                  = $message;
		$race_message['post_title']    = $prefix . ' race';
		$race_message['channel_paths'] = array(
			array(
				array(
					'name' => $prefix . ' Race root',
					'slug' => $prefix . '-race-root',
				),
				array(
					'name' => $prefix . ' Race child',
					'slug' => $prefix . '-race-child',
				),
			),
		);
		$race_filter                   = null;
		$race_filter                   = static function ( $name ) use ( &$race_filter, $race_parent, $prefix, $taxonomy, $unrelated ) {
			if ( $prefix . ' Race child' !== wp_unslash( $name ) ) {
				return $name; }
			remove_filter( 'pre_insert_term', $race_filter );
			wp_insert_term(
				$prefix . ' Race child',
				$taxonomy,
				array(
					'parent' => $race_parent['term_id'],
					'slug'   => $prefix . '-race-child',
				)
			);
			return new WP_Error( 'term_exists', 'Fixture race', $unrelated['term_id'] );
		};
		add_filter( 'pre_insert_term', $race_filter );
		try {
			$result     = $service->import_batch( array( $race_message ) );
			$race_terms = wp_get_object_terms( acafs_channel_message_id( $race_message['post_title'] ), $taxonomy );
			acafs_channel_assert( 1 === $result['inserted'] && (int) $race_parent['term_id'] === (int) $race_terms[0]->parent, 'Concurrent creation is re-resolved under the correct parent, ignoring error-supplied IDs.' );
		} finally {
			remove_filter( 'pre_insert_term', $race_filter );
		}

		global $wp_filesystem;
		$legacy['post_title'] = $prefix . ' legacy JSON';
		$wp_filesystem->put_contents( $file, wp_json_encode( array( $legacy ) ), FS_CHMOD_FILE );
		$legacy_records = $service->read_file( $file );
		acafs_channel_assert( ! is_wp_error( $legacy_records ) && 1 === $service->import_batch( $legacy_records )['inserted'], 'An actual legacy JSON file is accepted by the shared CLI/admin reader.' );
		$empty                  = $message;
		$empty['post_title']    = $prefix . ' explicit no channel';
		$empty['channel_paths'] = array();
		$result                 = $service->import_batch( array( $empty ) );
		acafs_channel_assert( 1 === $result['inserted'] && array() === wp_get_object_terms( acafs_channel_message_id( $empty['post_title'] ), $taxonomy ), 'Explicit empty paths never fall back to conflicting legacy channel fields.' );
		// The admin background worker consumes exactly the same portable records as CLI.
		delete_transient( 'acafs_import_result' );
		delete_transient( 'acafs_import_success' );
		$worker                      = new ACAFS_Channel_Import_Test_Worker( $service );
		$admin_message               = $message;
		$admin_message['post_title'] = $prefix . ' admin batch';
		$worker->run_batch( array( $admin_message ) );
		add_filter( 'pre_insert_term', $fail_creation );
		try {
			$failed_message['post_title'] .= ' admin';
			// Force a fresh parent so the injected creation failure is exercised.
			$failed_message['channel_paths'][0][0]['name'] = $prefix . ' Failing root';
			$failed_parent                                 = get_term_by( 'slug', $prefix . '-failing-root', $taxonomy );
			$children                                      = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'parent'     => $failed_parent->term_id,
					'hide_empty' => false,
				)
			);
			foreach ( $children as $child ) {
				wp_delete_term( $child->term_id, $taxonomy ); }
			wp_delete_term( $failed_parent->term_id, $taxonomy );
			$worker->run_batch( array( $failed_message ) );
		} finally {
			remove_filter( 'pre_insert_term', $fail_creation );
		}
		$worker->finish();
		$totals = get_transient( 'acafs_import_result' );
		acafs_channel_assert( 2 === $totals['processed'] && 1 === $totals['inserted'] && 1 === $totals['failed'] && false === get_transient( 'acafs_import_success' ), 'Admin batches retain failure totals and never report unconditional success.' );
		$admin_ids = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		wp_set_current_user( $admin_ids[0] );
		wp_set_current_user( 0 );
		ob_start();
		( new ACAFS_Import( $worker, $service ) )->acafs_show_import_notice();
		$restricted_notice = ob_get_clean();
		acafs_channel_assert( '' === $restricted_notice && is_array( get_transient( 'acafs_import_result' ) ), 'Unauthorized users cannot view or consume import results.' );
		wp_set_current_user( $admin_ids[0] );
		ob_start();
		( new ACAFS_Import( $worker, $service ) )->acafs_show_import_notice();
		$notice = ob_get_clean();
		acafs_channel_assert( false !== strpos( $notice, 'notice-error' ) && false !== strpos( $notice, 'Failed: 1' ) && false === strpos( $notice, 'All messages have been imported successfully' ), 'Admin notice accurately reports channel failure counts.' );
		acafs_channel_assert( 1 === count( wp_get_object_terms( acafs_channel_message_id( $admin_message['post_title'] ), $taxonomy ) ), 'Admin worker preserves the same mapped hierarchy as CLI/service imports.' );

		$admin_message['post_title'] .= ' success';
		$worker->run_batch( array( $admin_message ) );
		$worker->finish();
		ob_start();
		( new ACAFS_Import( $worker, $service ) )->acafs_show_import_notice();
		$success_notice = ob_get_clean();
		acafs_channel_assert( false !== strpos( $success_notice, 'notice-success' ) && false !== strpos( $success_notice, 'Imported: 1' ) && false !== strpos( $success_notice, 'Failed: 0' ), 'Successful admin import reports accurate inserted and failure counts.' );

		// Corrupt only a fixture hierarchy to prove export detects cycles.
		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'parent' => $correct[0]->term_id ),
			array(
				'term_id'  => $destination_root['term_id'],
				'taxonomy' => $taxonomy,
			)
		);
		clean_term_cache( array( $destination_root['term_id'], $correct[0]->term_id ), $taxonomy );
		$cycle = ( new ACAFS_Channel_Hierarchy() )->export_channels( acafs_channel_message_id( $message['post_title'] ) );
		acafs_channel_assert( is_wp_error( $cycle ) && 'acafs_export_channel_cycle' === $cycle->get_error_code(), 'A corrupt cyclic source hierarchy fails export rather than losing ancestry or looping.' );
	} finally {
		$wpdb->query( 'ROLLBACK' );
		if ( is_file( $file ) ) {
			wp_delete_file( $file ); }
		wp_cache_flush();
		wp_set_current_user( $original_user );
	}
	WP_CLI::success( 'Channel hierarchy regressions passed; fixture data rolled back.' );
}
acafs_channel_run_regression();
