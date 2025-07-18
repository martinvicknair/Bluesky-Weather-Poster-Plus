<?php

/**
 * File: includes/Core/Cron.php
 * Scheduler – registers WP-Cron job based on frequency preset & first run time.
 *
 * @package BWPP\Core
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

final class Cron
{

    public const EVENT_HOOK = 'bwpp/post_weather_update';

    /*--------------------------------------------------------------*/
    /* Public API                                                   */
    /*--------------------------------------------------------------*/

    /**
     * (Re)register the cron event.
     *
     * @param array|null $settings Sanitized settings array, or null to pull fresh.
     */
    public static function register(?array $settings = null): void
    {

        if (null === $settings) {
            $settings = get_option(\BWPP\Admin\Settings::OPTION_KEY, []);
        }

        // Clear any existing schedule.
        wp_clear_scheduled_hook(self::EVENT_HOOK);

        // ── Frequency preset (1 | 3 | 6 | 12 | 24 hours) ─────────────────
        $hours = (int) ($settings['bwp_freq_preset'] ?? 1);
        if (! in_array($hours, [1, 3, 6, 12, 24], true)) {
            $hours = 1;
        }
        $interval_slug = 'bwpp_custom_' . $hours . 'h';

        // ── First-run timestamp ──────────────────────────────────────────
        $hour = (int) ($settings['bwp_first_post_hour'] ?? 0);
        $min  = (int) ($settings['bwp_first_post_minute'] ?? 0);
        $first = strtotime(sprintf('%02d:%02d', $hour, $min), current_time('timestamp'));
        if ($first <= time()) {
            $first += $hours * HOUR_IN_SECONDS;
        }

        wp_schedule_event($first, $interval_slug, self::EVENT_HOOK);
    }

    /**
     * Execute the post.
     */
    public static function post_weather_update(): void
    {

        $settings = get_option(\BWPP\Admin\Settings::OPTION_KEY, []);
        $data     = ClientrawParser::fetch($settings['bwp_clientraw_url'] ?? '');

        if (! $data) {
            return; // Could log error here.
        }

        $payload = Formatter::build($data, $settings);
        BlueskyPoster::send($payload, $settings);
    }

    /*--------------------------------------------------------------*/
    /* Custom intervals filter                                      */
    /*--------------------------------------------------------------*/

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
// EOF
