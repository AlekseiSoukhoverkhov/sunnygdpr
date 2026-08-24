<?php
/**
 * Plugin Name:       SunnyGDPR
 * Description:       High-performance engine for GDPR
 * Version:           1.1.0
 * Author:            SunnyWebStudio, Aleksei Sukhoverkhov
 * Author URI:        https://sunnywebstudio.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sunnygdpr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin initialization function to include all core modules.
 */
function sunnygdpr_initialize_core() {
    $plugin_path = plugin_dir_path( __FILE__ );

    // Core Modules
    $modules = array(
        'inc/class-sunnygdpr-setup.php',
        'inc/class-sunnygdpr-notification.php',        
    );

    foreach ( $modules as $module ) {
        if ( file_exists( $plugin_path . $module ) ) {
            require_once $plugin_path . $module;
        }
    }
}
add_action( 'plugins_loaded', 'sunnygdpr_initialize_core' );