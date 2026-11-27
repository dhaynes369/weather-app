@props(['hours', 'forecast'])

@php
    $values = [];
    foreach ($hours as $record) {
        foreach (['temperature_2m', 'apparent_temperature'] as $field) {
            if (is_numeric($record[$field] ?? null)) {
                $values[] = (float) $record[$field];
            }
        }
    }
    $count = count($hours);
    $min = $values === [] ? 0 : floor(min($values)) - 1;
    $max = $values === [] ? 1 : ceil(max($values)) + 1;
    $x = fn ($i) => 48 + 624 * $i / max(1, $count - 1);
    $y = fn ($value) => 16 + 152 * ($max - $value) / ($max - $min);
@endphp

<section class="mt-6 rounded-xl border border-slate-200 p-4" aria-label="Hourly temperature chart">
    <h3 class="font-semibold">Hourly temperature chart</h3>
    @if ($values === [])
        <p class="mt-2 text-sm text-slate-500">Temperature forecast unavailable for this period.</p>
    @else
        <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
            <span><span class="mr-1 inline-block h-2 w-4 rounded bg-sky-600"></span>Temperature</span>
            <span><span class="mr-1 inline-block h-2 w-4 rounded bg-orange-600"></span>Feels like</span>
        </div>
        <div class="overflow-x-auto">
            <svg viewBox="0 0 700 210" class="mt-3 w-full min-w-[420px]" role="img" aria-label="Hourly temperature and feels-like temperature in degrees Celsius">
                @foreach (range(0, 4) as $tick)
                    @php $value = $min + ($max - $min) * $tick / 4; @endphp
                    <line x1="48" x2="672" y1="{{ $y($value) }}" y2="{{ $y($value) }}" stroke="#e2e8f0" />
                    <text x="40" y="{{ $y($value) + 4 }}" text-anchor="end" font-size="11" fill="#475569">{{ round($value, 1) }}°</text>
                @endforeach
                @foreach (['temperature_2m' => '#0284c7', 'apparent_temperature' => '#ea580c'] as $field => $color)
                    @php $previous = null; @endphp
                    @foreach ($hours as $i => $record)
                        @php $value = $record[$field] ?? null; @endphp
                        @if (is_numeric($value))
                            @if ($previous !== null)
                                <line x1="{{ $previous[0] }}" y1="{{ $previous[1] }}" x2="{{ $x($i) }}" y2="{{ $y($value) }}" stroke="{{ $color }}" stroke-width="2.5" stroke-linecap="round" />
                            @endif
                            <circle cx="{{ $x($i) }}" cy="{{ $y($value) }}" r="2.5" fill="{{ $color }}"><title>{{ $forecast->localTime($record['time'])->format('D H:i T') }}: {{ $value }}°C ({{ $field === 'temperature_2m' ? 'Temperature' : 'Feels like' }})</title></circle>
                            @php $previous = [$x($i), $y($value)]; @endphp
                        @else
                            @php $previous = null; @endphp
                        @endif
                    @endforeach
                @endforeach
                @foreach ($hours as $i => $record)
                    @if ($i % max(1, (int) ceil($count / 6)) === 0)
                        <text x="{{ $x($i) }}" y="190" text-anchor="middle" font-size="11" fill="#475569">{{ $forecast->localTime($record['time'])->format('H:i') }}</text>
                        <text x="{{ $x($i) }}" y="204" text-anchor="middle" font-size="10" fill="#64748b">{{ $forecast->localTime($record['time'])->format('D j M') }}</text>
                    @endif
                @endforeach
            </svg>
        </div>
        <p class="text-xs text-slate-500">Local time · °C. Select an hourly tile for exact measurements.</p>
    @endif
</section>
