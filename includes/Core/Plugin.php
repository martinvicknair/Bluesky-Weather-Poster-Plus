<?php

/**
 * File: includes/Core/Plugin.php
 * Bootstrap singleton – orchestrates all components.
 *
 * @package BWPP\Core
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

use BWPP\Admin\{Settings, Ajax};

final class Plugin
{

    private static ?self $instance = null;

    public static function get_instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        // Always load Admin classes so they can hook REST in any context.
        require_once BWPP_PATH . 'includes/Admin/Settings.php';
        require_once BWPP_PATH . 'includes/Admin/Ajax.php';

        new Ajax();                 // ← instantiate **before** hooks run
        Settings::instance();

        $this->init_hooks();

        register_activation_hook(BWPP_PATH . 'bluesky-weather-poster-plus.php', [$this, 'on_activate']);
        register_deactivation_hook(BWPP_PATH . 'bluesky-weather-poster-plus.php', [$this, 'on_deactivate']);
    }

    private function init_hooks(): void
    {
        add_filter('cron_schedules', ['\BWPP\Core\Cron', 'add_custom_schedules']);
        add_action(Cron::EVENT_HOOK,  ['\BWPP\Core\Cron', 'post_weather_update']);
    }

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
