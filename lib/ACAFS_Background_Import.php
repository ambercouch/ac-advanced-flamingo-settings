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
    protected function task($batch) {
        global $wpdb;

        if (!is_array($batch) || empty($batch)) {
            return false;
        }

        foreach ($batch as $message) {
            // Optimized duplicate check (see next step)
            $existing_post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'flamingo_inbound' AND post_title = %s AND post_content = %s",
                sanitize_text_field($message['post_title']),
                wp_kses_post($message['post_content'])
            ));

            if ($existing_post_id) {
                continue; // Skip duplicate
            }

            $post_id = wp_insert_post([
                'post_title'   => sanitize_text_field($message['post_title']),
                'post_content' => wp_kses_post($message['post_content']),
                'post_status'  => 'publish',
                'post_type'    => 'flamingo_inbound',
                'post_date'    => $message['post_date'],
                'post_author'  => isset($message['post_author']) ? intval($message['post_author']) : 0,
            ]);

            if (!$post_id) {
                continue;
            }

            if (!empty($message['meta'])) {
                foreach ($message['meta'] as $key => $values) {
                    foreach ($values as $value) {
                        update_post_meta($post_id, sanitize_key($key), maybe_unserialize($value));
                    }
                }
            }

            if (!empty($message['channel_id']) && is_numeric($message['channel_id'])) {
                wp_set_object_terms($post_id, (int) $message['channel_id'], 'flamingo_inbound_channel', false);
            }
        }

        return false;
    }

    protected function complete() {
        delete_transient('acafs_import_started'); // Clear in-progress marker
        set_transient('acafs_import_success', 'completed');
        parent::complete();
    }
}
