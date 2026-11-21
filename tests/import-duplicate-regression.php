<?php
/**
 * Focused integration regression; run on a disposable WordPress database:
 * wp --skip-plugins --skip-themes eval-file tests/import-duplicate-regression.php
 *
 * Uses real WordPress sanitisation, queries, insertion, metadata and taxonomy APIs.
 * Test records are rolled back; requires transactional WordPress tables.
 */

require_once dirname( __DIR__ ) . '/includes/services/class-acafs-import-service.php';

register_post_type( 'flamingo_inbound' );
register_taxonomy( 'flamingo_inbound_channel', 'flamingo_inbound' );

/**
 * Fail the regression when an expectation is not met.
 *
 * @param bool   $condition Expected condition.
 * @param string $message   Failure description.
 */
function acafs_regression_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

global $wpdb;
$wpdb->query( 'START TRANSACTION' );
try {
	$service = new ACAFS_Import_Service();
	$prefix  = 'acafs-regression-' . wp_generate_uuid4();
	$channel = $prefix . '-channel';
	$base    = array(
		'post_content' => '<p>Message content</p><script>alert(1)</script>',
		'post_date'    => '2026-01-01 12:00:00',
		'post_author'  => 0,
		'channel_id'   => 987654321,
		'channel_slug' => $channel,
		'channel_name' => 'Regression channel',
		'meta'         => array(
			'_from'   => array( 'example@example.com' ),
			'_fields' => array( maybe_serialize( array( 'name' => 'Example' ) ) ),
		),
	);

	foreach ( array( "<b>$prefix</b>\n  title %41", "$prefix normal title" ) as $index => $fixture_title ) {
		$message               = $base;
		$message['post_title'] = $fixture_title;
		if ( 0 === $index ) {
			acafs_regression_assert( $fixture_title !== sanitize_text_field( $fixture_title ), 'Fixture title must change during sanitisation.' );
		}
		$first  = $service->import_batch( array( $message ) );
		$second = $service->import_batch( array( $message ) );
		acafs_regression_assert( 1 === $first['inserted'] && 0 === $first['skipped'] && 0 === $first['failed'] && array() === $first['errors'], 'First import must insert successfully.' );
		acafs_regression_assert( 0 === $second['inserted'] && 1 === $second['skipped'] && 0 === $second['failed'] && array() === $second['errors'], 'Repeated import must skip the duplicate.' );
		$stored_messages = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s", 'flamingo_inbound', sanitize_text_field( $fixture_title ) ) );
		acafs_regression_assert( 1 === count( $stored_messages ), 'Exactly one stored message must remain.' );
		$stored_message = $stored_messages[0];
		acafs_regression_assert( sanitize_text_field( $fixture_title ) === $stored_message->post_title && wp_kses_post( $base['post_content'] ) === $stored_message->post_content, 'Stored title/content must remain sanitised.' );
		acafs_regression_assert( 'example@example.com' === get_post_meta( $stored_message->ID, '_from', true ) && array( 'name' => 'Example' ) === get_post_meta( $stored_message->ID, '_fields', true ), 'Metadata must be preserved.' );
		$slugs = wp_get_object_terms( $stored_message->ID, 'flamingo_inbound_channel', array( 'fields' => 'slugs' ) );
		acafs_regression_assert( array( $channel ) === $slugs, 'Channel must be resolved by slug, ignoring source term ID.' );
	}

	$message['post_title']   = "$prefix batch <b>duplicate</b>";
	$canonical               = $message;
	$canonical['post_title'] = sanitize_text_field( $message['post_title'] );
	$batch                   = $service->import_batch( array( $message, $canonical, $message ) );
	acafs_regression_assert( 3 === $batch['processed'] && 1 === $batch['inserted'] && 2 === $batch['skipped'] && 0 === $batch['failed'], 'Canonical duplicates in one batch must be skipped.' );
	$message['post_content'] = 'Different content';
	$different               = $service->import_batch( array( $message ) );
	acafs_regression_assert( 1 === $different['inserted'], 'Same title with different content must remain a distinct message.' );
	WP_CLI::success( 'Import duplicate regression passed: sanitised/normal titles, repeat imports, batch duplicates, distinct content, metadata, channel creation/reuse.' );
} finally {
	$wpdb->query( 'ROLLBACK' );
}
