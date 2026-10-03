<x-layouts.app :title="$adviser->name">
    <x-page-header :title="$adviser->full_name" :subtitle="$adviser->member_no.' · '.$adviser->email" :breadcrumbs="['Advisers' => route('admin.advisers.index'), $adviser->name => null]">
        <x-slot:actions><x-badge :status="$adviser->status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Details">
            <x-detail-list class="sm:grid-cols-1" :items="['Email' => $adviser->email, 'Phone' => $adviser->phone, 'Joined' => $adviser->created_at->format('M j, Y'), 'Last sign-in' => $adviser->last_login_at?->diffForHumans()]" />
        </x-card>

        <x-card title="Classes" class="lg:col-span-2" :padding="false">
            <x-table>
                <x-slot:head><th>Class</th><th>Schedule</th><th>School year</th><th>Interns</th><th>Status</th></x-slot:head>
                @forelse ($classes as $class)
                    <tr>
                        <td class="font-medium">{{ $class->display_name }}<p class="text-xs font-normal text-stone-500">{{ $class->subject }}</p></td>
                        <td>{{ $class->schedule_label }}</td>
                        <td>{{ $class->school_year }}</td>
                        <td class="tabular-nums">{{ $class->intern_profiles_count }}</td>
                        <td><x-badge :status="$class->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state title="No classes yet" class="py-8" /></td></tr>
                @endforelse
            </x-table>
        </x-card>

        <x-card title="Interns across these classes" class="lg:col-span-3" :padding="false">
            <x-table>
                <x-slot:head><th>Intern</th><th>Student no.</th><th>Class</th><th>Hours</th></x-slot:head>
                @forelse ($interns as $profile)
                    <tr>
                        <td><a href="{{ route('admin.interns.show', $profile->user) }}" class="font-medium hover:underline">{{ $profile->user->name }}</a></td>
                        <td class="font-mono text-xs">{{ $profile->student_number }}</td>
                        <td>{{ $profile->classSection?->display_name }}</td>
                        <td class="tabular-nums">{{ $profile->total_hours }} h</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state title="No interns enrolled" class="py-8" /></td></tr>
                @endforelse
            </x-table>
        </x-card>

        {{-- Task 7 adds the account-status "Danger zone" card here --}}
    </div>
</x-layouts.app>
