<?php

use App\Data\Weather\LocationData;
use App\Exceptions\WeatherApiException;
use App\Services\Weather\GeocodingService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config(['weather.http.retry_delay_ms' => 0]);

    $this->location = [
        'id' => 2634202,
        'name' => 'Weymouth',
        'latitude' => 50.61448,
        'longitude' => -2.45991,
        'country' => 'United Kingdom',
        'country_code' => 'GB',
        'admin1' => 'England',
        'admin2' => 'Dorset',
        'admin3' => 'Weymouth and Portland District',
        'timezone' => 'Europe/London',
        'elevation' => -2,
        'population' => 53416,
    ];
});

/*
|--------------------------------------------------------------------------
| Location Mapping
|--------------------------------------------------------------------------
*/

test('normalises provider data into a location object', function () {
    Http::fake([
        '*' => Http::response([
            'results' => [$this->location],
        ]),
    ]);

    $locations = app(GeocodingService::class)->search('Weymouth');

    expect($locations)->toHaveCount(1);

    $location = $locations[0];

    expect($location)->toBeInstanceOf(LocationData::class)
        ->and($location->id)->toBe(2634202)
        ->and($location->name)->toBe('Weymouth')
        ->and($location->latitude)->toBe(50.61448)
        ->and($location->longitude)->toBe(-2.45991)
        ->and($location->country)->toBe('United Kingdom')
        ->and($location->countryCode)->toBe('GB')
        ->and($location->region)->toBe('England')
        ->and($location->district)->toBe('Dorset')
        ->and($location->localAuthority)->toBe('Weymouth and Portland District')
        ->and($location->timezone)->toBe('Europe/London')
        ->and($location->elevation)->toBe(-2.0)
        ->and($location->population)->toBe(53416)
        ->and($location->postcodes)->toBe([])
        ->and($location->displayName())
        ->toBe('Weymouth, Dorset, England, United Kingdom');
});

test('preserves missing optional fields and valid zero values', function () {
    Http::fake([
        '*' => Http::response([
            'results' => [
                [
                    'id' => 123,
                    'name' => 'Test Location',
                    'latitude' => 0,
                    'longitude' => 0,
                    'elevation' => 0,
                    'population' => 0,
                ],
            ],
        ]),
    ]);

    $location = app(GeocodingService::class)
        ->search('Test Location')[0];

    expect($location->latitude)->toBe(0.0)
        ->and($location->longitude)->toBe(0.0)
        ->and($location->elevation)->toBe(0.0)
        ->and($location->population)->toBe(0)
        ->and($location->country)->toBeNull()
        ->and($location->timezone)->toBeNull()
        ->and($location->postcodes)->toBe([]);
});

test('removes repeated administrative names from display labels', function () {
    $location = new LocationData(
        id: 2179432,
        name: 'Weymouth',
        latitude: -37.04207,
        longitude: 174.86508,
        country: 'New Zealand',
        region: 'Auckland',
        district: 'Auckland',
    );

    expect($location->displayName())
        ->toBe('Weymouth, Auckland, New Zealand');
});

test('preserves provider ordering and distinct location identifiers', function () {
    $first = array_replace($this->location, [
        'id' => 4955336,
        'latitude' => 42.22093,
        'longitude' => -70.93977,
        'country' => 'United States',
        'admin1' => 'Massachusetts',
        'admin2' => 'Norfolk',
    ]);

    $second = array_replace($first, [
        'id' => 7257589,
        'latitude' => 42.20682,
        'longitude' => -70.94434,
    ]);

    Http::fake([
        '*' => Http::response([
            'results' => [$first, $second],
        ]),
    ]);

    $locations = app(GeocodingService::class)->search('Weymouth');

    expect($locations)->toHaveCount(2)
        ->and($locations[0]->id)->toBe(4955336)
        ->and($locations[1]->id)->toBe(7257589)
        ->and($locations[0]->displayName())
        ->toBe($locations[1]->displayName());
});

/*
|--------------------------------------------------------------------------
| Search Validation and Parameters
|--------------------------------------------------------------------------
*/

test('sends configured search parameters and country filter', function () {
    Http::fake([
        '*' => Http::response([
            'results' => [$this->location],
        ]),
    ]);

    app(GeocodingService::class)->search(' Weymouth ', 'gb');

    Http::assertSent(fn ($request) => $request['name'] === 'Weymouth'
        && $request['countryCode'] === 'GB'
        && (int) $request['count'] === 10
        && $request['language'] === 'en'
    );

    Http::assertSentCount(1);
});

test('rejects invalid search lengths without contacting the provider', function (string $term) {
    Http::fake();

    expect(fn () => app(GeocodingService::class)->search($term))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'too short' => ['We'],
    'too long' => [str_repeat('a', 101)],
]);

test('rejects malformed country codes without contacting the provider', function (string $countryCode) {
    Http::fake();

    expect(fn () => app(GeocodingService::class)
        ->search('Weymouth', $countryCode))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
})->with(['GBR', '1A', '']);

/*
|--------------------------------------------------------------------------
| Cache Behaviour
|--------------------------------------------------------------------------
*/

test('reuses cached results for equivalent searches', function () {
    Http::fake([
        '*' => Http::response([
            'results' => [$this->location],
        ]),
    ]);

    $service = app(GeocodingService::class);

    $first = $service->search('Weymouth', 'GB');
    $second = $service->search(' weymouth ', 'gb');

    expect($first[0]->id)->toBe($second[0]->id);

    Http::assertSentCount(1);
});

test('maintains separate cache entries for different country filters', function () {
    Http::fake([
        '*' => Http::response([
            'results' => [$this->location],
        ]),
    ]);

    $service = app(GeocodingService::class);

    $service->search('Weymouth');
    $service->search('Weymouth', 'GB');
    $service->search('Weymouth', 'US');

    Http::assertSentCount(3);
});

test('refreshes successful results after cache expiry', function () {
    $this->freezeTime();

    Http::fake([
        '*' => Http::response([
            'results' => [$this->location],
        ]),
    ]);

    $service = app(GeocodingService::class);

    $service->search('Weymouth');

    $this->travel(86399)->seconds();

    $service->search('Weymouth');

    Http::assertSentCount(1);

    $this->travel(2)->seconds();

    $service->search('Weymouth');

    Http::assertSentCount(2);

    $this->travelBack();
});

test('returns and caches empty search results for five minutes', function () {
    $this->freezeTime();

    Http::fake([
        '*' => Http::response([
            'generationtime_ms' => 0.1,
        ]),
    ]);

    $service = app(GeocodingService::class);

    expect($service->search('zzzinvalidxyz'))->toBe([]);

    $this->travel(299)->seconds();

    expect($service->search('zzzinvalidxyz'))->toBe([]);

    Http::assertSentCount(1);

    $this->travel(2)->seconds();

    expect($service->search('zzzinvalidxyz'))->toBe([]);

    Http::assertSentCount(2);

    $this->travelBack();
});

/*
|--------------------------------------------------------------------------
| Provider Failure and Response Validation
|--------------------------------------------------------------------------
*/

test('does not cache failed provider requests', function () {
    Http::fakeSequence()
        ->push([], 503)
        ->push([], 503)
        ->push(['results' => [$this->location]], 200);

    $service = app(GeocodingService::class);

    expect(fn () => $service->search('Weymouth'))
        ->toThrow(WeatherApiException::class);

    expect($service->search('Weymouth'))->toHaveCount(1);

    Http::assertSentCount(3);
});

test('rejects malformed location data', function (array $overrides) {
    Http::fake([
        '*' => Http::response([
            'results' => [
                array_replace($this->location, $overrides),
            ],
        ]),
    ]);

    try {
        app(GeocodingService::class)->search('Weymouth');

        $this->fail('Expected an invalid response exception.');
    } catch (WeatherApiException $exception) {
        expect($exception->category)
            ->toBe(WeatherApiException::INVALID_RESPONSE);
    }

    Http::assertSentCount(1);
})->with([
    'missing ID' => [['id' => null]],
    'invalid ID' => [['id' => -1]],
    'empty name' => [['name' => '']],
    'missing latitude' => [['latitude' => null]],
    'invalid latitude' => [['latitude' => 91]],
    'invalid longitude' => [['longitude' => -181]],
    'invalid coordinate type' => [['latitude' => 'invalid']],
]);

test('rejects malformed result collections', function (mixed $results) {
    Http::fake([
        '*' => Http::response([
            'results' => $results,
        ]),
    ]);

    expect(fn () => app(GeocodingService::class)->search('Weymouth'))
        ->toThrow(WeatherApiException::class);

    Http::assertSentCount(1);
})->with([
    'null results' => [null],
    'string results' => ['invalid'],
    'non-list results' => [['unexpected' => 'value']],
    'invalid result entry' => [['invalid']],
]);
