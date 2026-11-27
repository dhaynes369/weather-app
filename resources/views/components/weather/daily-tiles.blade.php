@props(['days', 'selectedDate', 'displayDays', 'presenter'])
<section class="mt-6" aria-label="Daily forecast navigation">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-semibold">Daily outlook</h3>
        <div class="flex gap-1 rounded-lg bg-slate-100 p-1">
            @foreach ([7, 14] as $length)
                <button type="button" wire:click="setDisplayDays({{ $length }})" aria-pressed="{{ $displayDays === $length ? 'true' : 'false' }}" @class(['rounded-md px-3 py-2 text-sm', 'bg-sky-600 text-white' => $displayDays === $length])>{{ $length }} days</button>
            @endforeach
        </div>
    </div>
    <div class="flex gap-2 overflow-x-auto pb-2">
        @foreach ($days as $record)
            @php
                $date = $record['time'];
                $code = $record['weather_code'] ?? null;
                $condition = app(\App\Services\Weather\WeatherCodeMapper::class)->map(is_numeric($code) ? (float) $code : null);
            @endphp
            <button type="button" wire:key="daily-{{ $date }}" wire:click="selectDay('{{ $date }}')" aria-pressed="{{ $selectedDate === $date ? 'true' : 'false' }}" @class(['flex min-w-28 flex-1 flex-col items-center gap-2 rounded-xl border p-3 text-sm', 'border-sky-600 bg-sky-50' => $selectedDate === $date, 'border-slate-200 bg-slate-50' => $selectedDate !== $date])>
                <span class="font-semibold">{{ \Carbon\CarbonImmutable::parse($date)->format('D j M') }}</span>
                <x-weather.icon :name="$condition['icon']" class="h-9 w-9 text-sky-700" />
                <span class="sr-only">{{ $condition['description'] }}</span>
                <span class="font-bold">{{ $presenter->measurement($record['temperature_2m_max'] ?? null, '°C') }}</span>
                <span class="text-slate-600">{{ $presenter->measurement($record['temperature_2m_min'] ?? null, '°C') }}</span>
                <span class="text-xs text-sky-700" title="Maximum hourly precipitation probability">{{ $presenter->measurement($record['precipitation_probability_max'] ?? null, '%') }}</span>
            </button>
        @endforeach
    </div>
    <p class="mt-1 text-xs text-slate-500">High / low temperature · Maximum hourly precipitation probability</p>
</section>
