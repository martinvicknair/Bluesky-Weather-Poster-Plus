<?php
class ClientrawParser {
    private $data;

    public function __construct(string $clientrawUrl) {
        $this->data = file_get_contents($clientrawUrl);
        if ($this->data === false) {
            throw new RuntimeException("Could not fetch clientraw.txt");
        }
        $this->data = explode(' ', $this->data);
    }

    public function formatWeatherUpdate(): string {
        return sprintf(
            "Current conditions: %s°C, Wind %s %s km/h, Humidity %s%%, Pressure %s hPa, Rain today %s mm",
            $this->getTemperature(),
            $this->getWindDirection(),
            $this->getWindSpeed(),
            $this->getHumidity(),
            $this->getPressure(),
            $this->getRainToday()
        );
    }

    private function getTemperature(): string {
        return $this->data[4] ?? 'N/A';
    }

    private function getWindSpeed(): string {
        return $this->data[1] ?? 'N/A';
    }

    private function getWindDirection(): string {
        return $this->data[3] ?? 'N/A';
    }

    private function getHumidity(): string {
        return $this->data[5] ?? 'N/A';
    }

    private function getPressure(): string {
        return $this->data[6] ?? 'N/A';
    }

    private function getRainToday(): string {
        return $this->data[7] ?? 'N/A';
    }
}
