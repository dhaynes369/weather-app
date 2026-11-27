<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">

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
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <label for="location-search"
                   class="mb-2 block text-sm font-semibold text-slate-700">
                Search location
            </label>

            <input
                id="location-search"
                type="search"
                wire:model.live.debounce.500ms="searchTerm"
                placeholder="Enter a town or city..."
                maxlength="100"
                autocomplete="off"
                class="w-full rounded-xl border border-slate-300
                       bg-slate-50 px-4 py-3 outline-none
                       focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
            >

            <div wire:loading wire:target="searchTerm"
                 role="status"
                 class="mt-3 text-sm text-sky-700">
                Searching locations...
            </div>

            <div wire:loading.remove wire:target="searchTerm">

                @if ($locations !== [])
                    <ul aria-label="Location suggestions"
                        class="mt-3 divide-y divide-slate-100 overflow-hidden
                               rounded-xl border border-slate-200">

                        @foreach ($locations as $location)
                            <li wire:key="location-{{ $location['id'] }}">
                                <button
                                    type="button"
                                    wire:click="selectLocation({{ $location['id'] }})"
                                    class="w-full cursor-pointer px-4 py-3
                                           text-left hover:bg-sky-50
                                           focus:bg-sky-50"
                                >
                                    <span class="block font-semibold text-slate-800">
                                        {{ $location['name'] }}
                                    </span>

                                    <span class="block text-sm text-slate-500">
                                        {{ $location['displayName'] }}
                                    </span>
                                </button>
                            </li>
                        @endforeach

                    </ul>
                @endif

                @if ($searchMessage !== null)
                    <p role="status"
                       class="mt-3 rounded-lg bg-slate-50 p-3
                              text-sm text-slate-700">
                        {{ $searchMessage }}
                    </p>
                @endif

            </div>

            <p class="mt-2 text-xs text-slate-500">
                Enter at least three characters to search worldwide.
            </p>
        </section>

        {{-- Selected location --}}
        @if ($selectedLocation !== null)
            <section class="px-1">
                <h2 class="text-3xl font-bold tracking-tight">
                    {{ $selectedLocation['name'] }}
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    {{ $selectedLocation['displayName'] }}
                </p>
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

                {{-- Temporary state until forecast integration --}}
                <div class="py-12 text-center">
                    <h3 class="text-lg font-semibold">
                        Location selected
                    </h3>

                    <p class="mt-2 text-sm text-slate-500">
                        {{ $selectedLocation['displayName'] }}
                    </p>

                    <p class="mt-2 text-sm text-slate-500">
                        Forecast integration is pending.
                    </p>
                </div>

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