<?php

namespace App\Support;

use App\Data\Weather\ForecastData;
use App\Services\Weather\WeatherCodeMapper;
use Carbon\CarbonImmutable;

final class WeatherPresenter
{
    public function __construct(private readonly WeatherCodeMapper $mapper) {}

    public function measurement(int|float|string|null $value, string $unit): string
    {
        return is_numeric($value) ? number_format((float) $value, 1, '.', '').$unit : 'Unavailable';
    }

    /** @return array{description: string, icon: string} */
    public function condition(ForecastData $forecast): array
    {
        return $this->mapper->map($forecast->currentValue('weather_code'), $forecast->currentValue('is_day') != 0 || $forecast->currentValue('is_day') === null);
    }

    /** @return array<string, int|float|string|null> */
    public function today(ForecastData $forecast): array
    {
        $today = CarbonImmutable::now($forecast->timezone)->toDateString();
        foreach ($forecast->daily as $record) {
            if ($record['time'] === $today) {
                return $record;
            }
        }

        return [];
    }

    /**
     * @param  array<string, int|float|string|null>  $record
     * @return list<array{title: string, summary: string, measurements: array<string, string>}>
     */
    public function categories(ForecastData $forecast, array $record, string $period): array
    {
        $daily = $period === 'daily';
        $value = fn (string $key, string $unit = ''): string => $this->measurement($record[$key] ?? null, $unit);
        $scaled = function (string $key, float $divisor, string $unit) use ($record): string {
            $raw = $record[$key] ?? null;

            return $this->measurement(is_numeric($raw) ? (float) $raw / $divisor : null, $unit);
        };
        $direction = function (string $key) use ($record, $value): string {
            $raw = $record[$key] ?? null;
            if (! is_numeric($raw)) {
                return 'Unavailable';
            }
            $points = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
            $index = ((int) round((float) $raw / 22.5) % 16 + 16) % 16;

            return $value($key, '°').' '.$points[$index];
        };
        $solar = function (string $key) use ($record, $forecast): string {
            $raw = $record[$key] ?? null;

            return is_int($raw) ? ($forecast->localTime($raw)?->format('H:i T') ?? 'Unavailable') : 'Unavailable';
        };
        $daylight = match ($record['is_day'] ?? null) {
            1, 1.0 => 'Day',
            0, 0.0 => 'Night',
            default => 'Unavailable',
        };
        $category = static fn (string $title, string $summary, array $measurements): array => compact('title', 'summary', 'measurements');
        if ($daily) {
            return [
                $category('Temperature', 'Avg '.$value('temperature_2m_mean', '°C'), [
                    'Mean temperature' => $value('temperature_2m_mean', '°C'), 'Minimum temperature' => $value('temperature_2m_min', '°C'), 'Maximum temperature' => $value('temperature_2m_max', '°C'),
                    'Mean feels like' => $value('apparent_temperature_mean', '°C'), 'Minimum feels like' => $value('apparent_temperature_min', '°C'), 'Maximum feels like' => $value('apparent_temperature_max', '°C'),
                ]),
                $category('Wind', 'Avg '.$value('wind_speed_10m_mean', ' mph').' · '.$direction('wind_direction_10m_dominant'), [
                    'Mean wind speed' => $value('wind_speed_10m_mean', ' mph'), 'Minimum wind speed' => $value('wind_speed_10m_min', ' mph'), 'Maximum wind speed' => $value('wind_speed_10m_max', ' mph'), 'Maximum gust' => $value('wind_gusts_10m_max', ' mph'), 'Dominant direction' => $direction('wind_direction_10m_dominant'),
                ]),
                $category('Precipitation', 'Total '.$value('precipitation_sum', ' mm').' · Avg hourly chance '.$value('precipitation_probability_mean', '%'), [
                    'Total precipitation' => $value('precipitation_sum', ' mm'), 'Total rain' => $value('rain_sum', ' mm'), 'Total showers' => $value('showers_sum', ' mm'), 'Precipitation hours' => $value('precipitation_hours', ' h'), 'Mean hourly probability' => $value('precipitation_probability_mean', '%'), 'Minimum hourly probability' => $value('precipitation_probability_min', '%'), 'Maximum hourly probability' => $value('precipitation_probability_max', '%'),
                ]),
                $category('Humidity', 'Avg '.$value('relative_humidity_2m_mean', '%'), ['Mean humidity' => $value('relative_humidity_2m_mean', '%'), 'Minimum humidity' => $value('relative_humidity_2m_min', '%'), 'Maximum humidity' => $value('relative_humidity_2m_max', '%')]),
                $category('Cloud & visibility', 'Avg '.$value('cloud_cover_mean', '%').' · '.$scaled('visibility_mean', 1000, ' km'), ['Mean cloud cover' => $value('cloud_cover_mean', '%'), 'Mean visibility' => $scaled('visibility_mean', 1000, ' km')]),
                $category('Pressure', 'Avg '.$value('pressure_msl_mean', ' hPa'), ['Mean sea level pressure (daily mean)' => $value('pressure_msl_mean', ' hPa')]),
                $category('Sun & UV', 'Max UV '.$value('uv_index_max').' · '.$scaled('daylight_duration', 3600, ' h').' daylight', ['Maximum UV index' => $value('uv_index_max'), 'Maximum clear sky UV index' => $value('uv_index_clear_sky_max'), 'Sunshine duration' => $scaled('sunshine_duration', 3600, ' h'), 'Daylight duration' => $scaled('daylight_duration', 3600, ' h'), 'Sunrise' => $solar('sunrise'), 'Sunset' => $solar('sunset')]),
                $category('Snow & ice', 'Total '.$value('snowfall_sum', ' cm'), ['Total snowfall' => $value('snowfall_sum', ' cm')]),
            ];
        }
        $precipitation = ['Precipitation (interval)' => $value('precipitation', ' mm'), 'Rain (interval)' => $value('rain', ' mm'), 'Showers (interval)' => $value('showers', ' mm')];
        if ($period === 'hourly') {
            $precipitation['Precipitation probability'] = $value('precipitation_probability', '%');
        }

        return [
            $category('Temperature', $value('temperature_2m', '°C'), ['Temperature' => $value('temperature_2m', '°C'), 'Feels like' => $value('apparent_temperature', '°C'), 'Dew point' => $value('dew_point_2m', '°C'), 'Wet bulb temperature' => $value('wet_bulb_temperature_2m', '°C')]),
            $category('Wind', $value('wind_speed_10m', ' mph').' · '.$direction('wind_direction_10m'), ['Wind speed' => $value('wind_speed_10m', ' mph'), 'Wind gusts' => $value('wind_gusts_10m', ' mph'), 'Direction' => $direction('wind_direction_10m')]),
            $category('Precipitation', ($period === 'hourly' ? $value('precipitation_probability', '%').' · ' : '').$value('precipitation', ' mm'), $precipitation),
            $category('Humidity', $value('relative_humidity_2m', '%'), ['Relative humidity' => $value('relative_humidity_2m', '%')]),
            $category('Cloud & visibility', $value('cloud_cover', '%').' · '.$scaled('visibility', 1000, ' km'), ['Cloud cover' => $value('cloud_cover', '%'), 'Low cloud' => $value('cloud_cover_low', '%'), 'Middle cloud' => $value('cloud_cover_mid', '%'), 'High cloud' => $value('cloud_cover_high', '%'), 'Visibility' => $scaled('visibility', 1000, ' km')]),
            $category('Pressure', $value('pressure_msl', ' hPa'), ['Mean sea level pressure' => $value('pressure_msl', ' hPa'), 'Surface pressure' => $value('surface_pressure', ' hPa')]),
            $category('Sun & UV', 'UV '.$value('uv_index'), ['UV index' => $value('uv_index'), 'Clear sky UV index' => $value('uv_index_clear_sky'), 'Sunshine duration (interval)' => $scaled('sunshine_duration', 60, ' min'), 'Daylight status' => $daylight]),
            $category('Snow & ice', $value('snowfall', ' cm'), ['Snowfall (interval)' => $value('snowfall', ' cm'), 'Snow depth' => $value('snow_depth', ' m')]),
        ];
    }
}
