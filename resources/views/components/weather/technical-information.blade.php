@props(['forecast'])

<details class="mt-6 rounded-xl border border-slate-200 bg-white p-4">
    <summary class="cursor-pointer font-semibold text-slate-800">Technical information</summary>
    <p class="mt-3 text-sm text-slate-600">Weather model estimates, not direct station observations. Data age reflects retrieval time, not model run time.</p>
    @php
        $location = $forecast->location;
        $metadata = [
            'Provider' => 'Open-Meteo',
            'Data status' => $forecast->isStale ? 'Stale fallback' : 'Within cache freshness window',
            'Retrieved (local)' => $forecast->localRetrievedAt()->format('d M Y H:i:s T'),
            'Current measurement time' => $forecast->localCurrentTime()->format('d M Y H:i:s T'),
            'Retrieval age' => $forecast->ageInSeconds().' seconds',
            'Current measurement interval' => $forecast->currentInterval.' seconds',
            'Hourly records' => count($forecast->hourly),
            'Daily records' => count($forecast->daily),
            'Forecast timezone' => $forecast->timezone,
            'Provider UTC offset' => $forecast->utcOffsetSeconds.' seconds',
            'Provider location ID' => $location->id,
            'Selected latitude' => $location->latitude,
            'Selected longitude' => $location->longitude,
            'Selected elevation' => $location->elevation === null ? 'Unavailable' : $location->elevation.' m',
            'Grid latitude' => $forecast->gridLatitude,
            'Grid longitude' => $forecast->gridLongitude,
            'Grid elevation' => $forecast->gridElevation === null ? 'Unavailable' : $forecast->gridElevation.' m',
            'Country code' => $location->countryCode ?? 'Unavailable',
            'Region' => $location->region ?? 'Unavailable',
            'District' => $location->district ?? 'Unavailable',
            'Local authority' => $location->localAuthority ?? 'Unavailable',
            'Population' => $location->population === null ? 'Unavailable' : number_format($location->population),
            'Postcodes' => $location->postcodes === [] ? 'Unavailable' : implode(', ', $location->postcodes),
        ];
    @endphp
    <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
        @foreach ($metadata as $label => $value)
            <div class="min-w-0 border-b border-slate-100 pb-2">
                <dt class="text-xs text-slate-500">{{ $label }}</dt>
                <dd class="break-words text-sm font-medium text-slate-800">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
</details>
