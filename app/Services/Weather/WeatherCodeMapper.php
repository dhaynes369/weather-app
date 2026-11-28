<?php

namespace App\Services\Weather;

final class WeatherCodeMapper
{
    /**
     * @var array<int, array{description: string, icon: string}>
     */
    private const CONDITIONS = [
        0 => ['description' => 'Clear sky', 'icon' => 'sun'],
        1 => ['description' => 'Mainly clear', 'icon' => 'cloud-sun'],
        2 => ['description' => 'Partly cloudy', 'icon' => 'cloud-sun'],
        3 => ['description' => 'Overcast', 'icon' => 'cloud'],

        45 => ['description' => 'Fog', 'icon' => 'cloud-fog'],
        48 => ['description' => 'Depositing rime fog', 'icon' => 'cloud-fog'],

        51 => ['description' => 'Light drizzle', 'icon' => 'cloud-drizzle'],
        53 => ['description' => 'Moderate drizzle', 'icon' => 'cloud-drizzle'],
        55 => ['description' => 'Dense drizzle', 'icon' => 'cloud-drizzle'],

        56 => ['description' => 'Light freezing drizzle', 'icon' => 'cloud-sleet'],
        57 => ['description' => 'Dense freezing drizzle', 'icon' => 'cloud-sleet'],

        61 => ['description' => 'Slight rain', 'icon' => 'cloud-rain'],
        63 => ['description' => 'Moderate rain', 'icon' => 'cloud-rain'],
        65 => ['description' => 'Heavy rain', 'icon' => 'cloud-rain-heavy'],

        66 => ['description' => 'Light freezing rain', 'icon' => 'cloud-sleet'],
        67 => ['description' => 'Heavy freezing rain', 'icon' => 'cloud-sleet'],

        71 => ['description' => 'Slight snowfall', 'icon' => 'cloud-snow'],
        73 => ['description' => 'Moderate snowfall', 'icon' => 'cloud-snow'],
        75 => ['description' => 'Heavy snowfall', 'icon' => 'cloud-snow'],
        77 => ['description' => 'Snow grains', 'icon' => 'cloud-snow'],

        80 => ['description' => 'Slight rain showers', 'icon' => 'cloud-rain'],
        81 => ['description' => 'Moderate rain showers', 'icon' => 'cloud-rain'],
        82 => ['description' => 'Violent rain showers', 'icon' => 'cloud-rain-heavy'],

        85 => ['description' => 'Slight snow showers', 'icon' => 'cloud-snow'],
        86 => ['description' => 'Heavy snow showers', 'icon' => 'cloud-snow'],

        95 => ['description' => 'Thunderstorm', 'icon' => 'cloud-lightning'],
        96 => ['description' => 'Thunderstorm with slight hail', 'icon' => 'cloud-hail'],
        99 => ['description' => 'Thunderstorm with heavy hail', 'icon' => 'cloud-hail'],
    ];

    /**
     * Map a WMO code and daylight flag to a description and icon, with an unknown fallback.
     *
     * @return array{description: string, icon: string}
     */
    public function map(
        int|float|null $code,
        bool $isDay = true,
    ): array {
        if (
            $code === null
            || ! is_finite((float) $code)
            || $code < 0
            || $code > 99
            || floor((float) $code) !== (float) $code
        ) {
            return $this->unknownCondition();
        }

        $condition = self::CONDITIONS[(int) $code]
            ?? $this->unknownCondition();

        if (! $isDay) {
            $condition['icon'] = match ($condition['icon']) {
                'sun' => 'moon',
                'cloud-sun' => 'cloud-moon',
                default => $condition['icon'],
            };
        }

        return $condition;
    }

    /**
     * Return the human-readable description for a nullable WMO code.
     */
    public function description(int|float|null $code): string
    {
        return $this->map($code)['description'];
    }

    /**
     * Return the icon identifier for a WMO code and daylight flag.
     */
    public function icon(
        int|float|null $code,
        bool $isDay = true,
    ): string {
        return $this->map($code, $isDay)['icon'];
    }

    /**
     * Return the fallback description and icon for unsupported weather codes.
     *
     * @return array{description: string, icon: string}
     */
    private function unknownCondition(): array
    {
        return [
            'description' => 'Unknown conditions',
            'icon' => 'circle-help',
        ];
    }
}
