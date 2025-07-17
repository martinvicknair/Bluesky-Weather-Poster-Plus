<?php
/**
 * Plugin Name:       Bluesky Weather Poster Plus
 * Plugin URI:        https://example.com/bluesky-weather-poster-plus
 * Description:       Posts automated weather updates from your weather station to Bluesky. Refactored according to WordPress best practices.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bwpp
 * Domain Path:       /languages
 *
 * @package BWPP
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'BWPP_VERSION' ) ) {
    define( 'BWPP_VERSION', '2.0.0' );
}

if ( ! defined( 'BWPP_FILE' ) ) {
    define( 'BWPP_FILE', __FILE__ );
}

if ( ! defined( 'BWPP_PATH' ) ) {
    define( 'BWPP_PATH', plugin_dir_path( BWPP_FILE ) );
}

if ( ! defined( 'BWPP_URL' ) ) {
    define( 'BWPP_URL', plugin_dir_url( BWPP_FILE ) );
}

/*
 * Load Composer autoloader if present.
 */
$bwpp_autoloader = BWPP_PATH . 'vendor/autoload.php';

if ( file_exists( $bwpp_autoloader ) ) {
    require $bwpp_autoloader;
}

/**
 * Initializes the core plugin singleton after all plugins are loaded.
 *
 * @return void
 */
function bwpp_boot() {
    // Load translations.
    load_plugin_textdomain(
        'bwpp',
        false,
        dirname( plugin_basename( BWPP_FILE ) ) . '/languages'
    );

    // Bootstrap the plugin.
    if ( class_exists( '\\BWPP\\Core\\Plugin' ) ) {
        \\BWPP\\Core\\Plugin::get_instance();
    }
}
add_action( 'plugins_loaded', 'bwpp_boot' );

/**
 * Register activation and deactivation hooks.
 */
register_activation_hook(
    __FILE__,
    static function () {
        if ( class_exists( '\\BWPP\\Core\\Plugin' ) ) {
            \\BWPP\\Core\\Plugin::activate();
        }
    }
);

register_deactivation_hook(
    __FILE__,
    static function () {
        if ( class_exists( '\\BWPP\\Core\\Plugin' ) ) {
            \\BWPP\\Core\\Plugin::deactivate();
        }
    }
);
