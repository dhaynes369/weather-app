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
     * Return an individual current weather measurement.
     */
    public function currentValue(string $measurement): int|float|null
    {
        return $this->current[$measurement] ?? null;
    }

    /**
     * Return the age of the forecast in seconds.
     */
    public function ageInSeconds(): int
    {
        return max(
            0,
            now()->getTimestamp() - $this->retrievedAt->getTimestamp(),
        );
    }
}
