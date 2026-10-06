<x-layouts.app title="History">
    <x-page-header title="Placement history" subtitle="Interns who completed or left their placement with you." />

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Intern</th><th>Department</th><th>From</th><th>To</th><th>Hours</th><th>Certificates</th></x-slot:head>
            @forelse ($placements as $placement)
                <tr>
                    <td><p class="font-medium">{{ $placement->intern->name }}</p><p class="font-mono text-xs text-stone-500">{{ $placement->intern->internProfile?->student_number }}</p></td>
                    <td>{{ $placement->department?->name ?? '—' }}</td>
                    <td>{{ $placement->started_at->format('M j, Y') }}</td>
                    <td>{{ $placement->ended_at->format('M j, Y') }}</td>
                    <td class="tabular-nums">{{ $placement->hours_rendered }} h<p class="text-xs text-stone-500">{{ $placement->absences }} absences</p></td>
                    <td>{{ $placement->certificates_count }} {{ Str::plural('certificate', $placement->certificates_count) }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state title="No past placements" icon="heroicon-o-archive-box" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$placements" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
