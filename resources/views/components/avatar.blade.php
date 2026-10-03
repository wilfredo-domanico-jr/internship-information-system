@props(['user', 'size' => 'md'])
@php
    $dim = match ($size) {
        'xs' => 'size-6 text-[10px]', 'sm' => 'size-8 text-xs', 'lg' => 'size-16 text-xl', 'xl' => 'size-24 text-3xl',
        default => 'size-10 text-sm',
    };
@endphp
@if ($user->avatar_path)
    <img src="{{ Storage::disk('public')->url($user->avatar_path) }}" alt="{{ $user->name }}" {{ $attributes->merge(['class' => "$dim rounded-full object-cover"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$dim grid shrink-0 place-items-center rounded-full bg-brand-100 font-semibold text-brand-700 dark:bg-brand-900/50 dark:text-brand-200"]) }} aria-hidden="true">{{ $user->initials }}</span>
@endif
