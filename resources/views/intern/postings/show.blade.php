<x-layouts.app :title="$posting->title">
    <x-page-header :title="$posting->title" :subtitle="$posting->company->name.' · '.$posting->city" :breadcrumbs="['Internships' => route('intern.postings.index'), $posting->title => null]">
        <x-slot:actions>
            <x-badge color="teal">{{ $posting->vacancies }} {{ Str::plural('slot', $posting->vacancies) }}</x-badge>
            @if ($posting->closing_date)<x-badge color="gray">Closes {{ $posting->closing_date->format('M j, Y') }}</x-badge>@endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card title="About the role">
                <p class="whitespace-pre-line text-sm leading-6 text-stone-700 dark:text-stone-200">{{ $posting->description }}</p>
            </x-card>
            @if ($posting->responsibilities)
                <x-card title="Responsibilities">
                    <p class="whitespace-pre-line text-sm leading-6 text-stone-700 dark:text-stone-200">{{ $posting->responsibilities }}</p>
                </x-card>
            @endif
            <x-card title="About the company">
                <p class="text-sm leading-6 text-stone-700 dark:text-stone-200">{{ $posting->company->about ?? 'No description provided.' }}</p>
                <x-detail-list class="mt-4" :items="[
                    'Type' => $posting->company->type,
                    'Address' => $posting->company->address,
                    'Website' => $posting->company->website,
                    'Required hours' => $posting->required_hours ? $posting->required_hours.' h' : 'School requirement',
                ]" />
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Apply">
                @if ($blocker)
                    <p class="text-sm text-stone-600 dark:text-stone-300">{{ $blocker }}</p>
                    @if (Route::has('intern.applications.index'))<x-button variant="secondary" :href="route('intern.applications.index')" class="mt-4 w-full">My applications</x-button>@endif
                @else
                    <form method="POST" action="{{ route('intern.applications.store', $posting) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <x-form.file name="resume" label="Resume (PDF)" accept="application/pdf" required />
                        <x-form.file name="endorsement" label="Endorsement letter (PDF)" accept="application/pdf" required :hint="'PDF only, up to '.(int) (config('wiis.uploads.max_pdf_kb') / 1024).' MB each.'" />
                        <x-button class="w-full" icon="heroicon-o-paper-airplane">Send application</x-button>
                    </form>
                @endif
            </x-card>
            <x-card title="Contact">
                <x-detail-list class="sm:grid-cols-1" :items="['Name' => $posting->contact_name, 'Position' => $posting->contact_position, 'Phone' => $posting->contact_phone]" />
            </x-card>
        </div>
    </div>
</x-layouts.app>
