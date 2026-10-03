@props(['title', 'subtitle' => null, 'breadcrumbs' => []])
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        @if ($breadcrumbs)
            <nav class="mb-1 flex items-center gap-1.5 text-xs text-stone-500" aria-label="Breadcrumb">
                @foreach ($breadcrumbs as $label => $url)
                    @if (! $loop->first)<x-heroicon-o-chevron-right class="size-3" />@endif
                    @if ($url)<a href="{{ $url }}" class="hover:text-stone-800 dark:hover:text-stone-200">{{ $label }}</a>@else<span>{{ $label }}</span>@endif
                @endforeach
            </nav>
        @endif
        <h1 class="font-display text-2xl font-semibold tracking-tight sm:text-3xl">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-sm text-stone-500">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)<div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>@endisset
</div>
