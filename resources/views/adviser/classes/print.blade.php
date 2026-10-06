<x-layouts.print :title="'Interns · '.$class->display_name" :back="route('adviser.classes.people', $class)">
    <header class="border-b border-stone-300 pb-4">
        <p class="text-sm uppercase tracking-wide text-stone-500">{{ config('wiis.institution.name') }} · {{ config('wiis.institution.office') }}</p>
        <h1 class="mt-1 font-display text-2xl font-semibold">{{ $class->display_name }} — {{ $class->subject }}</h1>
        <p class="mt-1 text-sm text-stone-600">{{ $class->schedule_label }} · SY {{ $class->school_year }} · Adviser: {{ $class->adviser?->name ?? 'None' }}</p>
    </header>

    <table class="mt-6 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-stone-300 text-left text-xs uppercase tracking-wide text-stone-500">
                <th class="py-2 pr-3">#</th><th class="py-2 pr-3">Intern</th><th class="py-2 pr-3">Student no.</th><th class="py-2 pr-3">Company</th><th class="py-2 pr-3 text-right">Hours</th><th class="py-2">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($profiles as $profile)
                <tr class="border-b border-stone-200">
                    <td class="py-2 pr-3 tabular-nums">{{ $loop->iteration }}</td>
                    <td class="py-2 pr-3 font-medium">{{ $profile->user->last_name }}, {{ $profile->user->first_name }}</td>
                    <td class="py-2 pr-3 font-mono text-xs">{{ $profile->student_number }}</td>
                    <td class="py-2 pr-3">{{ $profile->user->activePlacement?->company?->name ?? '—' }}</td>
                    <td class="py-2 pr-3 text-right tabular-nums">{{ $profile->total_hours }} / {{ $hours->required() }}</td>
                    <td class="py-2">{{ $hours->tier($profile->total_hours)->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="mt-6 text-xs text-stone-500">{{ $profiles->count() }} interns · Printed {{ now()->format('M j, Y g:i A') }}</p>
</x-layouts.print>
