<?php

use App\Data\Weather\ForecastData;
use App\Data\Weather\LocationData;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(
        CarbonImmutable::parse('2026-11-27 16:30:00', 'UTC'),
    );

    config(['weather.forecast.default_display_days' => 7]);

    $start = CarbonImmutable::parse(
        '2026-11-27 00:00:00',
        'UTC',
    );

    $hourly = [];
    $daily = [];

    for ($hour = 0; $hour < 336; $hour++) {
        $hourly[] = [
            'time' => $start->addHours($hour)->getTimestamp(),
            'temperature_2m' => 10.5,
        ];
    }

    for ($day = 0; $day < 14; $day++) {
        $daily[] = [
            'time' => $start->addDays($day)->toDateString(),
            'temperature_2m_max' => 12.5,
            'temperature_2m_min' => 5.5,
        ];
    }

    $this->makeForecast = function (
        string $timezone = 'Europe/London',
    ) use ($hourly, $daily): ForecastData {
        return new ForecastData(
            location: new LocationData(
                id: 2634202,
                name: 'Test location',
                latitude: 50.61448,
                longitude: -2.45991,
                timezone: $timezone,
            ),
            retrievedAt: CarbonImmutable::parse(
                '2026-11-27 16:30:00',
                'UTC',
            ),
            currentTime: CarbonImmutable::parse(
                '2026-11-27 16:15:00',
                'UTC',
            ),
            currentInterval: 900,
            timezone: $timezone,
            utcOffsetSeconds: 0,
            gridLatitude: 50.61838,
            gridLongitude: -2.4716797,
            gridElevation: -1.0,
            current: ['temperature_2m' => 10.4],
            hourly: $hourly,
            daily: $daily,
            currentUnits: ['temperature_2m' => '°C'],
            hourlyUnits: ['time' => 'unixtime'],
            dailyUnits: ['time' => 'YYYY-MM-DD'],
        );
    };

    $this->forecast = ($this->makeForecast)();
});

afterEach(function () {
    $this->travelBack();
});

it('converts current measurement time to the location timezone', function () {
    $forecast = ($this->makeForecast)('America/New_York');

    expect($forecast->localCurrentTime()->format('Y-m-d H:i'))
        ->toBe('2026-11-27 11:15')
        ->and($forecast->currentTime->getTimezone()->getName())
        ->toBe('UTC');
});

it('converts retrieval time to the location timezone', function () {
    $forecast = ($this->makeForecast)('Asia/Kolkata');

    expect($forecast->localRetrievedAt()->format('Y-m-d H:i'))
        ->toBe('2026-11-27 22:00')
        ->and($forecast->retrievedAt->getTimezone()->getName())
        ->toBe('UTC');
});

it('converts timestamps across local calendar boundaries', function () {
    $forecast = ($this->makeForecast)('Australia/Sydney');

    $timestamp = CarbonImmutable::parse(
        '2026-11-27 16:00:00',
        'UTC',
    )->getTimestamp();

    expect($forecast->localTime($timestamp)?->format('Y-m-d H:i'))
        ->toBe('2026-11-28 03:00');
});

it('preserves missing solar timestamps', function () {
    expect($this->forecast->localTime(null))->toBeNull();
});

it('handles daylight saving offsets using the location timezone', function () {
    $first = CarbonImmutable::parse(
        '2026-10-25 00:00:00',
        'UTC',
    )->getTimestamp();

    $second = $first + 3600;

    expect($this->forecast->localTime($first)?->format('H:i P'))
        ->toBe('01:00 +01:00')
        ->and($this->forecast->localTime($second)?->format('H:i P'))
        ->toBe('01:00 +00:00');
});

it('returns the next 24 hourly forecast records by default', function () {
    $records = $this->forecast->upcomingHourly();

    $expected = CarbonImmutable::parse(
        '2026-11-27 17:00:00',
        'UTC',
    )->getTimestamp();

    expect($records)->toHaveCount(24)
        ->and($records[0]['time'])->toBe($expected)
        ->and($records[23]['time'])->toBe($expected + 23 * 3600);
});

it('supports an alternative hourly forecast limit', function () {
    expect($this->forecast->upcomingHourly(48))
        ->toHaveCount(48);
});

it('includes an hourly record exactly at the current time', function () {
    $this->travelTo(
        CarbonImmutable::parse('2026-11-27 17:00:00', 'UTC'),
    );

    $records = $this->forecast->upcomingHourly();

    expect($records[0]['time'])->toBe(now()->getTimestamp());
});

it('excludes elapsed hourly records as time advances', function () {
    $this->travel(2)->hours();

    $expected = CarbonImmutable::parse(
        '2026-11-27 19:00:00',
        'UTC',
    )->getTimestamp();

    expect($this->forecast->upcomingHourly()[0]['time'])
        ->toBe($expected)
        ->and($this->forecast->hourly)->toHaveCount(336);
});

it('returns only the hourly records that remain available', function () {
    $this->travelTo(
        CarbonImmutable::parse('2026-12-10 22:30:00', 'UTC'),
    );

    expect($this->forecast->upcomingHourly())->toHaveCount(1);
});

it('returns no hourly records after the forecast has elapsed', function () {
    $this->travelTo(
        CarbonImmutable::parse('2026-12-11 00:00:00', 'UTC'),
    );

    expect($this->forecast->upcomingHourly())->toBe([]);
});

it('returns no hourly records for non-positive limits', function (int $limit) {
    expect($this->forecast->upcomingHourly($limit))->toBe([]);
})->with([0, -1]);

it('returns seven daily forecast records by default', function () {
    $records = $this->forecast->upcomingDaily();

    expect($records)->toHaveCount(7)
        ->and($records[0]['time'])->toBe('2026-11-27')
        ->and($records[6]['time'])->toBe('2026-12-03');
});

it('supports the extended fourteen-day forecast', function () {
    $records = $this->forecast->upcomingDaily(14);

    expect($records)->toHaveCount(14)
        ->and($records[13]['time'])->toBe('2026-12-10');
});

it('uses the configured default daily forecast length', function () {
    config(['weather.forecast.default_display_days' => 5]);

    expect($this->forecast->upcomingDaily())->toHaveCount(5);
});

it('filters daily records using the selected location calendar date', function () {
    $forecast = ($this->makeForecast)('Australia/Sydney');

    $records = $forecast->upcomingDaily();

    expect($records)->toHaveCount(7)
        ->and($records[0]['time'])->toBe('2026-11-28')
        ->and($records[6]['time'])->toBe('2026-12-04');
});

it('excludes previous daily records after midnight', function () {
    $this->travelTo(
        CarbonImmutable::parse('2026-11-28 00:01:00', 'UTC'),
    );

    $records = $this->forecast->upcomingDaily(14);

    expect($records)->toHaveCount(13)
        ->and($records[0]['time'])->toBe('2026-11-28')
        ->and($this->forecast->daily)->toHaveCount(14);
});

it('returns no daily records after the forecast has elapsed', function () {
    $this->travelTo(
        CarbonImmutable::parse('2026-12-11 00:00:00', 'UTC'),
    );

    expect($this->forecast->upcomingDaily())->toBe([]);
});

it('returns no daily records for non-positive limits', function (int $limit) {
    expect($this->forecast->upcomingDaily($limit))->toBe([]);
})->with([0, -1]);
