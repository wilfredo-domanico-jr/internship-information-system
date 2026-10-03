@props(['align' => 'right', 'width' => 'w-56'])
<div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
    <div @click="open = ! open">{{ $trigger }}</div>
    <div x-cloak x-show="open" @click.outside="open = false" x-transition.origin.top
         class="absolute z-30 mt-2 {{ $width }} {{ $align === 'right' ? 'right-0' : 'left-0' }} overflow-hidden rounded-2xl border border-stone-200 bg-white p-1.5 shadow-lg dark:border-stone-700 dark:bg-stone-900">
        {{ $slot }}
    </div>
</div>
