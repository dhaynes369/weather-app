<?php

use App\Data\Weather\ForecastData;
use App\Data\Weather\LocationData;
use App\Exceptions\WeatherApiException;
use App\Services\Weather\ForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config(['weather.http.retry_delay_ms' => 0]);

    $this->location = new LocationData(
        id: 2634202,
        name: 'Weymouth',
        latitude: 50.61448,
        longitude: -2.45991,
        country: 'United Kingdom',
        countryCode: 'GB',
        timezone: 'Europe/London',
    );

    $this->payload = [
        'latitude' => 50.61838,
        'longitude' => -2.4716797,
        'elevation' => -1,
        'timezone' => 'Europe/London',
        'utc_offset_seconds' => 0,
        'current' => [
            'time' => 1795797900,
            'interval' => 900,
        ],
        'hourly' => [
            'time' => [1795737600, 1795741200],
        ],
        'daily' => [
            'time' => [1795737600, 1795824000],
        ],
        'current_units' => [
            'time' => 'unixtime',
            'interval' => 'seconds',
        ],
        'hourly_units' => [
            'time' => 'unixtime',
        ],
        'daily_units' => [
            'time' => 'unixtime',
        ],
    ];

    foreach (['current', 'hourly', 'daily'] as $interval) {
        foreach (config("weather.forecast.{$interval}") as $field) {
            $this->payload[$interval][$field] = $interval === 'current'
                ? 10.5
                : [10.5, 11.5];

            $this->payload["{$interval}_units"][$field] = '';
        }
    }

    $this->payload['current']['temperature_2m'] = 10.4;
    $this->payload['current']['relative_humidity_2m'] = 96;
    $this->payload['current']['wind_speed_10m'] = 10.7;
    $this->payload['current']['weather_code'] = 51;
    $this->payload['current']['is_day'] = 0;

    $this->payload['current_units']['temperature_2m'] = '°C';
    $this->payload['current_units']['wind_speed_10m'] = 'mp/h';
    $this->payload['current_units']['relative_humidity_2m'] = '%';

    $this->payload['hourly']['temperature_2m'] = [5.5, 6.2];

    $this->payload['daily']['sunrise'] = [
        1795765410,
        1795851900,
    ];

    $this->payload['daily']['sunset'] = [
        1795795871,
        1795882200,
    ];

    $this->payload['daily_units']['sunrise'] = 'unixtime';
    $this->payload['daily_units']['sunset'] = 'unixtime';
});

it('normalises provider data into a forecast object', function () {
    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    $forecast = app(ForecastService::class)
        ->getForecast($this->location);

    expect($forecast)
        ->toBeInstanceOf(ForecastData::class)
        ->and($forecast->location)->toBe($this->location)
        ->and($forecast->timezone)->toBe('Europe/London')
        ->and($forecast->utcOffsetSeconds)->toBe(0)
        ->and($forecast->gridLatitude)->toBe(50.61838)
        ->and($forecast->gridLongitude)->toBe(-2.4716797)
        ->and($forecast->gridElevation)->toBe(-1.0)
        ->and($forecast->currentInterval)->toBe(900)
        ->and($forecast->isStale)->toBeFalse()
        ->and($forecast->current)->toHaveCount(25)
        ->and($forecast->hourly)->toHaveCount(2)
        ->and($forecast->daily)->toHaveCount(2)
        ->and($forecast->hourly[0])->toHaveCount(27)
        ->and($forecast->daily[0])->toHaveCount(33);
});

it('sends the configured forecast request parameters', function () {
    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    app(ForecastService::class)->getForecast($this->location);

    Http::assertSent(function (Request $request) {
        return str_starts_with(
            $request->url(),
            config('weather.endpoints.forecast'),
        )
            && (float) $request['latitude'] === 50.61448
            && (float) $request['longitude'] === -2.45991
            && (int) $request['forecast_days'] === 14
            && $request['temperature_unit'] === 'celsius'
            && $request['wind_speed_unit'] === 'mph'
            && $request['precipitation_unit'] === 'mm'
            && $request['timezone'] === 'auto'
            && $request['timeformat'] === 'unixtime'
            && $request['current'] === implode(
                ',',
                config('weather.forecast.current'),
            )
            && $request['hourly'] === implode(
                ',',
                config('weather.forecast.hourly'),
            )
            && $request['daily'] === implode(
                ',',
                config('weather.forecast.daily'),
            );
    });

    Http::assertSentCount(1);
});

it('preserves current measurements and their units', function () {
    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    $forecast = app(ForecastService::class)
        ->getForecast($this->location);

    expect($forecast->currentValue('temperature_2m'))->toBe(10.4)
        ->and($forecast->currentValue('relative_humidity_2m'))->toBe(96)
        ->and($forecast->currentValue('wind_speed_10m'))->toBe(10.7)
        ->and($forecast->currentValue('weather_code'))->toBe(51)
        ->and($forecast->currentUnits['temperature_2m'])->toBe('°C')
        ->and($forecast->currentUnits['wind_speed_10m'])->toBe('mp/h')
        ->and($forecast->currentUnits['relative_humidity_2m'])->toBe('%')
        ->and($forecast->currentUnits['is_day'])->toBe('')
        ->and($forecast->currentValue('unknown_measurement'))->toBeNull();
});

it('transforms hourly columns into chronological records', function () {
    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    $forecast = app(ForecastService::class)
        ->getForecast($this->location);

    expect($forecast->hourly[0]['time'])->toBe(1795737600)
        ->and($forecast->hourly[0]['temperature_2m'])->toBe(5.5)
        ->and($forecast->hourly[1]['time'])->toBe(1795741200)
        ->and($forecast->hourly[1]['temperature_2m'])->toBe(6.2);
});

it('normalises daily dates and preserves solar timestamps', function () {
    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    $forecast = app(ForecastService::class)
        ->getForecast($this->location);

    expect($forecast->daily[0]['time'])->toBe('2026-11-27')
        ->and($forecast->daily[1]['time'])->toBe('2026-11-28')
        ->and($forecast->daily[0]['sunrise'])->toBe(1795765410)
        ->and($forecast->daily[0]['sunset'])->toBe(1795795871)
        ->and($forecast->dailyUnits['time'])->toBe('YYYY-MM-DD')
        ->and($forecast->hourlyUnits['time'])->toBe('unixtime')
        ->and($forecast->dailyUnits['sunrise'])->toBe('unixtime')
        ->and($forecast->dailyUnits['sunset'])->toBe('unixtime');
});

it('preserves null zero and negative measurements', function () {
    $this->payload['current']['temperature_2m'] = -3.5;
    $this->payload['current']['rain'] = 0;
    $this->payload['current']['visibility'] = null;

    $this->payload['hourly']['temperature_2m'] = [null, -2.5];
    $this->payload['daily']['sunrise'] = [null, 1795851900];

    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    $forecast = app(ForecastService::class)
        ->getForecast($this->location);

    expect($forecast->currentValue('temperature_2m'))->toBe(-3.5)
        ->and($forecast->currentValue('rain'))->toBe(0)
        ->and($forecast->currentValue('visibility'))->toBeNull()
        ->and($forecast->hourly[0]['temperature_2m'])->toBeNull()
        ->and($forecast->hourly[1]['temperature_2m'])->toBe(-2.5)
        ->and($forecast->daily[0]['sunrise'])->toBeNull();
});

it('preserves missing optional elevation', function () {
    unset($this->payload['elevation']);

    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    $forecast = app(ForecastService::class)
        ->getForecast($this->location);

    expect($forecast->gridElevation)->toBeNull();
});

it('stores independent retrieval and measurement timestamps', function () {
    $this->freezeTime();

    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    $forecast = app(ForecastService::class)
        ->getForecast($this->location);

    expect($forecast->retrievedAt->getTimestamp())
        ->toBe(now()->getTimestamp())
        ->and($forecast->currentTime->getTimestamp())
        ->toBe(1795797900)
        ->and($forecast->retrievedAt->getTimezone()->getName())
        ->toBe('UTC')
        ->and($forecast->currentTime->getTimezone()->getName())
        ->toBe('UTC')
        ->and($forecast->ageInSeconds())->toBe(0);

    $this->travel(5)->minutes();

    expect($forecast->ageInSeconds())->toBe(300);

    $this->travelBack();
});

it('normalises international local calendar dates', function (
    string $timezone,
    int $offset,
    int $timestamp,
) {
    $this->payload['timezone'] = $timezone;
    $this->payload['utc_offset_seconds'] = $offset;
    $this->payload['daily']['time'] = [
        $timestamp,
        $timestamp + 86400,
    ];

    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    $forecast = app(ForecastService::class)
        ->getForecast($this->location);

    expect($forecast->timezone)->toBe($timezone)
        ->and($forecast->utcOffsetSeconds)->toBe($offset)
        ->and($forecast->daily[0]['time'])->toBe('2026-11-27')
        ->and($forecast->daily[1]['time'])->toBe('2026-11-28');
})->with([
    'New York' => [
        'America/New_York',
        -18000,
        1795755600,
    ],
    'New Delhi' => [
        'Asia/Kolkata',
        19800,
        1795717800,
    ],
    'Sydney' => [
        'Australia/Sydney',
        39600,
        1795698000,
    ],
]);

it('preserves distinct hourly instants during a daylight saving transition', function () {
    $first = CarbonImmutable::parse(
        '2026-10-25 00:00:00',
        'UTC',
    )->getTimestamp();

    $second = $first + 3600;

    $this->payload['hourly']['time'] = [$first, $second];

    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    $forecast = app(ForecastService::class)
        ->getForecast($this->location);

    expect($forecast->hourly[0]['time'])->toBe($first)
        ->and($forecast->hourly[1]['time'])->toBe($second);

    $firstLocal = CarbonImmutable::createFromTimestamp(
        $first,
        'UTC',
    )->setTimezone($forecast->timezone);

    $secondLocal = CarbonImmutable::createFromTimestamp(
        $second,
        'UTC',
    )->setTimezone($forecast->timezone);

    expect($firstLocal->format('H:i'))->toBe('01:00')
        ->and($secondLocal->format('H:i'))->toBe('01:00')
        ->and($firstLocal->format('P'))->toBe('+01:00')
        ->and($secondLocal->format('P'))->toBe('+00:00');
});

it('rejects missing required response sections', function (string $section) {
    unset($this->payload[$section]);

    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    expect(fn () => app(ForecastService::class)
        ->getForecast($this->location))
        ->toThrow(WeatherApiException::class);

    Http::assertSentCount(1);
})->with([
    'current',
    'hourly',
    'daily',
    'current_units',
    'hourly_units',
    'daily_units',
]);

it('rejects missing current measurements', function () {
    unset($this->payload['current']['temperature_2m']);

    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    expect(fn () => app(ForecastService::class)
        ->getForecast($this->location))
        ->toThrow(WeatherApiException::class);
});

it('rejects invalid numeric measurements', function (mixed $value) {
    $this->payload['current']['temperature_2m'] = $value;

    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    expect(fn () => app(ForecastService::class)
        ->getForecast($this->location))
        ->toThrow(WeatherApiException::class);
})->with([
    'numeric string' => ['10.5'],
    'invalid string' => ['invalid'],
    'boolean' => [true],
    'array' => [[10.5]],
]);

it('rejects malformed hourly measurement series', function (mixed $values) {
    $this->payload['hourly']['temperature_2m'] = $values;

    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    expect(fn () => app(ForecastService::class)
        ->getForecast($this->location))
        ->toThrow(WeatherApiException::class);
})->with([
    'too short' => [[10.5]],
    'too long' => [[10.5, 11.5, 12.5]],
    'not an array' => ['invalid'],
    'null series' => [null],
    'non-list array' => [['first' => 10.5, 'second' => 11.5]],
]);

it('rejects invalid chronological ordering', function (array $timestamps) {
    $this->payload['hourly']['time'] = $timestamps;

    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    expect(fn () => app(ForecastService::class)
        ->getForecast($this->location))
        ->toThrow(WeatherApiException::class);
})->with([
    'duplicate timestamps' => [[1795737600, 1795737600]],
    'reversed timestamps' => [[1795741200, 1795737600]],
    'invalid timestamp type' => [[1795737600, 'invalid']],
    'empty timestamps' => [[]],
]);

it('rejects invalid forecast metadata', function (
    string $field,
    mixed $value,
) {
    $this->payload[$field] = $value;

    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    expect(fn () => app(ForecastService::class)
        ->getForecast($this->location))
        ->toThrow(WeatherApiException::class);
})->with([
    'invalid timezone' => ['timezone', 'Invalid/Timezone'],
    'invalid offset' => ['utc_offset_seconds', 'invalid'],
    'invalid latitude' => ['latitude', 91],
    'invalid longitude' => ['longitude', -181],
]);

it('rejects missing measurement units', function () {
    unset($this->payload['current_units']['temperature_2m']);

    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    expect(fn () => app(ForecastService::class)
        ->getForecast($this->location))
        ->toThrow(WeatherApiException::class);
});

it('rejects malformed solar timestamps', function () {
    $this->payload['daily']['sunrise'] = [10.5, 1795851900];

    Http::fake([
        '*' => Http::response($this->payload),
    ]);

    expect(fn () => app(ForecastService::class)
        ->getForecast($this->location))
        ->toThrow(WeatherApiException::class);
});

it('propagates provider failures without returning forecast data', function () {
    Http::fake([
        '*' => Http::response(['error' => true], 400),
    ]);

    try {
        app(ForecastService::class)->getForecast($this->location);

        $this->fail('Expected a weather API exception.');
    } catch (WeatherApiException $exception) {
        expect($exception->category)
            ->toBe(WeatherApiException::HTTP_ERROR)
            ->and($exception->statusCode)->toBe(400);
    }

    Http::assertSentCount(1);
});
