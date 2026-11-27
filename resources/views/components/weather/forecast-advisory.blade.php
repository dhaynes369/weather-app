@props(['date', 'timezone'])
@php
    $today = \Carbon\CarbonImmutable::now($timezone)->startOfDay();
    $forecastDate = \Carbon\CarbonImmutable::parse($date, $timezone)->startOfDay();
    $leadDays = $today->diffInDays($forecastDate);
@endphp
@if ($leadDays >= 7)
    <p role="status" class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
        Extended-range forecast. Conditions are indicative and subject to significant change, particularly hourly predictions.
    </p>
@elseif ($leadDays >= 3)
    <p role="status" class="mt-3 rounded-lg bg-slate-100 p-3 text-sm text-slate-700">
        Forecast uncertainty increases further ahead.
    </p>
@endif
