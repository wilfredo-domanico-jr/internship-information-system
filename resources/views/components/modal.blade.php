@props(['name', 'title' => null, 'maxWidth' => 'max-w-lg'])
<div x-data="{ show: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') show = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') show = false"
     x-on:keydown.escape.window="show = false"
     x-cloak x-show="show"
     class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif>
    <div x-show="show" x-transition.opacity class="fixed inset-0 bg-stone-900/60" @click="show = false"></div>
    <div x-show="show" x-transition class="relative w-full text-left {{ $maxWidth }} rounded-2xl bg-white p-6 shadow-xl dark:bg-stone-900">
        <div class="flex items-start justify-between gap-4">
            @if ($title)<h2 class="font-display text-lg font-semibold">{{ $title }}</h2>@endif
            <button type="button" class="btn-ghost -mr-2 -mt-1 p-1.5" @click="show = false" aria-label="Close"><x-heroicon-o-x-mark class="size-5" /></button>
        </div>
        <div class="mt-4">{{ $slot }}</div>
    </div>
</div>
