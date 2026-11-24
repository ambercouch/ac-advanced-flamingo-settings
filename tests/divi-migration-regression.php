<?php
/**
 * Divi migration regression against real WordPress, Flamingo and the standalone.
 *
 * Run on a local transactional database with plugins/themes skipped:
 * ddev wp --skip-plugins --skip-themes eval-file web/wp-content/plugins/ac-advanced-flamingo-settings/tests/divi-migration-regression.php
 *
 * @package AC_Advanced_Flamingo_Settings
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once dirname( __DIR__ ) . '/includes/core/class-acafs-settings.php';
require_once dirname( __DIR__ ) . '/includes/core/class-acafs-divi-migration-safety.php';
require_once WP_PLUGIN_DIR . '/flamingo/flamingo.php';
Flamingo_Inbound_Message::register_post_type();

require_once __DIR__ . '/fixtures/class-acafs-divi-migration-test-inventory.php';

/**
 * Assert and log a regression expectation.
 *
 * @param bool   $condition Expected condition.
 * @param string $message   Description.
 */
function acafs_divi_migration_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	WP_CLI::log( 'PASS: ' . $message );
}

/**
 * Capture the notice without outputting HTML to the CLI.
 *
 * @param ACAFS_Divi_Migration_Safety $monitor Notice controller.
 * @return string Notice HTML.
 */
function acafs_divi_migration_notice( $monitor ) {
	ob_start();
	$monitor->render_notice();
	return ob_get_clean();
}

global $wpdb;
$original_user     = get_current_user_id();
$original_hooks    = $GLOBALS['wp_filter'];
$original_settings = isset( $GLOBALS['wp_registered_settings'] ) ? $GLOBALS['wp_registered_settings'] : array();
$divi_theme        = static function () {
	return 'Divi';
};
$wpdb->query( 'START TRANSACTION' );
try {
	$admin_id = wp_insert_user(
		array(
			'user_login' => 'acafs-migration-' . wp_generate_uuid4(),
			'user_pass'  => wp_generate_password(),
			'role'       => 'administrator',
		)
	);
	acafs_divi_migration_assert( ! is_wp_error( $admin_id ), 'Administrator fixture created.' );
	wp_set_current_user( $admin_id );
	$monitor  = new ACAFS_Divi_Migration_Test_Inventory();
	$settings = new ACAFS_Settings();
	delete_option( ACAFS_Divi_Migration_Safety::OPTION );
	delete_option( ACAFS_Divi_Migration_Safety::FAILURE_OPTION );
	$monitor->installed = false;
	acafs_divi_migration_assert( '' === acafs_divi_migration_notice( $monitor ), 'Never-enabled sites receive no migration warning.' );
	acafs_divi_migration_assert( false === get_option( ACAFS_Divi_Migration_Safety::OPTION ), 'Readiness checks never enable or create the preference.' );

	// Upgrade fixture: Settings 1.4.3 stored this existing option for bundled capture.
	update_option( 'acafs_version', '1.4.3' );
	update_option( ACAFS_Divi_Migration_Safety::OPTION, '1' );
	$settings->acafs_register_plugin_settings();
	acafs_divi_migration_assert( '1' === get_option( ACAFS_Divi_Migration_Safety::OPTION ), 'Registering upgraded Settings preserves the 1.4.3 enabled preference.' );
	acafs_divi_migration_assert( 'missing' === $monitor->get_status(), 'Enabled 1.4.3 upgrade warns when standalone is missing.' );
	$notice = acafs_divi_migration_notice( $monitor );
	acafs_divi_migration_assert( false !== strpos( $notice, 'Install it' ) && false !== strpos( $notice, 'plugins.php' ) && false !== strpos( $notice, 'acafs-integrations' ), 'Missing warning includes restoration instructions and safe admin links.' );
	acafs_divi_migration_assert( $notice === acafs_divi_migration_notice( $monitor ) && false === strpos( $notice, 'is-dismissible' ), 'Warning persists on repeated renders without a dismiss control.' );
	update_option( ACAFS_Divi_Migration_Safety::OPTION, null );
	acafs_divi_migration_assert( '1' === get_option( ACAFS_Divi_Migration_Safety::OPTION ), 'An omitted shared-group option cannot silently disable upgraded capture.' );
	update_option( ACAFS_Divi_Migration_Safety::OPTION, '0' );
	acafs_divi_migration_assert( '' === $monitor->get_status(), 'Explicitly disabled capture has no warning.' );
	update_option( ACAFS_Divi_Migration_Safety::OPTION, '1' );
	$monitor->installed = true;
	$monitor->active    = false;
	acafs_divi_migration_assert( 'inactive' === $monitor->get_status() && false !== strpos( acafs_divi_migration_notice( $monitor ), 'Activate it' ), 'Inactive standalone has an actionable warning.' );
	wp_set_current_user( 0 );
	acafs_divi_migration_assert( '' === acafs_divi_migration_notice( $monitor ), 'Users without manage_options cannot see migration notices.' );
	wp_set_current_user( $admin_id );
	$config = $settings->acafs_get_integration_configs()[0];
	ob_start();
	$settings->acafs_render_integration_checkbox( $config );
	$checkbox = ob_get_clean();
	acafs_divi_migration_assert( false !== strpos( $checkbox, 'value="0"' ) && false !== strpos( $checkbox, 'checked=' ), 'Enabled Divi checkbox retains intent and submits explicit zero when unchecked.' );
	ob_start();
	$settings->acafs_render_integrations_page();
	$integration_page = ob_get_clean();
	acafs_divi_migration_assert( false !== strpos( $integration_page, 'name="acafs_enable_divi_contact_capture" value="0"' ) && false !== strpos( $integration_page, 'Uncheck only if you intend' ), 'The unavailable-integration page still allows capture to be explicitly disabled.' );


	$stale_active_plugin = static function () {
		return array( ACAFS_Divi_Migration_Safety::PLUGIN );
	};
	add_filter( 'pre_option_active_plugins', $stale_active_plugin );
	ob_start();
	$settings->acafs_render_integrations_page();
	$stale_page = ob_get_clean();
	remove_filter( 'pre_option_active_plugins', $stale_active_plugin );
	acafs_divi_migration_assert( false !== strpos( $stale_page, 'name="acafs_enable_divi_contact_capture" value="0"' ), 'A missing plugin still permits explicit disabling when activation metadata is stale.' );

	$monitor->active = true;
	unregister_taxonomy( 'flamingo_inbound_channel' );
	acafs_divi_migration_assert( 'flamingo' === $monitor->get_status(), 'Missing Flamingo taxonomy prevents a false healthy status.' );
	Flamingo_Inbound_Message::register_post_type();
	add_filter( 'pre_option_stylesheet', $divi_theme );
	add_filter( 'pre_option_template', $divi_theme );
	remove_all_actions( 'et_pb_contact_form_submit' );
	acafs_divi_migration_assert( 'handler' === $monitor->get_status(), 'Active plugin without its callable capture handler still warns.' );

	$standalone = getenv( 'ACAFS_DIVI_TEST_PLUGIN_PATH' );
	if ( false === $standalone || '' === $standalone ) {
		$standalone = ABSPATH . '../development/acafs-divi-contact-form-for-flamingo/';
	}
	acafs_divi_migration_assert( file_exists( $standalone . 'acafs-divi-contact-form-for-flamingo.php' ), 'Real standalone fixture is available without installing or activating it.' );
	require_once $standalone . 'acafs-divi-contact-form-for-flamingo.php';
	require_once $standalone . 'includes/class-acafs-flamingo-message-creator.php';
	require_once $standalone . 'includes/class-acafs-divi-integration.php';
	$capture = new ACAFS_Divi_Flamingo_Integration( new ACAFS_Flamingo_Message_Creator() );
	remove_all_actions( 'et_pb_contact_form_submit' );
	// @phpstan-ignore arguments.count (Deliberately invalid registration tests rejection of a handler missing validation context.)
	add_action( 'et_pb_contact_form_submit', array( $capture, 'capture_from_action' ), 10, 2 );
	acafs_divi_migration_assert( 'handler' === $monitor->get_status(), 'A handler lacking Divi validation context cannot be treated as ready.' );
	remove_action( 'et_pb_contact_form_submit', array( $capture, 'capture_from_action' ), 10 );
	add_action( 'et_pb_contact_form_submit', array( $capture, 'capture_from_action' ), 10, 3 );
	acafs_divi_migration_assert( '' === $monitor->get_status() && '' === acafs_divi_migration_notice( $monitor ), 'Successful migration automatically removes the warning with a real ready handler.' );
	$other_theme = static function () {
		return 'twentytwentyfive';
	};
	add_filter( 'pre_option_stylesheet', $other_theme, 20 );
	add_filter( 'pre_option_template', $other_theme, 20 );
	acafs_divi_migration_assert( 'theme' === $monitor->get_status(), 'Enabled capture warns when the active theme cannot render Divi forms.' );
	remove_filter( 'pre_option_stylesheet', $other_theme, 20 );
	remove_filter( 'pre_option_template', $other_theme, 20 );

	$failure = new WP_Error( 'acafs_divi_message_failed', 'Private fixture details must not be stored.', array( 'fields' => 'private' ) );
	do_action( 'acafs_divi_flamingo_capture_error', $failure );
	acafs_divi_migration_assert( 'capture_error' === $monitor->get_status(), 'Reported storage failure remains visible despite an active handler.' );
	acafs_divi_migration_assert( 'acafs_divi_message_failed' === get_option( ACAFS_Divi_Migration_Safety::FAILURE_OPTION ), 'Only a known failure code is persisted, never submission/error data.' );
	$monitor->record_failure( new WP_Error( 'unrelated', '<script>private</script>' ) );
	acafs_divi_migration_assert( 'acafs_divi_message_failed' === get_option( ACAFS_Divi_Migration_Safety::FAILURE_OPTION ), 'Unknown error codes cannot replace the safe diagnostic.' );
	$new_request = new ACAFS_Divi_Migration_Test_Inventory();
	acafs_divi_migration_assert( 'capture_error' === $new_request->get_status(), 'A reported failure survives a new monitor instance.' );
	$count  = static function () use ( $wpdb ) {
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'flamingo_inbound'" );
	};
	$before = $count();
	$fields = wp_slash(
		array(
			'name'    => array( 'value' => "O'Neil" ),
			'email'   => array( 'value' => 'migration@example.test' ),
			'message' => array( 'value' => 'C:\Bridal\dress' ),
		)
	);
	do_action( 'et_pb_contact_form_submit', $fields, true, array() );
	acafs_divi_migration_assert( $before === $count() && 'capture_error' === $monitor->get_status(), 'Validation failure saves nothing and does not clear the warning.' );
	$info = array( 'contact_form_id' => 'migration-regression-' . wp_generate_uuid4() );
	do_action( 'et_pb_contact_form_submit', $fields, false, $info );
	acafs_divi_migration_assert( $before + 1 === $count() && false === get_option( ACAFS_Divi_Migration_Safety::FAILURE_OPTION ), 'Real successful capture clears the persistent failure after channel persistence.' );
	acafs_divi_migration_assert( '' === acafs_divi_migration_notice( $monitor ), 'Restored capture removes the admin warning automatically.' );
	$message_id = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type = 'flamingo_inbound'" );
	acafs_divi_migration_assert( "O'Neil" === get_post_meta( $message_id, '_field_name', true ), 'Successful migration retains the standalone escaping fix.' );
	$monitor->record_failure( new WP_Error( 'acafs_divi_channel_failed' ) );
	acafs_divi_migration_assert( 'capture_error' === $monitor->get_status(), 'Channel-assignment failures also warn.' );
	// Exercise the real shutdown fallback as well as the normal Divi action.
	$fallback      = new ACAFS_Divi_Flamingo_Integration( new ACAFS_Flamingo_Message_Creator() );
	$original_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserve request state around a synthetic nonce-verified fixture.
	$_POST         = array(
		'et_pb_contactform_submit_0'              => '1',
		'_wpnonce-et-pb-contact-form-submitted-0' => wp_create_nonce( 'et-pb-contact-form-submit' ),
		'et_pb_contact_name_0'                    => wp_slash( "Fallback O'Neil" ),
		'et_pb_contact_email_0'                   => 'fallback@example.test',
	);
	$fallback->capture_from_post();
	$_POST  = $original_post;
	$before = $count();
	add_action( 'shutdown', array( $fallback, 'maybe_commit_fallback' ), 0 );
	do_action( 'shutdown' );
	remove_action( 'shutdown', array( $fallback, 'maybe_commit_fallback' ), 0 );
	acafs_divi_migration_assert( $before + 1 === $count() && false === get_option( ACAFS_Divi_Migration_Safety::FAILURE_OPTION ), 'Successful real POST fallback also clears the failure warning.' );
	$monitor->record_failure( new WP_Error( 'acafs_divi_channel_failed' ) );
	update_option( ACAFS_Divi_Migration_Safety::OPTION, '0' );
	acafs_divi_migration_assert( '' === $monitor->get_status(), 'Disabling capture suppresses even an outstanding failure warning.' );
	$monitor->record_failure( new WP_Error( 'acafs_divi_message_failed' ) );
	acafs_divi_migration_assert( 'acafs_divi_channel_failed' === get_option( ACAFS_Divi_Migration_Safety::FAILURE_OPTION ), 'Disabled capture ignores new capture-error events.' );
} finally {
	$wpdb->query( 'ROLLBACK' );
	$GLOBALS['wp_filter']              = $original_hooks; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore test-only hooks, including original third-party callbacks.
	$GLOBALS['wp_registered_settings'] = $original_settings;
	wp_cache_flush();
	wp_set_current_user( $original_user );
}
WP_CLI::success( 'Divi migration regressions passed; fixture data rolled back.' );
