<?php
/**
 * Keep enabled Divi capture visible during the standalone integration migration.
 *
 * @package AC_Advanced_Flamingo_Settings
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reports capture readiness without installing plugins or changing user intent.
 */
class ACAFS_Divi_Migration_Safety {

	/** Standalone integration entrypoint. */
	const PLUGIN = 'acafs-divi-contact-form-for-flamingo/acafs-divi-contact-form-for-flamingo.php';

	/** Saved capture preference shared with the standalone integration. */
	const OPTION = 'acafs_enable_divi_contact_capture';

	/** Last reported capture failure; contains only a known error code. */
	const FAILURE_OPTION = 'acafs_divi_capture_failure';

	/** Register observers; evaluate readiness only after all plugins initialize. */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'acafs_divi_flamingo_capture_error', array( $this, 'record_failure' ) );
		add_action( 'set_object_terms', array( $this, 'maybe_clear_failure' ), 10, 4 );
	}

	/**
	 * Get the current reason capture needs attention, or an empty string.
	 *
	 * A registered handler is readiness evidence, not a delivery guarantee.
	 *
	 * @return string Capture status.
	 */
	public function get_status() {
		if ( ! get_option( self::OPTION, false ) ) {
			return '';
		}
		if ( ! $this->is_installed() ) {
			return 'missing';
		}
		if ( ! $this->is_active() ) {
			return 'inactive';
		}
		if ( ! class_exists( 'Flamingo_Inbound_Message' ) || ! taxonomy_exists( 'flamingo_inbound_channel' ) ) {
			return 'flamingo';
		}
		$theme = wp_get_theme();
		if ( 'divi' !== strtolower( $theme->get_template() ) && 'divi' !== strtolower( $theme->get_stylesheet() )
			&& false === strpos( strtolower( $theme->get( 'Name' ) ), 'divi' ) ) {
			return 'theme';
		}
		if ( ! $this->has_capture_handler() ) {
			return 'handler';
		}
		if ( get_option( self::FAILURE_OPTION, false ) ) {
			return 'capture_error';
		}
		return '';
	}

	/**
	 * Check for the standalone plugin file.
	 *
	 * @return bool Whether the integration is installed.
	 */
	protected function is_installed() {
		return file_exists( WP_PLUGIN_DIR . '/' . self::PLUGIN );
	}

	/**
	 * Include network-active plugins in the activation check.
	 *
	 * @return bool Whether the integration is active.
	 */
	protected function is_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( self::PLUGIN );
	}

	/**
	 * Check the actual standalone callback, not merely a loaded class or file.
	 *
	 * @return bool Whether a callable Divi handler accepts the validation context.
	 */
	private function has_capture_handler() {
		global $wp_filter;
		if ( empty( $wp_filter['et_pb_contact_form_submit'] ) ) {
			return false;
		}
		foreach ( $wp_filter['et_pb_contact_form_submit']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$handler = $callback['function'];
				if ( is_array( $handler ) && is_object( $handler[0] ) && 'capture_from_action' === $handler[1]
					&& ( is_a( $handler[0], 'ACAFS_Divi_Flamingo_Integration' ) || is_a( $handler[0], 'ACAFS_Divi_Flamingo_Capture' ) )
					&& is_callable( $handler ) && 3 <= $callback['accepted_args'] ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Persist only safe, known failure codes; never store fields or error data.
	 *
	 * @param mixed $error Capture failure supplied by an integration action.
	 */
	public function record_failure( $error ) {
		if ( get_option( self::OPTION, false ) && is_wp_error( $error )
			&& in_array( $error->get_error_code(), array( 'acafs_divi_message_failed', 'acafs_divi_channel_failed' ), true ) ) {
			update_option( self::FAILURE_OPTION, $error->get_error_code(), false );
		}
	}

	/**
	 * Clear a previous failure only after a Divi message has a persisted channel.
	 *
	 * The standalone creator assigns its child channel after saving all fields.
	 * Its initial root-channel assignment must not hide a storage failure.
	 *
	 * @param int          $object_id Inbound message ID.
	 * @param array|string $terms     Requested terms (unused).
	 * @param array        $tt_ids    Term taxonomy IDs (unused).
	 * @param string       $taxonomy  Taxonomy name.
	 */
	public function maybe_clear_failure( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( 'flamingo_inbound_channel' !== $taxonomy || ! get_option( self::FAILURE_OPTION, false )
			|| 'flamingo_inbound' !== get_post_type( $object_id )
			|| ( ! doing_action( 'et_pb_contact_form_submit' ) && ! doing_action( 'shutdown' ) )
			|| ! $this->has_capture_handler() ) {
			return;
		}
		$stored = wp_get_object_terms( $object_id, $taxonomy );
		if ( is_wp_error( $stored ) || 1 !== count( $stored ) || ! $stored[0]->parent ) {
			return;
		}
		$parent = get_term( $stored[0]->parent, $taxonomy );
		if ( $parent && ! is_wp_error( $parent ) && 'Divi Contact Form' === $parent->name ) {
			delete_option( self::FAILURE_OPTION );
		}
	}

	/** Render an actionable, non-dismissible warning for site administrators. */
	public function render_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status = $this->get_status();
		if ( '' === $status ) {
			return;
		}
		$reasons = array(
			'missing'       => __( 'The AC Divi Contact Form for Flamingo integration is missing. Install it from your trusted plugin download, then activate it on the Plugins screen.', 'ac-advanced-flamingo-settings' ),
			'inactive'      => __( 'The AC Divi Contact Form for Flamingo integration is inactive. Activate it on the Plugins screen.', 'ac-advanced-flamingo-settings' ),
			'flamingo'      => __( 'Flamingo is unavailable. Install or activate Flamingo and resolve any plugin errors.', 'ac-advanced-flamingo-settings' ),
			'theme'         => __( 'A Divi theme is not active. Restore Divi or your Divi child theme if this site still uses Divi contact forms.', 'ac-advanced-flamingo-settings' ),
			'handler'       => __( 'The integration is active but its capture handler is unavailable. Update or reinstall the standalone integration with a compatible version and resolve its dependency notices.', 'ac-advanced-flamingo-settings' ),
			'capture_error' => __( 'The integration reported a message storage or channel assignment failure. Check Flamingo, database write access and the integration diagnostics. If a message was partially saved, repair that record instead of resubmitting it.', 'ac-advanced-flamingo-settings' ),
		);
		?>
		<div class="notice notice-warning acafs-divi-migration-warning">
			<p><strong><?php esc_html_e( 'Divi submission capture needs attention', 'ac-advanced-flamingo-settings' ); ?></strong></p>
			<p><?php esc_html_e( 'Divi capture is still enabled in your saved settings. It now requires the standalone AC Divi Contact Form for Flamingo plugin; Settings no longer provides bundled capture.', 'ac-advanced-flamingo-settings' ); ?></p>
			<p><?php echo esc_html( $reasons[ $status ] ); ?></p>
			<p><?php esc_html_e( 'Keep Divi Contact Form capture enabled under Flamingo Integrations, then submit a test through your website and confirm exactly one correctly populated Flamingo Inbound Message. This warning clears automatically when capture is ready; a reported storage failure clears after a successful capture. Existing messages are retained. If you no longer use Divi forms, explicitly disable Divi capture under Flamingo Integrations.', 'ac-advanced-flamingo-settings' ); ?></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Manage plugins', 'ac-advanced-flamingo-settings' ); ?></a>
				&middot; <a href="<?php echo esc_url( admin_url( 'admin.php?page=acafs-integrations' ) ); ?>"><?php esc_html_e( 'Flamingo Integrations', 'ac-advanced-flamingo-settings' ); ?></a>
			</p>
		</div>
		<?php
	}
}
