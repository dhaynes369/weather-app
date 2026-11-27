<?php

use App\Exceptions\WeatherApiException;
use App\Services\Weather\OpenMeteoClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config(['weather.http.retry_delay_ms' => 0]);
});

/*
|--------------------------------------------------------------------------
| Successful Requests
|--------------------------------------------------------------------------
*/

test('returns decoded data from a successful request', function () {
    $payload = [
        'results' => [
            [
                'id' => 2634202,
                'name' => 'Weymouth',
                'latitude' => 50.61448,
                'longitude' => -2.45991,
            ],
        ],
    ];

    Http::fake([
        '*' => Http::response($payload, 200),
    ]);

    $result = app(OpenMeteoClient::class)->get(
        'geocoding',
        ['name' => 'Weymouth'],
    );

    expect($result)->toBe($payload);

    Http::assertSentCount(1);

    Http::assertSent(fn ($request) => str_starts_with(
        $request->url(),
        config('weather.endpoints.geocoding'),
    )
        && $request['name'] === 'Weymouth'
        && $request->method() === 'GET'
    );
});

/*
|--------------------------------------------------------------------------
| Retry Behaviour
|--------------------------------------------------------------------------
*/

test('retries a temporary server failure and recovers', function () {
    Http::fakeSequence()
        ->push([], 503)
        ->push(['results' => []], 200);

    $result = app(OpenMeteoClient::class)->get(
        'geocoding',
        ['name' => 'Weymouth'],
    );

    expect($result)->toBe(['results' => []]);

    Http::assertSentCount(2);
});

test('stops after the configured retry limit', function () {
    Http::fakeSequence()
        ->push([], 503)
        ->push([], 503);

    expect(fn () => app(OpenMeteoClient::class)->get(
        'geocoding',
        ['name' => 'Weymouth'],
    ))->toThrow(WeatherApiException::class);

    Http::assertSentCount(2);
});

test('does not retry permanent HTTP failures', function (int $status) {
    Http::fake([
        '*' => Http::response([], $status),
    ]);

    try {
        app(OpenMeteoClient::class)->get(
            'geocoding',
            ['name' => 'Weymouth'],
        );

        $this->fail('Expected a weather API exception.');
    } catch (WeatherApiException $exception) {
        expect($exception->category)
            ->toBe(WeatherApiException::HTTP_ERROR)
            ->and($exception->statusCode)
            ->toBe($status);
    }

    Http::assertSentCount(1);
})->with([400, 401, 403, 404]);

test('retries connection failures', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            throw new ConnectionException(
                'Simulated connection failure.'
            );
        }

        return Http::response(['results' => []], 200);
    });

    $result = app(OpenMeteoClient::class)->get(
        'geocoding',
        ['name' => 'Weymouth'],
    );

    expect($result)
        ->toBe(['results' => []])
        ->and($attempts)
        ->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Rate Limiting and Cooldown
|--------------------------------------------------------------------------
*/

test('applies a cooldown when rate limited', function () {
    Http::fake([
        '*' => Http::response([], 429, [
            'Retry-After' => '120',
        ]),
    ]);

    try {
        app(OpenMeteoClient::class)->get(
            'geocoding',
            ['name' => 'Weymouth'],
        );

        $this->fail('Expected a rate-limit exception.');
    } catch (WeatherApiException $exception) {
        expect($exception->category)
            ->toBe(WeatherApiException::RATE_LIMIT)
            ->and($exception->statusCode)
            ->toBe(429);
    }

    expect(Cache::has('weather:open-meteo:cooldown'))
        ->toBeTrue();

    Http::assertSentCount(1);
});

test('prevents outbound requests during a cooldown', function () {
    Cache::put(
        'weather:open-meteo:cooldown',
        true,
        60,
    );

    Http::fake();

    expect(fn () => app(OpenMeteoClient::class)->get(
        'geocoding',
        ['name' => 'Weymouth'],
    ))->toThrow(WeatherApiException::class);

    Http::assertNothingSent();
});

test('allows requests after the cooldown expires', function () {
    $this->freezeTime();

    Cache::put(
        'weather:open-meteo:cooldown',
        true,
        60,
    );

    $this->travel(61)->seconds();

    Http::fake([
        '*' => Http::response(['results' => []], 200),
    ]);

    $result = app(OpenMeteoClient::class)->get(
        'geocoding',
        ['name' => 'Weymouth'],
    );

    expect($result)->toBe(['results' => []]);

    Http::assertSentCount(1);

    $this->travelBack();
});

test('applies rate limit cooldown across both endpoints', function () {
    Http::fake([
        '*' => Http::response([], 429, [
            'Retry-After' => '120',
        ]),
    ]);

    $client = app(OpenMeteoClient::class);

    expect(fn () => $client->get('geocoding', []))
        ->toThrow(WeatherApiException::class);

    expect(fn () => $client->get('forecast', []))
        ->toThrow(WeatherApiException::class);

    Http::assertSentCount(1);
});

test('uses configured fallback for missing or invalid retry headers', function (array $headers) {
    $this->freezeTime();

    config(['weather.http.rate_limit_cooldown' => 10]);

    Http::fake([
        '*' => Http::response([], 429, $headers),
    ]);

    expect(fn () => app(OpenMeteoClient::class)->get(
        'geocoding',
        [],
    ))->toThrow(WeatherApiException::class);

    expect(Cache::has('weather:open-meteo:cooldown'))
        ->toBeTrue();

    $this->travel(9)->seconds();

    expect(Cache::has('weather:open-meteo:cooldown'))
        ->toBeTrue();

    $this->travel(2)->seconds();

    expect(Cache::has('weather:open-meteo:cooldown'))
        ->toBeFalse();

    $this->travelBack();
})->with([
    'missing header' => [[]],
    'invalid header' => [['Retry-After' => 'invalid']],
    'relative date' => [['Retry-After' => 'tomorrow']],
]);

test('honours retry durations exceeding the previous maximum', function () {
    $this->freezeTime();

    Http::fake([
        '*' => Http::response([], 429, [
            'Retry-After' => '1800',
        ]),
    ]);

    expect(fn () => app(OpenMeteoClient::class)->get(
        'forecast',
        [],
    ))->toThrow(WeatherApiException::class);

    $this->travel(901)->seconds();

    expect(Cache::has('weather:open-meteo:cooldown'))
        ->toBeTrue();

    $this->travel(900)->seconds();

    expect(Cache::has('weather:open-meteo:cooldown'))
        ->toBeFalse();

    $this->travelBack();
});

test('honours an HTTP date retry header', function () {
    $this->freezeTime();

    $retryAfter = now()
        ->utc()
        ->addSeconds(120)
        ->format(DATE_RFC7231);

    Http::fake([
        '*' => Http::response([], 429, [
            'Retry-After' => $retryAfter,
        ]),
    ]);

    expect(fn () => app(OpenMeteoClient::class)->get(
        'forecast',
        [],
    ))->toThrow(WeatherApiException::class);

    $this->travel(119)->seconds();

    expect(Cache::has('weather:open-meteo:cooldown'))
        ->toBeTrue();

    $this->travel(2)->seconds();

    expect(Cache::has('weather:open-meteo:cooldown'))
        ->toBeFalse();

    $this->travelBack();
});

test('does not impose a cooldown when retry after is zero', function () {
    Http::fake([
        '*' => Http::response([], 429, [
            'Retry-After' => '0',
        ]),
    ]);

    expect(fn () => app(OpenMeteoClient::class)->get(
        'forecast',
        [],
    ))->toThrow(WeatherApiException::class);

    expect(Cache::has('weather:open-meteo:cooldown'))
        ->toBeFalse();

    Http::assertSentCount(1);
});

/*
|--------------------------------------------------------------------------
| Response Validation
|--------------------------------------------------------------------------
*/

test('rejects invalid provider responses without retrying', function (string $body) {
    Http::fake([
        '*' => Http::response($body, 200),
    ]);

    try {
        app(OpenMeteoClient::class)->get(
            'geocoding',
            ['name' => 'Weymouth'],
        );

        $this->fail('Expected an invalid response exception.');
    } catch (WeatherApiException $exception) {
        expect($exception->category)
            ->toBe(WeatherApiException::INVALID_RESPONSE);
    }

    Http::assertSentCount(1);
})->with([
    'malformed JSON' => ['{invalid json'],
    'JSON array' => ['[]'],
    'JSON null' => ['null'],
    'JSON string' => ['"unexpected"'],
    'provider error' => ['{"error":true,"reason":"Test error"}'],
]);

/*
|--------------------------------------------------------------------------
| Endpoint Validation
|--------------------------------------------------------------------------
*/

test('rejects unknown endpoints without sending requests', function () {
    Http::fake();

    expect(fn () => app(OpenMeteoClient::class)->get(
        'unknown',
        [],
    ))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});
