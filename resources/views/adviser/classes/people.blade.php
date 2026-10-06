<x-layouts.app :title="'People · '.$class->display_name">
    @include('adviser.classes.partials.header')

    <x-card title="Interns" :subtitle="$profiles->count().' enrolled'" :padding="false">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('adviser.classes.print', $class)" icon="heroicon-o-printer" target="_blank">Print list</x-button>
        </x-slot:actions>
        <x-table>
            <x-slot:head><th>Intern</th><th>Student no.</th><th>Company</th><th>Hours</th><th>Progress</th><th>Account</th></x-slot:head>
            @forelse ($profiles as $profile)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$profile->user" size="sm" />
                            <div class="min-w-0"><p class="font-medium">{{ $profile->user->name }}</p><p class="truncate text-xs text-stone-500">{{ $profile->user->email }}</p></div>
                        </div>
                    </td>
                    <td class="font-mono text-xs">{{ $profile->student_number }}</td>
                    <td>{{ $profile->user->activePlacement?->company?->name ?? '—' }}</td>
                    <td class="tabular-nums">{{ $profile->total_hours }} / {{ $hours->required() }} h</td>
                    <td><x-badge :status="$hours->tier($profile->total_hours)" /></td>
                    <td><x-badge :status="$profile->user->status" /></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state title="No interns yet" :description="'Share the join code '.$class->join_code.' so interns can enrol.'" class="py-8" /></td></tr>
            @endforelse
        </x-table>
    </x-card>
</x-layouts.app>
