<?php
namespace ACAFS\Admin\Flamingo;

defined('ABSPATH') || exit;

class FileLinker {
    private string $handle = 'acafs-flamingo-file-linker';

    public function register(): void {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_footer', [$this, 'print_inline_config']);
    }

    private function is_single_flamingo_message_screen(): bool {
        if ( ! is_admin() ) return false;
        if (empty($_GET['page']) || empty($_GET['action']) || empty($_GET['post'])) return false;
        return $_GET['page'] === 'flamingo_inbound' && $_GET['action'] === 'edit';
    }

    public function enqueue(string $hook): void {
        if ( ! $this->is_single_flamingo_message_screen() ) return;

        $src  = plugins_url('assets/js/flamingo-file-linker.js', dirname(__DIR__, 3) . '/ac-advanced-flamingo-settings.php');
        $file = plugin_dir_path(dirname(__DIR__, 3)) . 'assets/js/flamingo-file-linker.js';
        $ver  = file_exists($file) ? (string) filemtime($file) : ACAFS_VERSION;

        wp_enqueue_script($this->handle, $src, ['jquery'], $ver, true);
    }

    public function print_inline_config(): void {
        if ( ! $this->is_single_flamingo_message_screen() ) return;

        // Confine to wp-uploads host and your persisted subpath
        $uploads = wp_get_upload_dir();
        $config = [
            'allowedHosts' => [ wp_parse_url($uploads['baseurl'], PHP_URL_HOST) ],
            'pathHints'    => [
                '/acafs-cf7/',   // our persisted uploads
                '/wpcf7_uploads/' // in case legacy hashes slipped through
            ],
            'skipKeys'     => [ 'url','site_url','post_url' ],
        ];

        wp_add_inline_script(
            $this->handle,
            'window.ACAFS_LINKER_CONFIG = ' . wp_json_encode($config) . ';',
            'before'
        );
    }
}
