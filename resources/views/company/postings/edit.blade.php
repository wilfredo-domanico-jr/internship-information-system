<x-layouts.app :title="'Edit · '.$posting->title">
    <x-page-header :title="'Edit '.$posting->title" :breadcrumbs="['Postings' => route('company.postings.index'), $posting->title => null]">
        <x-slot:actions><x-badge :status="$posting->status" /></x-slot:actions>
    </x-page-header>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('company.postings.update', $posting) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('company.postings._form', ['posting' => $posting])
            <div class="flex items-center justify-end gap-3">
                <x-button variant="secondary" :href="route('company.postings.index')">Cancel</x-button>
                <x-button icon="heroicon-o-check">Save changes</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
