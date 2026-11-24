<?php
/**
 * Contextual CLI add-on information on the plugin's own admin screens.
 *
 * @package AC_Advanced_Flamingo_Settings
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render local add-on cards and persist each user's dismissal.
 */
class ACAFS_CLI_Promotion {
	const PRODUCT_URL = 'https://ambercouch.co.uk/ac-advanced-flamingo-cli/';
	const PLUGIN_FILE = 'ac-advanced-flamingo-cli/ac-advanced-flamingo-cli.php';
	const DISMISS_KEY = 'acafs_cli_promotion_dismissed';

	/**
	 * Register page-specific renderers and the authenticated dismissal handler.
	 */
	public function __construct() {
		add_action( 'acafs_after_import_export_page', array( $this, 'acafs_render_promotion' ) );
		add_action( 'acafs_render_developer_addons', array( $this, 'acafs_render_addon' ) );
		add_action( 'admin_post_acafs_dismiss_cli_promotion', array( $this, 'acafs_dismiss_promotion' ) );
	}

	/**
	 * Detect installed, site-active and network-active copies without remote calls.
	 *
	 * @return string Add-on state.
	 */
	public function acafs_get_state() {
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active( self::PLUGIN_FILE ) ) {
			return 'active';
		}
		$plugins = get_plugins();
		return isset( $plugins[ self::PLUGIN_FILE ] ) ? 'installed' : 'available';
	}

	/**
	 * Permanently dismiss for the current user only; no version-based reset.
	 */
	public function acafs_dismiss_promotion() {
		if ( ! current_user_can( 'manage_options' ) || 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'ac-advanced-flamingo-settings' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'acafs_dismiss_cli_promotion' );
		update_user_meta( get_current_user_id(), self::DISMISS_KEY, '1' );
		wp_safe_redirect( admin_url( 'admin.php?page=acafs-message-sync' ) );
		exit;
	}

	/**
	 * Render below the complete import/export interface, outside its forms.
	 */
	public function acafs_render_promotion() {
		if ( ! current_user_can( 'manage_options' ) || get_user_meta( get_current_user_id(), self::DISMISS_KEY, true ) || 'active' === $this->acafs_get_state() ) {
			return;
		}
		$this->acafs_render_card( true );
	}

	/**
	 * Keep the developer add-on discoverable regardless of dismissal or status.
	 */
	public function acafs_render_addon() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'Developer and automation add-ons', 'ac-advanced-flamingo-settings' ); ?></h2>
		<?php
		$this->acafs_render_card( false );
	}

	/**
	 * Render an accessible WordPress card using only bundled Dashicons.
	 *
	 * @param bool $dismissible Whether this is the Message Sync promotion.
	 */
	private function acafs_render_card( $dismissible ) {
		?>
		<section class="postbox acafs-cli-card" aria-labelledby="acafs-cli-heading" style="max-width:860px;clear:both;">
			<div class="inside">
				<?php if ( $dismissible ) : ?>
					<h2 id="acafs-cli-heading"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span> <?php esc_html_e( 'Automate Flamingo with WP-CLI and AI agents', 'ac-advanced-flamingo-settings' ); ?></h2>
				<?php else : ?>
					<h3 id="acafs-cli-heading"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span> <?php esc_html_e( 'AC Advanced Flamingo CLI', 'ac-advanced-flamingo-settings' ); ?></h3>
					<p><strong>
					<?php
					$states = array(
						'active'    => __( 'Installed and active', 'ac-advanced-flamingo-settings' ),
						'installed' => __( 'Installed, inactive', 'ac-advanced-flamingo-settings' ),
						'available' => __( 'Not installed', 'ac-advanced-flamingo-settings' ),
					);
					echo esc_html( $states[ $this->acafs_get_state() ] );
					?>
					</strong></p>
				<?php endif; ?>
				<p><?php esc_html_e( 'Extend your Flamingo import and export functionality with command-line tools designed for developers, agencies and AI-assisted workflows.', 'ac-advanced-flamingo-settings' ); ?></p>
				<ul style="list-style:disc;padding-inline-start:20px;">
					<li><?php esc_html_e( 'Enable AI agents to manage Flamingo imports and exports.', 'ac-advanced-flamingo-settings' ); ?></li>
					<li><?php esc_html_e( 'Preserve contact form submissions during website deployments.', 'ac-advanced-flamingo-settings' ); ?></li>
					<li><?php esc_html_e( 'Automate backups and migrations.', 'ac-advanced-flamingo-settings' ); ?></li>
					<li><?php esc_html_e( 'Integrate Flamingo into your existing development workflows.', 'ac-advanced-flamingo-settings' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'AI-assisted workflows require agents with authorised access to WP-CLI. Use scripts or scheduling tools to automate submission exports and imports; this add-on does not provide automatic two-way synchronisation or full-site backups.', 'ac-advanced-flamingo-settings' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( self::PRODUCT_URL ); ?>"><?php esc_html_e( 'Learn more about Flamingo CLI', 'ac-advanced-flamingo-settings' ); ?></a></p>
				<p class="description"><?php esc_html_e( 'Available as a separate premium add-on.', 'ac-advanced-flamingo-settings' ); ?></p>
				<?php if ( $dismissible ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="acafs_dismiss_cli_promotion">
						<?php wp_nonce_field( 'acafs_dismiss_cli_promotion' ); ?>
						<button type="submit" class="button-link"><?php esc_html_e( 'Dismiss Flamingo CLI promotion permanently', 'ac-advanced-flamingo-settings' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
