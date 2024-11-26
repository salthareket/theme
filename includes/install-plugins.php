<?php
/**
 * Plugin Name: Activate required plugins.
 * Description: Programmatically install and activate plugins based on a runtime config.
 * Version:     1.0
 * Author:      Hans Schuijff
 * Author URI:  http://dewitteprins.nl
 * License:     MIT
 * License URI: http://www.opensource.org/licenses/mit-license.php
 */

 /**
 * Inspired by https://gist.github.com/squarestar/37fe1ff964adaddc0697dd03155cf0d0
 * 
 * TODO:
 * - can the deactivation action links be removed or be caught with a better response?
 * - use core functionality runtime config. 
 */
namespace DeWittePrins\CoreFunctionality\MustUse;

require_once( ABSPATH . 'wp-load.php' );
require_once( ABSPATH . 'wp-includes/pluggable.php');
require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
require_once( ABSPATH . 'wp-admin/includes/file.php' );
require_once( ABSPATH . 'wp-admin/includes/misc.php' );
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

/* 
 * Hide the 'Activate Plugin' and other links when not using Quiet_Upgrader_Skin as these links will 
 * fail when not called from /wp-admin 
 */
// This will remove all links, so it is to generic to be usable. Have to replace it with a better solution.
// echo '<style>a {display: none;}</style>';

/**
 * Overwrite the feedback method in the WP_Upgrader_Skin
 * to suppress the normal feedback.
 */
class Quiet_Upgrader_Skin extends \WP_Upgrader_Skin {
    /*
     * Suppress normal upgrader feedback / output
     */
    public function feedback( $string, ...$args ) { 
        /* no output */ 
    }
}

/**
 * Activates a given plugin. 
 * 
 * If needed it dowloads and/or installs the plugin first.
 *
 * @param string $slug The plugin's basename (containing the plugin's base directory and the bootstrap filename).
 * @return void
 */
function activate_plugin( $plugin ) {
    $plugin_mainfile = trailingslashit( WP_PLUGIN_DIR ) . $plugin;
    /* Nothing to do, when plugin already active.
     * 
     * WARNING: When a plugin has been removed by ftp, 
     *          WordPress will still consider it active, 
     *          untill the plugin list has been visited 
     *          (and it checks the existence of it).
     */
     if ( \is_plugin_active( $plugin ) ) {
        // Make sure the plugin is still there (files could be removed without wordpress noticing)
        $error = \validate_plugin( $plugin );
        if ( ! is_wp_error( $error ) ) {
            return;
        }
    }

    // Install if neccessary.
    if ( ! is_plugin_installed( $plugin ) ) {
        $error = install_plugin( $plugin );
        if ( ! empty( $error ) ) {
            return $error;
        }
    }
    // Now we activate, when install has been successfull.
    if ( ! is_plugin_installed( $plugin ) ) {
        return 'Error: Plugin could not be installed (' . $plugin . '). '
            . '<br>This probably means there is an error in the plugin basename, '
            . 'or the plugin isn\'t in the wordpress repository on wordpress.org. '
            . '<br>Please correct the problem, and/or install and activate the plugin manually.<br>'
            . "\n";
    }

    $error = \validate_plugin( $plugin );
    if ( is_wp_error( $error ) ) {
        return 'Error: Plugin main file has not been found (' . $plugin . ').'
            . '<br/>This probably means the main file\'s name does not match the slug.'
            . '<br/>Please check the plugins listing in wp-admin.'
            . "<br>\n"
            . var_export( $error->get_error_code(), true ) . ': '
            . var_export( $error->get_error_message(), true )
            . "\n";
    }
    $error = \activate_plugin( $plugin_mainfile );
    if ( is_wp_error( $error ) ) {
        return 'Error: Plugin has not been activated (' . $plugin . ').'
            . '<br/>This probably means the main file\'s name does not match the slug.'
            . '<br/>Check the plugins listing in wp-admin.' 
            . "<br/>\n"
            . var_export( $error->get_error_code(), true ) . ': '
            . var_export( $error->get_error_message(), true )
            . "\n";
    }
}

/**
 * Is plugin installed?
 * 
 * Get_plugins() returns an array containing all installed plugins
 * with the plugin basename as key.
 * 
 * When you pass the plugin dir to get_plugins(),
 * it will return an empty array if that plugin is not yet installed,
 * 
 * When the plugin is installed it will return an array with that plugins data, 
 * using the plugins main filename as key (so not the basename).
 * 
 * @param  string  $plugin Plugin basename.
 * @return boolean         True when installed, otherwise false.
 */
function is_plugin_installed( $plugin ) {
    $plugins = \get_plugins( '/'.get_plugin_dir( $plugin ) );
    if ( ! empty( $plugins ) ) {
        return true;
    }
    return false;
}

/**
 * Extraxts the plugins directory (=slug for api) from the plugin basename.
 *
 * @param string $plugin Plugin basename.
 * @return string        The directory-part of the plugin basename.
 */
function get_plugin_dir( $plugin ) {
    $chunks = explode( '/', $plugin );
    if ( ! is_array( $chunks ) ) {
        $plugin_dir = $chunks;
    } else{
        $plugin_dir = $chunks[0];
    }
    return $plugin_dir;
}

/**
 * Intall a given plugin.
 *
 * @param  string      $plugin Plugin basename.
 * @return null|string         Null when install was succesfull, otherwise error message.
 */
function install_plugin( $plugin ) {
    $api = plugins_api(
        'plugin_information',
        array(
            'slug'   => get_plugin_dir( $plugin ),
            'fields' => array(
                'short_description' => false,
                'requires'          => false,
                'sections'          => false,
                'rating'            => false,
                'ratings'           => false,
                'downloaded'        => false,
                'last_updated'      => false,
                'added'             => false,
                'tags'              => false,
                'compatibility'     => false,
                'homepage'          => false,
                'donate_link'       => false,
            ),
        )
    );

    if ( is_wp_error( $api ) ) {
        return 'Error: Install process failed (' . $plugin . ').<br>' 
            . "\n"
            . var_export( $api->get_error_code(), true ) . ': '
            . var_export( $api->get_error_message(), true )
            . "\n";
    }

    // Replace new \Plugin_Installer_Skin with new Quiet_Upgrader_Skin when output needs to be suppressed.
    $skin      = new Quiet_Upgrader_Skin( array( 'api' => $api ) );
    $upgrader  = new \Plugin_Upgrader( $skin );
    $error     = $upgrader->install( $api->download_link );
    /* 
     * Check for errors...
     * $upgrader->install() returns NULL on success, 
     * otherwise a WP_Error object.
     */
    if ( is_wp_error( $error ) ) {
        return 'Error: Install process failed (' . $plugin . ').<br>' 
            . "\n"
            . var_export( $error->get_error_code(), true ) . ': '
            . var_export( $error->get_error_message(), true )
            . "\n";
    }
}

/**
 * Gets runtime config data for a given context.
 *
 * @param string $context What config data needs to be returned?
 * @return array          Runtime config for that context.
 */
function get_config( $context ) {
    if ( 'must-have-plugins' === $context ) {
        // Array of plugin basenames of plugins that need to be active.
        return $plugins = $GLOBALS["plugins"];
    }
}

/**
 * Launches auto-activation of required plugins.
 *
 * @return void
 */
function activate_required_plugins() {
    $plugins = get_config('must-have-plugins');
    foreach ( $plugins as $plugin ) {
        $error = activate_plugin( $plugin );
        if ( ! empty( $error ) ) {
            if(is_admin()){
                //add_admin_notice($error, "error");
                //echo $error;
            }else{
                //echo $error;
            }
        }
    }
}
activate_required_plugins();