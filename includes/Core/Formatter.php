<?php

/**
 * Converts parsed weather data into a Bluesky-ready post string + AT Proto
 * *facets* array (for hashtags, links) and optional *embed* block.
 *
 * @package BWPP\Core
 */

declared('ABSPATH') || exit;

namespace BWPP\Core;

final class Formatter
{

    /** Approximate max length Bluesky allows (there’s no strict doc yet). */
    private const MAX_LEN = 300;

    /**
     * Build text + facets from data and user settings.
     *
     * @param array       $data         Parsed clientraw values.
     * @param string|null $station_url  Link to user’s weather page (optional).
     * @param array       $settings     Settings array (already sanitized).
     *
     * @return array{ text:string, facets?:array, embed?:array }
     */
    public static function format_weather_output_with_facets(array $data, ?string $station_url = '', array $settings = []): array
    {
        // Defaults come from Settings sanitize() when missing.
        $units  = $settings['bwp_units']       ?? 'both';
        $prefix = trim($settings['bwp_post_prefix'] ?? __('Weather Update', 'bwpp'));
        $tags   = $settings['bwp_hashtags']    ?? '';

        /* ----------------------------------------------------------------- */
        /* –– UNIT CONVERSIONS ––                                            */
        /* ----------------------------------------------------------------- */
        $c    = $data['temperature']    ?? null;
        $kts  = $data['wind_speed']     ?? null;
        $hPa  = $data['pressure']       ?? null;
        $mm   = $data['rain_today']     ?? null;

        // Imperial conversions.
        $f    = null === $c   ? null : ($c * 9 / 5) + 32;
        $mph  = null === $kts ? null : $kts * 1.15078;
        $inhg = null === $hPa ? null : $hPa * 0.02953;
        $in   = null === $mm  ? null : $mm * 0.0393701;

        // Wind direction.
        $dir  = isset($data['wind_direction']) ? ClientrawParser::degrees_to_compass($data['wind_direction']) : null;

        /* ----------------------------------------------------------------- */
        /* –– STRING ASSEMBLY ––                                             */
        /* ----------------------------------------------------------------- */
        $parts = [];
        if ($units === 'metric' || $units === 'both') {
            if (null !== $c) {
                $parts[] = sprintf(__('Temp: %.1f°C', 'bwpp'), $c);
            }
            if (null !== $kts) {
                $parts[] = sprintf(__('Wind: %.0f kt', 'bwpp'), $kts) . ($dir ? " $dir" : '');
            }
            if (null !== $hPa) {
                $parts[] = sprintf(__('Pres: %.0f hPa', 'bwpp'), $hPa);
            }
            if (null !== $mm) {
                $parts[] = sprintf(__('Rain: %.1f mm', 'bwpp'), $mm);
            }
        }
        if ($units === 'imperial' || $units === 'both') {
            $imperial = [];
            if (null !== $f) {
                $imperial[] = sprintf(__('%.1f°F', 'bwpp'), $f);
            }
            if (null !== $mph) {
                $imperial[] = sprintf(__('%.0f mph', 'bwpp'), $mph);
            }
            if (null !== $inhg) {
                $imperial[] = sprintf(__('%.2f inHg', 'bwpp'), $inhg);
            }
            if (null !== $in) {
                $imperial[] = sprintf(__('%.2f in', 'bwpp'), $in);
            }
            if ($imperial) {
                $parts[] = '(' . implode(' | ', $imperial) . ')';
            }
        }

        // Humidity and description are unit-agnostic.
        if (isset($data['humidity'])) {
            $parts[] = sprintf(__('RH: %d%%', 'bwpp'), (int) $data['humidity']);
        }
        if (! empty($data['weather_desc'])) {
            $parts[] = $data['weather_desc'];
        }

        // Collect tags: convert comma-separated list to #tag format, ensure spaces.
        $tag_str = '';
        if ($tags) {
            $tags_arr = array_filter(array_map('trim', explode(',', $tags)));
            if ($tags_arr) {
                $tag_str = ' ' . implode(' ', array_map(static fn($t) => ('#' === $t[0] ? $t : '#' . $t), $tags_arr));
            }
        }

        // Build main text.
        $text = trim($prefix . ': ' . implode(', ', $parts) . $tag_str);

        // Ensure length <= MAX_LEN, naive clip if needed.
        if (mb_strlen($text) > self::MAX_LEN) {
            $text = mb_substr($text, 0, self::MAX_LEN - 1) . '…';
        }

        /* Facets & embed --------------------------------------------------- */
        $facets = [];
        $embed  = null;

        // Hashtags facets (Bluesky needs start/end byte offsets).
        if ($tag_str) {
            // Find each #tag in the text.
            preg_match_all('/#\w+/u', $text, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as [$tag, $offset]) {
                $facets[] = [
                    'index' => ['byteStart' => $offset, 'byteEnd' => $offset + strlen($tag)],
                    'features' => [
                        ['$type' => 'app.bsky.richtext.facet#tag', 'tag' => ltrim($tag, '#')],
                    ],
                ];
            }
        }

        // Station URL as external embed (if provided).
        if ($station_url) {
            $embed = [
                '$type' => 'app.bsky.embed.external',
                'external' => [
                    'uri'         => esc_url_raw($station_url),
                    'title'       => __('Weather Station', 'bwpp'),
                    'description' => __('Full data & charts', 'bwpp'),
                ],
            ];
        }

        return compact('text', 'facets', 'embed');
    }
}
