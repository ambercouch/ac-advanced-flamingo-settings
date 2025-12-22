<?php
defined( 'ABSPATH' ) || exit;

class ACAFS_Hooks {

	public function __construct() {
		$this->acafs_register_hooks();
	}

	private function acafs_register_hooks() {
		add_action( 'admin_init', array( $this, 'acafs_check_flamingo_dependency' ) );
		add_filter( 'plugin_action_links_' . ACAFS_PLUGIN_BASENAME, array( $this, 'acafs_add_settings_link' ) );

		register_activation_hook( ACAFS_PLUGIN, array( $this, 'acafs_on_activate' ) );
		register_deactivation_hook( ACAFS_PLUGIN, array( $this, 'acafs_on_deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'acafs_init' ) );
	}

	public function acafs_on_activate() {
		add_option( 'acafs_version', ACAFS_VERSION );
	}

	public function acafs_on_deactivate() {
		// Optional cleanup
	}

	public function acafs_init() {
		// Reserved for initializing future integrations
	}

	public function acafs_add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=acafs-settings' ) . '">' . __( 'Settings', 'ac-advanced-flamingo-settings' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function acafs_check_flamingo_dependency() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( ! file_exists( WP_PLUGIN_DIR . '/flamingo/flamingo.php' ) ) {
			add_action( 'admin_notices', array( $this, 'acafs_show_flamingo_missing_notice' ) );
			return;
		}

		if ( ! is_plugin_active( 'flamingo/flamingo.php' ) ) {
			add_action( 'admin_notices', array( $this, 'acafs_show_flamingo_inactive_notice' ) );
		}
	}

	public function acafs_show_flamingo_missing_notice() {
		$install_url = admin_url( 'plugin-install.php?s=flamingo&tab=search&type=term' );
		echo '<div class="notice notice-error">
            <p><strong>' . esc_html__( 'AC Advanced Flamingo Settings requires the Flamingo plugin to function.', 'ac-advanced-flamingo-settings' ) . '</strong></p>
            <p>' . esc_html__( 'Flamingo is not installed. You can search for it in the WordPress Plugin Repository and install it manually.', 'ac-advanced-flamingo-settings' ) . '</p>
            <p><a href="' . esc_url( $install_url ) . '">' . esc_html__( 'Search for Flamingo in the Plugin Repository', 'ac-advanced-flamingo-settings' ) . '</a></p>
        </div>';
	}

	public function acafs_show_flamingo_inactive_notice() {
		$activate_url = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=flamingo/flamingo.php' ), 'activate-plugin_flamingo/flamingo.php' );
		echo '<div class="notice notice-warning">
            <p>' . esc_html__( 'AC Advanced Flamingo Settings requires', 'ac-advanced-flamingo-settings' ) . ' <strong>Flamingo</strong> ' . esc_html__( 'to be activated.', 'ac-advanced-flamingo-settings' ) . '</p>
            <p><a href="' . esc_url( $activate_url ) . '" class="button button-primary">' . esc_html__( 'Activate Flamingo', 'ac-advanced-flamingo-settings' ) . '</a></p>
        </div>';
	}
}
