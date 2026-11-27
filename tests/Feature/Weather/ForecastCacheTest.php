<?php

use App\Data\Weather\LocationData;
use App\Exceptions\WeatherApiException;
use App\Services\Weather\ForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'weather.http.retry_delay_ms' => 0,
        'weather.cache.forecast_fresh' => 900,
        'weather.cache.forecast_retention' => 7200,
    ]);

    $this->travelTo(
        CarbonImmutable::parse('2026-11-27 16:30:00', 'UTC'),
    );

    $this->location = new LocationData(
        id: 2634202,
        name: 'Weymouth',
        latitude: 50.61448,
        longitude: -2.45991,
        timezone: 'Europe/London',
    );

    $this->service = app(ForecastService::class);

    $this->payload = [
        'latitude' => 50.61838,
        'longitude' => -2.4716797,
        'elevation' => -1,
        'timezone' => 'Europe/London',
        'utc_offset_seconds' => 0,
        'current' => [
            'time' => now()->getTimestamp(),
            'interval' => 900,
        ],
        'hourly' => [
            'time' => [1795737600, 1795741200],
        ],
        'daily' => [
            'time' => [1795737600, 1795824000],
        ],
        'current_units' => ['time' => 'unixtime'],
        'hourly_units' => ['time' => 'unixtime'],
        'daily_units' => ['time' => 'unixtime'],
    ];

    foreach (['current', 'hourly', 'daily'] as $interval) {
        foreach (config("weather.forecast.{$interval}") as $field) {
            $this->payload[$interval][$field] = $interval === 'current'
                ? 10.5
                : [10.5, 11.5];

            $this->payload["{$interval}_units"][$field] = '';
        }
    }

    $this->payload['daily']['sunrise'] = [
        1795765410,
        1795851900,
    ];

    $this->payload['daily']['sunset'] = [
        1795795871,
        1795882200,
    ];

    $this->payload['current']['temperature_2m'] = 10.4;
    $this->payload['current_units']['temperature_2m'] = '°C';
});

afterEach(function () {
    $this->travelBack();
});

it('stores a successful forecast and reuses the cached result', function (string $driver) {
    config(['cache.default' => $driver]);

    Http::fake(['*' => Http::response($this->payload)]);

    $first = $this->service->getForecast($this->location);
    $second = $this->service->getForecast($this->location);

    expect($second->toCacheArray())->toBe($first->toCacheArray())
        ->and($second->current)->toBe($first->current)
        ->and($second->retrievedAt->getTimestamp())
        ->toBe($first->retrievedAt->getTimestamp())
        ->and($second->isStale)->toBeFalse();

    Http::assertSentCount(1);
})->with(['array', 'database']);

it('reuses fresh forecasts until the freshness boundary', function (string $driver) {
    config(['cache.default' => $driver]);

    Http::fake(['*' => Http::response($this->payload)]);

    $this->service->getForecast($this->location);

    $this->travel(899)->seconds();

    $forecast = $this->service->getForecast($this->location);

    expect($forecast->ageInSeconds())->toBe(899)
        ->and($forecast->isStale)->toBeFalse();

    Http::assertSentCount(1);
})->with(['array', 'database']);

it('refreshes the forecast exactly at the freshness boundary', function (string $driver) {
    config(['cache.default' => $driver]);

    $updated = $this->payload;
    $updated['current']['temperature_2m'] = 12.5;

    Http::fakeSequence()
        ->push($this->payload)
        ->push($updated);

    $first = $this->service->getForecast($this->location);

    $this->travel(900)->seconds();

    $second = $this->service->getForecast($this->location);

    expect($second->currentValue('temperature_2m'))->toBe(12.5)
        ->and($second->ageInSeconds())->toBe(0)
        ->and($second->isStale)->toBeFalse()
        ->and($second->retrievedAt->getTimestamp())
        ->toBe($first->retrievedAt->getTimestamp() + 900);

    Http::assertSentCount(2);
})->with(['array', 'database']);

it('replaces the cached forecast after a successful refresh', function (string $driver) {
    config(['cache.default' => $driver]);

    $updated = $this->payload;
    $updated['current']['temperature_2m'] = 12.5;

    Http::fakeSequence()
        ->push($this->payload)
        ->push($updated);

    $this->service->getForecast($this->location);

    $this->travel(15)->minutes();

    $this->service->getForecast($this->location);

    $cached = $this->service->getForecast($this->location);

    expect($cached->currentValue('temperature_2m'))->toBe(12.5)
        ->and($cached->isStale)->toBeFalse();

    Http::assertSentCount(2);
})->with(['array', 'database']);

it('returns stale data when a refresh encounters server failures', function (string $driver) {
    config(['cache.default' => $driver]);

    Http::fakeSequence()
        ->push($this->payload)
        ->push([], 503)
        ->push([], 503);

    $first = $this->service->getForecast($this->location);

    $this->travel(15)->minutes();

    $stale = $this->service->getForecast($this->location);

    expect($stale->isStale)->toBeTrue()
        ->and($stale->current)->toBe($first->current)
        ->and($stale->hourly)->toBe($first->hourly)
        ->and($stale->daily)->toBe($first->daily)
        ->and($stale->retrievedAt->getTimestamp())
        ->toBe($first->retrievedAt->getTimestamp())
        ->and($stale->ageInSeconds())->toBe(900)
        ->and($first->isStale)->toBeFalse();

    Http::assertSentCount(3);
})->with(['array', 'database']);

it('returns stale data when the provider connection fails', function (string $driver) {
    config(['cache.default' => $driver]);

    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            return Http::response($this->payload);
        }

        throw new ConnectionException(
            'Simulated connection failure',
        );
    });

    $this->service->getForecast($this->location);

    $this->travel(15)->minutes();

    $stale = $this->service->getForecast($this->location);

    expect($stale->isStale)->toBeTrue()
        ->and($stale->ageInSeconds())->toBe(900)
        ->and($attempts)->toBe(3);
})->with(['array', 'database']);

it('returns stale data during provider rate limiting', function (string $driver) {
    config(['cache.default' => $driver]);

    Http::fakeSequence()
        ->push($this->payload)
        ->push([], 429, ['Retry-After' => '120']);

    $this->service->getForecast($this->location);

    $this->travel(15)->minutes();

    $first = $this->service->getForecast($this->location);
    $second = $this->service->getForecast($this->location);

    expect($first->isStale)->toBeTrue()
        ->and($second->isStale)->toBeTrue()
        ->and($second->ageInSeconds())->toBe(900);

    Http::assertSentCount(2);
})->with(['array', 'database']);

it('returns stale data when the refreshed response is malformed', function (string $driver) {
    config(['cache.default' => $driver]);

    $invalid = $this->payload;

    unset($invalid['current']);

    Http::fakeSequence()
        ->push($this->payload)
        ->push($invalid);

    $first = $this->service->getForecast($this->location);

    $this->travel(15)->minutes();

    $stale = $this->service->getForecast($this->location);

    expect($stale->isStale)->toBeTrue()
        ->and($stale->current)->toBe($first->current)
        ->and($stale->ageInSeconds())->toBe(900);

    Http::assertSentCount(2);
})->with(['array', 'database']);

it('permits stale fallback immediately before the retention boundary', function (string $driver) {
    config(['cache.default' => $driver]);

    Http::fakeSequence()
        ->push($this->payload)
        ->push([], 503)
        ->push([], 503);

    $this->service->getForecast($this->location);

    $this->travel(7199)->seconds();

    $stale = $this->service->getForecast($this->location);

    expect($stale->isStale)->toBeTrue()
        ->and($stale->ageInSeconds())->toBe(7199);

    Http::assertSentCount(3);
})->with(['array', 'database']);

it('rejects stale fallback exactly at the retention boundary', function (string $driver) {
    config(['cache.default' => $driver]);

    Http::fakeSequence()
        ->push($this->payload)
        ->push([], 503)
        ->push([], 503);

    $this->service->getForecast($this->location);

    $this->travel(7200)->seconds();

    expect(fn () => $this->service->getForecast($this->location))
        ->toThrow(WeatherApiException::class);

    Http::assertSentCount(3);
})->with(['array', 'database']);

it('does not extend cache retention following a failed refresh', function (string $driver) {
    config(['cache.default' => $driver]);

    Http::fakeSequence()
        ->push($this->payload)
        ->push([], 503)
        ->push([], 503)
        ->push([], 503)
        ->push([], 503);

    $this->service->getForecast($this->location);

    $this->travel(15)->minutes();

    $stale = $this->service->getForecast($this->location);

    expect($stale->isStale)->toBeTrue();

    $this->travel(105)->minutes();

    expect(fn () => $this->service->getForecast($this->location))
        ->toThrow(WeatherApiException::class);

    Http::assertSentCount(5);
})->with(['array', 'database']);

it('does not cache failed initial requests', function (string $driver) {
    config(['cache.default' => $driver]);

    Http::fakeSequence()
        ->push([], 400)
        ->push($this->payload);

    expect(fn () => $this->service->getForecast($this->location))
        ->toThrow(WeatherApiException::class);

    $forecast = $this->service->getForecast($this->location);

    expect($forecast->isStale)->toBeFalse()
        ->and($forecast->currentValue('temperature_2m'))->toBe(10.4);

    Http::assertSentCount(2);
})->with(['array', 'database']);

it('does not cache malformed initial responses', function (string $driver) {
    config(['cache.default' => $driver]);

    $invalid = $this->payload;

    unset($invalid['hourly']);

    Http::fakeSequence()
        ->push($invalid)
        ->push($this->payload);

    expect(fn () => $this->service->getForecast($this->location))
        ->toThrow(WeatherApiException::class);

    $forecast = $this->service->getForecast($this->location);

    expect($forecast->isStale)->toBeFalse();

    Http::assertSentCount(2);
})->with(['array', 'database']);

it('isolates forecasts for different locations', function (string $driver) {
    config(['cache.default' => $driver]);

    Http::fake(['*' => Http::response($this->payload)]);

    $other = new LocationData(
        id: 2643743,
        name: 'London',
        latitude: 51.50853,
        longitude: -0.12574,
    );

    $first = $this->service->getForecast($this->location);
    $second = $this->service->getForecast($other);

    expect($first->location->id)->toBe(2634202)
        ->and($second->location->id)->toBe(2643743);

    Http::assertSentCount(2);
})->with(['array', 'database']);

it('isolates different location identities sharing coordinates', function (string $driver) {
    config(['cache.default' => $driver]);

    Http::fake(['*' => Http::response($this->payload)]);

    $other = new LocationData(
        id: 9999999,
        name: 'Alternative location',
        latitude: $this->location->latitude,
        longitude: $this->location->longitude,
    );

    $this->service->getForecast($this->location);

    $forecast = $this->service->getForecast($other);

    expect($forecast->location->id)->toBe(9999999);

    Http::assertSentCount(2);
})->with(['array', 'database']);

it('preserves updated caller metadata on a cache hit', function (string $driver) {
    config(['cache.default' => $driver]);

    Http::fake(['*' => Http::response($this->payload)]);

    $this->service->getForecast($this->location);

    $updated = new LocationData(
        id: $this->location->id,
        name: 'Weymouth, Dorset',
        latitude: $this->location->latitude,
        longitude: $this->location->longitude,
    );

    $forecast = $this->service->getForecast($updated);

    expect($forecast->location)->toBe($updated);

    Http::assertSentCount(1);
})->with(['array', 'database']);

it('isolates cache entries when forecast configuration changes', function (string $driver) {
    config(['cache.default' => $driver]);

    Http::fake(['*' => Http::response($this->payload)]);

    $this->service->getForecast($this->location);

    config(['weather.forecast.days' => 7]);

    $this->service->getForecast($this->location);

    Http::assertSentCount(2);
})->with(['array', 'database']);

it('isolates cache entries when the schema version changes', function (string $driver) {
    config(['cache.default' => $driver]);

    Http::fake(['*' => Http::response($this->payload)]);

    $this->service->getForecast($this->location);

    config(['weather.cache.schema_version' => 999]);

    $this->service->getForecast($this->location);

    Http::assertSentCount(2);
})->with(['array', 'database']);

it('recovers from stale fallback after a successful refresh', function (string $driver) {
    config(['cache.default' => $driver]);

    $updated = $this->payload;
    $updated['current']['temperature_2m'] = 12.5;

    Http::fakeSequence()
        ->push($this->payload)
        ->push([], 503)
        ->push([], 503)
        ->push($updated);

    $this->service->getForecast($this->location);

    $this->travel(15)->minutes();

    $stale = $this->service->getForecast($this->location);

    $this->travel(1)->minutes();

    $recovered = $this->service->getForecast($this->location);

    expect($stale->isStale)->toBeTrue()
        ->and($recovered->isStale)->toBeFalse()
        ->and($recovered->ageInSeconds())->toBe(0)
        ->and($recovered->currentValue('temperature_2m'))->toBe(12.5);

    Http::assertSentCount(4);
})->with(['array', 'database']);

it('rejects incompatible cache representations', function () {
    Http::fake(['*' => Http::response($this->payload)]);
    config(['cache.default' => 'database']);

    $this->service->getForecast($this->location);

    $key = DB::table('cache')
        ->where('key', 'like', '%weather:open-meteo:forecast:%')
        ->value('key');

    expect($key)->toBeString();
    $cacheKey = substr($key, strlen((string) config('cache.prefix')));
    Cache::put($cacheKey, ['invalid' => true], 7200);

    $forecast = $this->service->getForecast($this->location);

    expect($forecast->currentValue('temperature_2m'))->toBe(10.4);
    Http::assertSentCount(2);
});
