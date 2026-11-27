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
