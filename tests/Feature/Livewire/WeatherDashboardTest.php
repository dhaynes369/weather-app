<?php

use App\Livewire\WeatherDashboard;
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

function fakeDashboardLocations(): void
{
    Http::preventStrayRequests();

    Http::fake([
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
        ->assertSee('Location selected');

    Http::assertSentCount(1);
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

    Http::assertSentCount(1);
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
