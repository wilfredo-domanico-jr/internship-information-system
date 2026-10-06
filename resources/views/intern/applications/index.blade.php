<x-layouts.app title="My applications">
    <x-page-header title="My applications" subtitle="Track every application from submission to decision.">
        <x-slot:actions><x-button variant="secondary" :href="route('intern.postings.index')" icon="heroicon-o-magnifying-glass">Browse postings</x-button></x-slot:actions>
    </x-page-header>

    @if ($applications->isEmpty())
        <x-card><x-empty-state title="No applications yet" description="Find an internship posting and apply with your resume and endorsement letter." icon="heroicon-o-paper-airplane"><x-slot:action><x-button :href="route('intern.postings.index')">Browse postings</x-button></x-slot:action></x-empty-state></x-card>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($applications as $application)
                <x-card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-display text-lg font-semibold">{{ $application->posting->title }}</p>
                            <p class="text-sm text-stone-500">{{ $application->posting->company->name }} · {{ $application->posting->city }}</p>
                        </div>
                        <x-badge :status="$application->status" />
                    </div>
                    <x-timeline :steps="$timelines[$application->id]" class="mt-5" />
                    <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-stone-100 pt-4 dark:border-stone-800">
                        <a href="{{ route('files.show', ['application-resume', $application]) }}" target="_blank" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">Resume</a>
                        <span class="text-stone-300">·</span>
                        <a href="{{ route('files.show', ['application-endorsement', $application]) }}" target="_blank" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">Endorsement</a>
                        @can('cancel', $application)
                            <x-confirm-form :action="route('intern.applications.cancel', $application)" confirm="Withdraw this application?" class="ml-auto">
                                <x-button variant="ghost" icon="heroicon-o-x-mark" class="text-rose-600">Cancel application</x-button>
                            </x-confirm-form>
                        @endcan
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
