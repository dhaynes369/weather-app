<?php

use App\Livewire\WeatherDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('renders the weather dashboard on the home page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeLivewire(WeatherDashboard::class);
});

it('renders successfully', function () {
    Livewire::test(WeatherDashboard::class)
        ->assertStatus(200)
        ->assertSee('Weather Dashboard');
});

it('defaults to current conditions', function () {
    Livewire::test(WeatherDashboard::class)
        ->assertSet('mode', 'current')
        ->assertSee('Current conditions');
});

it('displays the initial location selection state', function () {
    Livewire::test(WeatherDashboard::class)
        ->assertSet('searchTerm', '')
        ->assertSee('Select a location');
});

it('switches between forecast modes', function (string $mode, string $heading) {
    Livewire::test(WeatherDashboard::class)
        ->call('setMode', $mode)
        ->assertSet('mode', $mode)
        ->assertSee($heading);
})->with([
    ['current', 'Current conditions'],
    ['hourly', 'Hourly forecast'],
    ['daily', 'Daily forecast'],
]);

it('rejects unsupported forecast modes', function () {
    Livewire::test(WeatherDashboard::class)
        ->call('setMode', 'hourly')
        ->call('setMode', 'invalid')
        ->assertSet('mode', 'hourly');
});

function fakeDashboardLocations(bool $forecastFailure = false): void
{
    Http::preventStrayRequests();

    test()->travelTo(CarbonImmutable::parse('2026-11-27 12:00:00 UTC'));
    $now = now()->startOfDay()->getTimestamp();
    $payload = [
        'latitude' => 50.6, 'longitude' => -2.4, 'timezone' => 'Europe/London', 'utc_offset_seconds' => 0,
        'current' => ['time' => $now, 'interval' => 900],
        'hourly' => ['time' => [$now, $now + 3600]],
        'daily' => ['time' => [$now, $now + 86400]],
        'current_units' => ['time' => 'unixtime', 'interval' => 'seconds'],
        'hourly_units' => ['time' => 'unixtime'],
        'daily_units' => ['time' => 'unixtime'],
    ];
    foreach (['current', 'hourly', 'daily'] as $period) {
        foreach (config("weather.forecast.{$period}") as $field) {
            $payload[$period][$field] = $period === 'current' ? 10.4 : [10.4, 11.5];
            $payload["{$period}_units"][$field] = '';
        }
    }
    $payload['daily']['sunrise'] = [$now + 21600, $now + 108000];
    $payload['daily']['sunset'] = [$now + 64800, $now + 151200];
    $payload['current']['weather_code'] = 51;
    $payload['current']['is_day'] = 1;
    $payload['daily']['temperature_2m_max'] = [12, 13];
    $payload['daily']['temperature_2m_min'] = [8, 9];
    Http::fake([
        'https://api.open-meteo.com/*' => $forecastFailure ? Http::response([], 400) : Http::response($payload),
        'https://geocoding-api.open-meteo.com/*' => Http::response([
            'results' => [
                [
                    'id' => 2634202,
                    'name' => 'Weymouth',
                    'latitude' => 50.61448,
                    'longitude' => -2.45991,
                    'country' => 'United Kingdom',
                    'country_code' => 'GB',
                    'admin1' => 'England',
                    'admin2' => 'Dorset',
                    'timezone' => 'Europe/London',
                ],
            ],
        ]),
    ]);
}

it('does not search before the minimum character count', function () {
    Http::preventStrayRequests();
    Http::fake();

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', 'We')
        ->assertSet('locations', []);

    Http::assertNothingSent();
});

it('rejects excessively long search terms', function () {
    Http::preventStrayRequests();
    Http::fake();

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', str_repeat('a', 101))
        ->assertSet('locations', [])
        ->assertSee('The location search is too long.');

    Http::assertNothingSent();
});

it('displays matching location suggestions', function () {
    fakeDashboardLocations();

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', 'Weymouth')
        ->assertSet('locations.0.id', 2634202)
        ->assertSee('Weymouth')
        ->assertSee('Dorset')
        ->assertSee('United Kingdom');

    Http::assertSentCount(1);
});

it('selects a location from the available suggestions', function () {
    fakeDashboardLocations();

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', 'Weymouth')
        ->call('selectLocation', 2634202)
        ->assertSet('selectedLocation.id', 2634202)
        ->assertSet('selectedLocation.latitude', 50.61448)
        ->assertSet('selectedLocation.longitude', -2.45991)
        ->assertSet('locations', [])
        ->assertSee('Light drizzle')
        ->assertSee('10.4°C')
        ->assertSee('High 12.0°C');

    Http::assertSentCount(2);
});

it('rejects location identifiers outside the available suggestions', function () {
    fakeDashboardLocations();

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', 'Weymouth')
        ->call('selectLocation', 999999)
        ->assertSet('selectedLocation', null)
        ->assertSee('Please select a valid location');
});

it('displays a message when no locations match', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://geocoding-api.open-meteo.com/*' => Http::response([
            'generationtime_ms' => 0.1,
        ]),
    ]);

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', 'zzzinvalidxyz')
        ->assertSet('locations', [])
        ->assertSee('No matching locations found.');
});

it('handles location search provider failures gracefully', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://geocoding-api.open-meteo.com/*' => Http::response([], 503),
    ]);

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', 'Weymouth')
        ->assertSet('locations', [])
        ->assertSee('Location search is temporarily unavailable.');
});

it('clears suggestions when the search term is shortened', function () {
    fakeDashboardLocations();

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', 'Weymouth')
        ->assertSet('locations.0.id', 2634202)
        ->set('searchTerm', 'We')
        ->assertSet('locations', [])
        ->assertSet('searchMessage', null);

    Http::assertSentCount(1);
});

it('preserves the selected location when editing a new search', function () {
    fakeDashboardLocations();

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', 'Weymouth')
        ->call('selectLocation', 2634202)
        ->set('searchTerm', 'Lo')
        ->assertSet('selectedLocation.id', 2634202)
        ->assertSet('locations', []);
});

it('preserves the selected location when switching forecast modes', function () {
    fakeDashboardLocations();

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', 'Weymouth')
        ->call('selectLocation', 2634202)
        ->call('setMode', 'daily')
        ->assertSet('mode', 'daily')
        ->assertSet('selectedLocation.id', 2634202);

    Http::assertSentCount(2);
});

it('reuses cached location search results', function () {
    fakeDashboardLocations();

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', 'Weymouth')
        ->set('searchTerm', '')
        ->set('searchTerm', 'Weymouth')
        ->assertSet('locations.0.id', 2634202);

    Http::assertSentCount(1);
});

it('displays a friendly forecast error and allows retry', function () {
    fakeDashboardLocations(true);

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', 'Weymouth')
        ->call('selectLocation', 2634202)
        ->assertSee('Weather data is temporarily unavailable.')
        ->call('retryForecast')
        ->assertSee('Weather data is temporarily unavailable.');

    Http::assertSentCount(3);
});
it('renders the eight current weather categories and detailed measurements', function () {
    fakeDashboardLocations();

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', 'Weymouth')
        ->call('selectLocation', 2634202)
        ->assertSee('Weather details')
        ->assertSee('Wet bulb temperature')
        ->assertSee('Wind gusts')
        ->assertSee('Precipitation (interval)')
        ->assertSee('Relative humidity')
        ->assertSee('Cloud & visibility')
        ->assertSee('Surface pressure')
        ->assertSee('Clear sky UV index')
        ->assertSee('Snow depth');
});

it('selects a daily forecast and reuses cached data', function () {
    fakeDashboardLocations();

    Livewire::test(WeatherDashboard::class)
        ->set('searchTerm', 'Weymouth')
        ->call('selectLocation', 2634202)
        ->assertSee('Daily outlook')
        ->call('selectDay', '2026-11-28')
        ->assertSet('mode', 'daily')
        ->assertSet('selectedDate', '2026-11-28')
        ->assertSee('Saturday, 28 November')
        ->call('selectDay', '2099-01-01')
        ->assertSet('selectedDate', '2026-11-28')
        ->call('setDisplayDays', 14)
        ->assertSet('displayDays', 14)
        ->call('setDisplayDays', 99)
        ->assertSet('displayDays', 14);

    Http::assertSentCount(2);
});
