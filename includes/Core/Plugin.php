<?php

/**
 * File: includes/Core/Plugin.php
 * Bootstrap singleton ― wires every BWPP module together.
 *
 * @package BWPP\Core
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

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
        register_activation_hook(BWPP_PATH . 'bluesky-weather-poster-plus.php', [$this, 'on_activate']);
        register_deactivation_hook(BWPP_PATH . 'bluesky-weather-poster-plus.php', [$this, 'on_deactivate']);
    }

    /*--------------------------------------------------------------*/
    /* Hooks                                                        */
    /*--------------------------------------------------------------*/

    private function init_hooks(): void
    {

        // Make our custom cron intervals available **before** we register jobs.
        add_filter('cron_schedules', ['\BWPP\Core\Cron', 'add_custom_schedules']);

        // Core posting hook.
        add_action(Cron::EVENT_HOOK, ['\BWPP\Core\Cron', 'post_weather_update']);

        // Admin only.
        if (is_admin()) {
            new \BWPP\Admin\Settings();
            new \BWPP\Admin\Ajax();
        }
    }

    /*--------------------------------------------------------------*/
    /* Lifecycle                                                    */
    /*--------------------------------------------------------------*/

    public function on_activate(): void
    {
        Cron::register();                  // schedule first run
        flush_rewrite_rules();
    }

    public function on_deactivate(): void
    {
        wp_clear_scheduled_hook(Cron::EVENT_HOOK);
        flush_rewrite_rules();
    }
}
// EOF
