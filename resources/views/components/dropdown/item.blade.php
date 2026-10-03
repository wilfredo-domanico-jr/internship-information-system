@props(['href' => '#', 'icon' => null])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-stone-700 hover:bg-stone-100 dark:text-stone-200 dark:hover:bg-stone-800']) }}>
    @if ($icon)<x-dynamic-component :component="$icon" class="size-4 text-stone-500" />@endif
    {{ $slot }}
</a>
