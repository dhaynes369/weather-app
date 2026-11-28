<?php

namespace App\Services\Weather;

use App\Data\Weather\ForecastData;
use App\Data\Weather\LocationData;
use App\Exceptions\WeatherApiException;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class ForecastService
{
    /**
     * Inject the shared HTTP client for forecast requests.
     */
    public function __construct(
        private readonly OpenMeteoClient $client,
    ) {}

    /**
     * Retrieve and normalise weather data for a selected location.
     */
    public function getForecast(LocationData $location): ForecastData
    {
        $currentFields = $this->measurementFields('current');
        $hourlyFields = $this->measurementFields('hourly');
        $dailyFields = $this->measurementFields('daily');

        $parameters = [
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'current' => implode(',', $currentFields),
            'hourly' => implode(',', $hourlyFields),
            'daily' => implode(',', $dailyFields),
            'forecast_days' => (int) config('weather.forecast.days'),
            'temperature_unit' => config('weather.forecast.temperature_unit'),
            'wind_speed_unit' => config('weather.forecast.wind_speed_unit'),
            'precipitation_unit' => config('weather.forecast.precipitation_unit'),
            'timezone' => config('weather.forecast.timezone'),
            'timeformat' => config('weather.forecast.timeformat'),
        ];

        $fresh = (int) config('weather.cache.forecast_fresh');
        $retention = (int) config('weather.cache.forecast_retention');
        $version = (int) config('weather.cache.schema_version');
        $key = "weather:open-meteo:forecast:v{$version}:".hash('sha256', serialize([
            $location->id,
            $parameters,
        ]));

        $stored = Cache::get($key);
        $cached = is_array($stored) ? ForecastData::fromCacheArray($location, $stored) : null;

        if ($cached instanceof ForecastData && $cached->ageInSeconds() < $fresh && $cached->ageInSeconds() < $retention) {
            Log::debug('weather.forecast.cache_hit', ['age_seconds' => $cached->ageInSeconds()]);

            return $cached->forLocation($location);
        }

        try {
            $response = $this->client->get('forecast', $parameters);
            $forecast = $this->normaliseForecast(
                $location,
                $response,
                $currentFields,
                $hourlyFields,
                $dailyFields,
            );
        } catch (WeatherApiException $exception) {
            if ($cached instanceof ForecastData && $cached->ageInSeconds() < $retention) {
                Log::warning('weather.forecast.stale_fallback', [
                    'age_seconds' => $cached->ageInSeconds(),
                    'category' => $exception->category,
                ]);

                return $cached->forLocation($location, true);
            }

            throw $exception;
        }

        Cache::put($key, $forecast->toCacheArray(), $retention);
        Log::debug('weather.forecast.cache_updated', ['provider' => 'open-meteo']);

        return $forecast;
    }

    /**
     * Convert the provider response into our internal forecast model.
     *
     * @param  array<string, mixed>  $response
     * @param  list<string>  $currentFields
     * @param  list<string>  $hourlyFields
     * @param  list<string>  $dailyFields
     */
    private function normaliseForecast(
        LocationData $location,
        array $response,
        array $currentFields,
        array $hourlyFields,
        array $dailyFields,
    ): ForecastData {
        $timezone = $response['timezone'] ?? null;
        $offset = $response['utc_offset_seconds'] ?? null;

        if (
            ! is_string($timezone)
            || ! in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)
            || ! is_int($offset)
        ) {
            throw $this->invalidResponse('Invalid timezone metadata.');
        }

        $gridLatitude = $this->numericValue($response['latitude'] ?? null);
        $gridLongitude = $this->numericValue($response['longitude'] ?? null);

        if (
            $gridLatitude === null
            || $gridLatitude < -90
            || $gridLatitude > 90
            || $gridLongitude === null
            || $gridLongitude < -180
            || $gridLongitude > 180
        ) {
            throw $this->invalidResponse('Invalid grid coordinates.');
        }

        $current = $this->section($response, 'current');
        $hourly = $this->section($response, 'hourly');
        $daily = $this->section($response, 'daily');

        $currentTime = $current['time'] ?? null;
        $currentInterval = $current['interval'] ?? null;

        if (
            ! is_int($currentTime)
            || ! is_int($currentInterval)
            || $currentInterval <= 0
        ) {
            throw $this->invalidResponse('Invalid current measurement metadata.');
        }

        $currentValues = [];

        foreach ($currentFields as $field) {
            if (! array_key_exists($field, $current)) {
                throw $this->invalidResponse("Missing current measurement: {$field}.");
            }

            $currentValues[$field] = $this->numericValue($current[$field]);
        }

        return new ForecastData(
            location: $location,
            retrievedAt: CarbonImmutable::now('UTC'),
            currentTime: CarbonImmutable::createFromTimestamp($currentTime, 'UTC'),
            currentInterval: $currentInterval,
            timezone: $timezone,
            utcOffsetSeconds: $offset,
            gridLatitude: (float) $gridLatitude,
            gridLongitude: (float) $gridLongitude,
            gridElevation: $this->optionalFloat($response['elevation'] ?? null),
            current: $currentValues,
            hourly: $this->normaliseSeries($hourly, $hourlyFields, $timezone, false),
            daily: $this->normaliseSeries($daily, $dailyFields, $timezone, true),
            currentUnits: $this->normaliseUnits(
                $this->section($response, 'current_units'),
                $currentFields,
            ),
            hourlyUnits: $this->normaliseUnits(
                $this->section($response, 'hourly_units'),
                $hourlyFields,
            ),
            dailyUnits: array_replace(
                $this->normaliseUnits(
                    $this->section($response, 'daily_units'),
                    $dailyFields,
                ),
                ['time' => 'YYYY-MM-DD'],
            ),
        );
    }

    /**
     * Convert parallel measurement arrays into chronological records.
     *
     * @param  array<mixed>  $section
     * @param  list<string>  $fields
     * @return list<array<string, int|float|string|null>>
     */
    private function normaliseSeries(
        array $section,
        array $fields,
        string $timezone,
        bool $daily,
    ): array {
        $timestamps = $section['time'] ?? null;

        if (
            ! is_array($timestamps)
            || ! array_is_list($timestamps)
            || $timestamps === []
        ) {
            throw $this->invalidResponse('Invalid forecast timestamps.');
        }

        $count = count($timestamps);
        $columns = [];

        foreach ($fields as $field) {
            $values = $section[$field] ?? null;

            if (
                ! is_array($values)
                || ! array_is_list($values)
                || count($values) !== $count
            ) {
                throw $this->invalidResponse("Invalid measurement series: {$field}.");
            }

            $columns[$field] = $values;
        }

        $records = [];
        $previousTimestamp = null;

        foreach ($timestamps as $index => $timestamp) {
            if (
                ! is_int($timestamp)
                || ($previousTimestamp !== null && $timestamp <= $previousTimestamp)
            ) {
                throw $this->invalidResponse('Invalid chronological ordering.');
            }

            $previousTimestamp = $timestamp;

            $record = [
                'time' => $daily
                    ? CarbonImmutable::createFromTimestamp($timestamp, 'UTC')
                        ->setTimezone($timezone)
                        ->toDateString()
                    : $timestamp,
            ];

            foreach ($fields as $field) {
                $value = $columns[$field][$index];

                if (
                    in_array($field, ['sunrise', 'sunset'], true)
                    && $value !== null
                    && ! is_int($value)
                ) {
                    throw $this->invalidResponse("Invalid timestamp: {$field}.");
                }

                $record[$field] = $this->numericValue($value);
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * Validate and preserve provider measurement units.
     *
     * @param  array<mixed>  $units
     * @param  list<string>  $fields
     * @return array<string, string>
     */
    private function normaliseUnits(array $units, array $fields): array
    {
        $normalised = [];

        foreach (['time', ...$fields] as $field) {
            $unit = $units[$field] ?? null;

            if (! is_string($unit)) {
                throw $this->invalidResponse("Missing measurement unit: {$field}.");
            }

            $normalised[$field] = $unit;
        }

        return $normalised;
    }

    /**
     * Retrieve a required response section.
     *
     * @param  array<string, mixed>  $response
     * @return array<mixed>
     */
    private function section(array $response, string $name): array
    {
        $section = $response[$name] ?? null;

        if (! is_array($section)) {
            throw $this->invalidResponse("Missing or invalid section: {$name}.");
        }

        return $section;
    }

    /**
     * Retrieve the configured measurement register.
     *
     * @return list<string>
     */
    private function measurementFields(string $interval): array
    {
        $fields = config("weather.forecast.{$interval}");

        if (! is_array($fields) || $fields === []) {
            throw new \LogicException('Invalid forecast measurement configuration.');
        }

        $measurements = [];

        foreach ($fields as $field) {
            if (! is_string($field) || trim($field) === '') {
                throw new \LogicException('Invalid forecast measurement name.');
            }

            $measurements[] = $field;
        }

        return $measurements;
    }

    /**
     * Validate numeric measurements while preserving missing values.
     */
    private function numericValue(mixed $value): int|float|null
    {
        if ($value === null) {
            return null;
        }

        if (
            (! is_int($value) && ! is_float($value))
            || ! is_finite((float) $value)
        ) {
            throw $this->invalidResponse('Invalid numeric measurement.');
        }

        return $value;
    }

    /**
     * Convert an optional numeric value to a float.
     */
    private function optionalFloat(mixed $value): ?float
    {
        $number = $this->numericValue($value);

        return $number === null ? null : (float) $number;
    }

    /**
     * Record invalid forecast responses without logging payloads.
     */
    private function invalidResponse(string $reason): WeatherApiException
    {
        Log::warning('weather.forecast.invalid_response', [
            'provider' => 'open-meteo',
            'endpoint' => 'forecast',
            'category' => WeatherApiException::INVALID_RESPONSE,
            'reason' => $reason,
        ]);

        return new WeatherApiException(
            WeatherApiException::INVALID_RESPONSE,
            'Weather provider returned invalid forecast data.',
        );
    }
}
