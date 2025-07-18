<?php

/**
 * File: includes/Core/Plugin.php
 * Bootstrap singleton – wires every BWPP module together.
 *
 * @package BWPP\Core
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

use BWPP\Admin\{Settings, Ajax};

final class Plugin
{

    /*--------------------------------------------------------------*/
    /* Singleton                                                    */
    /*--------------------------------------------------------------*/

    private static ?self $instance = null;

    public static function get_instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->init_hooks();

        // plugin lifecycle
        register_activation_hook(BWPP_PATH . 'bluesky-weather-poster-plus.php', [$this, 'on_activate']);
        register_deactivation_hook(BWPP_PATH . 'bluesky-weather-poster-plus.php', [$this, 'on_deactivate']);
    }

    /*--------------------------------------------------------------*/
    /* Hooks                                                        */
    /*--------------------------------------------------------------*/

    private function init_hooks(): void
    {

        // ── make sure Admin classes are always available ──────────
        require_once BWPP_PATH . 'includes/Admin/Settings.php';
        require_once BWPP_PATH . 'includes/Admin/Ajax.php';

        // custom cron intervals
        add_filter('cron_schedules', ['\BWPP\Core\Cron', 'add_custom_schedules']);

        // posting callback
        add_action(Cron::EVENT_HOOK, ['\BWPP\Core\Cron', 'post_weather_update']);

        // admin-only modules
        if (is_admin()) {
            \BWPP\Admin\Settings::instance();
            new \BWPP\Admin\Ajax();
        }
    }
    /*--------------------------------------------------------------*/
    /* Lifecycle                                                    */
    /*--------------------------------------------------------------*/

    public function on_activate(): void
    {
        Cron::register();
        flush_rewrite_rules();
    }

    public function on_deactivate(): void
    {
        wp_clear_scheduled_hook(Cron::EVENT_HOOK);
        flush_rewrite_rules();
    }
}
// EOF
