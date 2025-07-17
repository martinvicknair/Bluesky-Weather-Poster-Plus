<?php

/**
 * Downloads and parses **Weather‑Display** *clientraw.txt* files.
 *
 * @link https://www.weather-watch.com/smf/index.php/topic,146465.0.html (spec)
 *
 * The file contains a single line of ~1600 space‑delimited values.  For our
 * purposes we map only the handful of fields required for posting to Bluesky
 * (temperature, wind, humidity, pressure, rain, description).  Additional
 * fields can easily be added by updating the `$map` below.
 *
 * @package BWPP\Core
 */

declared('ABSPATH') || exit;

namespace BWPP\Core;

use WP_Error;

final class ClientrawParser
{

    /** URL to the remote *clientraw.txt*. */
    private string $url;

    /** Parsed data cache. */
    private array $data = [];

    /**
     * @param string $url Public URL to *clientraw.txt* (must be HTTP/HTTPS).
     */
    public function __construct(string $url)
    {
        $this->url = esc_url_raw($url);
    }

    /* --------------------------------------------------------------------- */
    /* –– PUBLIC API ––                                                      */
    /* --------------------------------------------------------------------- */

    /**
     * Return an associative array of weather metrics or `false` on failure.
     *
     * Keys returned (all numeric values are *floats*):
     *  - temperature  (°C)
     *  - wind_direction (°)
     *  - wind_speed   (knots)
     *  - humidity     (%)
     *  - pressure     (hPa)
     *  - rain_today   (mm)
     *  - weather_desc (string)
     *
     * @return array|false
     */
    public function get_weather_data()
    {
        if (! empty($this->data)) {
            return $this->data;
        }

        $raw = $this->download();
        if (is_wp_error($raw)) {
            return false;
        }

        $parts = explode(' ', trim($raw));
        if (count($parts) < 50) {
            return false; // Not enough fields.
        }

        // Map raw positions ➜ named keys. See spec for full index list.
        $map = [
            'temperature'   => 4,  // Outdoor temperature (°C)
            'wind_direction' => 3,  // Wind direction (degrees)
            'wind_speed'    => 1,  // Wind speed (knots)
            'humidity'      => 5,  // Outdoor humidity (%)
            'pressure'      => 6,  // Barometer (hPa)
            'rain_today'    => 7,  // Rain today (mm)
            'weather_desc'  => 49, // Description text
        ];

        foreach ($map as $key => $index) {
            $value = $parts[$index] ?? null;
            // Cast numeric values.
            if (in_array($key, ['temperature', 'wind_direction', 'wind_speed', 'humidity', 'pressure', 'rain_today'], true)) {
                $value = is_numeric($value) ? 0 + $value : null; // Force numeric or null.
            }
            $this->data[$key] = $value;
        }

        return $this->data;
    }

    /* --------------------------------------------------------------------- */
    /* –– STATIC HELPERS ––                                                  */
    /* --------------------------------------------------------------------- */

    /**
     * Converts degrees to 16‑point compass (N, NNE, …).
     *
     * @param float|int $degrees
     */
    public static function degrees_to_compass($degrees): string
    {
        $dirs    = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
        $degrees = (float) $degrees;
        return $dirs[((int) round($degrees / 22.5)) % 16];
    }

    /* --------------------------------------------------------------------- */
    /* –– INTERNALS ––                                                       */
    /* --------------------------------------------------------------------- */

    /**
     * Download the remote file with WP‑HTTP, returning raw string content or
     * WP_Error on failure.
     */
    private function download()
    {
        if (empty($this->url)) {
            return new WP_Error('bwpp_invalid_url', __('Invalid clientraw.txt URL', 'bwpp'));
        }

        $response = wp_remote_get($this->url, [
            'timeout' => 10,
            'user-agent' => 'BWPP/' . (defined('BWPP_VERSION') ? BWPP_VERSION : 'dev'),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            return new WP_Error('bwpp_http_error', sprintf(__('clientraw.txt HTTP error: %d', 'bwpp'), $code));
        }

        $body = wp_remote_retrieve_body($response);
        if (! is_string($body) || '' === $body) {
            return new WP_Error('bwpp_empty_body', __('Empty response from clientraw.txt', 'bwpp'));
        }

        return $body;
    }
}
