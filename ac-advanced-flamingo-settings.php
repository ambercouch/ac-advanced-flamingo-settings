<?php

/**
 * Plugin Name:       AC Advanced Flamingo Settings
 * Requires Plugins:  flamingo
 * Description:       Enhances and extends the functionality of the CF7 Flamingo plugin by adding customization options and import / export functionality, for better contact form data management.
 * Version:           1.4.1
 * Author:            AmberCouch
 * Author URI:        https://ambercouch.co.uk/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ac-advanced-flamingo-settings
 */

defined( 'ABSPATH' ) || die( 'You do not have the required permissions' );

// Define plugin constants globally for accessibility
if ( ! defined( 'ACAFS_VERSION' ) ) {
	define( 'ACAFS_VERSION', '1.1.0' );
}
if ( ! defined( 'ACAFS_PLUGIN' ) ) {
	define( 'ACAFS_PLUGIN', __FILE__ );
}
if ( ! defined( 'ACAFS_PREFIX' ) ) {
	define( 'ACAFS_PREFIX', 'acafs_' );
}

define( 'ACAFS_PLUGIN_BASENAME', plugin_basename( ACAFS_PLUGIN ) );
define( 'ACAFS_PLUGIN_NAME', trim( dirname( ACAFS_PLUGIN_BASENAME ), '/' ) );

define( 'ACAFS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACAFS_PLUGIN_INC_DIR', plugin_dir_path( __FILE__ ) . 'includes/' );
define( 'ACAFS_PLUGIN_TEMPLATE_DIR', plugin_dir_path( __FILE__ ) . 'templates/' );

define( 'ACAFS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACAFS_PLUGIN_ASSETS_URL', ACAFS_PLUGIN_URL . 'assets/' );

require ACAFS_PLUGIN_DIR . '/vendor/autoload.php';
//require ACAFS_PLUGIN_DIR . '/lib/Admin/Flamingo/class-acafs-flamingo-file-linker.php';
require ACAFS_PLUGIN_INC_DIR . '/class-acafs-plugin.php';

if ( class_exists( \ACAFS\Admin\Flamingo\ACAFS_Flamingo_File_Linker::class ) ) {
    ( new \ACAFS\Admin\Flamingo\ACAFS_Flamingo_File_Linker() )->register();
}


// Initialize the plugin
if ( class_exists( 'ACAFS_Plugin' ) ) {
	new ACAFS_Plugin();
}
