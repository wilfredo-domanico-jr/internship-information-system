<x-layouts.app :title="$class->display_name">
    @include('intern.class.partials.header')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @forelse ($announcements as $announcement)
                @include('classroom.announcement', ['announcement' => $announcement])
            @empty
                <x-card><x-empty-state title="Nothing posted yet" description="Announcements from your adviser will appear here." icon="heroicon-o-megaphone" /></x-card>
            @endforelse
            <x-pagination :paginator="$announcements" />
        </div>

        <div class="space-y-4">
            <x-card title="About this class">
                <x-detail-list class="sm:grid-cols-1" :items="[
                    'Subject' => $class->subject,
                    'Schedule' => $class->schedule_label,
                    'School year' => $class->school_year,
                    'Adviser' => $class->adviser?->name ?? 'Not assigned yet',
                    'Classmates' => $class->intern_profiles_count,
                ]" />
            </x-card>
        </div>
    </div>
</x-layouts.app>
