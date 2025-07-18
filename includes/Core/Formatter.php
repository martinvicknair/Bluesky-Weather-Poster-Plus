<?php

/**
 * File: includes/Core/Formatter.php
 * Turns parsed weather data + user settings into a Bluesky-ready payload.
 *
 * Returns an array with:
 *   [
 *     'text'   => 'string',             // post body
 *     'facets' => [ … ],                // hashtag facets (optional)
 *     'embed'  => [ … ] | null,         // external or image embed
 *   ]
 *
 * @package BWPP\Core
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

final class Formatter
{

    /*------------------------------------------------------------------*/
    /* Public API                                                       */
    /*------------------------------------------------------------------*/

    /**
     * Build the Bluesky record content.
     *
     * @param array $wx           Parsed clientraw array.
     * @param array $settings     Raw bwpp_settings option.
     * @return array{ text:string, facets:array<int,mixed>, embed:array<string,mixed>|null }
     */
    public static function build(array $wx, array $settings): array
    {

        $prefix   = $settings['bwp_post_prefix'] ?? 'Current conditions:';
        $units    = $settings['bwp_units'] ?? 'both';
        $hashtags = self::prepare_hashtags($settings['bwp_hashtags'] ?? '');
        $include  = $settings['bwp_content_fields'] ?? ['temp' => 1]; // at least temp
        $station_url = $settings['bwp_station_url'] ?? '';
        $station_txt = $settings['bwp_station_text'] ?? '';

        // ------------------------------------------------------------------
        // 1. Data line
        // ------------------------------------------------------------------
        $parts = [];

        // Temperature block
        if (! empty($include['temp']) && isset($wx['temp_c'], $wx['temp_f'])) {
            $parts[] = self::format_temp($wx, $units);
        }
        if (! empty($include['windchill']) && isset($wx['windchill_c'])) {
            $parts[] = sprintf(
                _x('Feels like %s', 'windchill', 'bwpp'),
                self::unit_select($wx['windchill_c'], $wx['windchill_f'], $units, 1)
            );
        }
        if (! empty($include['dew']) && isset($wx['dew_c'])) {
            $parts[] = sprintf(
                _x('Dew pt %s', 'dew point', 'bwpp'),
                self::unit_select($wx['dew_c'], $wx['dew_f'], $units, 1)
            );
        }
        if (! empty($include['humidex']) && isset($wx['humidex'])) {
            $parts[] = sprintf(
                _x('Humidex %d', 'humidex', 'bwpp'),
                round($wx['humidex'])
            );
        }

        // Max/min temps today
        if (! empty($include['temp_max']) && isset($wx['temp_max_c'])) {
            $parts[] = sprintf(
                _x('↑%s', 'max temperature', 'bwpp'),
                self::unit_select($wx['temp_max_c'], $wx['temp_max_f'], $units, 1)
            );
        }
        if (! empty($include['temp_min']) && isset($wx['temp_min_c'])) {
            $parts[] = sprintf(
                _x('↓%s', 'min temperature', 'bwpp'),
                self::unit_select($wx['temp_min_c'], $wx['temp_min_f'], $units, 1)
            );
        }

        // Wind
        if (! empty($include['wind_dir']) && isset($wx['wind_dir_txt'])) {
            $parts[] = sprintf(
                _x('Wind %s', 'wind direction', 'bwpp'),
                $wx['wind_dir_txt']
            );
        }
        if (! empty($include['wind_speed']) && isset($wx['wind_kts'])) {
            $parts[] = sprintf(
                _x('%s kts', 'wind speed', 'bwpp'),
                round($wx['wind_kts'])
            );
        }
        if (! empty($include['wind_gust']) && isset($wx['gust_kts'])) {
            $parts[] = sprintf(
                _x('Gust %s kts', 'wind gust', 'bwpp'),
                round($wx['gust_kts'])
            );
        }

        // Other
        if (! empty($include['humidity']) && isset($wx['humidity'])) {
            $parts[] = sprintf(
                _x('RH %d%%', 'relative humidity', 'bwpp'),
                round($wx['humidity'])
            );
        }
        if (! empty($include['pressure']) && isset($wx['pressure_hpa'])) {
            $parts[] = sprintf(
                _x('Pressure %.1f hPa', 'pressure', 'bwpp'),
                $wx['pressure_hpa']
            );
        }
        if (! empty($include['rain']) && isset($wx['rain_mm'])) {
            $parts[] = sprintf(
                _x('Rain %.1f mm', 'rain today', 'bwpp'),
                $wx['rain_mm']
            );
        }
        if (! empty($include['desc']) && isset($wx['wx_desc'])) {
            $parts[] = $wx['wx_desc'];
        }

        // ------------------------------------------------------------------
        // Assemble full text
        // ------------------------------------------------------------------
        $text = trim($prefix) . ' ' . implode(' · ', $parts);

        // Append station link
        if ($station_url) {
            $link_text = $station_txt ?: $station_url;
            $text     .= ' ' . $link_text . ' ' . $station_url;
        }

        // Append hashtags
        if ($hashtags) {
            $text .= ' ' . implode(' ', $hashtags);
        }

        // Facets for tags
        $facets = self::build_facets($text, $hashtags);

        // ------------------------------------------------------------------
        // Optional webcam image embed
        // ------------------------------------------------------------------
        $embed = null;
        if (! empty($settings['bwp_webcam_url'])) {
            $embed = [
                '$type'   => 'app.bsky.embed.external',
                'external' => [
                    'uri'         => $settings['bwp_webcam_url'],
                    'title'       => $settings['bwp_webcam_alt'] ?: __('Webcam snapshot', 'bwpp'),
                    'description' => '',
                ],
            ];
        }

        // Ensure length ≤ 300. Simple hard truncate (safe because hashtags added last).
        if (mb_strlen($text) > 300) {
            $text = mb_substr($text, 0, 297) . '…';
        }

        return compact('text', 'facets', 'embed');
    }

    /*------------------------------------------------------------------*/
    /* Helpers                                                           */
    /*------------------------------------------------------------------*/

    private static function prepare_hashtags(string $raw): array
    {
        $tags = array_filter(array_map('trim', explode(',', $raw)));
        $tags = array_map(static function ($t) {
            $t = ltrim($t, '#');
            return '#' . preg_replace('/\s+/', '', $t);
        }, $tags);
        return $tags;
    }

    private static function build_facets(string $body, array $tags): array
    {
        $facets = [];
        foreach ($tags as $tag) {
            $pos = mb_strpos($body, $tag);
            if (false === $pos) {
                continue;
            }
            $facets[] = [
                'index' => ['byteStart' => $pos, 'byteEnd' => $pos + mb_strlen($tag)],
                'features' => [
                    ['$type' => 'app.bsky.richtext.facet#tag', 'tag' => ltrim($tag, '#')],
                ],
            ];
        }
        return $facets;
    }

    private static function format_temp(array $wx, string $units): string
    {
        return sprintf(
            '%s',
            self::unit_select($wx['temp_c'], $wx['temp_f'], $units, 1)
        );
    }

    private static function unit_select($c, $f, string $units, int $dec = 0): string
    {
        switch ($units) {
            case 'metric':
                return sprintf('%.' . $dec . 'f °C', $c);
            case 'imperial':
                return sprintf('%.' . $dec . 'f °F', $f);
            default: // both
                return sprintf('%.' . $dec . 'f °C / %.' . $dec . 'f °F', $c, $f);
        }
    }
}
