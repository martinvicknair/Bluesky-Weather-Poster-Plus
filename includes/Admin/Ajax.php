<?php

/**
 * AJAX endpoints used by the Settings screen.
 *
 * - `bwpp_char_count`   → returns post length after formatting, so we can warn
 *                         users when approaching Bluesky’s ~300‑char limit.
 * - `bwpp_live_preview` → returns the full formatted status *as it will be sent*
 *                         so the user can check appearance.
 *
 * Both actions are authenticated (current user must `manage_options`) and use
 * nonces. The simple JS that calls these endpoints will be enqueued later
 * inside Settings::render() or via an admin_enqueue_scripts hook.
 *
 * @package BWPP\Admin
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

use BWPP\Core\Formatter; // TBD

final class Ajax
{

    public function __construct()
    {
        add_action('wp_ajax_bwpp_char_count', [$this, 'char_count']);
        add_action('wp_ajax_bwpp_live_preview', [$this, 'live_preview']);
    }

    /* --------------------------------------------------------------------- */
    /* –– CALLBACKS ––                                                       */
    /* --------------------------------------------------------------------- */

    /**
     * Return character count for the currently saved (or draft) settings.
     */
    public function char_count(): void
    {
        check_ajax_referer('bwpp_settings_nonce');
        $this->require_cap();

        // For live editing we accept draft values in POST; fall back to saved.
        $draft = $_POST['draft'] ?? [];
        $settings = array_merge(get_option(Settings::OPTION_KEY, []), $draft);

        $formatted = Formatter::format_weather_output_with_facets($this->dummy_weather(), '', $settings);
        wp_send_json_success(['length' => mb_strlen($formatted['text'])]);
    }

    /**
     * Return the fully formatted status string so the admin can preview it.
     */
    public function live_preview(): void
    {
        check_ajax_referer('bwpp_settings_nonce');
        $this->require_cap();

        $draft    = $_POST['draft'] ?? [];
        $settings = array_merge(get_option(Settings::OPTION_KEY, []), $draft);

        $formatted = Formatter::format_weather_output_with_facets($this->dummy_weather(), '', $settings);
        wp_send_json_success(['text' => $formatted['text']]);
    }

    /* --------------------------------------------------------------------- */
    /* –– HELPERS ––                                                         */
    /* --------------------------------------------------------------------- */

    private function dummy_weather(): array
    {
        return [
            'temperature'    => 21.5,
            'wind_direction' => 180,
            'wind_speed'     => 5,
            'humidity'       => 55,
            'pressure'       => 1017,
            'rain_today'     => 0.0,
            'weather_desc'   => __('Clear', 'bwpp'),
        ];
    }

    private function require_cap(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Forbidden', 'bwpp')], 403);
        }
    }
}
