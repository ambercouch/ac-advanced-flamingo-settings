<?php
defined('ABSPATH') || exit;

class ACAFS_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'acafs_add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'acafs_enqueue_admin_styles'));
    }

    /**
     * Add plugin submenu pages under Flamingo
     */
    public function acafs_add_admin_menu() {
        // Import/Export page
        add_submenu_page(
            'flamingo',
            __('Import/Export Inbound Messages', 'ac-advanced-flamingo-settings'),
            __('Import/Export', 'ac-advanced-flamingo-settings'),
            'manage_options',
            'acafs-message-sync',
            array($this, 'acafs_render_import_export_page')
        );

        // Settings page
        add_submenu_page(
            'flamingo',
            __('Advanced Flamingo Settings', 'ac-advanced-flamingo-settings'),
            __('Settings', 'ac-advanced-flamingo-settings'),
            'manage_options',
            'acafs-settings',
            array($this, 'acafs_render_settings_page')
        );
    }

    /**
     * Conditionally enqueue admin styles for our plugin pages
     */
    public function acafs_enqueue_admin_styles($hook) {
        if (
            $hook !== 'flamingo_page_acafs-settings' &&
            $hook !== 'flamingo_page_acafs-message-sync'
        ) {
            return;
        }

        wp_register_style('acafs-admin-style', false);
        wp_enqueue_style('acafs-admin-style');

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
     * Placeholder for rendering the settings page
     */
    public function acafs_render_settings_page() {
        do_action('acafs_render_settings_page');
    }

    /**
     * Placeholder for rendering the import/export page
     */
    public function acafs_render_import_export_page() {
        do_action('acafs_render_import_export_page');
    }
}
