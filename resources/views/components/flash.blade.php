@php
    $types = [
        'success' => ['border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200', 'heroicon-o-check-circle'],
        'error' => ['border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200', 'heroicon-o-exclamation-circle'],
        'warning' => ['border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200', 'heroicon-o-exclamation-triangle'],
        'info' => ['border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200', 'heroicon-o-information-circle'],
    ];
@endphp
@foreach ($types as $key => [$classes, $icon])
    @if (session($key))
        <div x-data="{ show: true }" x-show="show" x-transition role="status" class="flex items-start gap-3 rounded-2xl border p-4 text-sm {{ $classes }}">
            <x-dynamic-component :component="$icon" class="mt-0.5 size-5 shrink-0" />
            <p class="flex-1">{{ session($key) }}</p>
            <button type="button" @click="show = false" class="-m-1 rounded-lg p-1 opacity-70 hover:opacity-100" aria-label="Dismiss">
                <x-heroicon-o-x-mark class="size-4" />
            </button>
        </div>
    @endif
@endforeach
