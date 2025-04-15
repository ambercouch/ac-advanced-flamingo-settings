<?php
defined('ABSPATH') || exit;

class ACAFS_Import {

    protected $import_process;

    public function __construct($import_process) {
        $this->import_process = $import_process;

        add_action('admin_post_acafs_import_flamingo_messages', array($this, 'acafs_import_flamingo_messages'));
        add_action('acafs_render_import_export_page', array($this, 'acafs_render_import_section'));
        add_action('admin_notices', array($this, 'acafs_show_import_notice'));
    }

    /**
     * Handle the import form submission
     */
    public function acafs_import_flamingo_messages() {
      // Permissions check
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'ac-advanced-flamingo-settings'));
        }

        // Nonce check
        if (!isset($_POST['acafs_import_nonce']) || !wp_verify_nonce($_POST['acafs_import_nonce'], 'acafs_import_nonce')) {
            wp_die(esc_html__('Security check failed.', 'ac-advanced-flamingo-settings'));
        }

        // File validation
        if (!isset($_FILES['flamingo_import_file']) || empty($_FILES['flamingo_import_file']['tmp_name'])) {
            wp_die(esc_html__('No file uploaded. Please select a valid JSON file.', 'ac-advanced-flamingo-settings'));
        }

        $file_content = file_get_contents($_FILES['flamingo_import_file']['tmp_name']);
        $messages = json_decode($file_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die(esc_html__('Invalid JSON file. Please check the format and try again.', 'ac-advanced-flamingo-settings'));
        }

        $chunks = array_chunk($messages, 50);
        foreach ($chunks as $batch) {
            $this->import_process->push_to_queue($batch);
        }

        $this->import_process->save()->dispatch();

        set_transient('acafs_import_started', 'processing');
        wp_redirect(admin_url('admin.php?page=acafs-message-sync&import_started=1'));
        exit;
    }

    /**
     * Show import notices
     */
    public function acafs_show_import_notice() {
        $done = get_transient('acafs_import_success');
        $started = get_transient('acafs_import_started');

        if ($done) {
            ?>
          <div class="notice notice-success is-dismissible">
            <h2 style="margin-bottom: 5px;"><?php esc_html_e('Import Complete', 'ac-advanced-flamingo-settings'); ?></h2>
            <p><?php esc_html_e('All messages have been imported successfully.', 'ac-advanced-flamingo-settings'); ?></p>
          </div>
            <?php
            delete_transient('acafs_import_success');
            delete_transient('acafs_import_started');
            return;
        }

        if ($started) {
            ?>
          <div class="notice notice-info is-dismissible">
            <h2 style="margin-bottom: 5px;"><?php esc_html_e('Import in Progress', 'ac-advanced-flamingo-settings'); ?></h2>
            <p><?php esc_html_e('Flamingo messages are being imported in the background. Please refresh the page to check progress.', 'ac-advanced-flamingo-settings'); ?></p>
          </div>
            <?php
        }
    }

    /**
     * Render the import section of the import/export page
     */
    public function acafs_render_import_section() {
        ?>
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
        <?php
    }
}
