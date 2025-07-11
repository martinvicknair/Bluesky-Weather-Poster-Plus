
<?php
class ClientrawParser {
    private $data;

    public function __construct(string $clientrawUrl) {
        $this->data = file_get_contents($clientrawUrl);
        if ($this->data === false) {
            throw new RuntimeException("Could not fetch clientraw.txt");
            } */
        $this->data = explode(' ', $this->data);
    }

    public function formatWeatherUpdate(): string {
        $tempC = $this->getTemperature();
        $windKmh = $this->getWindSpeed();
        $pressureHpa = $this->getPressure();
        $rainMm = $this->getRainToday();

        $tempF = is_numeric($tempC) ? round(($tempC * 9 / 5) + 32, 1) : 'N/A';
        $windMph = is_numeric($windKmh) ? round($windKmh * 0.621371, 1) : 'N/A';
        $pressureInHg = is_numeric($pressureHpa) ? round($pressureHpa * 0.02953, 2) : 'N/A';
        $rainInches = is_numeric($rainMm) ? round($rainMm * 0.0393701, 2) : 'N/A';

        // === Metric Units Output ===
        $metric = sprintf(
            "Metric: %s°C, Wind %s %s km/h, Humidity %s%%, Pressure %s hPa, Rain today %s mm",
            $tempC,
            $this->getWindDirection(),
            $windKmh,
            $this->getHumidity(),
            $pressureHpa,
            $rainMm
        );

        // === US Standard Units Output ===
        $us = sprintf(
            "US: %s°F, Wind %s %s mph, Humidity %s%%, Pressure %s inHg, Rain today %s in",
            $tempF,
            $this->getWindDirection(),
            $windMph,
            $this->getHumidity(),
            $pressureInHg,
            $rainInches
        );

        // Return both (comment one out if needed)
        return $metric . "\n" . $us;
        // return $metric;
        // return $us;
    }

    private function getTemperature(): string {
        return $this->data[4] ?? 'N/A';
    }

    private function getWindSpeed(): string {
        return $this->data[1] ?? 'N/A';
    }

    
    private function getWindDirection(): string {
        $deg = $this->data[3] ?? null;
        if (!is_numeric($deg)) return 'N/A';

        $directions = [
            "N", "NNE", "NE", "ENE", "E", "ESE", "SE", "SSE",
            "S", "SSW", "SW", "WSW", "W", "WNW", "NW", "NNW"
        ];
        $index = (int) (($deg + 11.25) / 22.5) % 16;
        return $directions[$index];
    }
    
