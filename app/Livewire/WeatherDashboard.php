<?php

namespace App\Livewire;

use App\Exceptions\WeatherApiException;
use App\Services\Weather\GeocodingService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class WeatherDashboard extends Component
{
    #[Locked]
    public string $mode = 'current';

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

                return;
            }
        }

        $this->searchMessage = 'Please select a valid location from the search results.';
    }

    public function render(): View
    {
        return view('livewire.weather-dashboard');
    }
}
