<x-layouts.app title="New posting">
    <x-page-header title="New internship posting" subtitle="Interns see open postings and apply with their resume and endorsement letter." :breadcrumbs="['Postings' => route('company.postings.index'), 'New posting' => null]" />

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('company.postings.store') }}" class="space-y-6">
            @csrf
            @include('company.postings._form', ['posting' => null])
            <div class="flex items-center justify-end gap-3">
                <x-button variant="secondary" :href="route('company.postings.index')">Cancel</x-button>
                <x-button icon="heroicon-o-megaphone">Publish posting</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
