<x-layouts.app :title="'Edit '.$company->name">
    <x-page-header :title="$company->name" :subtitle="'Code '.$company->company_code" :breadcrumbs="['Partner companies' => route('admin.partners.index'), $company->name => null]" />
    <form method="POST" action="{{ route('admin.partners.update', $company) }}" enctype="multipart/form-data" class="max-w-3xl">
        @csrf @method('PUT')
        <x-card>
            @include('admin.partners._form', ['company' => $company])
            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="secondary" :href="route('admin.partners.index')">Cancel</x-button>
                <x-button>Save changes</x-button>
            </div>
        </x-card>
    </form>
</x-layouts.app>
