@props(['categories'])
<section class="mt-6 space-y-3" aria-label="Detailed weather measurements">
    <h3 class="text-lg font-semibold">Weather details</h3>
    @foreach ($categories as $category)
        <details wire:key="weather-category-{{ $loop->index }}" class="group rounded-xl border border-slate-200 bg-white">
            <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 rounded-xl p-4 focus-visible:outline-2 focus-visible:outline-sky-600 [&::-webkit-details-marker]:hidden">
                <span class="flex-1 font-semibold text-slate-800">{{ $category['title'] }}</span>
                <span class="text-right text-sm font-medium text-slate-700">{{ $category['summary'] }}</span>
                <svg class="h-5 w-5 shrink-0 text-slate-500 group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </summary>
            <dl class="grid gap-x-8 gap-y-3 border-t border-slate-100 p-4 sm:grid-cols-2">
                @foreach ($category['measurements'] as $label => $value)
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <dt class="text-slate-600">{{ $label }}</dt>
                        <dd class="text-right font-medium text-slate-900">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </details>
    @endforeach
</section>
