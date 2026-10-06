<x-layouts.app title="Interns">
    <x-page-header title="Interns" subtitle="Everyone currently placed with you. Hours come from approved DTRs." />

    <x-search-form :action="route('company.interns.index')" placeholder="Search by name or email…" />

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Intern</th><th>Class</th><th>Hours here</th><th>OJT total</th><th>Department</th><th class="text-right"></th></x-slot:head>
            @forelse ($placements as $placement)
                @php $total = $placement->intern->internProfile?->total_hours ?? 0; @endphp
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$placement->intern" size="sm" />
                            <div class="min-w-0"><p class="font-medium">{{ $placement->intern->name }}</p><p class="font-mono text-xs text-stone-500">{{ $placement->intern->internProfile?->student_number }} · since {{ $placement->started_at->format('M j') }}</p></div>
                        </div>
                    </td>
                    <td>{{ $placement->intern->internProfile?->classSection?->display_name ?? '—' }}</td>
                    <td class="tabular-nums">{{ $placement->hours_rendered }} h<p class="text-xs text-stone-500">{{ $placement->absences }} absences</p></td>
                    <td><p class="tabular-nums">{{ $total }} / {{ $hours->required() }} h</p><x-badge :status="$hours->tier($total)" class="mt-1" /></td>
                    <td>
                        <form method="POST" action="{{ route('company.placements.department', $placement) }}" class="flex items-center gap-1">
                            @csrf
                            @method('PUT')
                            <select name="department_id" class="input py-1.5 text-sm" aria-label="Department" onchange="this.form.submit()">
                                <option value="">No department</option>
                                @foreach ($departments as $id => $name)<option value="{{ $id }}" @selected($placement->department_id === $id)>{{ $name }}</option>@endforeach
                            </select>
                            <noscript><x-button variant="ghost">Save</x-button></noscript>
                        </form>
                    </td>
                    <td class="text-right">
                        <x-confirm-form :action="route('company.placements.remove', $placement)" :confirm="'Remove '.$placement->intern->name.' from your company? Their approved hours are kept.'">
                            <x-button variant="ghost" icon="heroicon-o-user-minus" class="text-rose-600">Remove</x-button>
                        </x-confirm-form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state title="No active interns" description="Accepted applicants appear here once they join with your company code." icon="heroicon-o-users" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$placements" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
