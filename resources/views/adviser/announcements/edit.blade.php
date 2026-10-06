<x-layouts.app title="Edit announcement">
    <x-page-header title="Edit announcement" :breadcrumbs="['My classes' => route('adviser.classes.index'), $class->display_name => route('adviser.classes.show', $class), 'Edit announcement' => null]" />

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('adviser.announcements.update', $announcement) }}" class="space-y-6">
            @csrf
            @method('PUT')
            <x-form.editor name="body" label="Announcement" :value="$announcement->body" required />
            <div class="flex items-center justify-end gap-3">
                <x-button variant="secondary" :href="route('adviser.classes.show', $class)">Cancel</x-button>
                <x-button icon="heroicon-o-check">Save changes</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
