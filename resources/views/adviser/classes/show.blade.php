<x-layouts.app :title="$class->display_name">
    @include('adviser.classes.partials.header')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Task 7 adds the announcement composer here --}}
            @forelse ($announcements as $announcement)
                @include('classroom.announcement', ['announcement' => $announcement])
            @empty
                <x-card><x-empty-state title="No announcements yet" description="Posts to the class stream will appear here." icon="heroicon-o-megaphone" /></x-card>
            @endforelse
            <x-pagination :paginator="$announcements" />
        </div>

        <div class="space-y-4">
            <x-card title="About this class">
                <x-detail-list class="sm:grid-cols-1" :items="[
                    'Subject' => $class->subject,
                    'Schedule' => $class->schedule_label,
                    'School year' => $class->school_year,
                    'Adviser' => $class->adviser?->name ?? 'No adviser',
                    'Interns' => $class->intern_profiles_count,
                ]" />
            </x-card>
        </div>
    </div>
</x-layouts.app>
