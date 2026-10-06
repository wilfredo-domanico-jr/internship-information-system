<x-page-header :title="$class->display_name" :subtitle="$class->subject.' · '.$class->schedule_label.' · '.$class->school_year">
    <x-slot:actions>
        <span class="text-sm text-stone-500">Adviser: <span class="font-medium text-stone-800 dark:text-stone-100">{{ $class->adviser?->name ?? 'Not assigned yet' }}</span></span>
    </x-slot:actions>
</x-page-header>

@php
    $tabs = [['label' => 'Stream', 'route' => 'intern.class.show', 'active' => 'intern.class.show']];
    if (Route::has('intern.class.documents')) {
        $tabs[] = ['label' => 'Documents', 'route' => 'intern.class.documents', 'active' => ['intern.class.documents', 'intern.folders.*']];
    }
    $tabs[] = ['label' => 'People', 'route' => 'intern.class.people', 'active' => 'intern.class.people', 'count' => $class->intern_profiles_count];
@endphp
<x-tabs :tabs="$tabs" />
