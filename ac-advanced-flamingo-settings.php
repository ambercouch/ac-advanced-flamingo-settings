<?php

/**
 * Plugin Name:       AC Advanced Flamingo Settings
 * Plugin URI:        https://ambercouch.co.uk/plugins/ac-advanced-flamingo-settings/
 * Description:       Enhances and extends the functionality of the CF7 Flamingo plugin by adding advanced settings and customization options for better contact form data management.
 * Version:           1.0.0
 * Author:            AmberCouch
 * Author URI:        https://ambercouch.co.uk/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ac-advanced-flamingo-settings
 * Domain Path:       /languages/
 */

defined('ABSPATH') or die('You do not have the required permissions');

// Define plugin constants globally for accessibility
if (!defined('ACAFS_VERSION')) define('ACAFS_VERSION', '0.0.3');
if (!defined('ACAFS_PLUGIN')) define('ACAFS_PLUGIN', __FILE__);
if (!defined('ACAFS_PREFIX')) define('ACAFS_PREFIX', 'acafs_');

define('ACAFS_PLUGIN_BASENAME', plugin_basename(ACAFS_PLUGIN));
define('ACAFS_PLUGIN_NAME', trim(dirname(ACAFS_PLUGIN_BASENAME), '/'));

define('ACAFS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACAFS_PLUGIN_LIB_DIR', ACAFS_PLUGIN_DIR . 'lib/');
define('ACAFS_PLUGIN_TEMPLATE_DIR', ACAFS_PLUGIN_DIR . 'templates/');

define('ACAFS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ACAFS_PLUGIN_ASSETS_URL', ACAFS_PLUGIN_URL . 'assets/');


class ACAFS_Plugin {

    /**
     * Constructor - Initializes the plugin.
     */
    public function __construct() {

        // Check if Flamingo is installed and activated
        add_action('admin_init', array($this, 'acafs_check_flamingo_dependency'));

        // Register activation and deactivation hooks
        register_activation_hook(ACAFS_PLUGIN, [$this, 'acafs_activate']);
        register_deactivation_hook(ACAFS_PLUGIN, [$this, 'acafs_deactivate']);

        // Load settings and admin menu
        add_action('admin_menu', [$this, 'acafs_register_settings_page']);
        add_action('admin_init', [$this, 'acafs_register_plugin_settings']);

        add_action('admin_init', array($this, 'acafs_list_tables'));
        add_action('admin_head', array($this, 'acafs_admin_styles'));

    }

    public function acafs_check_flamingo_dependency() {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        if (!file_exists(WP_PLUGIN_DIR . '/flamingo/flamingo.php')) {
            add_action('admin_notices', array($this, 'acafs_flamingo_missing_notice'));
            return;
        }

        if (!is_plugin_active('flamingo/flamingo.php')) {
            add_action('admin_notices', array($this, 'acafs_flamingo_inactive_notice'));
        }
    }

    /**
     * Show notice if Flamingo is missing
     */
    public function acafs_flamingo_missing_notice() {
        $install_url = admin_url('plugin-install.php?s=flamingo&tab=search&type=term');
        echo '<div class="notice notice-error">
            <p><strong>AC Advanced Flamingo Settings requires the Flamingo plugin to function.</strong></p>
            <p>Flamingo is not installed. You can search for it in the WordPress Plugin Repository and install it manually.</p>
            <p><a href="'. esc_url($install_url) .'" >Search for Flamingo in the Plugin Repository</a></p>
          </div>';
    }

    /**
     * Show notice if Flamingo is inactive
     */
    public function acafs_flamingo_inactive_notice() {
        $activate_url = wp_nonce_url(admin_url('plugins.php?action=activate&plugin=flamingo/flamingo.php'), 'activate-plugin_flamingo/flamingo.php');
        echo '<div class="notice notice-warning">
            <p>AC Advanced Flamingo Settings requires <strong>Flamingo</strong> to be activated.</p>
            <p><a href="'. esc_url($activate_url) .'" class="button button-primary">Activate Flamingo</a></p>
          </div>';
    }

    /**
     * Add a custom column to display submission details.
     */
    public function acafs_list_tables() {
        add_filter('manage_flamingo_inbound_posts_columns', array($this, 'acafs_add_columns'));
        add_action('manage_flamingo_inbound_posts_custom_column', array($this, 'acafs_render_custom_column'), 10, 2);
    }

    /**
     * Add "Submission Details" column.
     */
    public function acafs_add_columns($columns) {
        $columns['submission_details'] = __('Submission Details', 'acafs');
        return $columns;
    }

    public function acafs_render_custom_column($column, $post_id) {
        if ($column !== 'submission_details') {
            return;
        }

        // Get selected fields from settings (default to "your-message" if none are chosen)
        $selected_fields = get_option('acafs_display_fields', ['your-message']);

        // Get all post meta data
        $all_meta = get_post_meta($post_id);

        $submission_data = [];
        foreach ($all_meta as $key => $value) {
            if (strpos($key, '_field_') === 0 && !empty($value[0])) {
                $clean_key = str_replace('_field_', '', $key);

                // Only include fields that the user selected
                if (in_array($clean_key, $selected_fields)) {
                    $unserialized_value = maybe_unserialize($value[0]);
                    if (is_array($unserialized_value)) {
                        $unserialized_value = implode(', ', $unserialized_value);
                    }
                    $submission_data[$clean_key] = $unserialized_value;
                }
            }
        }

        // If no relevant data exists, show a message
        if (empty($submission_data)) {
            echo '<em>No selected submission details available.</em>';
            return;
        }

        // Display submission data
        echo '<ul style="margin:0; padding:0; list-style:none;">';
        foreach ($submission_data as $field => $value) {
            echo '<li><strong>' . esc_html(ucwords(str_replace('_', ' ', $field))) . ':</strong> ' . esc_html($value) . '</li>';
        }
        echo '</ul>';
    }




    /**
     * Runs on plugin activation.
     */
    public function acafs_activate() {
        add_option('acafs_settings', []);
    }

    /**
     * Runs on plugin deactivation.
     */
    public function acafs_deactivate() {
        // Optional cleanup actions
    }

    /**
     * Register the settings page under "Settings".
     */
    public function acafs_register_settings_page() {
        add_options_page(
            'AC Flamingo Settings',
            'AC Flamingo',
            'manage_options',
            'acafs-settings',
            array($this, 'acafs_render_settings_page')
        );
    }


    /**
     * Render the settings page.
     */
    public function acafs_render_settings_page() {
        ?>
      <div class="wrap">
        <h1>AC Advanced Flamingo Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('acafs_settings_group');
            do_settings_sections('acafs-settings');
            submit_button();
            ?>
        </form>
      </div>
        <?php
    }


    /**
     * Register plugin settings with section descriptions.
     */
    public function acafs_register_plugin_settings() {
        register_setting('acafs_settings_group', 'acafs_display_fields');

        add_settings_section(
            'acafs_main_section',
            'Customize Submission Details Column',
            function() {
                echo '<p>Control which form fields are displayed in the Flamingo "Inbound Messages" table.</p>';
            },
            'acafs-settings'
        );

        add_settings_field(
            'acafs_display_fields',
            'Fields to Display',
            array($this, 'acafs_display_fields_callback'),
            'acafs-settings',
            'acafs_main_section'
        );
    }


    /**
     * Callback function to display checkboxes with improved UI.
     */
    public function acafs_display_fields_callback() {
        $saved_fields = get_option('acafs_display_fields', ['your-message']); // Default to "your-message"
        $all_meta_keys = $this->acafs_get_all_flamingo_fields();

        if (empty($all_meta_keys)) {
            echo '<p><em>No submission data available yet.</em></p>';
            return;
        }

        echo '<p><strong>Select which fields to display in the "Submission Details" column of Flamingo Inbound Messages.</strong></p>';

        echo '<div class="acafs-field-options" style="display:flex; flex-wrap:wrap; gap:20px; padding-top:20px">';

        foreach ($all_meta_keys as $key) {
            $checked = in_array($key, $saved_fields) ? 'checked' : '';

            echo '<label style="flex: 1 1 30%; display:block; margin-bottom: 10px;">';
            echo '<input type="checkbox" name="acafs_display_fields[]" value="' . esc_attr($key) . '" ' . $checked . '> ';
            echo esc_html(ucwords(str_replace('_', ' ', $key)));
            echo '</label>';
        }

        echo '</div>';
    }


    /**
     * Retrieve all Flamingo form field names based on stored messages.
     */
    private function acafs_get_all_flamingo_fields() {
        global $wpdb;

        $meta_keys = $wpdb->get_col("
        SELECT DISTINCT meta_key FROM {$wpdb->postmeta}
        WHERE meta_key LIKE '_field_%'
    ");

        return array_map(function($key) {
            return str_replace('_field_', '', $key);
        }, $meta_keys);
    }

    /**
     * Enqueue admin styles for the settings page.
     */
    public function acafs_admin_styles($hook) {
        if ($hook !== 'settings_page_acafs-settings') {
            return;
        }

        echo '<style>
        .acafs-field-options label {
            font-size: 14px;
            cursor: pointer;
        }
        .acafs-field-options input {
            margin-right: 8px;
        }
        .acafs-field-options {
            padding: 10px;
            border: 1px solid #ddd;
            background: #f9f9f9;
            border-radius: 5px;
        }
    </style>';
    }


}

// Initialize the plugin
if (class_exists('ACAFS_Plugin')) {
    new ACAFS_Plugin();
}
