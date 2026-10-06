<x-layouts.app title="New class">
    <x-page-header title="New class" subtitle="Create a class and share its join code with your interns." :breadcrumbs="['My classes' => route('adviser.classes.index'), 'New class' => null]" />

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('adviser.classes.store') }}" class="space-y-6">
            @csrf
            @include('adviser.classes._form', ['class' => null, 'days' => $days])
            <div class="flex items-center justify-end gap-3">
                <x-button variant="secondary" :href="route('adviser.classes.index')">Cancel</x-button>
                <x-button icon="heroicon-o-plus">Create class</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
