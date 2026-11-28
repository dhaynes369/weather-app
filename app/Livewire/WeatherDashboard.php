<?php

namespace App\Livewire;

use App\Data\Weather\ForecastData;
use App\Data\Weather\LocationData;
use App\Exceptions\WeatherApiException;
use App\Services\Weather\ForecastService;
use App\Services\Weather\GeocodingService;
use App\Support\WeatherPresenter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read ForecastData|null $forecast
 */
class WeatherDashboard extends Component
{
    #[Locked]
    public string $mode = 'current';

    #[Locked]
    public int $displayDays = 7;

    #[Locked]
    public ?string $selectedDate = null;

    #[Locked]
    public ?int $selectedHour = null;

    /**
     * Return upcoming hourly records, optionally restricted to the selected local calendar date.
     *
     * @return list<array<string, int|float|string|null>>
     */
    public function hourlyRecords(): array
    {
        $forecast = $this->forecast;
        if ($forecast === null) {
            return [];
        }

        if ($this->selectedDate === null) {
            return $forecast->upcomingHourly();
        }

        return array_values(array_filter($forecast->hourly, function (array $record) use ($forecast): bool {
            $time = $record['time'] ?? null;

            return is_int($time) && $time >= now()->getTimestamp()
                && $forecast->localTime($time)?->toDateString() === $this->selectedDate;
        }));
    }

    /**
     * Select a UTC epoch timestamp only if it belongs to the displayed hourly records.
     */
    public function selectHour(int $timestamp): void
    {
        foreach ($this->hourlyRecords() as $record) {
            if ($record['time'] === $timestamp) {
                $this->selectedHour = $timestamp;

                return;
            }
        }
    }

    /**
     * Clear date and hour selections to restore the next 24 hours.
     */
    public function showUpcomingHours(): void
    {
        $this->selectedDate = null;
        $this->selectedHour = null;
    }

    /**
     * Accept a seven- or fourteen-day display range and reset dependent selections.
     */
    public function setDisplayDays(int $days): void
    {
        if (! in_array($days, [7, 14], true)) {
            return;
        }
        $this->displayDays = $days;
        $this->selectedDate = null;
        $this->selectedHour = null;
    }

    /**
     * Select an available local date (YYYY-MM-DD), preserving hourly or daily mode.
     */
    public function selectDay(string $date): void
    {
        foreach ($this->forecast?->upcomingDaily($this->displayDays) ?? [] as $record) {
            if ($record['time'] === $date) {
                $this->selectedDate = $date;
                $this->selectedHour = null;
                if ($this->mode === 'current') {
                    $this->mode = 'daily';
                }

                return;
            }
        }
    }

    public string $searchTerm = '';

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $locations = [];

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $selectedLocation = null;

    #[Locked]
    public ?string $searchMessage = null;

    /**
     * Switch to a supported forecast view; ignore invalid mode names.
     */
    public function setMode(string $mode): void
    {
        if (! in_array($mode, ['current', 'hourly', 'daily'], true)) {
            return;
        }

        $this->mode = $mode;
    }

    /**
     * Handle Livewire search updates, validate the term and populate suggestions or a friendly error.
     */
    public function updatedSearchTerm(): void
    {
        $this->locations = [];
        $this->searchMessage = null;

        $term = trim($this->searchTerm);

        $minimum = (int) config('weather.geocoding.minimum_search_length', 3);
        $maximum = (int) config('weather.geocoding.maximum_search_length', 100);

        if (mb_strlen($term) < $minimum) {
            return;
        }

        if (mb_strlen($term) > $maximum) {
            $this->searchMessage = 'The location search is too long.';

            return;
        }

        try {
            $results = app(GeocodingService::class)->search($term);

            foreach ($results as $location) {
                $this->locations[] = [
                    ...get_object_vars($location),
                    'displayName' => $location->displayName(),
                ];
            }

            if ($this->locations === []) {
                $this->searchMessage = 'No matching locations found.';
            }
        } catch (WeatherApiException) {
            $this->searchMessage = 'Location search is temporarily unavailable. Please try again.';
        }
    }

    /**
     * Activate a provider ID only from the server-controlled search results.
     */
    public function selectLocation(int $id): void
    {
        foreach ($this->locations as $location) {
            if ($location['id'] === $id) {
                $this->activateLocation($location);

                return;
            }
        }

        $this->searchMessage = 'Please select a valid location from the search results.';
    }

    /**
     * Apply a validated location array, reset navigation and invalidate the computed forecast.
     *
     * @param  array<string, mixed>  $location
     */
    private function activateLocation(array $location): void
    {
        $this->selectedLocation = $location;
        $this->searchTerm = $location['displayName'];
        $this->locations = [];
        $this->searchMessage = null;
        $this->mode = 'current';
        $this->selectedDate = null;
        $this->displayDays = 7;
        $this->selectedHour = null;
        unset($this->forecast);
    }

    /**
     * Validate browser coordinates in degrees and activate a rounded, unnamed location.
     */
    public function selectCoordinates(float $latitude, float $longitude): void
    {
        if (! is_finite($latitude) || ! is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
            $this->searchMessage = 'Invalid location coordinates. Please search manually.';

            return;
        }

        // Reduce location precision and avoid unnecessary cache fragmentation.
        $latitude = round($latitude, 3);
        $longitude = round($longitude, 3);
        $location = new LocationData(id: 0, name: 'Current location', latitude: $latitude, longitude: $longitude);
        $this->activateLocation([...get_object_vars($location), 'displayName' => $location->displayName()]);
    }

    /**
     * Decrypt a saved location token and activate its location; reject invalid tokens.
     */
    public function selectFavourite(string $token): void
    {
        if (strlen($token) > 16000) {
            return;
        }

        try {
            $location = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            $this->searchMessage = 'This favourite is no longer valid. Search for the location again.';

            return;
        }

        if (! is_array($location) || ! isset($location['id'], $location['displayName'])) {
            return;
        }

        $this->activateLocation($location);
    }

    /**
     * Resolve the selected location to a request-scoped forecast, or null when unavailable.
     */
    #[Computed]
    public function forecast(): ?ForecastData
    {
        if ($this->selectedLocation === null) {
            return null;
        }

        // Keep the full forecast out of Livewire public state.
        $data = $this->selectedLocation;
        unset($data['displayName']);
        /** @var array{id: int, name: string, latitude: float, longitude: float, country: ?string, countryCode: ?string, region: ?string, district: ?string, localAuthority: ?string, timezone: ?string, elevation: ?float, population: ?int, postcodes: list<string>} $data */
        try {
            return app(ForecastService::class)->getForecast(new LocationData(...$data));
        } catch (WeatherApiException) {
            return null;
        }
    }

    /**
     * Invalidate the request-scoped forecast so rendering attempts retrieval again.
     */
    public function retryForecast(): void
    {
        unset($this->forecast);
    }

    /**
     * Return the dashboard view with forecast data, formatting helpers and a protected favourite token.
     */
    public function render(): View
    {
        return view('livewire.weather-dashboard', [
            'forecast' => $this->forecast,
            'presenter' => app(WeatherPresenter::class),
            'favouriteToken' => $this->selectedLocation === null ? null : Crypt::encryptString(json_encode($this->selectedLocation, JSON_THROW_ON_ERROR)),
        ]);
    }
}
