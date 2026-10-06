<x-layouts.app title="My classes">
    <x-page-header title="My classes" subtitle="Classes you advise this school year.">
        <x-slot:actions>
            <x-button :href="route('adviser.classes.create')" icon="heroicon-o-plus">New class</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($classes->isEmpty())
        <x-card>
            <x-empty-state title="You have no classes yet" description="Create a class, or claim one the office imported by entering its join code." icon="heroicon-o-rectangle-group">
                <x-slot:action><x-button :href="route('adviser.classes.create')" icon="heroicon-o-plus">New class</x-button></x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($classes as $class)
                <x-card class="flex flex-col">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-display text-lg font-semibold">{{ $class->display_name }}</p>
                            <p class="text-sm text-stone-500">{{ $class->subject }} · {{ $class->school_year }}</p>
                            <p class="mt-1 text-sm text-stone-500">{{ $class->schedule_label }}</p>
                        </div>
                        <x-badge :status="$class->status" />
                    </div>
                    <dl class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
                        <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-900"><dt class="text-xs text-stone-500">Interns</dt><dd class="font-semibold tabular-nums">{{ $class->intern_profiles_count }}</dd></div>
                        <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-900"><dt class="text-xs text-stone-500">Folders</dt><dd class="font-semibold tabular-nums">{{ $class->folders_count }}</dd></div>
                        <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-900"><dt class="text-xs text-stone-500">Posts</dt><dd class="font-semibold tabular-nums">{{ $class->announcements_count }}</dd></div>
                    </dl>
                    <p class="mt-4 text-xs text-stone-500">{{ $class->intern_profiles_count }} interns · Join code <span class="font-mono font-semibold text-stone-800 dark:text-stone-100">{{ $class->join_code }}</span></p>
                    <div class="mt-4 flex items-center gap-2">
                        <x-button variant="secondary" :href="route('adviser.classes.edit', $class)" icon="heroicon-o-pencil-square">Edit</x-button>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
