<?php

/**
 * File: bluesky-weather-poster-plus.php
 * Root bootstrap for *Bluesky Weather Poster Plus*.
 *
 * Plugin Name:  Bluesky Weather Poster Plus
 * Description:  Auto-post Weather-Display clientraw data (and webcam snapshot) to Bluesky.
 * Version:      1.0.0
 * Author:       Your Name
 * License:      GPL-2.0+
 */

defined('ABSPATH') || exit;

/*------------------------------------------------------------------
 * Constants
 *----------------------------------------------------------------*/
define('BWPP_VERSION', '1.0.0');
define('BWPP_PATH', plugin_dir_path(__FILE__));

/*------------------------------------------------------------------
 * Autoloader (Composer or simple PSR-4 fallback)
 *----------------------------------------------------------------*/
if (file_exists(BWPP_PATH . 'vendor/autoload.php')) {
    require BWPP_PATH . 'vendor/autoload.php';
} else {
    spl_autoload_register(static function ($class) {
        if (str_starts_with($class, 'BWPP\\')) {
            $path = BWPP_PATH . 'includes/' . str_replace('\\', '/', substr($class, 5)) . '.php';
            if (file_exists($path)) {
                require $path;
            }
        }
    });
}

/*------------------------------------------------------------------
 * Boot
 *----------------------------------------------------------------*/
add_action('plugins_loaded', static function () {
    \BWPP\Core\Plugin::get_instance();
});

/*------------------------------------------------------------------
 * Lifecycle hooks
 *----------------------------------------------------------------*/
register_activation_hook(__FILE__,   ['\BWPP\Core\Plugin', 'on_activate']);
register_deactivation_hook(__FILE__, ['\BWPP\Core\Plugin', 'on_deactivate']);

// EOF
