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

      register_setting(
          'acafs_settings_group',
          'acafs_enable_divi_contact_capture',
          array(
              'sanitize_callback' => array( $this, 'acafs_sanitize_checkbox' ),
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

		add_settings_field(
			'acafs_enable_divi_contact_capture',
			__( 'Divi Contact Form', 'ac-advanced-flamingo-settings' ),
			array( $this, 'acafs_enable_divi_contact_capture_callback' ),
			'acafs-integrations',
			'acafs_integrations_section'
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
	 * Render the integrations settings page
	 */
	public function acafs_render_integrations_page() {
		$integrations         = $this->acafs_get_integration_configs();
		$grouped_integrations = $this->acafs_get_integrations_grouped_by_state( $integrations );
		$has_active_settings  = ! empty( $grouped_integrations['active'] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Flamingo Integrations', 'ac-advanced-flamingo-settings' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'acafs_settings_group' );
				$this->acafs_render_integrations_section(
					__( 'Activated Integrations', 'ac-advanced-flamingo-settings' ),
					__( 'These integrations are installed and active. Use the controls below to enable or disable capture behaviour.', 'ac-advanced-flamingo-settings' ),
					$grouped_integrations['active'],
					'active'
				);
				$this->acafs_render_integrations_section(
					__( 'Installed Integrations', 'ac-advanced-flamingo-settings' ),
					__( 'These integrations are installed but inactive. Activate the add-on plugin to enable the integration setting.', 'ac-advanced-flamingo-settings' ),
					$grouped_integrations['installed'],
					'installed'
				);
				$this->acafs_render_integrations_section(
					__( 'Available Integrations', 'ac-advanced-flamingo-settings' ),
					__( 'These premium integrations are not installed yet.', 'ac-advanced-flamingo-settings' ),
					$grouped_integrations['available'],
					'available'
				);
				if ( $has_active_settings ) {
					submit_button();
				}
				?>
			</form>
		</div>
		<?php
	}

	private function acafs_get_integration_configs() {
		return array(
			array(
				'slug'          => 'divi',
				'name'          => __( 'Divi', 'ac-advanced-flamingo-settings' ),
				'option_name'   => 'acafs_enable_divi_contact_capture',
				'plugin_file'   => 'acafs-divi-integration/acafs-divi-integration.php',
				'price_badge'   => __( '$29', 'ac-advanced-flamingo-settings' ),
				'purchase_url'  => 'https://assetcleanly.com/',
				'active_label'  => __( 'Enable Divi Contact Form → Flamingo capture', 'ac-advanced-flamingo-settings' ),
				'description'   => __( 'Capture Divi Contact Form submissions into Flamingo.', 'ac-advanced-flamingo-settings' ),
			),
			array(
				'slug'         => 'wpbakery',
				'name'         => __( 'WPBakery', 'ac-advanced-flamingo-settings' ),
				'plugin_file'  => 'acafs-wpbakery-integration/acafs-wpbakery-integration.php',
				'price_badge'  => __( '$29', 'ac-advanced-flamingo-settings' ),
				'purchase_url' => 'https://assetcleanly.com/',
			),
			array(
				'slug'         => 'enfold',
				'name'         => __( 'Enfold', 'ac-advanced-flamingo-settings' ),
				'plugin_file'  => 'acafs-enfold-integration/acafs-enfold-integration.php',
				'price_badge'  => __( '$29', 'ac-advanced-flamingo-settings' ),
				'purchase_url' => 'https://assetcleanly.com/',
			),
			array(
				'slug'         => 'beaver-builder',
				'name'         => __( 'Beaver Builder', 'ac-advanced-flamingo-settings' ),
				'plugin_file'  => 'acafs-beaver-builder-integration/acafs-beaver-builder-integration.php',
				'price_badge'  => __( '$29', 'ac-advanced-flamingo-settings' ),
				'purchase_url' => 'https://assetcleanly.com/',
			),
		);
	}

	private function acafs_get_integrations_grouped_by_state( $integrations ) {
		$grouped_integrations = array(
			'active'    => array(),
			'installed' => array(),
			'available' => array(),
		);

		foreach ( $integrations as $integration ) {
			$state                           = $this->acafs_get_integration_state( $integration );
			$grouped_integrations[ $state ][] = $integration;
		}

		return $grouped_integrations;
	}

	private function acafs_get_integration_state( $integration ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = isset( $integration['plugin_file'] ) ? $integration['plugin_file'] : '';

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
			return 'available';
		}

		if ( is_plugin_active( $plugin_file ) ) {
			return 'active';
		}

		return 'installed';
	}

	private function acafs_render_integrations_section( $title, $description, $integrations, $state ) {
		if ( empty( $integrations ) ) {
			return;
		}
		?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<p><?php echo esc_html( $description ); ?></p>
		<div class="acafs-integration-grid">
			<?php
			foreach ( $integrations as $integration ) {
				$this->acafs_render_integration_card( $integration, $state );
			}
			?>
		</div>
		<?php
	}

	private function acafs_render_integration_card( $integration, $state ) {
		$name = isset( $integration['name'] ) ? $integration['name'] : '';
		?>
		<div class="postbox" style="max-width: 720px; margin: 0 0 16px;">
			<div class="inside">
				<h3><?php echo esc_html( $name ); ?></h3>
				<?php if ( 'active' === $state ) : ?>
					<?php if ( current_user_can( 'manage_options' ) && ! empty( $integration['option_name'] ) ) : ?>
						<?php $enabled = (bool) get_option( $integration['option_name'], false ); ?>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $integration['option_name'] ); ?>" value="1" <?php checked( 1, $enabled ); ?>>
							<?php echo esc_html( $integration['active_label'] ); ?>
						</label>
						<p class="description"><?php echo esc_html( $integration['description'] ); ?></p>
					<?php endif; ?>
				<?php elseif ( 'installed' === $state ) : ?>
					<p><?php esc_html_e( 'This add-on is installed but not active yet.', 'ac-advanced-flamingo-settings' ); ?></p>
					<p><a class="button" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Activate Plugin', 'ac-advanced-flamingo-settings' ); ?></a></p>
				<?php else : ?>
					<?php if ( ! empty( $integration['price_badge'] ) ) : ?>
						<p><span class="button button-secondary" disabled><?php echo esc_html( $integration['price_badge'] ); ?></span></p>
					<?php endif; ?>
					<p><a class="button button-primary" href="<?php echo esc_url( $integration['purchase_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get the Add-on', 'ac-advanced-flamingo-settings' ); ?></a></p>
				<?php endif; ?>
			</div>
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
     * Render field: enable Divi Contact Form capture.
     */
    public function acafs_enable_divi_contact_capture_callback() {
        if ( ! current_user_can( 'manage_options' ) ) {
            echo esc_html__( 'You do not have permission to manage this setting.', 'ac-advanced-flamingo-settings' );
            return;
        }

        $enabled = (bool) get_option( 'acafs_enable_divi_contact_capture', false );
        echo '<label>';
        echo '<input type="checkbox" name="acafs_enable_divi_contact_capture" value="1" ' . checked( 1, $enabled, false ) . '> ';
        echo esc_html__( 'Enable Divi Contact Form → Flamingo capture', 'ac-advanced-flamingo-settings' );
        echo '</label>';
    }

}
