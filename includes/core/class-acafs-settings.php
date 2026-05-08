<?php
defined( 'ABSPATH' ) || exit;

class ACAFS_Settings {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'acafs_register_plugin_settings' ) );
		add_action( 'admin_init', array( $this, 'acafs_handle_dismiss_integrations_notice' ) );
		add_action( 'admin_notices', array( $this, 'acafs_maybe_render_integrations_admin_notice' ) );
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
			<?php $this->acafs_render_settings_page_integrations_cta(); ?>
		</div>
		<?php
	}

	/**
	 * Get the integrations admin page URL.
	 *
	 * @return string
	 */
	public function acafs_get_integrations_page_url() {
		return add_query_arg(
			array(
				'page' => 'acafs-integrations',
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Check whether the current user has dismissed the integrations notice.
	 *
	 * @return bool
	 */
	public function acafs_has_dismissed_integrations_notice() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		return (bool) get_user_meta( $user_id, 'acafs_integrations_notice_dismissed', true );
	}

	/**
	 * Dismiss integrations notice for the current user.
	 *
	 * @return void
	 */
	public function acafs_handle_dismiss_integrations_notice() {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_GET['acafs_action'] ) ? sanitize_text_field( wp_unslash( $_GET['acafs_action'] ) ) : '';
		if ( 'dismiss_integrations_notice' !== $action ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'acafs_dismiss_integrations_notice' ) ) {
			return;
		}

		update_user_meta( get_current_user_id(), 'acafs_integrations_notice_dismissed', 1 );

		$redirect_url = isset( $_GET['acafs_redirect'] ) ? sanitize_url( wp_unslash( $_GET['acafs_redirect'] ) ) : admin_url();
		$redirect_url = wp_validate_redirect( $redirect_url, admin_url() );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render notice promoting integrations when appropriate.
	 *
	 * @return void
	 */
	public function acafs_maybe_render_integrations_admin_notice() {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! is_admin() || ! current_user_can( 'manage_options' ) || $this->acafs_has_dismissed_integrations_notice() ) {
			return;
		}

		$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( isset( $current_screen->id ) && 'flamingo_page_acafs-integrations' === $current_screen->id ) {
			return;
		}

		$grouped_integrations = $this->acafs_get_integrations_grouped_by_state();
		$detected_integration = $this->acafs_get_detected_supported_integration();
		$integrations_url     = $this->acafs_get_integrations_page_url();
		$dismiss_url          = wp_nonce_url(
			add_query_arg(
				array(
					'acafs_action'   => 'dismiss_integrations_notice',
					'acafs_redirect' => rawurlencode( remove_query_arg( array( 'acafs_action', '_wpnonce', 'acafs_redirect' ) ) ),
				),
				admin_url( 'admin.php' )
			),
			'acafs_dismiss_integrations_notice'
		);

		$notice_map = array(
			'divi' => array(
				'title'   => __( 'Using Divi forms?', 'ac-advanced-flamingo-settings' ),
				'message' => __( 'Save Divi Contact Form submissions directly into Flamingo Inbound Messages with the Divi integration add-on.', 'ac-advanced-flamingo-settings' ),
				'button'  => __( 'View Divi Integration', 'ac-advanced-flamingo-settings' ),
			),
			'wpbakery' => array(
				'title'   => __( 'Using WPBakery forms?', 'ac-advanced-flamingo-settings' ),
				'message' => __( 'Save WPBakery contact form submissions directly into Flamingo Inbound Messages with the WPBakery integration add-on.', 'ac-advanced-flamingo-settings' ),
				'button'  => __( 'View WPBakery Integration', 'ac-advanced-flamingo-settings' ),
			),
			'beaver_builder' => array(
				'title'   => __( 'Using Beaver Builder forms?', 'ac-advanced-flamingo-settings' ),
				'message' => __( 'Save Beaver Builder form submissions directly into Flamingo Inbound Messages with the Beaver Builder integration add-on.', 'ac-advanced-flamingo-settings' ),
				'button'  => __( 'View Beaver Builder Integration', 'ac-advanced-flamingo-settings' ),
			),
			'enfold' => array(
				'title'   => __( 'Using Enfold forms?', 'ac-advanced-flamingo-settings' ),
				'message' => __( 'Save Enfold contact form submissions directly into Flamingo Inbound Messages with the Enfold integration add-on.', 'ac-advanced-flamingo-settings' ),
				'button'  => __( 'View Enfold Integration', 'ac-advanced-flamingo-settings' ),
			),
		);

		$active_notice_map = array(
			'divi' => array(
				'title'   => __( 'Divi integration is active', 'ac-advanced-flamingo-settings' ),
				'message' => __( 'Divi Contact Form submissions can now be saved in Flamingo Inbound Messages alongside Contact Form 7 submissions.', 'ac-advanced-flamingo-settings' ),
				'button'  => __( 'View Inbound Messages', 'ac-advanced-flamingo-settings' ),
				'url'     => add_query_arg( array( 'page' => 'flamingo_inbound' ), admin_url( 'admin.php' ) ),
				'class'   => 'notice-success',
			),
			'wpbakery' => array(
				'title'   => __( 'WPBakery integration is active', 'ac-advanced-flamingo-settings' ),
				'message' => __( 'WPBakery form submissions can now be saved in Flamingo Inbound Messages alongside Contact Form 7 submissions.', 'ac-advanced-flamingo-settings' ),
				'button'  => __( 'View Inbound Messages', 'ac-advanced-flamingo-settings' ),
				'url'     => add_query_arg( array( 'page' => 'flamingo_inbound' ), admin_url( 'admin.php' ) ),
				'class'   => 'notice-success',
			),
			'beaver_builder' => array(
				'title'   => __( 'Beaver Builder integration is active', 'ac-advanced-flamingo-settings' ),
				'message' => __( 'Beaver Builder form submissions can now be saved in Flamingo Inbound Messages alongside Contact Form 7 submissions.', 'ac-advanced-flamingo-settings' ),
				'button'  => __( 'View Inbound Messages', 'ac-advanced-flamingo-settings' ),
				'url'     => add_query_arg( array( 'page' => 'flamingo_inbound' ), admin_url( 'admin.php' ) ),
				'class'   => 'notice-success',
			),
			'enfold' => array(
				'title'   => __( 'Enfold integration is active', 'ac-advanced-flamingo-settings' ),
				'message' => __( 'Enfold form submissions can now be saved in Flamingo Inbound Messages alongside Contact Form 7 submissions.', 'ac-advanced-flamingo-settings' ),
				'button'  => __( 'View Inbound Messages', 'ac-advanced-flamingo-settings' ),
				'url'     => add_query_arg( array( 'page' => 'flamingo_inbound' ), admin_url( 'admin.php' ) ),
				'class'   => 'notice-success',
			),
		);

		$notice_data = array(
			'title'   => __( 'Extend Flamingo with form integrations', 'ac-advanced-flamingo-settings' ),
			'message' => __( 'AC Advanced Flamingo Settings can connect Flamingo with additional form builders and themes, helping you store more submissions inside WordPress.', 'ac-advanced-flamingo-settings' ),
			'button'  => __( 'View Integrations', 'ac-advanced-flamingo-settings' ),
			'url'     => $integrations_url,
			'class'   => 'notice-info',
		);

		if ( isset( $notice_map[ $detected_integration ] ) ) {
			$notice_data['title']   = $notice_map[ $detected_integration ]['title'];
			$notice_data['message'] = $notice_map[ $detected_integration ]['message'];
			$notice_data['button']  = $notice_map[ $detected_integration ]['button'];
		}

		$detected_config = $this->acafs_get_integration_config_by_key( $detected_integration );
		if ( ! empty( $detected_config ) && $this->acafs_is_integration_active( $detected_config['plugin_file'] ) && isset( $active_notice_map[ $detected_integration ] ) ) {
			$notice_data = $active_notice_map[ $detected_integration ];
		} elseif ( empty( $detected_integration ) && empty( $grouped_integrations['available'] ) ) {
			return;
		}
		?>
		<div class="notice <?php echo esc_attr( $notice_data['class'] ); ?>">
			<p><strong><?php echo esc_html( $notice_data['title'] ); ?></strong></p>
			<p><?php echo esc_html( $notice_data['message'] ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $notice_data['url'] ); ?>">
					<?php echo esc_html( $notice_data['button'] ); ?>
				</a>
				<a class="button button-secondary" href="<?php echo esc_url( $dismiss_url ); ?>">
					<?php esc_html_e( 'Dismiss', 'ac-advanced-flamingo-settings' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Detect which supported builder/theme is in use.
	 *
	 * @return string
	 */
	public function acafs_get_detected_supported_integration() {
		if ( $this->acafs_is_theme_match( array( 'divi' ) ) ) {
			return 'divi';
		}

		if ( $this->acafs_is_wpbakery_detected() ) {
			return 'wpbakery';
		}

		if ( $this->acafs_is_beaver_builder_detected() ) {
			return 'beaver_builder';
		}

		if ( $this->acafs_is_theme_match( array( 'enfold', 'avia' ) ) ) {
			return 'enfold';
		}

		return '';
	}

	/**
	 * Get a single integration config by key.
	 *
	 * @param string $key Integration key.
	 * @return array<string,string>
	 */
	public function acafs_get_integration_config_by_key( $key ) {
		foreach ( $this->acafs_get_integration_configs() as $integration ) {
			if ( isset( $integration['key'] ) && $integration['key'] === $key ) {
				return $integration;
			}
		}

		return array();
	}

	public function acafs_is_theme_match( $needles ) {
		$theme = wp_get_theme();
		$haystacks = array(
			strtolower( (string) $theme->get( 'Name' ) ),
			strtolower( (string) $theme->get_template() ),
			strtolower( (string) $theme->get_stylesheet() ),
		);

		$parent_theme = $theme->parent();
		if ( $parent_theme instanceof WP_Theme ) {
			$haystacks[] = strtolower( (string) $parent_theme->get( 'Name' ) );
			$haystacks[] = strtolower( (string) $parent_theme->get_template() );
			$haystacks[] = strtolower( (string) $parent_theme->get_stylesheet() );
		}

		foreach ( $needles as $needle ) {
			$needle = strtolower( (string) $needle );
			foreach ( $haystacks as $haystack ) {
				if ( false !== strpos( $haystack, $needle ) ) {
					return true;
				}
			}
		}

		return false;
	}

	public function acafs_is_beaver_builder_detected() {
		if ( class_exists( 'FLBuilder' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'bb-plugin/fl-builder.php' ) || is_plugin_active( 'beaver-builder-lite-version/fl-builder.php' );
	}

	public function acafs_is_wpbakery_detected() {
		if ( class_exists( 'Vc_Manager' ) || class_exists( 'WPBakeryVisualComposerAbstract' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'js_composer/js_composer.php' );
	}

	/**
	 * Render CTA card linking to integrations page on settings screen.
	 *
	 * @return void
	 */
	public function acafs_render_settings_page_integrations_cta() {
		?>
		<div class="postbox" style="max-width:860px;margin-top:24px;">
			<div class="inside">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Need more form integrations?', 'ac-advanced-flamingo-settings' ); ?></h2>
				<p><?php esc_html_e( 'Explore available ACAFS add-ons for Divi, WPBakery, Enfold and Beaver Builder, and store more contact form submissions in Flamingo.', 'ac-advanced-flamingo-settings' ); ?></p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $this->acafs_get_integrations_page_url() ); ?>">
						<?php esc_html_e( 'Configure Integrations', 'ac-advanced-flamingo-settings' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the integrations settings page
	 */
	public function acafs_render_integrations_page() {
		$grouped_integrations = $this->acafs_get_integrations_grouped_by_state();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Flamingo Integrations', 'ac-advanced-flamingo-settings' ); ?></h1>
			<p><?php esc_html_e( 'Enable optional integrations that capture submissions into Flamingo.', 'ac-advanced-flamingo-settings' ); ?></p>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'acafs_settings_group' );
				$this->acafs_render_integrations_section(
					__( 'Activated Integrations', 'ac-advanced-flamingo-settings' ),
					__( 'These integrations are active and can be enabled or disabled below.', 'ac-advanced-flamingo-settings' ),
					$grouped_integrations['active'],
					'active'
				);
				$this->acafs_render_integrations_section(
					__( 'Installed Integrations', 'ac-advanced-flamingo-settings' ),
					__( 'These add-ons are installed but must be activated before capture can be enabled.', 'ac-advanced-flamingo-settings' ),
					$grouped_integrations['installed'],
					'installed'
				);
				$this->acafs_render_integrations_section(
					__( 'Available Integrations', 'ac-advanced-flamingo-settings' ),
					__( 'Install a premium add-on to capture submissions from these builders.', 'ac-advanced-flamingo-settings' ),
					$grouped_integrations['available'],
					'available'
				);
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
			return 'available';
		}

		if ( $this->acafs_is_integration_active( $plugin_file ) ) {
			return 'active';
		}

		return 'installed';
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

		if ( 'available' === $state ) {
			$this->acafs_render_integration_upgrade_card( $config );
			return;
		}

		if ( 'installed' === $state ) {
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

	/**
	 * Group integration configs by detected state.
	 *
	 * @return array<string,array<int,array<string,string>>>
	 */
	public function acafs_get_integrations_grouped_by_state() {
		$grouped = array(
			'active'    => array(),
			'installed' => array(),
			'available' => array(),
		);

		foreach ( $this->acafs_get_integration_configs() as $integration ) {
			$state = $this->acafs_get_integration_state( $integration['plugin_file'] );
			if ( isset( $grouped[ $state ] ) ) {
				$grouped[ $state ][] = $integration;
			}
		}

		return $grouped;
	}

	/**
	 * Render integrations section for a specific state.
	 *
	 * @param string                                    $title        Section title.
	 * @param string                                    $description  Section description.
	 * @param array<int,array<string,string>>           $integrations Integrations for this section.
	 * @param string                                    $state        Integration state.
	 * @return void
	 */
	public function acafs_render_integrations_section( $title, $description, $integrations, $state ) {
		if ( empty( $integrations ) ) {
			return;
		}
		?>
		<h2 style="margin-top:24px;"><?php echo esc_html( $title ); ?></h2>
		<p><?php echo esc_html( $description ); ?></p>
		<?php
		foreach ( $integrations as $integration ) {
			$this->acafs_render_integration_card( $integration, $state );
		}
	}

	/**
	 * Render an integration card by state.
	 *
	 * @param array<string,string> $config Integration config.
	 * @param string               $state  Integration state.
	 * @return void
	 */
	public function acafs_render_integration_card( $config, $state ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			echo esc_html__( 'You do not have permission to manage this setting.', 'ac-advanced-flamingo-settings' );
			return;
		}

		if ( 'available' === $state ) {
			$this->acafs_render_integration_upgrade_card( $config );
			return;
		}

		if ( 'installed' === $state ) {
			$this->acafs_render_integration_activation_card( $config );
			return;
		}

		?>
		<div class="postbox" style="max-width:860px;">
			<div class="inside">
				<h3 style="margin-top:0;"><?php echo esc_html( $config['label'] ); ?></h3>
				<?php $this->acafs_render_integration_checkbox( $config ); ?>
			</div>
		</div>
		<?php
	}

}
