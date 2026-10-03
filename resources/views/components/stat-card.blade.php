@props(['label', 'value', 'icon' => null, 'hint' => null, 'color' => 'brand'])
@php
    $tone = match ($color) {
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10',
        'green' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10',
        'rose' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10',
        'sky' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10',
        default => 'bg-brand-50 text-brand-600 dark:bg-brand-500/10',
    };
@endphp
<div {{ $attributes->merge(['class' => 'card flex items-center gap-4 p-5']) }}>
    @if ($icon)
        <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $tone }}"><x-dynamic-component :component="$icon" class="size-5" /></span>
    @endif
    <div class="min-w-0">
        <p class="text-sm text-stone-500">{{ $label }}</p>
        <p class="font-display text-2xl font-semibold tabular-nums">{{ $value }}</p>
        @if ($hint)<p class="text-xs text-stone-500">{{ $hint }}</p>@endif
    </div>
</div>
