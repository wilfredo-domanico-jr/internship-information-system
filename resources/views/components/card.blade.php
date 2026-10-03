@props(['title' => null, 'subtitle' => null, 'padding' => true])
<section {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($actions))
        <header class="flex items-start justify-between gap-4 border-b border-stone-200/80 px-5 py-4 dark:border-stone-800">
            <div>
                @if ($title)<h2 class="font-display text-base font-semibold">{{ $title }}</h2>@endif
                @if ($subtitle)<p class="mt-0.5 text-sm text-stone-500">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="shrink-0">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div @class(['p-5' => $padding])>{{ $slot }}</div>
</section>
