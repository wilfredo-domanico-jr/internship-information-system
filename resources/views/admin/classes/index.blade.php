<x-layouts.app title="Classes">
    <x-page-header title="Classes" subtitle="Practicum class sections. Advisers claim a class with its join code; interns join with the same code." />

    <x-card :padding="false">
        <div class="border-b border-stone-200/80 p-4 dark:border-stone-800">
            <x-search-form :action="route('admin.classes.index')" placeholder="Course code, subject, section or join code">
                <select name="year" class="input sm:w-40" aria-label="School year">
                    <option value="">All years</option>
                    @foreach ($years as $year)<option value="{{ $year }}" @selected(request('year') === $year)>{{ $year }}</option>@endforeach
                </select>
                <select name="status" class="input sm:w-36" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach (\App\Enums\ClassStatus::options() as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach
                </select>
            </x-search-form>
        </div>
        <x-table>
            <x-slot:head><th>Class</th><th>Adviser</th><th>Schedule</th><th>School year</th><th>Interns</th><th>Join code</th><th>Status</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($classes as $class)
                <tr>
                    <td class="font-medium">{{ $class->display_name }}<p class="text-xs font-normal text-stone-500">{{ $class->subject }}</p></td>
                    <td>{{ $class->adviser?->name ?? 'No adviser' }}</td>
                    <td>{{ $class->schedule_label }}</td>
                    <td>{{ $class->school_year }}</td>
                    <td class="tabular-nums">{{ $class->intern_profiles_count }}</td>
                    <td><code class="rounded bg-stone-100 px-1.5 py-0.5 font-mono text-xs dark:bg-stone-800">{{ $class->join_code }}</code></td>
                    <td><x-badge :status="$class->status" /></td>
                    <td class="text-right"><a href="{{ route('admin.classes.show', $class) }}" class="btn-ghost px-3 py-1.5">View</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state title="No classes yet" description="Import classes from Excel or let advisers create them." icon="heroicon-o-rectangle-group" /></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$classes" /></div>
    </x-card>
</x-layouts.app>
