<?php

/**
 * File: includes/Core/Cron.php
 * Scheduler – registers WP-Cron event based on the “frequency preset” and
 * first-run hour/minute dropdowns.
 *
 * @package BWPP\Core
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

final class Cron
{

    private const EVENT_HOOK = 'bwpp/post_weather_update';

    /*------------------------------------------------------------------*/
    /* Public API                                                       */
    /*------------------------------------------------------------------*/

    /**
     * (Re)register the cron job when settings save or on activation.
     *
     * @param array|null $settings  Pass pre-sanitized settings when called
     *                              from Settings::sanitize(); otherwise it
     *                              fetches fresh from get_option().
     */
    public static function register(?array $settings = null): void
    {

        if (null === $settings) {
            $settings = get_option(Settings::OPTION_KEY, []);
        }

        // clear any prior job
        wp_clear_scheduled_hook(self::EVENT_HOOK);

        // interval in hours (preset 1|3|6|12|24)
        $hours = (int) ($settings['bwp_freq_preset'] ?? 1);
        if (! in_array($hours, [1, 3, 6, 12, 24], true)) {
            $hours = 1;
        }
        $interval = $hours * HOUR_IN_SECONDS;

        // first run timestamp (today at chosen HH:MM or next future occurrence)
        $h = (int) ($settings['bwp_first_post_hour'] ?? 0);
        $m = (int) ($settings['bwp_first_post_minute'] ?? 0);

        $first = strtotime(sprintf('%02d:%02d', $h, $m), current_time('timestamp'));
        if ($first <= time()) {
            $first += $interval;
        }

        wp_schedule_event($first, 'bwpp_custom_' . $hours . 'h', self::EVENT_HOOK);
    }

    /**
     * Hook callback – build and send the post.
     */
    public static function post_weather_update(): void
    {

        $settings = get_option(Settings::OPTION_KEY, []);
        $data     = ClientrawParser::fetch($settings['bwp_clientraw_url'] ?? '');

        if (! $data) {
            return; // optionally log failure
        }

        $payload = Formatter::build($data, $settings);
        BlueskyPoster::send($payload, $settings);
    }

    /*------------------------------------------------------------------*/
    /* Activation / interval filter                                     */
    /*------------------------------------------------------------------*/

    public static function add_custom_schedules(array $schedules): array
    {
        foreach ([1, 3, 6, 12, 24] as $h) {
            $schedules['bwpp_custom_' . $h . 'h'] = [
                'interval' => $h * HOUR_IN_SECONDS,
                'display'  => sprintf(__('Every %d hours', 'bwpp'), $h),
            ];
        }
        return $schedules;
    }
}
