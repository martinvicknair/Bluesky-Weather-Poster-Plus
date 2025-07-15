<?php
class Clientraw_Parser {
    private $data;

    public function __construct(string $clientrawUrl) {
        $this->data = @file_get_contents($clientrawUrl);
        if ($this->data === false) {
            throw new RuntimeException("Could not fetch clientraw.txt");
        }
        $this->data = explode(' ', $this->data);
    }

    /**
     * Returns an associative array of all key weather fields, for plugin formatting
     */
    public function get_weather_data() {
        return [
            // Main
            'temperature'      => isset($this->data[4])  ? floatval($this->data[4])  : null,
            'wind_direction'   => isset($this->data[3])  ? floatval($this->data[3])  : null,
            'wind_speed'       => isset($this->data[2])  ? floatval($this->data[2])  : null, // current windspeed (knots)
            'humidity'         => isset($this->data[5])  ? intval($this->data[5])    : null,
            'pressure'         => isset($this->data[6])  ? floatval($this->data[6])  : null, // hPa
            'rain_today'       => isset($this->data[7])  ? floatval($this->data[7])  : null, // mm

            // Temperature & Feel
            'windchill'        => isset($this->data[44]) ? floatval($this->data[44]) : null, // °C
            'humidex'          => isset($this->data[45]) ? floatval($this->data[45]) : null, // °C
            'max_temp'         => isset($this->data[46]) ? floatval($this->data[46]) : null, // °C
            'min_temp'         => isset($this->data[47]) ? floatval($this->data[47]) : null, // °C
            'dew_point'        => isset($this->data[72]) ? floatval($this->data[72]) : null, // °C

            // Wind
            'max_gust'         => isset($this->data[71]) ? floatval($this->data[71]) : null, // knots

            // Text
            'weather_desc'     => isset($this->data[49]) ? trim($this->data[49])     : null,
        ];
    }
}
