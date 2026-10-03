@props(['action', 'placeholder' => 'Search…'])
<form method="GET" action="{{ $action }}" {{ $attributes->merge(['class' => 'flex flex-wrap items-end gap-3']) }}>
    <label class="sr-only" for="q">Search</label>
    <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ $placeholder }}" class="input sm:max-w-xs">
    {{ $slot }}
    <x-button type="submit" variant="secondary" icon="heroicon-o-magnifying-glass">Search</x-button>
    @if (request()->query())
        <a href="{{ $action }}" class="text-sm font-medium text-stone-500 hover:text-stone-800 dark:hover:text-stone-200">Reset</a>
    @endif
</form>
