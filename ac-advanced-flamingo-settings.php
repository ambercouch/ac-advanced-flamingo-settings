<?php

/**
 * Plugin Name:       AC Advanced Flamingo Settings
 * Description:       Enhances and extends the functionality of the CF7 Flamingo plugin by adding advanced settings and customization options for better contact form data management.
 * Version:           1.0.0
 * Author:            AmberCouch
 * Author URI:        https://ambercouch.co.uk/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ac-advanced-flamingo-settings
 */

defined('ABSPATH') or die('You do not have the required permissions');

// Define plugin constants globally for accessibility
if (!defined('ACAFS_VERSION')) define('ACAFS_VERSION', '1.0.0');
if (!defined('ACAFS_PLUGIN')) define('ACAFS_PLUGIN', __FILE__);
if (!defined('ACAFS_PREFIX')) define('ACAFS_PREFIX', 'acafs_');

define('ACAFS_PLUGIN_BASENAME', plugin_basename(ACAFS_PLUGIN));
define('ACAFS_PLUGIN_NAME', trim(dirname(ACAFS_PLUGIN_BASENAME), '/'));

define('ACAFS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACAFS_PLUGIN_LIB_DIR', plugin_dir_path(__FILE__) . 'lib/');
define('ACAFS_PLUGIN_TEMPLATE_DIR', plugin_dir_path(__FILE__) . 'templates/');

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
        add_action('admin_enqueue_scripts', array($this, 'acafs_admin_styles'));

        add_filter('manage_flamingo_contact_posts_columns', array($this, 'acafs_address_book_columns'));
        add_action('manage_flamingo_contact_posts_custom_column', array($this, 'acafs_render_address_book_column'), 10, 2);

        add_action('admin_menu', array($this, 'acafs_modify_flamingo_menu'), 10);
        add_action('admin_init', array($this, 'acafs_redirect_address_book'), 10);


        add_action('admin_menu', array($this, 'acafs_rename_flamingo_menu'), 10);
        add_action('admin_menu', array($this, 'acafs_set_flamingo_default'), 10);
    }

    /**
     * Runs on plugin activation.
     */
    public function acafs_activate() {
        add_option('acafs_version', ACAFS_VERSION);
    }

    /**
     * Runs on plugin deactivation.
     */
    public function acafs_deactivate() {
        // Optional cleanup actions
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
     * Sanitize checkbox input (1 or 0).
     */
    public function acafs_sanitize_checkbox($input) {
        return ($input === '1') ? '1' : '0';
    }

    /**
     * Sanitize dropdown input.
     */
    public function acafs_sanitize_select($input) {
        $valid_options = ['flamingo_inbound', 'flamingo_address_book'];

        return in_array($input, $valid_options, true) ? $input : 'flamingo_inbound';
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
        $columns['submission_details'] = __('Submission Details', 'ac-advanced-flamingo-settings');
        return $columns;
    }

    public function acafs_render_custom_column($column, $post_id) {
        if ($column !== 'submission_details') {
            return;
        }

        // Get selected fields from settings (default to "your-message" if none are chosen)
        $selected_fields = get_option('acafs_display_fields', ['your-message']);

        // Ensure selected fields is always an array
        if (!is_array($selected_fields)) {
            $selected_fields = ['your-message'];
        }

        // Get all post meta data
        $all_meta = get_post_meta($post_id);

        $submission_data = [];
        foreach ($all_meta as $key => $value) {
            if (strpos($key, '_field_') === 0 && !empty($value[0])) {
                $clean_key = str_replace('_field_', '', $key);

                // Only include fields that the user selected
                if (in_array($clean_key, $selected_fields, true)) {
                    $unserialized_value = maybe_unserialize($value[0]);

                    // Ensure it's properly formatted for safe display
                    if (is_array($unserialized_value)) {
                        $unserialized_value = array_map('sanitize_text_field', $unserialized_value);
                        $unserialized_value = implode(', ', $unserialized_value);
                    } else {
                        $unserialized_value = sanitize_text_field($unserialized_value);
                    }

                    $submission_data[$clean_key] = $unserialized_value;
                }
            }
        }

        // If no relevant data exists, show a message
        if (empty($submission_data)) {
            echo '<em>' . esc_html__('No selected submission details available.', 'ac-advanced-flamingo-settings') . '</em>';
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
     * Register all plugin settings.
     */
    public function acafs_register_plugin_settings() {
        // ✅ Submission Details Settings (Checkboxes)
        register_setting('acafs_settings_group', 'acafs_display_fields', array(
            'sanitize_callback' => array($this, 'acafs_sanitize_display_fields')
        ));

        add_settings_section(
            'acafs_submission_section',
            'Submission Details Customization',
            function() {
                echo '<p>Select which form fields should appear in the "Submission Details" column of Flamingo Inbound Messages.</p>';
            },
            'acafs-settings'
        );

        add_settings_field(
            'acafs_display_fields',
            'Fields to Display',
            array($this, 'acafs_display_fields_callback'),
            'acafs-settings',
            'acafs_submission_section'
        );

        //  Flamingo Menu Customization Settings
        register_setting('acafs_settings_group', 'acafs_disable_address_book', array(
            'sanitize_callback' => array($this, 'acafs_sanitize_checkbox')
        ));

        register_setting('acafs_settings_group', 'acafs_default_flamingo_page', array(
            'sanitize_callback' => array($this, 'acafs_sanitize_select')
        ));

        register_setting('acafs_settings_group', 'acafs_rename_flamingo', array(
            'sanitize_callback' => array($this, 'acafs_sanitize_menu_name')
        ));

        add_settings_section(
            'acafs_menu_settings_section',
            'Flamingo Menu Customization',
            function() {
                echo '<p>Customize the Flamingo admin menu behavior.</p>';
            },
            'acafs-settings'
        );

        add_settings_field(
            'acafs_disable_address_book',
            'Disable Address Book',
            array($this, 'acafs_disable_address_book_callback'),
            'acafs-settings',
            'acafs_menu_settings_section'
        );

        add_settings_field(
            'acafs_rename_flamingo',
            'Rename Flamingo Menu',
            array($this, 'acafs_rename_flamingo_callback'),
            'acafs-settings',
            'acafs_menu_settings_section'
        );

        add_settings_field(
            'acafs_default_flamingo_page',
            'Set Default Flamingo Page',
            array($this, 'acafs_default_flamingo_page_callback'),
            'acafs-settings',
            'acafs_menu_settings_section'
        );
    }

    /**
     * Sanitize checkbox values (array of selected fields).
     */
    public function acafs_sanitize_display_fields($input) {
        if (!is_array($input)) {
            return [];
        }

        return array_map('sanitize_text_field', $input);
    }

    /**
     * Ensure Flamingo menu name is not empty when saving.
     */
    public function acafs_sanitize_menu_name($input) {
        $clean_input = sanitize_text_field($input);
        return !empty($clean_input) ? $clean_input : 'Flamingo';
    }

    /**
     * Callback function to display the checkbox for disabling the Address Book.
     */
    public function acafs_disable_address_book_callback() {
        $disabled = (bool) get_option('acafs_disable_address_book', false); // Ensure boolean value

        echo '<label>';
        echo '<input type="checkbox" name="acafs_disable_address_book" value="' . esc_attr(1) . '" ' . checked(1, $disabled, false) . '> ';
        echo esc_html__('Remove Address Book from the menu', 'ac-advanced-flamingo-settings');
        echo '</label>';
    }


    /**
     * Callback function to display the text field for renaming Flamingo in the admin menu.
     */
    public function acafs_rename_flamingo_callback() {
        // Get the option and ensure it is sanitized before use
        $menu_name = sanitize_text_field(get_option('acafs_rename_flamingo', esc_html__('Contact Log', 'ac-advanced-flamingo-settings')));

        echo '<input type="text" name="acafs_rename_flamingo" value="' . esc_attr($menu_name) . '" class="regular-text">';
    }

    /**
     * Dropdown to set the default Flamingo page.
     */
    public function acafs_default_flamingo_page_callback() {
        // Get the option and sanitize it
        $selected = sanitize_text_field(get_option('acafs_default_flamingo_page', 'flamingo_inbound'));
        ?>
      <select name="<?php echo esc_attr('acafs_default_flamingo_page'); ?>">
        <option value="flamingo_inbound" <?php selected($selected, 'flamingo_inbound'); ?>>
            <?php echo esc_html__('Inbound Messages', 'ac-advanced-flamingo-settings'); ?>
        </option>
        <option value="flamingo_address_book" <?php selected($selected, 'flamingo_address_book'); ?>>
            <?php echo esc_html__('Address Book', 'ac-advanced-flamingo-settings'); ?>
        </option>
      </select>
        <?php
    }


    /**
     * Remove the Address Book menu if the option is enabled.
     */
    public function acafs_modify_flamingo_menu() {
        global $submenu;

        // Ensure option is enabled before modifying the menu
        if (!get_option('acafs_disable_address_book', false)) {
            return;
        }

        // Ensure the Flamingo menu exists before trying to modify it
        if (!isset($submenu['flamingo']) || empty($submenu['flamingo'])) {
            return;
        }

        // Loop through the submenu items and remove the Address Book
        foreach ($submenu['flamingo'] as $index => $submenu_item) {
            if (!isset($submenu_item[2])) {
                continue;
            }

            if ($submenu_item[2] === 'flamingo') {
                unset($submenu['flamingo'][$index]);
                break;
            }
        }
    }

    /**
     * Redirect users away from the Address Book if it's disabled.
     */
    public function acafs_redirect_address_book() {
        if (!get_option('acafs_disable_address_book', false)) {
            return;
        }

        if (isset($_GET['page']) && $_GET['page'] === 'flamingo') {
            wp_safe_redirect(admin_url('admin.php?page=flamingo_inbound'));
            exit;
        }
    }

    /**
     * Rename Flamingo menu item based on user settings.
     */
    public function acafs_rename_flamingo_menu() {
        global $menu;

        // Get saved menu name, default to "Flamingo" if empty, and sanitize it
        $new_name = sanitize_text_field(get_option('acafs_rename_flamingo', esc_html__('Flamingo', 'ac-advanced-flamingo-settings')));

        if (empty($new_name)) {
            $new_name = esc_html__('Flamingo', 'ac-advanced-flamingo-settings'); // Ensure default is translatable
        }

        foreach ($menu as &$item) {
            if ($item[2] === 'flamingo') {
                $item[0] = esc_html($new_name);
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
        $default_page = sanitize_text_field(get_option('acafs_default_flamingo_page', 'flamingo_inbound'));

        // Ensure the Flamingo submenu exists
        if (!isset($submenu['flamingo']) || empty($submenu['flamingo'])) {
            return;
        }

        // Move the selected default page to the first position
        foreach ($submenu['flamingo'] as $index => $item) {
            if (isset($item[2]) && $item[2] === $default_page) {
                unset($submenu['flamingo'][$index]);
                array_unshift($submenu['flamingo'], $item);
                break;
            }
        }
    }


    /**
     * Callback function to display checkboxes for available fields.
     */
    public function acafs_display_fields_callback() {
        $saved_fields = get_option('acafs_display_fields', ['your-message']); // Default to "your-message"

        // Ensure it's always an array
        if (!is_array($saved_fields)) {
            $saved_fields = ['your-message'];
        }

        $all_meta_keys = $this->acafs_get_all_flamingo_fields();

        if (empty($all_meta_keys)) {
            echo '<em>' . esc_html__('No submission data available yet.', 'ac-advanced-flamingo-settings') . '</em>';
            return;
        }

        echo '<p>' . esc_html__('Select which fields to display in the "Submission Details" column of Flamingo Inbound Messages.', 'ac-advanced-flamingo-settings') . '</p>';

        echo '<div class="acafs-field-options" >';
        foreach ($all_meta_keys as $key) {
            // Sanitize the key before using it
            $sanitized_key = sanitize_text_field($key);

            // Escape properly
            $checked = in_array($sanitized_key, $saved_fields, true) ? 'checked="checked"' : '';

            echo '<label style="flex: 1 1 30%; display:block; margin-bottom: 10px;">';
            echo '<input type="checkbox" name="acafs_display_fields[]" value="' . esc_attr($sanitized_key) . '" ' . esc_attr($checked) . '> ';
            echo esc_html(ucwords(str_replace('_', ' ', $sanitized_key)));
            echo '</label>';
        }


        echo '</div>';
    }

    /**
     * Retrieve all Flamingo form field names based on stored messages.
     */
    private function acafs_get_all_flamingo_fields() {
        global $wpdb;

        // Attempt to get cached data first
        $cached_meta_keys = wp_cache_get('acafs_flamingo_meta_keys', 'acafs_cache');
        if ($cached_meta_keys !== false) {
            return $cached_meta_keys;
        }

        // Use $wpdb->prepare() directly inside get_col()
        $meta_keys = $wpdb->get_col($wpdb->prepare("
        SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s
    ", '_field_%'));

        // Sanitize and format results
        $meta_keys = array_map(function($key) {
            return str_replace('_field_', '', sanitize_text_field($key));
        }, (array) $meta_keys);

        // Store result in cache for future requests
        wp_cache_set('acafs_flamingo_meta_keys', $meta_keys, 'acafs_cache', 3600); // Cache for 1 hour

        return $meta_keys;
    }



    /**
     * Enqueue admin styles for the settings page.
     */
    public function acafs_admin_styles($hook) {

        if ($hook !== 'settings_page_acafs-settings') {
            return;
        }

        // Register and enqueue the CSS file (empty if using only inline styles)
        wp_register_style('acafs-admin-style', false);
        wp_enqueue_style('acafs-admin-style');

        // Inline CSS moved into wp_add_inline_style()
        $custom_css = "
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
            display:flex;
            flex-wrap:wrap;
            gap:20px;
            margin-top: 20px;
        }
    ";

        wp_add_inline_style('acafs-admin-style', $custom_css);
    }


    /**
     * Modify the Flamingo Address Book table by adding extra columns.
     */
    public function acafs_address_book_columns($columns) {
        // Add a "Last Message" column after Email
        $columns['last_message'] = __('Last Message', 'ac-advanced-flamingo-settings');

        return $columns;
    }

    /**
     * Populate the "Last Message" column with a preview and a link.
     */
    public function acafs_render_address_book_column($column, $post_id) {
        if ($column !== 'last_message') {
            return;
        }

        // Get the associated email for this contact
        $contact_email = get_post_meta($post_id, '_email', true);

        if (!$contact_email) {
            echo '<em>No messages found</em>';
            return;
        }

        // Query the most recent inbound message with matching email
        $args = [
            'post_type'      => 'flamingo_inbound',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_from_email',
                    'value' => sanitize_email($contact_email),
                    'compare' => '='
                ]
            ],
            'orderby'        => 'date',
            'order'          => 'DESC'
        ];

        $messages = get_posts($args);

        if (empty($messages)) {
            echo '<em>' . esc_html__('No messages found', 'ac-advanced-flamingo-settings') . '</em>';
            return;
        }

        // Get the message ID
        $last_message_id = $messages[0]->ID;

        // Get the message content
        $message_content = get_post_meta($last_message_id, '_field_your-message', true);
        if (!$message_content) {
            $message_content = '<em>' . esc_html__('Message unavailable', 'ac-advanced-flamingo-settings') . '</em>';
        } else {
            $message_content = wp_trim_words($message_content, 10, '...');
        }

        // Correct Flamingo admin link
        $view_link = admin_url('admin.php?page=flamingo_inbound&post=' . $last_message_id . '&action=edit');

        echo '<div style="display:flex; flex-direction:column; gap:5px;">';
        echo '<span style="font-size: 12px; color: #666;">' . esc_html($message_content) . '</span>';
        echo '<a href="' . esc_url($view_link) . '" class="button button-small">' . esc_html__('View Last Message', 'ac-advanced-flamingo-settings') . '</a>';
        echo '</div>';
    }



}

// Initialize the plugin
if (class_exists('ACAFS_Plugin')) {
    new ACAFS_Plugin();
}
