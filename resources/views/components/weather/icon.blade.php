@props(['name'])
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @if (in_array($name, ['sun', 'cloud-sun']))
        <circle cx="{{ $name === 'sun' ? 12 : 7 }}" cy="{{ $name === 'sun' ? 12 : 7 }}" r="4"/>
        @if ($name === 'sun')<path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2"/>@endif
    @endif
    @if (in_array($name, ['moon', 'cloud-moon']))
        <path d="M11 2a8 8 0 0 0 10 10A9 9 0 1 1 11 2Z"/>
    @endif
    @if (str_starts_with($name, 'cloud'))
        <path d="M6 17a4 4 0 1 1 0-8 6 6 0 0 1 11 1 3.5 3.5 0 1 1 1 7Z"/>
    @endif
    @switch($name)
        @case('cloud-rain') @case('cloud-rain-heavy') @case('cloud-drizzle')
            <path d="M8 19l-1 3m5-3-1 3m5-3-1 3"/>@break
        @case('cloud-snow') @case('cloud-sleet') @case('cloud-hail')
            <circle cx="8" cy="21" r=".7"/><circle cx="12" cy="20" r=".7"/><circle cx="16" cy="22" r=".7"/>@break
        @case('cloud-fog')<path d="M5 20h14M8 23h8"/>@break
        @case('cloud-lightning')<path d="M13 17l-3 4h4l-2 3"/>@break
        @case('circle-help')<circle cx="12" cy="12" r="9"/><path d="M9 9a3 3 0 1 1 4 3c-1 1-1 2-1 2m0 3h.01"/>@break
    @endswitch
</svg>
