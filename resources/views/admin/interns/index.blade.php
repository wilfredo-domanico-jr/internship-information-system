<x-layouts.app title="Interns">
    <x-page-header title="Interns" subtitle="Every student account, with class, placement and rendered hours." />

    <x-card :padding="false">
        <div class="border-b border-stone-200/80 p-4 dark:border-stone-800">
            <x-search-form :action="route('admin.interns.index')" placeholder="Name, email, student or member no.">
                <select name="class" class="input sm:w-56" aria-label="Class">
                    <option value="">All classes</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(request('class') == $class->id)>{{ $class->course_code }} · {{ $class->section }}</option>
                    @endforeach
                </select>
                <select name="placement" class="input sm:w-40" aria-label="Placement">
                    <option value="">Any placement</option>
                    <option value="placed" @selected(request('placement') === 'placed')>Placed</option>
                    <option value="unplaced" @selected(request('placement') === 'unplaced')>Unplaced</option>
                </select>
                <select name="status" class="input sm:w-36" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach (\App\Enums\AccountStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-search-form>
        </div>
        <x-table>
            <x-slot:head><th>Intern</th><th>Class</th><th>Placement</th><th>Hours</th><th>Status</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($interns as $intern)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$intern" size="sm" />
                            <div>
                                <p class="font-medium">{{ $intern->name }}</p>
                                <p class="text-xs text-stone-500">{{ $intern->internProfile?->student_number }} · {{ $intern->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td>{{ $intern->internProfile?->classSection?->display_name ?? '—' }}</td>
                    <td>{{ $intern->activePlacement?->company?->name ?? 'Unplaced' }}</td>
                    <td class="tabular-nums">{{ $intern->internProfile?->total_hours ?? 0 }} h</td>
                    <td><x-badge :status="$intern->status" /></td>
                    <td class="text-right"><a href="{{ route('admin.interns.show', $intern) }}" class="btn-ghost px-3 py-1.5">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state title="No interns match" description="Try a different search or clear the filters." /></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$interns" /></div>
    </x-card>
</x-layouts.app>
