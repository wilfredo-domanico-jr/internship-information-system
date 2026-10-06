<x-page-header :title="$class->display_name" :subtitle="$class->subject.' · '.$class->schedule_label.' · '.$class->school_year" :breadcrumbs="['My classes' => route('adviser.classes.index'), $class->display_name => null]">
    <x-slot:actions>
        <span class="inline-flex items-center gap-2 rounded-xl border border-dashed border-stone-300 px-3 py-1.5 text-sm dark:border-stone-700">Join code <span class="font-mono font-semibold">{{ $class->join_code }}</span></span>
        @can('manage', $class)
            <x-button variant="secondary" :href="route('adviser.classes.edit', $class)" icon="heroicon-o-pencil-square">Edit</x-button>
        @endcan
    </x-slot:actions>
</x-page-header>

@php
    $tabs = [['label' => 'Stream', 'route' => 'adviser.classes.show', 'params' => $class, 'active' => 'adviser.classes.show']];
    if (Route::has('adviser.classes.documents')) {
        $tabs[] = ['label' => 'Documents', 'route' => 'adviser.classes.documents', 'params' => $class, 'active' => 'adviser.classes.documents'];
    }
    $tabs[] = ['label' => 'People', 'route' => 'adviser.classes.people', 'params' => $class, 'active' => 'adviser.classes.people', 'count' => $class->intern_profiles_count ?? null];
@endphp
<x-tabs :tabs="$tabs" />
