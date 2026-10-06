<x-layouts.app :title="$intern->name">
    <x-page-header :title="$intern->full_name" :subtitle="'Applied for '.$application->posting->title.' · '.$application->created_at->format('M j, Y')" :breadcrumbs="['Postings' => route('company.postings.index'), $application->posting->title => route('company.postings.applicants', $application->posting), $intern->name => null]">
        <x-slot:actions><x-badge :status="$application->status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card title="Applicant">
                <div class="flex items-start gap-4">
                    <x-avatar :user="$intern" size="lg" />
                    <div class="min-w-0 flex-1">
                        <x-detail-list :items="[
                            'Email' => $intern->email,
                            'Phone' => $intern->phone,
                            'Student no.' => $profile?->student_number,
                            'Class' => $profile?->classSection?->display_name,
                            'Adviser' => $profile?->classSection?->adviser?->name,
                            'School year' => $profile?->school_year,
                            'OJT hours so far' => ($profile?->total_hours ?? 0).' / '.$hours->required().' h',
                            'Address' => $profile?->present_address,
                        ]" />
                        @if ($profile?->about)<p class="mt-4 text-sm leading-6 text-stone-700 dark:text-stone-200">{{ $profile->about }}</p>@endif
                    </div>
                </div>
            </x-card>

            <x-card title="Documents">
                <div class="flex flex-wrap gap-2">
                    <x-button variant="secondary" :href="route('files.show', ['application-resume', $application])" target="_blank" icon="heroicon-o-document-text">Resume</x-button>
                    <x-button variant="secondary" :href="route('files.show', ['application-endorsement', $application])" target="_blank" icon="heroicon-o-document-text">Endorsement letter</x-button>
                </div>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Interview">
                @if ($application->interview)
                    <x-detail-list class="sm:grid-cols-1" :items="[
                        'Title' => $application->interview->title,
                        'When' => $application->interview->scheduled_on->format('M j, Y').' · '.\Illuminate\Support\Carbon::parse($application->interview->starts_at)->format('g:i A').' – '.\Illuminate\Support\Carbon::parse($application->interview->ends_at)->format('g:i A'),
                        'Venue' => $application->interview->venue,
                        'Link' => $application->interview->link,
                        'Notes' => $application->interview->notes,
                    ]" />
                @else
                    <p class="text-sm text-stone-500">No interview scheduled yet.</p>
                @endif
            </x-card>

            @if ($application->decline_reason)
                <x-card title="Decline reason"><p class="text-sm text-stone-700 dark:text-stone-200">{{ $application->decline_reason }}</p></x-card>
            @endif

            {{-- Task 6 adds the interview and decline controls here --}}
        </div>
    </div>
</x-layouts.app>
