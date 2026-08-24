<?php
/**
 * Uninstall SunnyGDPR
 *
 * Cleans up options from the database when the plugin is uninstalled.
 *
 * @package SunnyGDPR
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Option key used by SunnyGDPR_Setup.
$sunnygdpr_option_name = 'sunnygdpr_settings';

// Delete options for single site.
delete_option( $sunnygdpr_option_name );

// Delete options for multisite installations if network active.
delete_site_option( $sunnygdpr_option_name );