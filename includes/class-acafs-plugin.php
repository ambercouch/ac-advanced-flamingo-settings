<?php

use background\ACAFS_Background_Import;

defined('ABSPATH') || exit;

class ACAFS_Plugin {

    /**
     * Constructor - Initializes the plugin.
     */
    public function __construct() {


        add_action('admin_init', array($this, 'acafs_list_tables'));


        add_action('admin_menu', array($this, 'acafs_modify_flamingo_menu'), 10);
        add_action('admin_init', array($this, 'acafs_redirect_address_book'), 10);


        add_action('admin_menu', array($this, 'acafs_rename_flamingo_menu'), 10);
        add_action('admin_menu', array($this, 'acafs_set_flamingo_default'), 10);



        // Register the export function in WordPress
        add_action('admin_post_acafs_export_flamingo_messages', array($this, 'acafs_export_flamingo_messages'));

        // Register the import function in WordPress
        add_action('admin_post_acafs_import_flamingo_messages', array($this, 'acafs_import_flamingo_messages'));

        // Hook into the plugin action links filter
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'acafs_add_settings_link'));

        // Hook to display notice
        add_action('admin_notices', array($this, 'acafs_show_export_notice'));

        // Hook to display notice
        add_action('admin_notices', array($this, 'acafs_show_import_notice'));

        add_action('admin_post_acafs_get_message_count', array($this, 'acafs_get_message_count'));
        add_action('admin_post_nopriv_acafs_get_message_count', array($this, 'acafs_get_message_count'));

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

//        // Features
        require_once ACAFS_PLUGIN_INC_DIR . 'features/class-acafs-columns.php';
//        require_once ACAFS_PLUGIN_INC_DIR . 'features/class-acafs-export.php';
//        require_once ACAFS_PLUGIN_INC_DIR . 'features/class-acafs-import.php';
//
//        // Background imports
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
        // Background import handler
        $this->import_process = new ACAFS_Background_Import();

        // Core modules
        new ACAFS_Hooks();
        new ACAFS_Admin();
        new ACAFS_Settings();
//
//        // Features
        new ACAFS_Columns();
//        new ACAFS_Export( $this->import_process ); // Pass background import if needed
//        new ACAFS_Import( $this->import_process );

        // Optional integrations (can be conditional later)
        if ( class_exists( 'ACAFS_Compat_Divi' ) ) {
            new ACAFS_Compat_Divi();
        }
    }


    protected $acafs_import_messages;


    /**
     * Add a "Settings" link to the plugin list page.
     */
    public function acafs_add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=acafs-settings') . '">' . __('Settings', 'ac-advanced-flamingo-settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Sanitize checkbox input (1 or 0).
     */
    public function acafs_sanitize_checkbox($input) {
        return ($input === '1') ? '1' : '0';
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
     * Export Flamingo messages to a JSON file, processing in chunks.
     */
    public function acafs_export_flamingo_messages() {
        global $wpdb;

        // Get date range
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $export_all = isset($_GET['export_all']) ? intval($_GET['export_all']) : 0;

        // Construct date filter SQL
        $date_filter = '';
        if (!$export_all && !empty($start_date) && !empty($end_date)) {
            $date_filter = $wpdb->prepare("AND post_date BETWEEN %s AND %s", $start_date . ' 00:00:00', $end_date . ' 23:59:59');
        }

        // Count total messages
        $total_messages = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->posts} 
        WHERE post_type = 'flamingo_inbound' 
        AND post_status = 'publish' 
        $date_filter
    ");

        if ($total_messages === 0) {
            set_transient('acafs_export_success', 0, 30);
            wp_redirect(admin_url('admin.php?page=acafs-message-sync&export_success=1'));
            exit;
        }

        // Fetch messages in chunks
        $batch_size = 500;
        $offset = 0;
        $all_messages = [];

        while ($offset < $total_messages) {
            $messages = $wpdb->get_results("
            SELECT * FROM {$wpdb->posts} 
            WHERE post_type = 'flamingo_inbound' 
            AND post_status = 'publish' 
            $date_filter 
            LIMIT $batch_size OFFSET $offset
        ", ARRAY_A);

            foreach ($messages as &$message) {
                $message['meta'] = get_post_meta($message['ID']);
                $terms = wp_get_post_terms($message['ID'], 'flamingo_inbound_channel', array("fields" => "ids"));
                $message['channel_id'] = (!empty($terms) ? $terms[0] : 0);
            }

            $all_messages = array_merge($all_messages, $messages);
            $offset += $batch_size;
        }

        // Start timing file creation
        $file_start_time = microtime(true);

        // Save JSON file
        $file_name = 'flamingo-messages';
        if (!$export_all && !empty($start_date) && !empty($end_date)) {
            $file_name .= "-{$start_date}_to_{$end_date}";
        }
        $file_name .= '-' . time() . '.json';

        $upload_dir = wp_upload_dir();
        $file_path = trailingslashit($upload_dir['basedir']) . $file_name;
        file_put_contents($file_path, json_encode($all_messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // End timing file creation
        $file_creation_time = microtime(true) - $file_start_time;

        // Store file URL in transient BEFORE redirect
        set_transient('acafs_export_file', $upload_dir['baseurl'] . '/' . $file_name, 30);
        set_transient('acafs_export_success', $total_messages, 5 * MINUTE_IN_SECONDS);

        // Redirect after confirming transient exists
        wp_redirect(admin_url('admin.php?page=acafs-message-sync&export_success=1'));
        exit;
    }


    /**
     * Display success message after export.
     */
    public function acafs_show_export_notice() {
        $export_status = get_transient('acafs_export_success');

        if (!empty($export_status)) {
            echo '<div class="notice notice-success is-dismissible">
                <h2 style="margin-bottom: 5px;">' . esc_html__('Export Complete', 'ac-advanced-flamingo-settings') . '</h2>
                <p>' . sprintf(esc_html__('%s messages exported successfully.', 'ac-advanced-flamingo-settings'), esc_html($export_status)) . '</p>';

            $file_url = get_transient('acafs_export_file');
            if (!empty($file_url)) {
                echo '<p><a href="' . esc_url($file_url) . '" class="button button-primary" download>' . esc_html__('Download Exported File', 'ac-advanced-flamingo-settings') . '</a></p>';
            }

            echo '</div>';

            // Delete the transient immediately after displaying
            delete_transient('acafs_export_success');
            delete_transient('acafs_export_file');
        }
    }

    /**
     * Import Flamingo messages from a JSON file using background processing.
     */
    public function acafs_import_flamingo_messages() {

        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'ac-advanced-flamingo-settings'));
        }

        // Check if a file was uploaded
        if (!isset($_FILES['flamingo_import_file']) || empty($_FILES['flamingo_import_file']['tmp_name'])) {
            wp_die(__('No file uploaded. Please select a valid JSON file.', 'ac-advanced-flamingo-settings'));
        }

        // Read and decode the JSON file
        $file_content = file_get_contents($_FILES['flamingo_import_file']['tmp_name']);
        $messages = json_decode($file_content, true);

        // Check if JSON decoding was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die(__('Invalid JSON file. Please check the format and try again.', 'ac-advanced-flamingo-settings'));
        }

        $chunked_messages = array_chunk($messages, 50); // Or 10, or 50
        foreach ($chunked_messages as $batch) {
            $this->acafs_import_process->push_to_queue($batch);
        }
        $this->acafs_import_process->save()->dispatch();

        // Store import started notice
        set_transient('acafs_import_started', 'processing');

        // Redirect back to settings page with a notice
        wp_redirect(admin_url('admin.php?page=acafs-message-sync&import_started=1'));
        exit;
    }

    /**
     * Display import status messages.
     */
    public function acafs_show_import_notice() {
        $import_success = get_transient('acafs_import_success');
        $import_started = get_transient('acafs_import_started');

        if ($import_success) {
            echo '<div class="notice notice-success is-dismissible">
            <h2 style="margin-bottom: 5px;">' . esc_html__('Import Complete', 'ac-advanced-flamingo-settings') . '</h2>
            <p>' . esc_html__('All messages have been imported successfully.', 'ac-advanced-flamingo-settings') . '</p>
        </div>';

            delete_transient('acafs_import_success');
            delete_transient('acafs_import_started'); // Clear in-progress transient too
            return; // Don't show any other notice
        }

        if ($import_started) {
            echo '<div class="notice notice-info is-dismissible">
            <h2 style="margin-bottom: 5px;">' . esc_html__('Import in Progress', 'ac-advanced-flamingo-settings') . '</h2>
            <p>' . esc_html__('Flamingo messages are being imported in the background. Please refresh the page to check progress.', 'ac-advanced-flamingo-settings') . '</p>
        </div>';
        }
    }



    /**
     * Render the import/export settings page with optional date filtering.
     */
    public function acafs_render_import_export_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Import/Export Inbound Messages', 'ac-advanced-flamingo-settings'); ?></h1>

            <?php $this->acafs_show_export_notice(); ?>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">

                    <!-- Export Section -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php esc_html_e('Export Messages', 'ac-advanced-flamingo-settings'); ?></h2>
                        </div>
                        <div class="inside">
                            <p><?php esc_html_e('Download Flamingo messages as a JSON file. You can select a date range or download all messages.', 'ac-advanced-flamingo-settings'); ?></p>

                            <form id="acafs-export-form">
                                <input type="hidden" name="action" value="acafs_export_flamingo_messages">

                                <label>
                                    <input type="checkbox" id="export_all" name="export_all" value="1">
                                    <?php esc_html_e('Export all messages', 'ac-advanced-flamingo-settings'); ?>
                                </label>

                                <div id="date-filters">
                                    <label for="start_date"><?php esc_html_e('Start Date:', 'ac-advanced-flamingo-settings'); ?></label>
                                    <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr(date('Y-m-01')); ?>">

                                    <label for="end_date"><?php esc_html_e('End Date:', 'ac-advanced-flamingo-settings'); ?></label>
                                    <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr(date('Y-m-d')); ?>">
                                </div>

                                <p id="message-count"><?php esc_html_e('Messages to be exported: 0', 'ac-advanced-flamingo-settings'); ?></p>

                                <button type="submit" class="button button-primary">
                                    <?php esc_html_e('Export Messages', 'ac-advanced-flamingo-settings'); ?>
                                </button>
                            </form>

                            <div id="acafs-export-feedback" style="margin-top: 10px;"></div>
                        </div>
                    </div>

                    <!-- Import Section -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php esc_html_e('Import Messages', 'ac-advanced-flamingo-settings'); ?></h2>
                        </div>
                        <div class="inside">
                            <p><?php esc_html_e('Upload a previously exported JSON file to restore Flamingo messages.', 'ac-advanced-flamingo-settings'); ?></p>

                            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php?action=acafs_import_flamingo_messages')); ?>">
                                <?php wp_nonce_field('acafs_import_nonce', 'acafs_import_nonce'); ?>
                                <input type="file" name="flamingo_import_file" accept=".json" required>
                                <input type="submit" class="button button-primary" value="<?php esc_attr_e('Import Messages', 'ac-advanced-flamingo-settings'); ?>">
                            </form>
                        </div>
                    </div>

                </div>
            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    var feedbackDiv = document.getElementById("acafs-import-feedback");
                    var importForm = document.getElementById("acafs-import-form");

                    importForm.addEventListener("submit", function(e) {
                        e.preventDefault(); // Prevent default form submission

                        feedbackDiv.innerHTML = "<p><strong>Importing messages... This may take a few minutes.</strong></p>";

                        // Submit the form normally
                        importForm.submit();
                    });
                });
                document.addEventListener("DOMContentLoaded", function() {
                    var startDateInput = document.getElementById("start_date");
                    var endDateInput = document.getElementById("end_date");
                    var exportAllCheckbox = document.getElementById("export_all");
                    var dateFilters = document.getElementById("date-filters");
                    var messageCount = document.getElementById("message-count");
                    var feedbackDiv = document.getElementById("acafs-export-feedback");
                    var exportForm = document.getElementById("acafs-export-form");

                    function updateMessageCount() {
                        var startDate = startDateInput.value;
                        var endDate = endDateInput.value;
                        var exportAll = exportAllCheckbox.checked ? 1 : 0;

                        fetch("<?php echo esc_url(admin_url('admin-post.php?action=acafs_get_message_count')); ?>" +
                            "&start_date=" + encodeURIComponent(startDate) +
                            "&end_date=" + encodeURIComponent(endDate) +
                            "&export_all=" + exportAll)
                            .then(response => response.text())
                            .then(count => {
                                messageCount.innerHTML = "<?php esc_html_e('Messages to be exported:', 'ac-advanced-flamingo-settings'); ?> " + count;
                            })
                            .catch(error => console.error("Message count fetch error:", error));
                    }

                    exportAllCheckbox.addEventListener("change", function() {
                        dateFilters.style.display = this.checked ? "none" : "block";
                        updateMessageCount();
                    });

                    startDateInput.addEventListener("change", updateMessageCount);
                    endDateInput.addEventListener("change", updateMessageCount);

                    exportForm.addEventListener("submit", function(e) {
                        e.preventDefault(); // Prevent default submission

                        var startDate = startDateInput.value;
                        var endDate = endDateInput.value;
                        var exportAll = exportAllCheckbox.checked ? 1 : 0;

                        feedbackDiv.innerHTML = "<p><strong>Exporting messages... Please wait.</strong></p>";

                        // **Manually update form action URL to include parameters**
                        exportForm.action = "<?php echo esc_url(admin_url('admin-post.php?action=acafs_export_flamingo_messages')); ?>" +
                            "&start_date=" + encodeURIComponent(startDate) +
                            "&end_date=" + encodeURIComponent(endDate) +
                            "&export_all=" + exportAll;

                        exportForm.submit(); // Submit the form normally
                    });

                    updateMessageCount();
                });

            </script>
        </div>
        <?php
    }

    /**
     * Return the number of messages matching the selected date range.
     */
    public function acafs_get_message_count() {
        global $wpdb;

        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $export_all = isset($_GET['export_all']) ? intval($_GET['export_all']) : 0;

        $date_filter = '';
        if (!$export_all && !empty($start_date) && !empty($end_date)) {
            $date_filter = $wpdb->prepare("AND post_date BETWEEN %s AND %s", $start_date . ' 00:00:00', $end_date . ' 23:59:59');
        }

        $message_count = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->posts} 
        WHERE post_type = 'flamingo_inbound'
        AND post_status = 'publish'
        $date_filter
    ");

        echo intval($message_count);
        exit;
    }




}