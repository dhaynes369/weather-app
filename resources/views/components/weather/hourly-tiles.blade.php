@props(['hours', 'selectedHour', 'forecast', 'presenter', 'selectedDate'])
<section class="mt-6" aria-label="Hourly forecast navigation">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-semibold">{{ $selectedDate === null ? 'Next 24 hours' : 'Hours for '.\Carbon\CarbonImmutable::parse($selectedDate)->format('D j M') }}</h3>
        @if ($selectedDate !== null)
            <button type="button" wire:click="showUpcomingHours" class="rounded-lg bg-slate-100 px-3 py-2 text-sm text-sky-700">Back to next 24 hours</button>
        @endif
    </div>
    <div class="flex gap-2 overflow-x-auto pb-2">
        @foreach ($hours as $record)
            @php
                $timestamp = $record['time'];
                $code = $record['weather_code'] ?? null;
                $condition = app(\App\Services\Weather\WeatherCodeMapper::class)->map(is_numeric($code) ? (float) $code : null, ($record['is_day'] ?? 1) != 0);
            @endphp
            <button type="button" wire:key="hourly-{{ $timestamp }}" wire:click="selectHour({{ $timestamp }})" aria-pressed="{{ $selectedHour === $timestamp ? 'true' : 'false' }}" @class(['flex min-w-28 flex-col items-center gap-2 rounded-xl border p-3 text-sm', 'border-sky-600 bg-sky-50' => $selectedHour === $timestamp, 'border-slate-200 bg-slate-50' => $selectedHour !== $timestamp])>
                <span class="text-xs text-slate-500">{{ $forecast->localTime($timestamp)->format('D j M') }}</span>
                <span class="font-semibold">{{ $forecast->localTime($timestamp)->format('H:i T') }}</span>
                <x-weather.icon :name="$condition['icon']" class="h-9 w-9 text-sky-700" />
                <span class="sr-only">{{ $condition['description'] }}</span>
                <span class="font-bold">{{ $presenter->measurement($record['temperature_2m'] ?? null, '°C') }}</span>
                <span class="text-xs text-sky-700" title="Precipitation probability">{{ $presenter->measurement($record['precipitation_probability'] ?? null, '%') }}</span>
            </button>
        @endforeach
    </div>
    <p class="mt-1 text-xs text-slate-500">Temperature · Hourly precipitation probability · Local time</p>
</section>
