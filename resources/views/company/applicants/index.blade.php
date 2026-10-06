<x-layouts.app :title="'Applicants · '.$posting->title">
    <x-page-header :title="$posting->title" :subtitle="'Applicants · '.$posting->city" :breadcrumbs="['Postings' => route('company.postings.index'), $posting->title => null]">
        <x-slot:actions><x-badge :status="$posting->status" /><x-button variant="secondary" :href="route('company.postings.edit', $posting)" icon="heroicon-o-pencil-square">Edit posting</x-button></x-slot:actions>
    </x-page-header>

    <nav class="flex flex-wrap gap-2" aria-label="Applicant groups">
        @foreach (\App\Enums\ApplicationStatus::cases() as $status)
            @continue(! in_array($status->value, \App\Http\Controllers\Company\ApplicantController::GROUPS, true))
            <a href="{{ route('company.postings.applicants', [$posting, 'status' => $status->value]) }}"
               @class(['inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                       'bg-brand-600 text-white ring-brand-600' => $group === $status->value,
                       'bg-white text-stone-600 ring-stone-300 hover:bg-stone-50 dark:bg-stone-900 dark:text-stone-300 dark:ring-stone-700' => $group !== $status->value])
               @if ($group === $status->value) aria-current="page" @endif>
                {{ $status->label() }} <span class="rounded-full bg-black/10 px-1.5 text-xs tabular-nums dark:bg-white/10">{{ $counts[$status->value] }}</span>
            </a>
        @endforeach
    </nav>

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Applicant</th><th>Class</th><th>Applied</th><th>Documents</th><th>Status</th><th class="text-right"></th></x-slot:head>
            @forelse ($applications as $application)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$application->intern" size="sm" />
                            <div class="min-w-0"><p class="font-medium">{{ $application->intern->name }}</p><p class="font-mono text-xs text-stone-500">{{ $application->intern->internProfile?->student_number }}</p></div>
                        </div>
                    </td>
                    <td>{{ $application->intern->internProfile?->classSection?->display_name ?? '—' }}</td>
                    <td class="text-stone-500">{{ $application->created_at->format('M j, Y') }}</td>
                    <td class="space-x-2 text-sm">
                        <a href="{{ route('files.show', ['application-resume', $application]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Resume</a>
                        <a href="{{ route('files.show', ['application-endorsement', $application]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Endorsement</a>
                    </td>
                    <td>
                        <x-badge :status="$application->status" />
                        @if ($application->interview)<p class="mt-1 text-xs text-stone-500">{{ $application->interview->scheduled_on->format('M j') }} · {{ $application->interview->venue }}</p>@endif
                    </td>
                    <td class="text-right"><x-button variant="secondary" :href="route('company.applications.show', $application)">View applicant</x-button></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state :title="'No '.str_replace('_', ' ', $group).' applicants'" description="Only interns who are not yet placed appear here." icon="heroicon-o-user-group" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$applications" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
