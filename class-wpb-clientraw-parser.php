<?php

/**
 * Weather Poster Bluesky - Clientraw.txt Parser
 *
 * Responsible for downloading and parsing the clientraw.txt weather data file.
 * Converts the raw data into a structured array suitable for social posting.
 *
 * @package    Weather Poster Bluesky
 * @subpackage Parsers
 * @author     Your Name
 * @since      1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Class WPB_Clientraw_Parser
 *
 * Usage:
 * $parser = new WPB_Clientraw_Parser();
 * $data = $parser->parse('https://your-site.com/clientraw.txt');
 */
class WPB_Clientraw_Parser
{

    /**
     * Downloads and parses the clientraw.txt data.
     *
     * @param string $url URL to the remote clientraw.txt file.
     * @return array|false Structured weather data array or false on failure.
     */
    public function parse($url)
    {
        // Download the file; suppress warnings if file doesn't exist.
        $raw = @file_get_contents($url);
        if (!$raw) {
            return false; // Return false if download fails.
        }
        // Split the contents by spaces, as per the clientraw.txt spec.
        $data = explode(' ', trim($raw));
        if (count($data) < 50) {
            return false; // Not enough data fields.
        }

        // Map commonly used fields to readable keys.
        return [
            'temp'       => isset($data[4])  ? (float)$data[4]  : null,  // Outdoor temperature (°C)
            'wind_dir'   => isset($data[3])  ? (int)$data[3]    : null,  // Wind direction (degrees)
            'wind_speed' => isset($data[1])  ? (float)$data[1]  : null,  // Wind speed (kts)
            'humidity'   => isset($data[5])  ? (int)$data[5]    : null,  // Outdoor humidity (%)
            'pressure'   => isset($data[6])  ? (float)$data[6]  : null,  // Barometer (hPa)
            'rain'       => isset($data[7])  ? (float)$data[7]  : null,  // Rain today (mm)
            'desc'       => isset($data[49]) ? $data[49]        : '',    // Weather description
        ];
    }

    /**
     * Helper to convert wind direction in degrees to compass (e.g., "N", "SW").
     *
     * @param int|float $degrees
     * @return string Compass direction (e.g. "ESE").
     */
    public static function degrees_to_compass($degrees)
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
        $degrees = floatval($degrees);
        return $dirs[round($degrees / 22.5) % 16];
    }
}
// End of class WPB_Clientraw_Parser