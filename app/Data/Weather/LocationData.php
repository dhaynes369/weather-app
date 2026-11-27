<?php

namespace App\Data\Weather;

final readonly class LocationData
{
    /**
     * @param  list<string>  $postcodes
     */
    public function __construct(
        public int $id,
        public string $name,
        public float $latitude,
        public float $longitude,
        public ?string $country = null,
        public ?string $countryCode = null,
        public ?string $region = null,
        public ?string $district = null,
        public ?string $localAuthority = null,
        public ?string $timezone = null,
        public ?float $elevation = null,
        public ?int $population = null,
        public array $postcodes = [],
    ) {}

    /**
     * Generate a human-readable location label.
     */
    public function displayName(): string
    {
        $parts = array_filter(
            [
                $this->name,
                $this->district,
                $this->region,
                $this->country,
            ],
            fn (?string $value): bool => $value !== null && trim($value) !== '',
        );

        return implode(', ', array_unique($parts));
    }
}
