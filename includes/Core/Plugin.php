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
 *
 * Nothing here renders HTML or touches global scope beyond WordPress hooks –
 * it simply instantiates the right classes at the right time.
 */
final class Plugin
{

    /**
     * Static instance holder.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor – private so it can only be instantiated through get_instance().
     */
    private function __construct()
    {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include (require) any core class files that aren’t loaded via Composer.
     * Composer handles PSR‑4 autoloading for everything under /includes, so this
     * is currently a no‑op – kept for forward‑compatibility if we need to pull
     * files conditionally.
     *
     * @return void
     */
    private function includes(): void
    {
        // All classes autoloaded via Composer – nothing to include manually.
    }

    /**
     * Wire WordPress hooks.
     * Instantiates Admin sub‑modules only in wp‑admin.
     *
     * @return void
     */
    private function init_hooks(): void
    {
        if (is_admin()) {
            // Admin settings & AJAX live‑preview endpoints.
            new Settings();
            new Ajax();
        }

        // Public‑facing hooks (WP‑Cron, etc.) live inside their own classes.
        add_action('bwpp/post_weather_update', [$this, 'handle_weather_post']);
    }

    /* --------------------------------------------------------------------- */
    /* –– ACTIVATION / DEACTIVATION ––                                       */
    /* --------------------------------------------------------------------- */

    /**
     * Fired on plugin activation.
     *
     * Registers cron schedule and anything else that must happen exactly once.
     *
     * @return void
     */
    public static function activate(): void
    {
        // Ensure all files are autoloadable in the activation context.
        if (! class_exists(Cron::class)) {
            return; // Something went horribly wrong – fail gracefully.
        }

        Cron::register();
    }

    /**
     * Fired on plugin deactivation.
     *
     * Clear scheduled events so we don’t leave orphaned tasks.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        if (class_exists(Cron::class)) {
            Cron::clear();
        }
    }

    /* --------------------------------------------------------------------- */
    /* –– PUBLIC HOOK CALLBACKS ––                                           */
    /* --------------------------------------------------------------------- */

    /**
     * Handle the bwpp/post_weather_update action.
     * Delegates to Cron::post_weather_update() so tests can call it directly.
     *
     * @return void
     */
    public function handle_weather_post(): void
    {
        if (class_exists(Cron::class)) {
            Cron::post_weather_update();
        }
    }
}
