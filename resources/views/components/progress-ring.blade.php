@props(['percent' => 0, 'size' => 128, 'stroke' => 10, 'color' => 'brand', 'label' => null])
@php
    $percent = max(0, min(100, (int) $percent));
    $radius = ($size - $stroke) / 2;
    $circumference = 2 * M_PI * $radius;
    $offset = $circumference * (1 - $percent / 100);
    $strokeClass = match ($color) {
        'green' => 'stroke-emerald-500', 'amber' => 'stroke-amber-500', 'rose' => 'stroke-rose-500', default => 'stroke-brand-600',
    };
@endphp
<div {{ $attributes->merge(['class' => 'relative inline-grid place-items-center']) }} style="width: {{ $size }}px; height: {{ $size }}px" role="img" aria-label="{{ $percent }} percent">
    <svg width="{{ $size }}" height="{{ $size }}" class="-rotate-90">
        <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}" stroke-width="{{ $stroke }}" fill="none" class="stroke-stone-200 dark:stroke-stone-800" />
        <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}" stroke-width="{{ $stroke }}" fill="none" stroke-linecap="round"
                class="{{ $strokeClass }} transition-[stroke-dashoffset] duration-700"
                stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}" />
    </svg>
    <div class="absolute inset-0 grid place-items-center text-center">
        <div>
            <p class="font-display text-2xl font-semibold tabular-nums">{{ $percent }}%</p>
            @if ($label)<p class="text-xs text-stone-500">{{ $label }}</p>@endif
        </div>
    </div>
</div>
