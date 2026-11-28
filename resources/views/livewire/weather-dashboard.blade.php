{{-- Alpine owns browser-local favourites, search focus and permission-based geolocation. Livewire owns forecast retrieval. --}}
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8" x-data="{
        items: [], message: '', open: false, query: '', locating: false, locationMessage: '',
        locate() {
            this.open = false; this.locationMessage = '';
            if (!navigator.geolocation) { this.locationMessage = 'Location detection is unavailable. Please search manually.'; return; }
            this.locating = true;
            navigator.geolocation.getCurrentPosition(async position => {
                try { await this.$wire.selectCoordinates(position.coords.latitude, position.coords.longitude); }
                catch { this.locationMessage = 'Could not load your location. Please search manually.'; }
                finally { this.locating = false; }
            }, error => {
                this.locating = false;
                this.locationMessage = error.code === 1 ? 'Location permission denied. Please search manually.' : 'Could not determine your location. Please search manually.';
            }, {enableHighAccuracy: false, timeout: 10000, maximumAge: 60000});
        },
        matches() { const q = this.query.trim().toLowerCase(); return this.items.filter(item => item.label.toLowerCase().includes(q)); },
        init() {
            try {
                const saved = JSON.parse(localStorage.getItem('weather.favourites.v1') || '[]');
                if (Array.isArray(saved)) this.items = saved.filter(item => item && Number.isInteger(item.id) && typeof item.label === 'string' && typeof item.token === 'string').slice(0, 10);
            } catch { this.message = 'Saved favourites could not be loaded.'; }
        },
        save() {
            try { localStorage.setItem('weather.favourites.v1', JSON.stringify(this.items)); this.message = ''; }
            catch { this.message = 'Favourites could not be saved on this browser.'; }
        },
        has(id) { return this.items.some(item => item.id === id); },
        remove(id) { this.items = this.items.filter(item => item.id !== id); this.save(); },
        toggle(id, label, token) {
            if (this.has(id)) { this.remove(id); return; }
            if (this.items.length >= 10) { this.message = 'You can save up to 10 favourites.'; return; }
            this.items = [...this.items, {id, label, token}]; this.save();
        }
    }">

    {{-- Application header --}}
    <header class="mb-8">
        <div class="flex items-center gap-3">
            <div class="rounded-xl bg-sky-600 p-3 text-white">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-7 w-7"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.5"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M2 12h2M20 12h2
                             M4.93 4.93l1.42 1.42
                             M17.65 17.65l1.42 1.42
                             M4.93 19.07l1.42-1.42
                             M17.65 6.35l1.42-1.42"/>
                </svg>
            </div>

            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Weather Dashboard
                </h1>

                <p class="text-sm text-slate-500">
                    Current conditions and detailed forecasts
                </p>
            </div>
        </div>
    </header>

    <main class="space-y-6">

        {{-- Location search --}}
        <section class="relative z-20 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @click.outside="open = false" @focusout="if (!$event.currentTarget.contains($event.relatedTarget)) open = false" @keydown.escape="open = false">
            <label for="location-search" class="mb-2 block text-sm font-semibold text-slate-700">Search location</label>
            <div class="flex items-center gap-2">
            <input id="location-search" type="search" wire:model.live.debounce.500ms="searchTerm"
                @focus="open = true; query = ''; $event.target.select()"
                @click="open = true" @input="query = $event.target.value; open = true"
                :aria-expanded="open" aria-controls="location-dropdown"
                placeholder="Enter a town or city..." maxlength="100" autocomplete="off"
                class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
            <button type="button" @click="locate()" :disabled="locating" aria-label="Use my current location" title="Use my current location" class="shrink-0 rounded-xl border border-slate-300 p-3 text-sky-700 hover:bg-sky-50 disabled:opacity-50">
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
            </button>
            </div>
            <p x-show="locating" role="status" class="mt-2 text-sm text-sky-700">Detecting location...</p>
            <p x-show="locationMessage" x-text="locationMessage" role="status" class="mt-2 text-sm text-slate-700"></p>
            {{-- Keep pointer interactions inside the dropdown from prematurely blurring the search input. --}}
            <div id="location-dropdown" @mousedown="if ($event.target.closest('button')) $event.preventDefault()" x-show="open" style="display: none" class="absolute inset-x-5 top-full z-30 max-h-80 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                <p x-show="matches().length > 0" class="px-2 py-1 text-xs font-semibold text-slate-500">Favourites</p>
                {{-- Show matching saved locations before provider suggestions. --}}
                <template x-for="item in matches()" :key="item.id">
                    <div class="flex items-center gap-2 border-b border-slate-100">
                        <button type="button" class="min-w-0 flex-1 break-words p-2 text-left hover:bg-sky-50" @click="open = false; $wire.selectFavourite(item.token)" x-text="item.label"></button>
                        <button type="button" class="p-2 text-amber-600" :aria-label="'Remove favourite: ' + item.label" @click="remove(item.id)">★</button>
                    </div>
                </template>
                <div wire:loading wire:target="searchTerm" role="status" class="p-2 text-sm text-sky-700">Searching locations...</div>
                <div wire:loading.remove wire:target="searchTerm" x-show="query.trim().length >= 3">
                    @if ($locations !== [])
                        <p class="px-2 py-1 text-xs font-semibold text-slate-500">Search results</p>
                        @foreach ($locations as $location)
                            <div wire:key="location-{{ $location['id'] }}" x-show="!matches().some(item => item.id === @js($location['id']))" class="flex items-center gap-2 border-b border-slate-100">
                                <button type="button" wire:click="selectLocation({{ $location['id'] }})" @click="open = false" class="min-w-0 flex-1 break-words p-2 text-left hover:bg-sky-50">
                                    <span class="block font-semibold">{{ $location['name'] }}</span>
                                    <span class="block text-sm text-slate-500">{{ $location['displayName'] }}</span>
                                </button>
                                <button type="button" class="p-2 text-slate-500 hover:text-amber-600" :aria-label="(has(@js($location['id'])) ? 'Remove favourite: ' : 'Add favourite: ') + @js($location['displayName'])" @click="toggle(@js($location['id']), @js($location['displayName']), @js(\Illuminate\Support\Facades\Crypt::encryptString(json_encode($location, JSON_THROW_ON_ERROR))))" x-text="has(@js($location['id'])) ? '★' : '☆'"></button>
                            </div>
                        @endforeach
                    @endif
                    @if ($searchMessage !== null)
                        <p role="status" class="p-2 text-sm text-slate-700">{{ $searchMessage }}</p>
                    @endif
                </div>
                <p x-show="query.trim().length < 3 && matches().length === 0" class="p-2 text-sm text-slate-500">No matching favourites. Enter at least three characters to search.</p>
            </div>
            <p class="mt-2 text-xs text-slate-500">Enter at least three characters or use your current location.</p>
            <p role="status" x-show="message" x-text="message" class="text-sm text-slate-600"></p>
            @if ($searchMessage !== null)
                <p x-show="!open" role="status" class="mt-2 text-sm text-slate-700">{{ $searchMessage }}</p>
            @endif
        </section>

        @if ($selectedLocation !== null)
            <section class="px-1">
                <div class="flex items-center gap-2">
                    <h2 class="text-3xl font-bold tracking-tight">{{ $selectedLocation['name'] }}</h2>
                    @if ($selectedLocation['id'] !== 0)
                    <button type="button" class="p-2 text-2xl text-amber-600" :aria-pressed="has(@js($selectedLocation['id']))" :aria-label="has(@js($selectedLocation['id'])) ? 'Remove favourite' : 'Add favourite'" @click="toggle(@js($selectedLocation['id']), @js($selectedLocation['displayName']), @js($favouriteToken))" x-text="has(@js($selectedLocation['id'])) ? '★' : '☆'">☆</button>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-500">{{ $selectedLocation['displayName'] }}</p>
            </section>
        @endif

        {{-- Forecast navigation --}}
        <nav aria-label="Weather forecast view"
             class="grid grid-cols-3 gap-2 rounded-xl border
                    border-slate-200 bg-white p-1.5 shadow-sm">

            @foreach (['current' => 'Current', 'hourly' => 'Hourly', 'daily' => 'Daily'] as $value => $label)
                <button
                    type="button"
                    wire:click="setMode('{{ $value }}')"
                    wire:key="mode-{{ $value }}"
                    aria-pressed="{{ $mode === $value ? 'true' : 'false' }}"
                    @class([
                        'cursor-pointer rounded-lg px-3 py-3 text-sm font-semibold transition-colors',
                        'bg-sky-600 text-white' => $mode === $value,
                        'text-slate-600 hover:bg-slate-100' => $mode !== $value,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach

        </nav>

        <p wire:loading wire:target="selectLocation,selectFavourite,selectCoordinates,retryForecast" role="status" class="text-sm text-sky-700">Loading weather...</p>

        {{-- Weather content --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">

            <h2 class="text-lg font-semibold">
                @switch($mode)
                    @case('hourly')
                        Hourly forecast
                        @break

                    @case('daily')
                        Daily forecast
                        @break

                    @default
                        Current conditions
                @endswitch
            </h2>

            @if ($selectedLocation === null)

                {{-- Initial empty state --}}
                <div class="py-12 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center
                                justify-center rounded-full bg-sky-50 text-sky-600">

                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-8 w-8"
                             viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.5"
                             stroke-linecap="round"
                             stroke-linejoin="round"
                             aria-hidden="true">
                            <path d="M20 10c0 5-8 10-8 10S4 15 4 10a8 8 0 1 1 16 0Z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                    </div>

                    <h3 class="text-lg font-semibold">
                        Select a location
                    </h3>

                    <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">
                        Search for a town or city to explore current weather
                        conditions and detailed forecasts.
                    </p>
                </div>

            @else

                @if ($forecast === null)
                    <div role="alert" class="mt-4 rounded-xl bg-amber-50 p-5 text-amber-900">
                        <p>Weather data is temporarily unavailable. Please try again.</p>
                        <button type="button" wire:click="retryForecast" wire:loading.attr="disabled"
                                class="mt-3 rounded-lg bg-amber-800 px-4 py-2 text-white">Retry</button>
                    </div>
                @else
                    @if ($forecast->isStale)
                        <p role="status" class="mt-4 rounded-lg bg-amber-50 p-3 text-amber-900">
                            Showing cached weather data. The latest update could not be retrieved.
                        </p>
                    @endif
                    @php
                        $days = $forecast->upcomingDaily($displayDays);
                        $day = collect($days)->firstWhere('time', $selectedDate) ?? ($days[0] ?? null);
                    @endphp
                    @if ($mode === 'daily')
                        <x-weather.daily-tiles :days="$days" :selected-date="$day['time'] ?? null" :display-days="$displayDays" :presenter="$presenter" />
                        @if ($day !== null)
                            <h3 class="mt-6 text-xl font-semibold">{{ \Carbon\CarbonImmutable::parse($day['time'], $forecast->timezone)->format('l, j F') }}</h3>
                            <x-weather.forecast-advisory :date="$day['time']" :timezone="$forecast->timezone" />
                            <x-weather.categories :categories="$presenter->categories($forecast, $day, 'daily')" />
                        @else
                            <p class="mt-4 text-slate-500">No daily forecast available.</p>
                        @endif
                    @elseif ($mode === 'current')
                        <x-weather.current-card :forecast="$forecast" :presenter="$presenter" />
                        <x-weather.categories :categories="$presenter->categories($forecast, $forecast->current, 'current')" />
                    @else
                        @php
                            $hours = $this->hourlyRecords();
                            $hour = collect($hours)->firstWhere('time', $selectedHour) ?? ($hours[0] ?? null);
                        @endphp
                        <x-weather.hourly-tiles :hours="$hours" :selected-hour="$hour['time'] ?? null" :forecast="$forecast" :presenter="$presenter" :selected-date="$selectedDate" />
                        <x-weather.temperature-chart :hours="$hours" :forecast="$forecast" />
                        @if ($hour !== null)
                            <h3 class="mt-6 text-xl font-semibold">{{ $forecast->localTime($hour['time'])->format('l, j F · H:i T') }}</h3>
                            <x-weather.forecast-advisory :date="$forecast->localTime($hour['time'])->toDateString()" :timezone="$forecast->timezone" />
                            <x-weather.categories :categories="$presenter->categories($forecast, $hour, 'hourly')" />
                        @else
                            <p class="mt-4 text-slate-500">No upcoming hourly forecast available for this period.</p>
                        @endif
                    @endif
                    @if ($mode !== 'daily')
                        <x-weather.daily-tiles :days="$days" :selected-date="$mode === 'hourly' ? $selectedDate : null" :display-days="$displayDays" :presenter="$presenter" />
                    @endif
                    <x-weather.technical-information :forecast="$forecast" />
                    <p class="mt-3 text-xs text-slate-500">Retrieved: {{ $forecast->localRetrievedAt()->format('d M Y H:i T') }} · {{ $forecast->ageInSeconds() }} seconds ago</p>
                @endif

            @endif

        </section>

    </main>

    {{-- Provider attribution --}}
    <footer class="mt-8 text-center text-xs text-slate-500">
        Weather data provided by
        <a href="https://open-meteo.com/"
           target="_blank"
           rel="noopener noreferrer"
           class="font-medium text-sky-700 hover:underline">
            Open-Meteo
        </a>
    </footer>

</div>
