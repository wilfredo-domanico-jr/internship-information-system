@props(['variant' => 'primary', 'type' => 'submit', 'href' => null, 'icon' => null])
@php
    $class = match ($variant) {
        'secondary' => 'btn-secondary', 'danger' => 'btn-danger', 'ghost' => 'btn-ghost', default => 'btn-primary',
    };
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>
        @if ($icon)<x-dynamic-component :component="$icon" class="size-4" />@endif{{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }}>
        @if ($icon)<x-dynamic-component :component="$icon" class="size-4" />@endif{{ $slot }}
    </button>
@endif
