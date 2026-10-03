<x-layouts.app title="Add partner company">
    <x-page-header title="Add partner company" subtitle="Partner (COS) companies host interns without a portal login; the class adviser reviews their DTRs." :breadcrumbs="['Partner companies' => route('admin.partners.index'), 'Add' => null]" />
    <form method="POST" action="{{ route('admin.partners.store') }}" enctype="multipart/form-data" class="max-w-3xl">
        @csrf
        <x-card>
            @include('admin.partners._form')
            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="secondary" :href="route('admin.partners.index')">Cancel</x-button>
                <x-button>Add partner</x-button>
            </div>
        </x-card>
    </form>
</x-layouts.app>
