<?php
/**
 * Plugin Name:       SunnyComments
 * Plugin URI:        https://sunnywebstudio.com/
 * Description:       Comments Marketing Tools
 * Version:           1.0.0
 * Author:            SunnyWebStudio, Aleksei Sukhoverkhov
 * Author URI:        https://sunnywebstudio.com/
 * Text Domain:       sunnycomments
 * Requires at least: 5.0
 * Requires PHP:      7.0
 * Tested up to:      7.0   
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin initialization function to include all core modules.
 */
function sunnycom_initialize_core() {
    $plugin_path = plugin_dir_path( __FILE__ );

    // Core Modules
    $modules = array(
        'inc/class-sunnycom-setup.php',
        'inc/class-sunnycom-expert.php',
        'inc/class-sunnycom-avatars.php',
        'inc/class-sunnycom-antispam.php',
        'inc/class-sunnycom-supercommenter.php',
        'inc/class-sunnycom-mailer.php',          
    );

    foreach ( $modules as $module ) {
        if ( file_exists( $plugin_path . $module ) ) {
            require_once $plugin_path . $module;
        }
    }
}
add_action( 'plugins_loaded', 'sunnycom_initialize_core' );

/**
 * Enqueue plugin scripts and styles.
 */
function sunnycom_enqueue_assets() {
    $plugin_url = plugin_dir_url( __FILE__ );

    // Enqueue main CSS file
    wp_enqueue_style(
        'sunnycom-styles',
        $plugin_url . 'assets/css/sunnycom.css',
        array(),
        '1.0.0'
    );
}
add_action( 'wp_enqueue_scripts', 'sunnycom_enqueue_assets' );