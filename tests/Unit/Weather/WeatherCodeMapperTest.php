<?php

use App\Services\Weather\WeatherCodeMapper;

beforeEach(function () {
    $this->mapper = new WeatherCodeMapper;
});

it('maps supported weather codes to descriptions and icons', function (
    int $code,
    string $description,
    string $icon,
) {
    expect($this->mapper->map($code))->toBe([
        'description' => $description,
        'icon' => $icon,
    ]);
})->with([
    [0, 'Clear sky', 'sun'],
    [1, 'Mainly clear', 'cloud-sun'],
    [2, 'Partly cloudy', 'cloud-sun'],
    [3, 'Overcast', 'cloud'],

    [45, 'Fog', 'cloud-fog'],
    [48, 'Depositing rime fog', 'cloud-fog'],

    [51, 'Light drizzle', 'cloud-drizzle'],
    [53, 'Moderate drizzle', 'cloud-drizzle'],
    [55, 'Dense drizzle', 'cloud-drizzle'],

    [56, 'Light freezing drizzle', 'cloud-sleet'],
    [57, 'Dense freezing drizzle', 'cloud-sleet'],

    [61, 'Slight rain', 'cloud-rain'],
    [63, 'Moderate rain', 'cloud-rain'],
    [65, 'Heavy rain', 'cloud-rain-heavy'],

    [66, 'Light freezing rain', 'cloud-sleet'],
    [67, 'Heavy freezing rain', 'cloud-sleet'],

    [71, 'Slight snowfall', 'cloud-snow'],
    [73, 'Moderate snowfall', 'cloud-snow'],
    [75, 'Heavy snowfall', 'cloud-snow'],
    [77, 'Snow grains', 'cloud-snow'],

    [80, 'Slight rain showers', 'cloud-rain'],
    [81, 'Moderate rain showers', 'cloud-rain'],
    [82, 'Violent rain showers', 'cloud-rain-heavy'],

    [85, 'Slight snow showers', 'cloud-snow'],
    [86, 'Heavy snow showers', 'cloud-snow'],

    [95, 'Thunderstorm', 'cloud-lightning'],
    [96, 'Thunderstorm with slight hail', 'cloud-hail'],
    [99, 'Thunderstorm with heavy hail', 'cloud-hail'],
]);

it('uses nighttime icons for appropriate conditions', function (
    int $code,
    string $icon,
) {
    expect($this->mapper->icon($code, false))->toBe($icon);
})->with([
    [0, 'moon'],
    [1, 'cloud-moon'],
    [2, 'cloud-moon'],
]);

it('preserves other weather icons at night', function (
    int $code,
    string $icon,
) {
    expect($this->mapper->icon($code, false))->toBe($icon);
})->with([
    [3, 'cloud'],
    [45, 'cloud-fog'],
    [51, 'cloud-drizzle'],
    [63, 'cloud-rain'],
    [75, 'cloud-snow'],
    [95, 'cloud-lightning'],
]);

it('preserves weather descriptions regardless of day or night', function () {
    expect($this->mapper->map(0, false)['description'])
        ->toBe('Clear sky')
        ->and($this->mapper->map(2, false)['description'])
        ->toBe('Partly cloudy');
});

it('returns a safe fallback for invalid or unsupported codes', function (
    int|float|null $code,
) {
    expect($this->mapper->map($code))->toBe([
        'description' => 'Unknown conditions',
        'icon' => 'circle-help',
    ]);
})->with([
    'null' => [null],
    'negative' => [-1],
    'unsupported' => [4],
    'out of range' => [100],
    'fractional' => [51.5],
    'infinity' => [INF],
    'negative infinity' => [-INF],
    'not a number' => [NAN],
]);

it('accepts whole-number floating point weather codes', function () {
    expect($this->mapper->map(51.0))
        ->toBe($this->mapper->map(51));
});

it('provides independent description and icon accessors', function () {
    expect($this->mapper->description(63))
        ->toBe('Moderate rain')
        ->and($this->mapper->icon(63))
        ->toBe('cloud-rain');
});

it('preserves the unknown condition fallback at night', function () {
    expect($this->mapper->map(null, false))->toBe([
        'description' => 'Unknown conditions',
        'icon' => 'circle-help',
    ]);
});
