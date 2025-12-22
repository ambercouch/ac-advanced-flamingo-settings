<?php
defined( 'ABSPATH' ) || exit;

class ACAFS_Settings {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'acafs_register_plugin_settings' ) );
		add_action( 'acafs_render_settings_page', array( $this, 'acafs_render_settings_page' ) );
	}

	/**
	 * Register all plugin settings
	 */
	public function acafs_register_plugin_settings() {
		// Submission Details (field checkboxes)
		register_setting(
			'acafs_settings_group',
			'acafs_display_fields',
			array(
				'sanitize_callback' => array( $this, 'acafs_sanitize_display_fields' ),
			)
		);

		add_settings_section(
			'acafs_submission_section',
			__( 'Submission Details Customization', 'ac-advanced-flamingo-settings' ),
			function () {
				echo '<p>' . esc_html__( 'Select which form fields should appear in the "Submission Details" column of Flamingo Inbound Messages.', 'ac-advanced-flamingo-settings' ) . '</p>';
			},
			'acafs-settings'
		);

		add_settings_field(
			'acafs_display_fields',
			__( 'Fields to Display', 'ac-advanced-flamingo-settings' ),
			array( $this, 'acafs_display_fields_callback' ),
			'acafs-settings',
			'acafs_submission_section'
		);

		// Menu Behavior Options
		register_setting(
			'acafs_settings_group',
			'acafs_disable_address_book',
			array(
				'sanitize_callback' => array( $this, 'acafs_sanitize_checkbox' ),
			)
		);

		register_setting(
			'acafs_settings_group',
			'acafs_default_flamingo_page',
			array(
				'sanitize_callback' => array( $this, 'acafs_sanitize_select' ),
			)
		);

		register_setting(
			'acafs_settings_group',
			'acafs_rename_flamingo',
			array(
				'sanitize_callback' => array( $this, 'acafs_sanitize_menu_name' ),
			)
		);

		add_settings_section(
			'acafs_menu_settings_section',
			__( 'Flamingo Menu Customization', 'ac-advanced-flamingo-settings' ),
			function () {
				echo '<p>' . esc_html__( 'Customize the Flamingo admin menu behavior.', 'ac-advanced-flamingo-settings' ) . '</p>';
			},
			'acafs-settings'
		);

		add_settings_field(
			'acafs_disable_address_book',
			__( 'Disable Address Book', 'ac-advanced-flamingo-settings' ),
			array( $this, 'acafs_disable_address_book_callback' ),
			'acafs-settings',
			'acafs_menu_settings_section'
		);

		add_settings_field(
			'acafs_rename_flamingo',
			__( 'Rename Flamingo Menu', 'ac-advanced-flamingo-settings' ),
			array( $this, 'acafs_rename_flamingo_callback' ),
			'acafs-settings',
			'acafs_menu_settings_section'
		);

		add_settings_field(
			'acafs_default_flamingo_page',
			__( 'Set Default Flamingo Page', 'ac-advanced-flamingo-settings' ),
			array( $this, 'acafs_default_flamingo_page_callback' ),
			'acafs-settings',
			'acafs_menu_settings_section'
		);
	}

	/**
	 * Render the plugin settings page
	 */
	public function acafs_render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AC Advanced Flamingo Settings', 'ac-advanced-flamingo-settings' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'acafs_settings_group' );
				do_settings_sections( 'acafs-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize checkbox input (1 or 0)
	 */
	public function acafs_sanitize_checkbox( $input ) {
		return ( $input === '1' ) ? '1' : '0';
	}

	/**
	 * Sanitize select dropdown
	 */
	public function acafs_sanitize_select( $input ) {
		$valid_options = array( 'flamingo_inbound', 'flamingo_address_book' );
		return in_array( $input, $valid_options, true ) ? $input : 'flamingo_inbound';
	}

	/**
	 * Sanitize checkbox list
	 */
	public function acafs_sanitize_display_fields( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_map( 'sanitize_text_field', $input );
	}

	/**
	 * Sanitize custom menu name
	 */
	public function acafs_sanitize_menu_name( $input ) {
		$clean_input = sanitize_text_field( $input );
		return ! empty( $clean_input ) ? $clean_input : 'Flamingo';
	}

	/**
	 * Render field: disable address book checkbox
	 */
	public function acafs_disable_address_book_callback() {
		$disabled = (bool) get_option( 'acafs_disable_address_book', false );
		echo '<label>';
		echo '<input type="checkbox" name="acafs_disable_address_book" value="1" ' . checked( 1, $disabled, false ) . '> ';
		echo esc_html__( 'Remove Address Book from the menu', 'ac-advanced-flamingo-settings' );
		echo '</label>';
	}

	/**
	 * Render field: rename menu
	 */
	public function acafs_rename_flamingo_callback() {
		$menu_name = sanitize_text_field( get_option( 'acafs_rename_flamingo', esc_html__( 'Contact Log', 'ac-advanced-flamingo-settings' ) ) );
		echo '<input type="text" name="acafs_rename_flamingo" value="' . esc_attr( $menu_name ) . '" class="regular-text">';
	}

	/**
	 * Render field: dropdown for default page
	 */
	public function acafs_default_flamingo_page_callback() {
		$selected = sanitize_text_field( get_option( 'acafs_default_flamingo_page', 'flamingo_inbound' ) );
		?>
		<select name="acafs_default_flamingo_page">
			<option value="flamingo_inbound" <?php selected( $selected, 'flamingo_inbound' ); ?>>
				<?php esc_html_e( 'Inbound Messages', 'ac-advanced-flamingo-settings' ); ?>
			</option>
			<option value="flamingo_address_book" <?php selected( $selected, 'flamingo_address_book' ); ?>>
				<?php esc_html_e( 'Address Book', 'ac-advanced-flamingo-settings' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Render field: checkboxes for meta fields
	 */
	public function acafs_display_fields_callback() {
		$saved_fields = get_option( 'acafs_display_fields', array( 'your-message' ) );
		if ( ! is_array( $saved_fields ) ) {
			$saved_fields = array( 'your-message' );
		}

		$meta_keys = $this->acafs_get_all_flamingo_fields();

		if ( empty( $meta_keys ) ) {
			echo '<em>' . esc_html__( 'No submission data available yet.', 'ac-advanced-flamingo-settings' ) . '</em>';
			return;
		}

		echo '<div class="acafs-field-options">';
		foreach ( $meta_keys as $key ) {
			$sanitized_key = sanitize_text_field( $key );
			$checked       = in_array( $sanitized_key, $saved_fields, true ) ? 'checked' : '';

			echo '<label>';
			echo '<input type="checkbox" name="acafs_display_fields[]" value="' . esc_attr( $sanitized_key ) . '" ' . esc_attr( $checked ) . '> ';
			echo esc_html( ucwords( str_replace( '_', ' ', $sanitized_key ) ) );
			echo '</label>';
		}
		echo '</div>';
	}

	/**
	 * Retrieve meta keys from Flamingo
	 */
	private function acafs_get_all_flamingo_fields() {
		global $wpdb;

		$cached = wp_cache_get( 'acafs_flamingo_meta_keys', 'acafs_cache' );
		if ( $cached !== false ) {
			return $cached;
		}

		$meta_keys = $wpdb->get_col(
			$wpdb->prepare(
				"
            SELECT DISTINCT meta_key FROM {$wpdb->postmeta}
            WHERE meta_key LIKE %s
        ",
				'_field_%'
			)
		);

		$meta_keys = array_map(
			function ( $key ) {
				return str_replace( '_field_', '', sanitize_text_field( $key ) );
			},
			(array) $meta_keys
		);

		wp_cache_set( 'acafs_flamingo_meta_keys', $meta_keys, 'acafs_cache', 3600 );

		return $meta_keys;
	}
}
