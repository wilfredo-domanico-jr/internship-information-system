<x-layouts.app :title="$class->display_name">
    <x-page-header :title="$class->display_name" :subtitle="$class->subject.' · '.$class->schedule_label.' · '.$class->school_year" :breadcrumbs="['Classes' => route('admin.classes.index'), $class->display_name => null]">
        <x-slot:actions><x-badge :status="$class->status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Class">
            <x-detail-list class="sm:grid-cols-1" :items="[
                'Adviser' => $class->adviser?->name ?? 'No adviser',
                'Join code' => $class->join_code,
                'Folders' => $class->folders_count,
                'Announcements' => $class->announcements_count,
                'Created' => $class->created_at->format('M j, Y'),
            ]" />
        </x-card>

        <x-card title="Roster" :subtitle="$profiles->count().' interns'" class="lg:col-span-2" :padding="false">
            <x-table>
                <x-slot:head><th>Intern</th><th>Student no.</th><th>Hours</th><th>Status</th></x-slot:head>
                @forelse ($profiles as $profile)
                    <tr>
                        <td><a href="{{ route('admin.interns.show', $profile->user) }}" class="font-medium hover:underline">{{ $profile->user->name }}</a></td>
                        <td class="font-mono text-xs">{{ $profile->student_number }}</td>
                        <td class="tabular-nums">{{ $profile->total_hours }} h</td>
                        <td><x-badge :status="$profile->user->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state title="No interns enrolled" class="py-8" /></td></tr>
                @endforelse
            </x-table>
        </x-card>
    </div>
</x-layouts.app>
