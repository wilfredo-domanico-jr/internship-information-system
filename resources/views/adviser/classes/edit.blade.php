<x-layouts.app :title="'Edit · '.$class->display_name">
    <x-page-header :title="'Edit '.$class->display_name" :subtitle="'Join code '.$class->join_code" :breadcrumbs="['My classes' => route('adviser.classes.index'), $class->display_name => null]" />

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('adviser.classes.update', $class) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('adviser.classes._form', ['class' => $class, 'days' => $days])
            <div class="flex items-center justify-end gap-3">
                <x-button variant="secondary" :href="route('adviser.classes.index')">Cancel</x-button>
                <x-button icon="heroicon-o-check">Save changes</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
