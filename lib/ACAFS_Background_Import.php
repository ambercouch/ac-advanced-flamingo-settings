<?php
if (!class_exists('WP_Background_Process')) {
    require_once plugin_dir_path(__FILE__) . 'lib/wp-background-processing.php';
}

/**
 * Background process for importing Flamingo messages.
 */
class ACAFS_Background_Import extends WP_Background_Process {

    protected $action = 'acafs_import_flamingo';
    public function __construct()
    {
        parent::__construct(); // Call WP_Background_Process constructor
    }

    /**
     * Process a single message import.
     */
    protected function task($message) {
        global $wpdb;
        // Check if message exists
        $existing_post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'flamingo_inbound' AND post_title = %s AND post_content = %s",
            sanitize_text_field($message['post_title']),
            wp_kses_post($message['post_content'])
        ));

        if ($existing_post_id) {
            return false; // Skip duplicate
        }

        // Insert new message
        $post_id = wp_insert_post([
            'post_title'   => sanitize_text_field($message['post_title']),
            'post_content' => wp_kses_post($message['post_content']),
            'post_status'  => 'publish',
            'post_type'    => 'flamingo_inbound',
            'post_date'    => $message['post_date'],
            'post_author'  => isset($message['post_author']) ? intval($message['post_author']) : 0,
        ]);

        if (!$post_id) {
            return false;
        }

        // Restore metadata
        if (!empty($message['meta'])) {
            foreach ($message['meta'] as $key => $values) {
                foreach ($values as $value) {
                    update_post_meta($post_id, sanitize_key($key), maybe_unserialize($value));
                }
            }
        }

        // Assign channel using Term ID
        if (!empty($message['channel_id']) && is_numeric($message['channel_id'])) {
            wp_set_object_terms($post_id, (int) $message['channel_id'], 'flamingo_inbound_channel', false);
        }

        return false; // Mark this task as complete
    }

    protected function complete() {
        set_transient('acafs_import_success', 'completed', 30);
        parent::complete();
    }
}
