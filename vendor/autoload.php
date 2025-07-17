<?php

/**
 * Core Plugin bootstrap – orchestrates all components.
 *
 * @package BWPP\Core
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

use BWPP\Admin\Settings;
use BWPP\Admin\Ajax;
use BWPP\Core\Cron;

/**
 * Main singleton that wires all sub‑modules together.
 */
final class Plugin
{

    /** @var self|null */
    private static ?self $instance = null;

    /**
     * Singleton accessor.
     */
    public static function get_instance(): self
    {
        return self::$instance ?? (self::$instance = new self());
    }

    /** Hide constructor. */
    private function __construct()
    {
        $this->init_hooks();
    }

    /** Register hooks. */
    private function init_hooks(): void
    {
        if (is_admin()) {
            new Settings();
            new Ajax();
        }

        add_action(Cron::HOOK, [$this, 'handle_weather_post']);
    }

    /* Activation ---------------------------------------------------------- */

    public static function activate(): void
    {
        if (class_exists(Cron::class)) {
            Cron::register();
        }
    }

    public static function deactivate(): void
    {
        if (class_exists(Cron::class)) {
            Cron::clear();
        }
    }

    /* Public hooks -------------------------------------------------------- */

    public function handle_weather_post(): void
    {
        if (class_exists(Cron::class)) {
            Cron::post_weather_update();
        }
    }
}
