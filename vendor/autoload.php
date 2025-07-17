<?php

/**
 * Handles WP‑Cron scheduling, rescheduling when settings change, and posting
 * the actual weather update by delegating to other Core components.
 *
 * @package BWPP\Core
 */

defined('ABSPATH') || exit;

namespace BWPP\Core;

use DateTime;
use DateTimeZone;

final class Cron
{

    public const HOOK = 'bwpp/post_weather_update';
    private const INTERVAL = 'bwpp_custom_interval';

    /* --------------------------------------------------------------------- */
    /* –– REGISTRATION / TEARDOWN ––                                         */
    /* --------------------------------------------------------------------- */

    public static function register(): void
    {
        self::clear();

        $settings   = get_option('bwpp_settings', []);
        $frequency  = isset($settings['bwp_post_frequency']) ? (int) $settings['bwp_post_frequency'] : 1;
        $first_hour = isset($settings['bwp_first_post_hour']) ? (int) $settings['bwp_first_post_hour'] : 0;
        $first_min  = isset($settings['bwp_first_post_minute']) ? (int) $settings['bwp_first_post_minute'] : 0;

        $interval_seconds = max(1, $frequency) * HOUR_IN_SECONDS;

        add_filter('cron_schedules', static function ($schedules) use ($interval_seconds, $frequency) {
            $schedules[self::INTERVAL] = [
                'interval' => $interval_seconds,
                'display'  => sprintf( /* translators: %d interval hrs */__('Every %d hour(s)', 'bwpp'), $frequency),
            ];
            return $schedules;
        });

        $tz = ($tz_string = get_option('timezone_string')) ? new DateTimeZone($tz_string) : null;
        $now = current_time('timestamp');

        $dt = new DateTime('now', $tz);
        $dt->setTime($first_hour, $first_min, 0);
        $first_post = $dt->getTimestamp();
        while ($first_post <= $now) {
            $first_post += $interval_seconds;
        }

        wp_schedule_event($first_post, self::INTERVAL, self::HOOK);
    }

    public static function clear(): void
    {
        if ($timestamp = wp_next_scheduled(self::HOOK)) {
            wp_unschedule_event($timestamp, self::HOOK);
        }
    }

    /* --------------------------------------------------------------------- */
    /* –– WEATHER POST ––                                                    */
    /* --------------------------------------------------------------------- */

    public static function post_weather_update(): void
    {
        if (! class_exists(ClientrawParser::class) || ! class_exists(BlueskyPoster::class)) {
            return;
        }

        $settings      = get_option('bwpp_settings', []);
        $clientraw_url = $settings['bwp_clientraw_url'] ?? '';
        if (empty($clientraw_url)) {
            return;
        }

        $station_url = $settings['bwp_station_url'] ?? '';
        $frequency   = isset($settings['bwp_post_frequency']) ? (int) $settings['bwp_post_frequency'] : 1;
        $max_age     = $frequency * HOUR_IN_SECONDS;

        $last_modified = wp_remote_retrieve_header(wp_remote_head($clientraw_url, ['timeout' => 10]), 'last-modified');
        if ($last_modified && (time() - strtotime($last_modified)) > $max_age) {
            $status = sprintf(__('No new updates – clientraw.txt is %d hour(s) old.', 'bwpp'), round((time() - strtotime($last_modified)) / HOUR_IN_SECONDS));
            BlueskyPoster::post_status($status);
            return;
        }

        $parser = new ClientrawParser($clientraw_url);
        if (! ($data = $parser->get_weather_data())) {
            return;
        }

        $post = Formatter::format_weather_output_with_facets($data, $station_url, $settings);
        BlueskyPoster::post_status($post['text'], $post['facets'] ?? [], $post['embed'] ?? null);

        update_option('bwpp_last_post_time', current_time('timestamp'), false);
    }

    /* --------------------------------------------------------------------- */

    public static function maybe_attach_settings_listener(): void
    {
        static $attached = false;
        if (! $attached) {
            add_action('update_option_bwpp_settings', [__CLASS__, 'register']);
            $attached = true;
        }
    }
}

Cron::maybe_attach_settings_listener();
