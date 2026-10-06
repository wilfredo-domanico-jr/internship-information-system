<x-layouts.app title="Dashboard">
    <x-page-header :title="'Hello, '.auth()->user()->first_name" subtitle="Your classes and the interns you advise." />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Classes" :value="$stats['classes']" icon="heroicon-o-rectangle-group" />
        <x-stat-card label="Interns" :value="$stats['interns']" icon="heroicon-o-academic-cap" color="sky" />
        <x-stat-card label="Completed OJT" :value="$stats['completed']" icon="heroicon-o-check-badge" color="green" />
    </div>

    <x-card title="My classes" :padding="false">
        @forelse ($classes as $class)
            <div class="flex items-center justify-between gap-4 border-b border-stone-100 px-5 py-4 last:border-0 dark:border-stone-800">
                <div>
                    <p class="font-semibold"><a href="{{ route('adviser.classes.show', $class) }}" class="hover:underline">{{ $class->display_name }}</a></p>
                    <p class="text-sm text-stone-500">{{ $class->subject }} · {{ $class->schedule_label }} · {{ $class->school_year }}</p>
                </div>
                <div class="text-right">
                    <p class="font-display text-xl font-semibold tabular-nums">{{ $class->intern_profiles_count }}</p>
                    <p class="text-xs text-stone-500">interns</p>
                </div>
            </div>
        @empty
            <x-empty-state title="No classes yet" description="Classes you advise will appear here." icon="heroicon-o-rectangle-group" />
        @endforelse
    </x-card>
</x-layouts.app>
