<x-layouts.app title="Dashboard">
    <x-page-header title="Hi, {{ auth()->user()->first_name }}" subtitle="Track your hours, placement and class in one place." />

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card title="OJT progress" class="lg:row-span-2">
            <div class="flex flex-col items-center gap-4 text-center">
                <x-progress-ring :percent="$progress['percent']" :color="$progress['tier']->badgeColor()" label="of required hours" :size="160" />
                <div>
                    <p class="font-display text-3xl font-semibold tabular-nums">{{ $progress['hours'] }} <span class="text-base font-normal text-stone-500">/ {{ $progress['required'] }} h</span></p>
                    <p class="mt-1 text-sm text-stone-500">{{ $progress['remaining'] }} hours remaining</p>
                </div>
                <x-badge :status="$progress['tier']" />
            </div>
        </x-card>

        <x-card title="Current placement" class="lg:col-span-2">
            @if ($placement)
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-display text-lg font-semibold">{{ $placement->company->name }}</p>
                        <p class="text-sm text-stone-500">Since {{ $placement->started_at->format('M j, Y') }} · {{ $placement->hours_rendered }} h rendered here</p>
                    </div>
                    <x-badge color="green">Active</x-badge>
                </div>
            @else
                <x-empty-state title="Not placed yet" description="Apply to an internship posting and join a company with its code once you are accepted." icon="heroicon-o-briefcase" class="py-8" />
            @endif
        </x-card>

        <x-card title="My class" class="lg:col-span-2">
            @if ($section)
                <p class="font-display text-lg font-semibold">{{ $section->display_name }}</p>
                <p class="text-sm text-stone-500">{{ $section->subject }} · {{ $section->schedule_label }}</p>
                <p class="mt-3 text-sm">Adviser: <span class="font-medium">{{ $section->adviser?->name ?? 'Not assigned yet' }}</span></p>
            @else
                <x-empty-state title="No class joined" description="Ask your adviser for the class join code." icon="heroicon-o-rectangle-group" class="py-8" />
            @endif
        </x-card>
    </div>
</x-layouts.app>
