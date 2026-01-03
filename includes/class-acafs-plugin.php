<?php

use background\ACAFS_Background_Import;

defined( 'ABSPATH' ) || exit;

class ACAFS_Plugin {

	/**
	 * Constructor - Initializes the plugin.
	 */
	public function __construct() {
		// Early menu modifications
		add_action( 'admin_menu', array( $this, 'acafs_modify_flamingo_menu' ), 10 );
		add_action( 'admin_init', array( $this, 'acafs_redirect_address_book' ), 10 );
		add_action( 'admin_menu', array( $this, 'acafs_rename_flamingo_menu' ), 10 );
		add_action( 'admin_menu', array( $this, 'acafs_set_flamingo_default' ), 10 );

		// Plugin list page settings link
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'acafs_add_settings_link' ) );

		// Load modules
		$this->load_dependencies();
		$this->init_modules();
	}
	/**
	 * Load all required class files.
	 */
	private function load_dependencies() {
		// Core setup
		require_once ACAFS_PLUGIN_INC_DIR . 'core/class-acafs-hooks.php';
		require_once ACAFS_PLUGIN_INC_DIR . 'core/class-acafs-admin.php';
		require_once ACAFS_PLUGIN_INC_DIR . 'core/class-acafs-settings.php';

		// Features
		require_once ACAFS_PLUGIN_INC_DIR . 'features/class-acafs-columns.php';
		require_once ACAFS_PLUGIN_INC_DIR . 'features/class-acafs-export.php';
		require_once ACAFS_PLUGIN_INC_DIR . 'features/class-acafs-import.php';
		require_once ACAFS_PLUGIN_INC_DIR . 'features/class-acafs-cf7-persist-uploads.php';

		// Background imports
		require_once ACAFS_PLUGIN_INC_DIR . 'background/class-acafs-background-import.php';

		// Future: Divi compatibility
		$divi_file = ACAFS_PLUGIN_INC_DIR . 'features/class-acafs-compat-divi.php';
		if ( file_exists( $divi_file ) ) {
			require_once $divi_file;
		}
	}

	/**
	 * Instantiate and initialize plugin modules.
	 */
	private function init_modules() {
		error_log( 'init_modules' );
		// Background import handler
		$this->import_process = new ACAFS_Background_Import();

		// Core modules
		new ACAFS_Hooks();
		new ACAFS_Admin();
		new ACAFS_Settings();
		//
		//        // Features
		new ACAFS_Columns();
		//new ACAFS_Single_Message();
		new ACAFS_Export( $this->import_process ); // Pass background import if needed
		new ACAFS_Import( $this->import_process );
		if ( get_option( 'acafs_enable_persistent_uploads', false ) ) {
			new ACAFS_CF7_Persist_Uploads();
		}

		// Optional integrations (can be conditional later)
		if ( class_exists( 'ACAFS_Compat_Divi' ) ) {
			new ACAFS_Compat_Divi();
		}
	}


	protected $acafs_import_messages;


	/**
	 * Add a "Settings" link to the plugin list page.
	 */
	public function acafs_add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=acafs-settings' ) . '">' . __( 'Settings', 'ac-advanced-flamingo-settings' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Remove the Address Book menu if the option is enabled.
	 */
	public function acafs_modify_flamingo_menu() {
		global $submenu;

		// Ensure option is enabled before modifying the menu
		if ( ! get_option( 'acafs_disable_address_book', false ) ) {
			return;
		}

		// Ensure the Flamingo menu exists before trying to modify it
		if ( ! isset( $submenu['flamingo'] ) || empty( $submenu['flamingo'] ) ) {
			return;
		}

		// Loop through the submenu items and remove the Address Book
		foreach ( $submenu['flamingo'] as $index => $submenu_item ) {
			if ( ! isset( $submenu_item[2] ) ) {
				continue;
			}

			if ( $submenu_item[2] === 'flamingo' ) {
				unset( $submenu['flamingo'][ $index ] );
				break;
			}
		}
	}

	/**
	 * Redirect users away from the Address Book if it's disabled.
	 */
	public function acafs_redirect_address_book() {
		if ( ! get_option( 'acafs_disable_address_book', false ) ) {
			return;
		}

		if ( isset( $_GET['page'] ) && $_GET['page'] === 'flamingo' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=flamingo_inbound' ) );
			exit;
		}
	}

	/**
	 * Rename Flamingo menu item based on user settings.
	 */
	public function acafs_rename_flamingo_menu() {
		global $menu;

		// Get saved menu name, default to "Flamingo" if empty, and sanitize it
		$new_name = sanitize_text_field( get_option( 'acafs_rename_flamingo', esc_html__( 'Flamingo', 'ac-advanced-flamingo-settings' ) ) );

		if ( empty( $new_name ) ) {
			$new_name = esc_html__( 'Flamingo', 'ac-advanced-flamingo-settings' ); // Ensure default is translatable
		}

		foreach ( $menu as &$item ) {
			if ( $item[2] === 'flamingo' ) {
				$item[0] = esc_html( $new_name );
				break;
			}
		}
	}

	/**
	 * Change Flamingo default page to Inbound Messages or Address Book.
	 */
	public function acafs_set_flamingo_default() {
		global $submenu;

		// Get user-selected default page and sanitize it
		$default_page = sanitize_text_field( get_option( 'acafs_default_flamingo_page', 'flamingo_inbound' ) );

		// Ensure the Flamingo submenu exists
		if ( ! isset( $submenu['flamingo'] ) || empty( $submenu['flamingo'] ) ) {
			return;
		}

		// Move the selected default page to the first position
		foreach ( $submenu['flamingo'] as $index => $item ) {
			if ( isset( $item[2] ) && $item[2] === $default_page ) {
				unset( $submenu['flamingo'][ $index ] );
				array_unshift( $submenu['flamingo'], $item );
				break;
			}
		}
	}
}
