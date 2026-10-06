@props(['steps' => []])
<ol {{ $attributes->merge(['class' => 'relative space-y-5 border-l border-stone-200 pl-6 dark:border-stone-800']) }}>
    @foreach ($steps as $step)
        @php
            $state = $step['state'] ?? 'upcoming';
            $dot = match ($state) {
                'done' => 'bg-emerald-500 ring-emerald-100 dark:ring-emerald-500/20',
                'current' => 'bg-brand-600 ring-brand-100 dark:ring-brand-500/20',
                'failed' => 'bg-rose-500 ring-rose-100 dark:ring-rose-500/20',
                default => 'bg-stone-300 ring-stone-100 dark:bg-stone-700 dark:ring-stone-800',
            };
        @endphp
        <li class="relative" data-state="{{ $state }}">
            <span class="absolute -left-[31px] top-1 size-3 rounded-full ring-4 {{ $dot }}" aria-hidden="true"></span>
            <p @class(['text-sm font-semibold', 'text-stone-400 dark:text-stone-500' => $state === 'upcoming'])>{{ $step['label'] }}</p>
            @if (! empty($step['description']))<p class="text-sm text-stone-600 dark:text-stone-300">{{ $step['description'] }}</p>@endif
            @if (! empty($step['at']))<time class="text-xs text-stone-500" datetime="{{ $step['at']->toIso8601String() }}">{{ $step['at']->format('M j, Y g:i A') }}</time>@endif
        </li>
    @endforeach
</ol>
