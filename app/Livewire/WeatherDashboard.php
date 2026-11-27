<?php

namespace App\Livewire;

use App\Data\Weather\ForecastData;
use App\Data\Weather\LocationData;
use App\Exceptions\WeatherApiException;
use App\Services\Weather\ForecastService;
use App\Services\Weather\GeocodingService;
use App\Support\WeatherPresenter;
use Illuminate\Contracts\View\View;
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

    public function setDisplayDays(int $days): void
    {
        if (! in_array($days, [7, 14], true)) {
            return;
        }
        $this->displayDays = $days;
        $this->selectedDate = null;
    }

    public function selectDay(string $date): void
    {
        foreach ($this->forecast?->upcomingDaily($this->displayDays) ?? [] as $record) {
            if ($record['time'] === $date) {
                $this->selectedDate = $date;
                $this->mode = 'daily';

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

    public function setMode(string $mode): void
    {
        if (! in_array($mode, ['current', 'hourly', 'daily'], true)) {
            return;
        }

        $this->mode = $mode;
    }

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

    public function selectLocation(int $id): void
    {
        foreach ($this->locations as $location) {
            if ($location['id'] === $id) {
                $this->selectedLocation = $location;
                $this->searchTerm = $location['displayName'];
                $this->locations = [];
                $this->searchMessage = null;
                $this->mode = 'current';
                $this->selectedDate = null;
                $this->displayDays = 7;

                return;
            }
        }

        $this->searchMessage = 'Please select a valid location from the search results.';
    }

    #[Computed]
    public function forecast(): ?ForecastData
    {
        if ($this->selectedLocation === null) {
            return null;
        }

        $data = $this->selectedLocation;
        unset($data['displayName']);
        /** @var array{id: int, name: string, latitude: float, longitude: float, country: ?string, countryCode: ?string, region: ?string, district: ?string, localAuthority: ?string, timezone: ?string, elevation: ?float, population: ?int, postcodes: list<string>} $data */
        try {
            return app(ForecastService::class)->getForecast(new LocationData(...$data));
        } catch (WeatherApiException) {
            return null;
        }
    }

    public function retryForecast(): void
    {
        unset($this->forecast);
    }

    public function render(): View
    {
        return view('livewire.weather-dashboard', [
            'forecast' => $this->forecast,
            'presenter' => app(WeatherPresenter::class),
        ]);
    }
}
