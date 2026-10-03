@props(['item'])
@if (Route::has($item['route']))
    @php $active = request()->routeIs($item['active'] ?? $item['route']); @endphp
    <a href="{{ route($item['route']) }}"
       @class([
           'flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition',
           'bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-200' => $active,
           'text-stone-600 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800' => ! $active,
       ])
       @if ($active) aria-current="page" @endif>
        <x-dynamic-component :component="$item['icon']" class="size-5 shrink-0" />
        <span class="flex-1">{{ $item['label'] }}</span>
        @if (! empty($item['badge']))
            <span class="rounded-full bg-brand-600 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $item['badge'] }}</span>
        @endif
    </a>
@endif
