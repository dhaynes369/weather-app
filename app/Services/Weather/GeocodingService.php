<?php

namespace App\Services\Weather;

use App\Data\Weather\LocationData;
use App\Exceptions\WeatherApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class GeocodingService
{
    public function __construct(
        private readonly OpenMeteoClient $client,
    ) {}

    /**
     * Search for locations matching a name.
     *
     * @return list<LocationData>
     */
    public function search(
        string $term,
        ?string $countryCode = null,
    ): array {
        $term = trim($term);
        $length = mb_strlen($term);

        $minimum = (int) config('weather.geocoding.minimum_search_length');
        $maximum = (int) config('weather.geocoding.maximum_search_length');

        if ($length < $minimum || $length > $maximum) {
            throw new InvalidArgumentException(
                "Search must contain between {$minimum} and {$maximum} characters."
            );
        }

        if ($countryCode !== null) {
            $countryCode = strtoupper(trim($countryCode));

            if (preg_match('/^[A-Z]{2}$/D', $countryCode) !== 1) {
                throw new InvalidArgumentException(
                    'Country code must contain two letters.'
                );
            }
        }

        $parameters = [
            'name' => $term,
            'count' => (int) config('weather.geocoding.result_limit'),
            'language' => (string) config('weather.geocoding.language'),
        ];

        if ($countryCode !== null) {
            $parameters['countryCode'] = $countryCode;
        }

        $cacheKey = $this->cacheKey($parameters);

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $this->normaliseResults($cached);
        }

        $response = $this->client->get('geocoding', $parameters);

        $results = array_key_exists('results', $response)
            ? $response['results']
            : [];

        if (! is_array($results) || ! array_is_list($results)) {
            throw $this->invalidResponse();
        }

        $locations = $this->normaliseResults($results);

        $ttl = $locations === []
            ? (int) config('weather.cache.geocoding_empty')
            : (int) config('weather.cache.geocoding');

        Cache::put($cacheKey, $results, $ttl);

        return $locations;
    }

    /**
     * Generate a consistent cache key for equivalent searches.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function cacheKey(array $parameters): string
    {
        $parameters['name'] = mb_strtolower(
            (string) $parameters['name']
        );

        $version = (int) config('weather.cache.schema_version');

        return "weather:open-meteo:geocoding:v{$version}:"
            .hash('sha256', serialize($parameters));
    }

    /**
     * Convert provider results into internal location objects.
     *
     * @param  array<mixed>  $results
     * @return list<LocationData>
     */
    private function normaliseResults(array $results): array
    {
        $locations = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                throw $this->invalidResponse();
            }

            $locations[] = $this->normaliseLocation($result);
        }

        return $locations;
    }

    /**
     * Validate and map an individual provider location.
     *
     * @param  array<mixed>  $result
     */
    private function normaliseLocation(array $result): LocationData
    {
        $id = $result['id'] ?? null;
        $name = $result['name'] ?? null;
        $latitude = $this->optionalFloat($result['latitude'] ?? null);
        $longitude = $this->optionalFloat($result['longitude'] ?? null);

        if (
            ! is_int($id)
            || $id <= 0
            || ! is_string($name)
            || trim($name) === ''
            || $latitude === null
            || $latitude < -90
            || $latitude > 90
            || $longitude === null
            || $longitude < -180
            || $longitude > 180
        ) {
            throw $this->invalidResponse();
        }

        $postcodes = [];

        if (is_array($result['postcodes'] ?? null)) {
            foreach ($result['postcodes'] as $postcode) {
                if (is_string($postcode) && trim($postcode) !== '') {
                    $postcodes[] = $postcode;
                }
            }
        }

        $population = $result['population'] ?? null;

        return new LocationData(
            id: $id,
            name: $name,
            latitude: $latitude,
            longitude: $longitude,
            country: $this->optionalString($result['country'] ?? null),
            countryCode: $this->optionalString($result['country_code'] ?? null),
            region: $this->optionalString($result['admin1'] ?? null),
            district: $this->optionalString($result['admin2'] ?? null),
            localAuthority: $this->optionalString($result['admin3'] ?? null),
            timezone: $this->optionalString($result['timezone'] ?? null),
            elevation: $this->optionalFloat($result['elevation'] ?? null),
            population: is_int($population) && $population >= 0
                ? $population
                : null,
            postcodes: $postcodes,
        );
    }

    /**
     * Preserve unavailable text values as null.
     */
    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== ''
            ? $value
            : null;
    }

    /**
     * Preserve unavailable numeric values as null.
     */
    private function optionalFloat(mixed $value): ?float
    {
        if (! is_int($value) && ! is_float($value)) {
            return null;
        }

        return is_finite((float) $value)
            ? (float) $value
            : null;
    }

    /**
     * Record invalid provider data without logging its contents.
     */
    private function invalidResponse(): WeatherApiException
    {
        Log::warning('weather.geocoding.invalid_response', [
            'provider' => 'open-meteo',
            'endpoint' => 'geocoding',
            'category' => WeatherApiException::INVALID_RESPONSE,
        ]);

        return new WeatherApiException(
            WeatherApiException::INVALID_RESPONSE,
            'Weather provider returned invalid location data.',
        );
    }
}
