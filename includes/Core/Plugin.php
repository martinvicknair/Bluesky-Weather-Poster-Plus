<?php

/**
 * File: includes/Core/Plugin.php
 * Bootstrap singleton – orchestrates all BWPP components.
 *
 * @package BWPP\Core
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

use BWPP\Admin\{Settings, Ajax};

final class Plugin
{

    /*--------------------------------------------------------------------
	 * Singleton
	 *------------------------------------------------------------------*/
    private static ?self $instance = null;

    public static function get_instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {

        /* Ensure Admin classes are available everywhere
		   (autoload fallback may not cover them). */
        require_once BWPP_PATH . 'includes/Admin/Settings.php';
        require_once BWPP_PATH . 'includes/Admin/Ajax.php';

        /* Instantiate early so REST routes register before router builds. */
        new Ajax();
        Settings::instance();

        $this->init_hooks();
    }

    /*--------------------------------------------------------------------
	 * WP Hooks
	 *------------------------------------------------------------------*/
    private function init_hooks(): void
    {

        // custom cron intervals
        add_filter('cron_schedules', ['\BWPP\Core\Cron', 'add_custom_schedules']);

        // posting callback
        add_action(Cron::EVENT_HOOK, ['\BWPP\Core\Cron', 'post_weather_update']);
    }

    /*--------------------------------------------------------------------
	 * Lifecycle handlers (called by bootstrap wrappers)
	 *------------------------------------------------------------------*/
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
