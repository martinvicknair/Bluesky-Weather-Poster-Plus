<?php

/**
 * File: includes/Core/ClientrawParser.php
 * Fetches & converts Weather-Display “clientraw.txt” into a tidy array that
 * Formatter understands.  Results are cached (transient) for 5 min so repeated
 * AJAX previews don’t hammer the URL.
 *
 * @package BWPP\Core
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

final class ClientrawParser
{

    /*--------------------------------------------------------------*/
    /* Public API                                                   */
    /*--------------------------------------------------------------*/

    /**
     * Download + parse clientraw.txt.
     *
     * @param string $url Full URL to clientraw.txt
     * @return array|false  Parsed associative array, or false on failure.
     */
    public static function fetch(string $url)
    {

        if (empty($url)) {
            return false;
        }

        $key   = 'bwpp_clientraw_' . md5($url);
        $cache = get_transient($key);
        if ($cache) {
            return $cache;
        }

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'user-agent' => 'BWPP/1.0 (+https://wordpress.org)',
        ]);

        if (
            is_wp_error($response)
            || wp_remote_retrieve_response_code($response) !== 200
        ) {
            return false;
        }

        $body = trim(wp_remote_retrieve_body($response));
        $parts = preg_split('/\s+/', $body);
        if (count($parts) < 200) {         // clientraw has 200+ fields
            return false;
        }

        $data = self::map_fields($parts);

        // 5-minute cache
        set_transient($key, $data, 5 * MINUTE_IN_SECONDS);

        return $data;
    }

    /*--------------------------------------------------------------*/
    /* Internal helpers                                             */
    /*--------------------------------------------------------------*/

    /**
     * Convert raw numeric slots into friendly keys the Formatter expects.
     *
     * Weather-Display clientraw index reference:
     *   3  = temp °F,  4 = humidity %,  1 = wind mph, 166 = gust mph today,
     *   0  = wind direction deg,  5 = pressure hPa,  7 = rain today inches,
     * 147  = dew °F,  32/33 = max/min temp F today, 44 = weather description.
     *
     * @param string[] $p Row array.
     * @return array
     */
    private static function map_fields(array $p): array
    {

        $temp_f = (float) $p[3];
        $temp_c = ($temp_f - 32) * 5 / 9;

        $dew_f = (float) $p[147];
        $dew_c = ($dew_f - 32) * 5 / 9;

        $dir_deg = (int) $p[0];

        return [
            // Temperatures
            'temp_c'      => round($temp_c, 1),
            'temp_f'      => round($temp_f, 1),
            'dew_c'       => round($dew_c, 1),
            'dew_f'       => round($dew_f, 1),
            'temp_max_c'  => round(((float) $p[32] - 32) * 5 / 9, 1),
            'temp_max_f'  => (float) $p[32],
            'temp_min_c'  => round(((float) $p[33] - 32) * 5 / 9, 1),
            'temp_min_f'  => (float) $p[33],

            // Wind
            'wind_dir_deg' => $dir_deg,
            'wind_dir_txt' => self::degrees_to_compass($dir_deg),
            'wind_kts'     => round((float) $p[1] * 0.868976, 1),
            'gust_kts'     => round((float) $p[166] * 0.868976, 1),

            // Others
            'humidity'     => (int) $p[4],
            'pressure_hpa' => round((float) $p[5], 1),
            'rain_mm'      => round((float) $p[7] * 25.4, 1),
            'wx_desc'      => sanitize_text_field($p[44]),
        ];
    }

    private static function degrees_to_compass(int $deg): string
    {
        $dirs = [
            'N',
            'NNE',
            'NE',
            'ENE',
            'E',
            'ESE',
            'SE',
            'SSE',
            'S',
            'SSW',
            'SW',
            'WSW',
            'W',
            'WNW',
            'NW',
            'NNW'
        ];
        return $dirs[(int) floor(($deg / 22.5) + 0.5) % 16];
    }
}
// EOF
