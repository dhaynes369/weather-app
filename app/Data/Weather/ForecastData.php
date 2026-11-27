<?php

namespace App\Data\Weather;

use Carbon\CarbonImmutable;

final readonly class ForecastData
{
    /**
     * @param  array<string, int|float|null>  $current
     * @param  list<array<string, int|float|string|null>>  $hourly
     * @param  list<array<string, int|float|string|null>>  $daily
     * @param  array<string, string>  $currentUnits
     * @param  array<string, string>  $hourlyUnits
     * @param  array<string, string>  $dailyUnits
     */
    public function __construct(
        public LocationData $location,
        public CarbonImmutable $retrievedAt,
        public CarbonImmutable $currentTime,
        public int $currentInterval,
        public string $timezone,
        public int $utcOffsetSeconds,
        public float $gridLatitude,
        public float $gridLongitude,
        public ?float $gridElevation,
        public array $current,
        public array $hourly,
        public array $daily,
        public array $currentUnits,
        public array $hourlyUnits,
        public array $dailyUnits,
        public bool $isStale = false,
    ) {}

    /**
     * @return array{retrievedAt: int, currentTime: int, currentInterval: int, timezone: string, utcOffsetSeconds: int, gridLatitude: float, gridLongitude: float, gridElevation: float|null, current: array<string, int|float|null>, hourly: list<array<string, int|float|string|null>>, daily: list<array<string, int|float|string|null>>, currentUnits: array<string, string>, hourlyUnits: array<string, string>, dailyUnits: array<string, string>}
     */
    public function toCacheArray(): array
    {
        return [
            'retrievedAt' => $this->retrievedAt->getTimestamp(),
            'currentTime' => $this->currentTime->getTimestamp(),
            'currentInterval' => $this->currentInterval,
            'timezone' => $this->timezone,
            'utcOffsetSeconds' => $this->utcOffsetSeconds,
            'gridLatitude' => $this->gridLatitude,
            'gridLongitude' => $this->gridLongitude,
            'gridElevation' => $this->gridElevation,
            'current' => $this->current,
            'hourly' => $this->hourly,
            'daily' => $this->daily,
            'currentUnits' => $this->currentUnits,
            'hourlyUnits' => $this->hourlyUnits,
            'dailyUnits' => $this->dailyUnits,
        ];
    }

    /**
     * Reconstruct a forecast from the application-owned cache representation.
     *
     * @param  array<mixed>  $data
     */
    public static function fromCacheArray(LocationData $location, array $data): ?self
    {
        if (
            ! is_int($data['retrievedAt'] ?? null)
            || ! is_int($data['currentTime'] ?? null)
            || ! is_int($data['currentInterval'] ?? null)
            || ! is_string($data['timezone'] ?? null)
            || ! is_int($data['utcOffsetSeconds'] ?? null)
            || ! is_float($data['gridLatitude'] ?? null)
            || ! is_float($data['gridLongitude'] ?? null)
            || ! array_key_exists('gridElevation', $data) || ($data['gridElevation'] !== null && ! is_float($data['gridElevation']))
            || ! is_array($data['current'] ?? null)
            || ! is_array($data['hourly'] ?? null)
            || ! is_array($data['daily'] ?? null)
            || ! is_array($data['currentUnits'] ?? null)
            || ! is_array($data['hourlyUnits'] ?? null)
            || ! is_array($data['dailyUnits'] ?? null)
            || $data['currentInterval'] <= 0
            || ! in_array($data['timezone'], \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)
        ) {
            return null;
        }

        // Nested measurement arrays originate from the validated forecast model.
        /** @var array{retrievedAt: int, currentTime: int, currentInterval: int, timezone: string, utcOffsetSeconds: int, gridLatitude: float, gridLongitude: float, gridElevation: float|null, current: array<string, int|float|null>, hourly: list<array<string, int|float|string|null>>, daily: list<array<string, int|float|string|null>>, currentUnits: array<string, string>, hourlyUnits: array<string, string>, dailyUnits: array<string, string>} $data */
        return new self(
            location: $location,
            retrievedAt: CarbonImmutable::createFromTimestamp($data['retrievedAt'], 'UTC'),
            currentTime: CarbonImmutable::createFromTimestamp($data['currentTime'], 'UTC'),
            currentInterval: $data['currentInterval'],
            timezone: $data['timezone'],
            utcOffsetSeconds: $data['utcOffsetSeconds'],
            gridLatitude: $data['gridLatitude'],
            gridLongitude: $data['gridLongitude'],
            gridElevation: $data['gridElevation'],
            current: $data['current'],
            hourly: $data['hourly'],
            daily: $data['daily'],
            currentUnits: $data['currentUnits'],
            hourlyUnits: $data['hourlyUnits'],
            dailyUnits: $data['dailyUnits'],
        );
    }

    public function forLocation(LocationData $location, bool $isStale = false): self
    {
        return new self(
            location: $location,
            retrievedAt: $this->retrievedAt,
            currentTime: $this->currentTime,
            currentInterval: $this->currentInterval,
            timezone: $this->timezone,
            utcOffsetSeconds: $this->utcOffsetSeconds,
            gridLatitude: $this->gridLatitude,
            gridLongitude: $this->gridLongitude,
            gridElevation: $this->gridElevation,
            current: $this->current,
            hourly: $this->hourly,
            daily: $this->daily,
            currentUnits: $this->currentUnits,
            hourlyUnits: $this->hourlyUnits,
            dailyUnits: $this->dailyUnits,
            isStale: $isStale,
        );
    }

    public function currentValue(string $measurement): int|float|null
    {
        return $this->current[$measurement] ?? null;
    }

    public function ageInSeconds(): int
    {
        return max(
            0,
            now()->getTimestamp() - $this->retrievedAt->getTimestamp(),
        );
    }

    public function localCurrentTime(): CarbonImmutable
    {
        return $this->currentTime->setTimezone($this->timezone);
    }

    public function localRetrievedAt(): CarbonImmutable
    {
        return $this->retrievedAt->setTimezone($this->timezone);
    }

    public function localTime(?int $timestamp): ?CarbonImmutable
    {
        if ($timestamp === null) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp(
            $timestamp,
            'UTC',
        )->setTimezone($this->timezone);
    }

    /**
     * @return list<array<string, int|float|string|null>>
     */
    public function upcomingHourly(int $limit = 24): array
    {
        if ($limit <= 0) {
            return [];
        }

        $now = now()->getTimestamp();

        $records = array_filter(
            $this->hourly,
            static function (array $record) use ($now): bool {
                $timestamp = $record['time'] ?? null;

                return is_int($timestamp) && $timestamp >= $now;
            },
        );

        return array_slice(array_values($records), 0, $limit);
    }

    /**
     * @return list<array<string, int|float|string|null>>
     */
    public function upcomingDaily(?int $days = null): array
    {
        $days ??= (int) config(
            'weather.forecast.default_display_days',
            7,
        );

        if ($days <= 0) {
            return [];
        }

        $today = CarbonImmutable::now(
            $this->timezone,
        )->toDateString();

        $records = array_filter(
            $this->daily,
            static function (array $record) use ($today): bool {
                $date = $record['time'] ?? null;

                return is_string($date) && $date >= $today;
            },
        );

        return array_slice(array_values($records), 0, $days);
    }
}
