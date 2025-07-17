<?php

/**
 * File: includes/Admin/Ajax.php
 * AJAX endpoints for the Settings screen.
 * - bwpp_char_count   → returns post length.
 * - bwpp_live_preview → returns formatted status preview.
 * Requires `manage_options` capability and a nonce.
 *
 * @package BWPP\Admin
 */

namespace BWPP\Admin;

defined('ABSPATH') || exit;

use BWPP\Core\Formatter;

final class Ajax
{

    public function __construct()
    {
        add_action('wp_ajax_bwpp_char_count', [$this, 'char_count']);
        add_action('wp_ajax_bwpp_live_preview', [$this, 'live_preview']);
    }

    /** Character count endpoint. */
    public function char_count(): void
    {
        check_ajax_referer('bwpp_settings_nonce');
        $this->require_cap();

        $draft    = $_POST['draft'] ?? [];
        $settings = array_merge(get_option(Settings::OPTION_KEY, []), $draft);

        $formatted = Formatter::format_weather_output_with_facets($this->dummy_weather(), '', $settings);
        wp_send_json_success(['length' => mb_strlen($formatted['text'])]);
    }

    /** Live preview endpoint. */
    public function live_preview(): void
    {
        check_ajax_referer('bwpp_settings_nonce');
        $this->require_cap();

        $draft    = $_POST['draft'] ?? [];
        $settings = array_merge(get_option(Settings::OPTION_KEY, []), $draft);

        $formatted = Formatter::format_weather_output_with_facets($this->dummy_weather(), '', $settings);
        wp_send_json_success(['text' => $formatted['text']]);
    }

    /* Helpers ----------------------------------------------------------------- */

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
