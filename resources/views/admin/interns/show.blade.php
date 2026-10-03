<x-layouts.app :title="$intern->name">
    <x-page-header :title="$intern->full_name" :subtitle="$intern->member_no.' · '.($profile?->student_number ?? 'No student number')" :breadcrumbs="['Interns' => route('admin.interns.index'), $intern->name => null]">
        <x-slot:actions><x-badge :status="$intern->status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="OJT progress">
            <div class="flex flex-col items-center gap-3 text-center">
                <x-progress-ring :percent="$progress['percent']" :color="$progress['tier']->badgeColor()" label="of required hours" :size="140" />
                <p class="font-display text-2xl font-semibold tabular-nums">{{ $progress['hours'] }} <span class="text-sm font-normal text-stone-500">/ {{ $progress['required'] }} h</span></p>
                <p class="text-sm text-stone-500">{{ $progress['remaining'] }} hours remaining</p>
                <x-badge :status="$progress['tier']" />
            </div>
        </x-card>

        <x-card title="Details" class="lg:col-span-2">
            <x-detail-list :items="[
                'Email' => $intern->email,
                'Phone' => $intern->phone,
                'Gender' => $profile?->gender,
                'Birthdate' => $profile?->birthdate?->format('M j, Y'),
                'Class' => $profile?->classSection?->display_name,
                'Adviser' => $profile?->classSection?->adviser?->name,
                'School year' => $profile?->school_year,
                'Present address' => $profile?->present_address,
                'Joined' => $intern->created_at->format('M j, Y'),
                'Last sign-in' => $intern->last_login_at?->diffForHumans(),
            ]" />
        </x-card>

        <x-card title="Placements" subtitle="Current and past companies" class="lg:col-span-3" :padding="false">
            <x-table>
                <x-slot:head><th>Company</th><th>From</th><th>To</th><th>Hours</th><th>Status</th></x-slot:head>
                @forelse ($intern->placements as $placement)
                    <tr>
                        <td class="font-medium">{{ $placement->company->name }}</td>
                        <td>{{ $placement->started_at->format('M j, Y') }}</td>
                        <td>{{ $placement->ended_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="tabular-nums">{{ $placement->hours_rendered }} h</td>
                        <td>@if ($placement->isActive())<x-badge color="green">Active</x-badge>@else<x-badge color="gray">Ended</x-badge>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state title="Not placed yet" icon="heroicon-o-briefcase" class="py-8" /></td></tr>
                @endforelse
            </x-table>
        </x-card>

        @include('admin.partials.account-status', ['user' => $intern])
    </div>
</x-layouts.app>
