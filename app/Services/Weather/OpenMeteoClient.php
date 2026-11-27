<?php

namespace App\Services\Weather;

use App\Exceptions\WeatherApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class OpenMeteoClient
{
    /**
     * Execute a request against a configured Open-Meteo endpoint.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function get(string $endpoint, array $parameters): array
    {
        $url = config("weather.endpoints.{$endpoint}");

        if (! is_string($url)) {
            throw new \InvalidArgumentException(
                'Unknown weather API endpoint.'
            );
        }

        $cooldownKey = "weather:open-meteo:cooldown:{$endpoint}";

        if (Cache::has($cooldownKey)) {
            throw new WeatherApiException(
                WeatherApiException::RATE_LIMIT,
                'Weather provider cooldown is active.',
                429,
            );
        }

        $maxAttempts = (int) config('weather.http.retries') + 1;
        $retryDelay = (int) config('weather.http.retry_delay_ms');

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $startedAt = microtime(true);

            try {
                $response = Http::acceptJson()
                    ->connectTimeout((int) config('weather.http.connect_timeout'))
                    ->timeout((int) config('weather.http.timeout'))
                    ->get($url, $parameters);
            } catch (ConnectionException $exception) {
                $this->logFailure(
                    $endpoint,
                    $attempt,
                    $startedAt,
                    WeatherApiException::CONNECTION,
                );

                if ($attempt < $maxAttempts) {
                    usleep($retryDelay * 1000);

                    continue;
                }

                throw new WeatherApiException(
                    WeatherApiException::CONNECTION,
                    'Unable to connect to the weather provider.',
                    previous: $exception,
                );
            }

            $status = $response->status();

            if ($response->successful()) {
                try {
                    return $this->decodeResponse($response);
                } catch (WeatherApiException $exception) {
                    $this->logFailure(
                        $endpoint,
                        $attempt,
                        $startedAt,
                        $exception->category,
                        $status,
                    );

                    throw $exception;
                }
            }

            $category = $status === 429
                ? WeatherApiException::RATE_LIMIT
                : WeatherApiException::HTTP_ERROR;

            $this->logFailure(
                $endpoint,
                $attempt,
                $startedAt,
                $category,
                $status,
            );

            if ($status === 429) {
                $this->applyCooldown($cooldownKey, $response);

                throw new WeatherApiException(
                    WeatherApiException::RATE_LIMIT,
                    'Weather provider rate limit exceeded.',
                    $status,
                );
            }

            if (
                $attempt < $maxAttempts
                && in_array(
                    $status,
                    config('weather.http.retryable_statuses'),
                    true,
                )
            ) {
                usleep($retryDelay * 1000);

                continue;
            }

            throw new WeatherApiException(
                WeatherApiException::HTTP_ERROR,
                'Weather provider returned an unsuccessful response.',
                $status,
            );
        }

        throw new WeatherApiException(
            WeatherApiException::HTTP_ERROR,
            'Weather provider request attempts exhausted.',
        );
    }

    /**
     * Decode and validate the basic JSON response structure.
     *
     * @return array<string, mixed>
     */
    private function decodeResponse(Response $response): array
    {
        try {
            $decoded = json_decode(
                $response->body(),
                false,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (! $decoded instanceof \stdClass) {
                throw new WeatherApiException(
                    WeatherApiException::INVALID_RESPONSE,
                    'Weather provider response is not a JSON object.',
                    $response->status(),
                );
            }

            $data = json_decode(
                $response->body(),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new WeatherApiException(
                WeatherApiException::INVALID_RESPONSE,
                'Weather provider returned invalid JSON.',
                $response->status(),
                $exception,
            );
        }

        if (! is_array($data) || ($data['error'] ?? false) === true) {
            throw new WeatherApiException(
                WeatherApiException::INVALID_RESPONSE,
                'Weather provider returned an invalid response.',
                $response->status(),
            );
        }

        return $data;
    }

    /**
     * Prevent immediate requests following a rate-limit response.
     */
    private function applyCooldown(
        string $key,
        Response $response,
    ): void {
        $retryAfter = $response->header('Retry-After');
        $seconds = 60;

        if (is_string($retryAfter) && ctype_digit($retryAfter)) {
            $seconds = (int) $retryAfter;
        } elseif (is_string($retryAfter) && $retryAfter !== '') {
            $timestamp = strtotime($retryAfter);

            if ($timestamp !== false) {
                $seconds = $timestamp - time();
            }
        }

        $seconds = max(1, min($seconds, 900));

        Cache::put($key, true, $seconds);
    }

    /**
     * Record technical failure information without logging payloads.
     */
    private function logFailure(
        string $endpoint,
        int $attempt,
        float $startedAt,
        string $category,
        ?int $status = null,
    ): void {
        Log::warning('weather.api.failed', [
            'provider' => 'open-meteo',
            'endpoint' => $endpoint,
            'category' => $category,
            'http_status' => $status,
            'attempt' => $attempt,
            'duration_ms' => round(
                (microtime(true) - $startedAt) * 1000,
                2,
            ),
        ]);
    }
}
