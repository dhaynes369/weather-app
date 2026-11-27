@props(['forecast', 'presenter'])
@php
    $condition = $presenter->condition($forecast);
    $today = $presenter->today($forecast);
@endphp
<div class="mt-4 rounded-2xl bg-sky-800 p-6 text-white sm:p-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <p class="text-sm text-sky-200">{{ $forecast->location->name }}</p>
            <p class="mt-2 text-4xl font-bold sm:text-6xl">{{ $presenter->measurement($forecast->currentValue('temperature_2m'), '°C') }}</p>
            <p class="mt-2 text-xl">{{ $condition['description'] }}</p>
            <p class="text-sky-100">Feels like {{ $presenter->measurement($forecast->currentValue('apparent_temperature'), '°C') }}</p>
        </div>
        <x-weather.icon :name="$condition['icon']" class="h-20 w-20 shrink-0 sm:h-28 sm:w-28" />
    </div>
    <div class="mt-5 flex flex-wrap gap-5 text-sm">
        <span>High {{ $presenter->measurement($today['temperature_2m_max'] ?? null, '°C') }}</span>
        <span>Low {{ $presenter->measurement($today['temperature_2m_min'] ?? null, '°C') }}</span>
    </div>
    <div class="mt-5 grid grid-cols-2 gap-4 border-t border-sky-600 pt-4">
        <div><p class="text-sm text-sky-200">Humidity</p><p class="text-xl font-semibold">{{ $presenter->measurement($forecast->currentValue('relative_humidity_2m'), '%') }}</p></div>
        <div><p class="text-sm text-sky-200">Wind speed</p><p class="text-xl font-semibold">{{ $presenter->measurement($forecast->currentValue('wind_speed_10m'), ' mph') }}</p></div>
    </div>
    <p class="mt-5 text-xs text-sky-200">Measurement time: {{ $forecast->localCurrentTime()->format('d M Y H:i T') }}</p>
</div>
