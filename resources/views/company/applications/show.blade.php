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

            @can('decide', $application)
                @if ($application->status->isOpen())
                    <x-card title="Decision">
                        <div class="flex flex-col gap-2">
                            <x-button type="button" variant="secondary" icon="heroicon-o-calendar-days" @click="$dispatch('open-modal', 'schedule-interview')">{{ $application->interview ? 'Reschedule interview' : 'Schedule interview' }}</x-button>
                            @if (Route::has('company.applications.accept'))
                                <x-confirm-form :action="route('company.applications.accept', $application)" :confirm="'Accept '.$intern->name.'? They will be told to join with your company code.'">
                                    <x-button class="w-full" icon="heroicon-o-check">Accept applicant</x-button>
                                </x-confirm-form>
                            @endif
                            <x-button type="button" variant="danger" icon="heroicon-o-x-mark" @click="$dispatch('open-modal', 'decline-application')">Decline</x-button>
                        </div>
                    </x-card>

                    <x-modal name="schedule-interview" :title="$application->interview ? 'Reschedule interview' : 'Schedule interview'">
                        <form method="POST" action="{{ route('company.applications.interview', $application) }}" class="space-y-4"
                              x-init="@if ($errors->hasAny(['title', 'venue', 'link', 'scheduled_on', 'starts_at', 'ends_at', 'notes'])) $nextTick(() => $dispatch('open-modal', 'schedule-interview')) @endif">
                            @csrf
                            <x-form.input name="title" label="Title" :value="$application->interview?->title ?? 'Initial interview'" required maxlength="100" />
                            <x-form.input name="venue" label="Venue or platform" :value="$application->interview?->venue" required placeholder="Google Meet, Zoom, or your office address" />
                            <x-form.input name="link" label="Meeting link" type="url" :value="$application->interview?->link" placeholder="https://" />
                            <div class="grid gap-4 sm:grid-cols-3">
                                <x-form.input name="scheduled_on" label="Date" type="date" :value="$application->interview?->scheduled_on?->toDateString()" required />
                                <x-form.input name="starts_at" label="From" type="time" :value="$application->interview ? substr($application->interview->starts_at, 0, 5) : null" required />
                                <x-form.input name="ends_at" label="To" type="time" :value="$application->interview ? substr($application->interview->ends_at, 0, 5) : null" required />
                            </div>
                            <x-form.textarea name="notes" label="Notes for the applicant" :value="$application->interview?->notes" rows="3" />
                            <div class="flex justify-end gap-2">
                                <x-button type="button" variant="secondary" @click="$dispatch('close-modal', 'schedule-interview')">Cancel</x-button>
                                <x-button icon="heroicon-o-calendar-days">Save interview</x-button>
                            </div>
                        </form>
                    </x-modal>

                    <x-modal name="decline-application" title="Decline application">
                        <form method="POST" action="{{ route('company.applications.decline', $application) }}" class="space-y-4"
                              x-init="@if ($errors->has('reason')) $nextTick(() => $dispatch('open-modal', 'decline-application')) @endif">
                            @csrf
                            <p class="text-sm text-stone-600 dark:text-stone-300">The reason is sent to {{ $intern->name }}.</p>
                            <x-form.textarea name="reason" label="Reason" rows="3" required maxlength="500" />
                            <div class="flex justify-end gap-2">
                                <x-button type="button" variant="secondary" @click="$dispatch('close-modal', 'decline-application')">Cancel</x-button>
                                <x-button variant="danger" icon="heroicon-o-x-mark">Decline</x-button>
                            </div>
                        </form>
                    </x-modal>
                @endif
            @endcan
        </div>
    </div>
</x-layouts.app>
