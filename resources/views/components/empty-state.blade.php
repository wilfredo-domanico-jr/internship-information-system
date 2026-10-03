@props(['title', 'description' => null, 'icon' => 'heroicon-o-inbox'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="grid size-14 place-items-center rounded-2xl bg-stone-100 text-stone-400 dark:bg-stone-800"><x-dynamic-component :component="$icon" class="size-7" /></span>
    <h3 class="mt-4 font-display text-base font-semibold">{{ $title }}</h3>
    @if ($description)<p class="mt-1 max-w-sm text-sm text-stone-500">{{ $description }}</p>@endif
    @isset($action)<div class="mt-5">{{ $action }}</div>@endisset
</div>
