<?php

/**
 * Handles WP-Cron scheduling, rescheduling when settings change, and posting
 * the actual weather update by delegating to other Core components.
 *
 * @package BWPP\Core
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

use DateTime;
use DateTimeZone;

/**
 * Cron helper – static-only utility class.
 *
 * All methods are static so they can be referenced directly in hook
 * callbacks without instantiating.
 */
final class Cron
{

    /** Hook name for our scheduled event. */
    public const HOOK = 'bwpp/post_weather_update';

    /** Name for our custom interval. */
    private const INTERVAL = 'bwpp_custom_interval';

    /* --------------------------------------------------------------------- */
    /* –– REGISTRATION / TEARDOWN ––                                         */
    /* --------------------------------------------------------------------- */

    /**
     * Register (or re-register) the scheduled event.
     * Called on plugin activation *and* whenever the user updates settings
     * that affect frequency or first-post time.
     *
     * @return void
     */
    public static function register(): void
    {
        // Remove any previous schedule first so we do not create duplicates.
        self::clear();

        $settings   = get_option('bwpp_settings', []);
        $frequency  = isset($settings['bwp_post_frequency']) ? (int) $settings['bwp_post_frequency'] : 1; // hours
        $first_hour = isset($settings['bwp_first_post_hour']) ? (int) $settings['bwp_first_post_hour'] : 0;
        $first_min  = isset($settings['bwp_first_post_minute']) ? (int) $settings['bwp_first_post_minute'] : 0;

        $interval_seconds = max(1, $frequency) * HOUR_IN_SECONDS;

        // Register custom interval with WordPress.
        add_filter('cron_schedules', static function ($schedules) use ($interval_seconds, $frequency) {
            $schedules[self::INTERVAL] = [
                'interval' => $interval_seconds,
                'display'  => sprintf( /* translators: %d = interval in hours */__('Every %d hour(s)', 'bwpp'), $frequency),
            ];
            return $schedules;
        });

        // Calculate first run – honours the site time-zone.
        $tz_string = get_option('timezone_string');
        $tz        = $tz_string ? new DateTimeZone($tz_string) : null;
        $now       = current_time('timestamp');

        $dt = new DateTime('now', $tz);
        $dt->setTime($first_hour, $first_min, 0);
        $first_post = $dt->getTimestamp();

        // If that time is in the past, jump ahead until it is in the future.
        while ($first_post <= $now) {
            $first_post += $interval_seconds;
        }

        wp_schedule_event($first_post, self::INTERVAL, self::HOOK);
    }

    /**
     * Removes any scheduled events for this plugin. Safe to call even if none
     * exist.
     *
     * @return void
     */
    public static function clear(): void
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
        }
    }

    /* --------------------------------------------------------------------- */
    /* –– WEATHER POST ––                                                    */
    /* --------------------------------------------------------------------- */

    /**
     * Reads the latest clientraw.txt, formats the post, and sends it to
     * Bluesky via BlueskyPoster.
     *
     * Hooked to self::HOOK by Plugin::init_hooks().
     *
     * @return void
     */
    public static function post_weather_update(): void
    {
        // Guard: make sure required classes exist.
        if (! class_exists(ClientrawParser::class) || ! class_exists(BlueskyPoster::class)) {
            return;
        }

        $settings = get_option('bwpp_settings', []);

        $clientraw_url = $settings['bwp_clientraw_url'] ?? '';
        if (empty($clientraw_url)) {
            return; // Nothing to parse.
        }

        $station_url = $settings['bwp_station_url'] ?? '';
        $frequency   = isset($settings['bwp_post_frequency']) ? (int) $settings['bwp_post_frequency'] : 1;
        $max_age     = $frequency * HOUR_IN_SECONDS;

        // Check Last-Modified header against posting interval.
        $last_modified = wp_remote_retrieve_header(wp_remote_head($clientraw_url, ['timeout' => 10]), 'last-modified');
        if ($last_modified) {
            $age = time() - strtotime($last_modified);
            if ($age > $max_age) {
                // Post a quick status explaining no new data.
                $status = sprintf(
                    /* translators: 1 = age hours, 2 = interval hours */
                    __('No new updates – clientraw.txt is %1$d hour(s) old (interval %2$d h).', 'bwpp'),
                    round($age / HOUR_IN_SECONDS),
                    $frequency
                );
                BlueskyPoster::post_status($status);
                return;
            }
        }

        $parser = new ClientrawParser($clientraw_url);
        $data   = $parser->get_weather_data();
        if (empty($data)) {
            return; // Parsing failed – silently abort.
        }

        $post_struct = Formatter::format_weather_output_with_facets($data, $station_url); // TODO: implement Formatter later.

        BlueskyPoster::post_status($post_struct['text'], $post_struct['facets'] ?? [], $post_struct['embed'] ?? null);

        // Store last post time for admin display.
        update_option('bwpp_last_post_time', current_time('timestamp'), false);
    }

    /* --------------------------------------------------------------------- */
    /* –– HOOKS ON SETTINGS CHANGE ––                                        */
    /* --------------------------------------------------------------------- */

    /**
     * Attach to option update so schedule re-calculates automatically.
     * Done once, when file is loaded.
     */
    public static function maybe_attach_settings_listener(): void
    {
        static $attached = false;
        if ($attached) {
            return;
        }
        add_action('update_option_bwpp_settings', [__CLASS__, 'register'], 10, 0);
        $attached = true;
    }
}

// Ensure listener for settings change is attached as soon as class loads.
Cron::maybe_attach_settings_listener();
