@props(['status' => null, 'color' => null])
@php
    $color = $color ?? ($status?->badgeColor() ?? 'gray');
    $label = $slot->isNotEmpty() ? $slot : ($status?->label() ?? '');
    $classes = match ($color) {
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300',
        'teal' => 'bg-brand-50 text-brand-700 ring-brand-600/20 dark:bg-brand-500/10 dark:text-brand-300',
        default => 'bg-stone-100 text-stone-700 ring-stone-500/20 dark:bg-stone-500/10 dark:text-stone-300',
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset $classes"]) }} data-color="{{ $color }}">{{ $label }}</span>
