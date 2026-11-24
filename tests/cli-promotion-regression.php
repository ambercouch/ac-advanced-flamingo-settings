<?php
/**
 * Transactional promotion regressions; run against a disposable WordPress DB.
 *
 * @package AC_Advanced_Flamingo_Settings
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}
require_once dirname( __DIR__ ) . '/includes/core/class-acafs-cli-promotion.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

/**
 * Assert the expected behavior.
 *
 * @param bool   $condition Expected condition.
 * @param string $message Description.
 */
function acafs_promotion_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	WP_CLI::log( 'PASS: ' . $message );
}

/**
 * Capture page output without sending a response.
 *
 * @param ACAFS_CLI_Promotion $promotion Renderer.
 * @param bool               $addon Whether to render the listing.
 * @return string HTML.
 */
function acafs_promotion_html( $promotion, $addon = false ) {
	ob_start();
	if ( $addon ) {
		$promotion->acafs_render_addon();
	} else {
		$promotion->acafs_render_promotion();
	}
	return ob_get_clean();
}

global $wpdb;
$original_user   = get_current_user_id();
$original_cache  = wp_cache_get( 'plugins', 'plugins' );
$inactive_filter = static function () {
	return array();
};
$active_filter   = static function () {
	return array( ACAFS_CLI_Promotion::PLUGIN_FILE );
};
$wpdb->query( 'START TRANSACTION' );
try {
	$admin = wp_insert_user(
		array(
			'user_login' => 'acafs-promo-' . wp_generate_uuid4(),
			'user_pass'  => wp_generate_password(),
			'role'       => 'administrator',
		)
	);
	if ( is_wp_error( $admin ) ) {
		throw new RuntimeException( 'Unable to create transactional user.' );
	}
	wp_set_current_user( $admin );
	add_filter( 'pre_option_active_plugins', $inactive_filter );
	wp_cache_set( 'plugins', array( '' => array() ), 'plugins' );
	$promotion = new ACAFS_CLI_Promotion();
	acafs_promotion_assert( 'available' === $promotion->acafs_get_state(), 'Missing plugin is available.' );
	$html = acafs_promotion_html( $promotion );
	acafs_promotion_assert( false !== strpos( $html, 'Automate Flamingo with WP-CLI and AI agents' ), 'Administrator sees promotion when CLI is missing.' );
	acafs_promotion_assert( false !== strpos( $html, ACAFS_CLI_Promotion::PRODUCT_URL ) && false !== strpos( $html, '_wpnonce' ), 'Card has official URL and nonce-protected dismissal form.' );
	wp_cache_set( 'plugins', array( '' => array( ACAFS_CLI_Promotion::PLUGIN_FILE => array( 'Name' => 'AC Advanced Flamingo CLI' ) ) ), 'plugins' );
	acafs_promotion_assert( 'installed' === $promotion->acafs_get_state(), 'Inactive installed copy is detected.' );
	acafs_promotion_assert( false !== strpos( acafs_promotion_html( $promotion, true ), 'Installed, inactive' ), 'Inactive add-on listing shows status.' );
	remove_filter( 'pre_option_active_plugins', $inactive_filter );
	add_filter( 'pre_option_active_plugins', $active_filter );
	acafs_promotion_assert( '' === acafs_promotion_html( $promotion ), 'Active CLI hides promotion.' );
	acafs_promotion_assert( false !== strpos( acafs_promotion_html( $promotion, true ), 'Installed and active' ), 'Active CLI remains in add-ons.' );
	remove_filter( 'pre_option_active_plugins', $active_filter );
	add_filter( 'pre_option_active_plugins', $inactive_filter );
	update_user_meta( $admin, ACAFS_CLI_Promotion::DISMISS_KEY, '1' );
	acafs_promotion_assert( '' === acafs_promotion_html( new ACAFS_CLI_Promotion() ), 'Dismissal survives a new renderer instance.' );
	acafs_promotion_assert( false !== strpos( acafs_promotion_html( $promotion, true ), 'AC Advanced Flamingo CLI' ), 'Dismissal does not remove add-on listing.' );
	wp_set_current_user( 0 );
	acafs_promotion_assert( '' === acafs_promotion_html( $promotion ) && '' === acafs_promotion_html( $promotion, true ), 'Anonymous users receive no promotional output.' );
	$subscriber = wp_insert_user(
		array(
			'user_login' => 'acafs-promo-' . wp_generate_uuid4(),
			'user_pass'  => wp_generate_password(),
			'role'       => 'subscriber',
		)
	);
	if ( is_wp_error( $subscriber ) ) {
		throw new RuntimeException( 'Unable to create transactional user.' );
	}
	wp_set_current_user( $subscriber );
	acafs_promotion_assert( '' === acafs_promotion_html( $promotion ) && '' === acafs_promotion_html( $promotion, true ), 'Subscribers receive no promotional output.' );
} finally {
	remove_filter( 'pre_option_active_plugins', $inactive_filter );
	remove_filter( 'pre_option_active_plugins', $active_filter );
	$wpdb->query( 'ROLLBACK' );
	wp_cache_flush();
	if ( false !== $original_cache ) {
		wp_cache_set( 'plugins', $original_cache, 'plugins' );
	}
	wp_set_current_user( $original_user );
}
