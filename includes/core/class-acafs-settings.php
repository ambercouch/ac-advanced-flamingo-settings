<?php
defined( 'ABSPATH' ) || exit;

class ACAFS_Settings {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'acafs_register_plugin_settings' ) );
		add_action( 'acafs_render_settings_page', array( $this, 'acafs_render_settings_page' ) );
		add_action( 'acafs_render_integrations_page', array( $this, 'acafs_render_integrations_page' ) );
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

		register_setting(
			'acafs_settings_group',
			'acafs_enable_persistent_uploads',
			array(
				'sanitize_callback' => array( $this, 'acafs_sanitize_checkbox' ),
			)
		);

		foreach ( $this->acafs_get_integration_configs() as $integration ) {
			register_setting(
				'acafs_settings_group',
				$integration['option_name'],
				array(
					'sanitize_callback' => array( $this, 'acafs_sanitize_checkbox' ),
				)
			);
		}

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

		add_settings_section(
			'acafs_upload_settings_section',
			__( 'File Uploads', 'ac-advanced-flamingo-settings' ),
			function () {
				echo '<p>' . esc_html__( 'Control how Contact Form 7 file uploads are stored for Flamingo.', 'ac-advanced-flamingo-settings' ) . '</p>';
			},
			'acafs-settings'
		);

		add_settings_field(
			'acafs_enable_persistent_uploads',
			__( 'Persistent Uploads', 'ac-advanced-flamingo-settings' ),
			array( $this, 'acafs_enable_persistent_uploads_callback' ),
			'acafs-settings',
			'acafs_upload_settings_section'
		);

		add_settings_section(
			'acafs_integrations_section',
			__( 'Integrations', 'ac-advanced-flamingo-settings' ),
			function () {
				echo '<p>' . esc_html__( 'Enable optional integrations that capture submissions into Flamingo.', 'ac-advanced-flamingo-settings' ) . '</p>';
			},
			'acafs-integrations'
		);

		foreach ( $this->acafs_get_integration_configs() as $integration ) {
			add_settings_field(
				$integration['option_name'],
				$integration['label'],
				array( $this, 'acafs_render_integration_field' ),
				'acafs-integrations',
				'acafs_integrations_section',
				array(
					'config' => $integration,
				)
			);
		}
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
	 * Render the integrations settings page
	 */
	public function acafs_render_integrations_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Flamingo Integrations', 'ac-advanced-flamingo-settings' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'acafs_settings_group' );
				do_settings_sections( 'acafs-integrations' );
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
	 * Render field: enable persistent uploads checkbox
	 */
	public function acafs_enable_persistent_uploads_callback() {
		$enabled = (bool) get_option( 'acafs_enable_persistent_uploads', false );
		echo '<label>';
		echo '<input type="checkbox" name="acafs_enable_persistent_uploads" value="1" ' . checked( 1, $enabled, false ) . '> ';
		echo esc_html__( 'Store Contact Form 7 uploads in a permanent folder and save URLs in Flamingo', 'ac-advanced-flamingo-settings' );
		echo '</label>';
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

	/**
	 * Get integrations configuration.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function acafs_get_integration_configs() {
		return array(
			array(
				'key'                   => 'divi',
				'label'                 => __( 'Divi Contact Form', 'ac-advanced-flamingo-settings' ),
				'option_name'           => 'acafs_enable_divi_contact_capture',
				'plugin_file'           => 'acafs-divi-contact-form-for-flamingo/acafs-divi-contact-form-for-flamingo.php',
				'product_url'           => 'https://ambercouch.co.uk/divi-contact-form-integration-for-flamingo-database/',
				'headline'              => __( 'Save Divi Contact Form submissions to Flamingo', 'ac-advanced-flamingo-settings' ),
				'description'           => __( 'Capture Divi Contact Form module submissions directly into Flamingo Inbound Messages, just like Contact Form 7.', 'ac-advanced-flamingo-settings' ),
				'active_checkbox_label' => __( 'Enable Divi Contact Form → Flamingo capture', 'ac-advanced-flamingo-settings' ),
				'inactive_message'      => __( 'Activate the plugin to enable Divi → Flamingo capture.', 'ac-advanced-flamingo-settings' ),
				'price'                 => '£29.95',
				'builder_name'          => __( 'Divi', 'ac-advanced-flamingo-settings' ),
			),
			array(
				'key'                   => 'wpbakery',
				'label'                 => __( 'WPBakery Contact Form', 'ac-advanced-flamingo-settings' ),
				'option_name'           => 'acafs_enable_wpbakery_contact_capture',
				'plugin_file'           => 'acafs-wpbakery-contact-form-for-flamingo/acafs-wpbakery-contact-form-for-flamingo.php',
				'product_url'           => 'https://ambercouch.co.uk/wpbakery-contact-form-integration-for-flamingo-database/',
				'headline'              => __( 'Save WPBakery contact form submissions to Flamingo', 'ac-advanced-flamingo-settings' ),
				'description'           => __( 'Capture WPBakery contact form submissions directly into Flamingo Inbound Messages.', 'ac-advanced-flamingo-settings' ),
				'active_checkbox_label' => __( 'Enable WPBakery Contact Form → Flamingo capture', 'ac-advanced-flamingo-settings' ),
				'inactive_message'      => __( 'Activate the plugin to enable WPBakery → Flamingo capture.', 'ac-advanced-flamingo-settings' ),
				'price'                 => '£29.95',
				'builder_name'          => __( 'WPBakery', 'ac-advanced-flamingo-settings' ),
			),
			array(
				'key'                   => 'enfold',
				'label'                 => __( 'Enfold Contact Form', 'ac-advanced-flamingo-settings' ),
				'option_name'           => 'acafs_enable_enfold_contact_capture',
				'plugin_file'           => 'acafs-enfold-contact-form-for-flamingo/acafs-enfold-contact-form-for-flamingo.php',
				'product_url'           => 'https://ambercouch.co.uk/enfold-contact-form-integration-for-flamingo-database/',
				'headline'              => __( 'Save Enfold contact form submissions to Flamingo', 'ac-advanced-flamingo-settings' ),
				'description'           => __( 'Capture Enfold contact form submissions directly into Flamingo Inbound Messages.', 'ac-advanced-flamingo-settings' ),
				'active_checkbox_label' => __( 'Enable Enfold Contact Form → Flamingo capture', 'ac-advanced-flamingo-settings' ),
				'inactive_message'      => __( 'Activate the plugin to enable Enfold → Flamingo capture.', 'ac-advanced-flamingo-settings' ),
				'price'                 => '£29.95',
				'builder_name'          => __( 'Enfold', 'ac-advanced-flamingo-settings' ),
			),
			array(
				'key'                   => 'beaver_builder',
				'label'                 => __( 'Beaver Builder Contact Form', 'ac-advanced-flamingo-settings' ),
				'option_name'           => 'acafs_enable_beaver_builder_contact_capture',
				'plugin_file'           => 'acafs-beaver-builder-contact-form-for-flamingo/acafs-beaver-builder-contact-form-for-flamingo.php',
				'product_url'           => 'https://ambercouch.co.uk/beaver-builder-contact-form-integration-for-flamingo-database/',
				'headline'              => __( 'Save Beaver Builder contact form submissions to Flamingo', 'ac-advanced-flamingo-settings' ),
				'description'           => __( 'Capture Beaver Builder contact form submissions directly into Flamingo Inbound Messages.', 'ac-advanced-flamingo-settings' ),
				'active_checkbox_label' => __( 'Enable Beaver Builder Contact Form → Flamingo capture', 'ac-advanced-flamingo-settings' ),
				'inactive_message'      => __( 'Activate the plugin to enable Beaver Builder → Flamingo capture.', 'ac-advanced-flamingo-settings' ),
				'price'                 => '£29.95',
				'builder_name'          => __( 'Beaver Builder', 'ac-advanced-flamingo-settings' ),
			),
		);
	}

	public function acafs_is_integration_installed( $plugin_file ) {
		return file_exists( WP_PLUGIN_DIR . '/' . ltrim( $plugin_file, '/' ) );
	}

	public function acafs_is_integration_active( $plugin_file ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin_file );
	}

	public function acafs_get_integration_state( $plugin_file ) {
		if ( ! $this->acafs_is_integration_installed( $plugin_file ) ) {
			return 'not_installed';
		}

		if ( $this->acafs_is_integration_active( $plugin_file ) ) {
			return 'active';
		}

		return 'inactive';
	}

	public function acafs_render_integration_field( $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			echo esc_html__( 'You do not have permission to manage this setting.', 'ac-advanced-flamingo-settings' );
			return;
		}

		$config = isset( $args['config'] ) ? $args['config'] : array();
		if ( empty( $config['plugin_file'] ) ) {
			return;
		}

		$state = $this->acafs_get_integration_state( $config['plugin_file'] );

		if ( 'not_installed' === $state ) {
			$this->acafs_render_integration_upgrade_card( $config );
			return;
		}

		if ( 'inactive' === $state ) {
			$this->acafs_render_integration_activation_card( $config );
			return;
		}

		$this->acafs_render_integration_checkbox( $config );
	}

	public function acafs_render_integration_upgrade_card( $config ) {
		?>
		<div class="postbox" style="max-width:860px;">
			<div class="inside">
				<h3 style="margin-top:0;display:flex;align-items:center;gap:8px;">
					<span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
					<?php echo esc_html( $config['headline'] ); ?>
				</h3>
				<p class="description" style="margin-top:8px;">
					<?php echo esc_html( $config['description'] ); ?>
				</p>
				<ul style="list-style:disc;padding-left:20px;margin:12px 0;">
					<li><?php esc_html_e( 'Store submissions in the WordPress database', 'ac-advanced-flamingo-settings' ); ?></li>
					<li><?php esc_html_e( 'Prevent lost email enquiries', 'ac-advanced-flamingo-settings' ); ?></li>
					<li><?php esc_html_e( 'View submissions inside Flamingo', 'ac-advanced-flamingo-settings' ); ?></li>
					<li><?php esc_html_e( 'Works with existing forms', 'ac-advanced-flamingo-settings' ); ?></li>
					<li><?php esc_html_e( 'No need to rebuild forms in Contact Form 7', 'ac-advanced-flamingo-settings' ); ?></li>
				</ul>
				<p>
					<span class="dashicons dashicons-tag" aria-hidden="true"></span>
					<strong><?php echo esc_html( $config['price'] ); ?></strong>
				</p>
				<p style="margin:12px 0;">
					<a href="<?php echo esc_url( $config['product_url'] ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Get the Add-on', 'ac-advanced-flamingo-settings' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	public function acafs_render_integration_activation_card( $config ) {
		$plugins_url = admin_url( 'plugins.php' );
		?>
		<div class="postbox" style="max-width:860px;">
			<div class="inside">
				<h3 style="margin-top:0;display:flex;align-items:center;gap:8px;">
					<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
					<?php
					/* translators: %s: builder name, such as Divi or WPBakery. */
					echo esc_html( sprintf( __( 'AC %s Contact Form for Flamingo is installed', 'ac-advanced-flamingo-settings' ), $config['builder_name'] ) );
					?>
				</h3>
				<p class="description">
					<?php echo esc_html( $config['inactive_message'] ); ?>
				</p>
				<p style="margin-bottom:4px;">
					<a href="<?php echo esc_url( $plugins_url ); ?>" class="button button-primary">
						<?php esc_html_e( 'Activate Plugin', 'ac-advanced-flamingo-settings' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	public function acafs_render_integration_checkbox( $config ) {
		$enabled = (bool) get_option( $config['option_name'], false );
		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( $config['option_name'] ) . '" value="1" ' . checked( 1, $enabled, false ) . '> ';
		echo esc_html( $config['active_checkbox_label'] );
		echo '</label>';
		echo '<p class="description">' . esc_html( $config['description'] ) . '</p>';
	}

}
