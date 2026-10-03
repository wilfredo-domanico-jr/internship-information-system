<x-layouts.app title="Departments">
    <x-page-header title="Departments" subtitle="The units a company can assign an intern to. Shared across all companies." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Add department">
            <form method="POST" action="{{ route('admin.departments.store') }}" class="space-y-4">
                @csrf
                <x-form.input name="name" label="Name" placeholder="Information Technology" required />
                <x-button class="w-full" icon="heroicon-o-plus">Add</x-button>
            </form>
        </x-card>

        <x-card title="All departments" class="lg:col-span-2" :padding="false">
            <x-table>
                <x-slot:head><th>Name</th><th>Placements</th><th class="sr-only">Actions</th></x-slot:head>
                @forelse ($departments as $department)
                    <tr x-data="{ editing: false }">
                        <td>
                            <span x-show="!editing" class="font-medium">{{ $department->name }}</span>
                            <form x-cloak x-show="editing" method="POST" action="{{ route('admin.departments.update', $department) }}" class="flex items-center gap-2">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $department->name }}" class="input py-1.5" required aria-label="Department name">
                                <x-button class="py-1.5">Save</x-button>
                                <x-button type="button" variant="ghost" class="py-1.5" @click="editing = false">Cancel</x-button>
                            </form>
                        </td>
                        <td class="tabular-nums">{{ $department->placements_count }}</td>
                        <td>
                            <div class="flex justify-end gap-1" x-show="!editing">
                                <button type="button" class="btn-ghost px-3 py-1.5" @click="editing = true">Rename</button>
                                <x-confirm-form :action="route('admin.departments.destroy', $department)" method="DELETE" :confirm="'Remove '.$department->name.'? Interns assigned to it will become unassigned.'">
                                    <button class="btn-ghost px-3 py-1.5 text-rose-600">Remove</button>
                                </x-confirm-form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3"><x-empty-state title="No departments yet" /></td></tr>
                @endforelse
            </x-table>
        </x-card>
    </div>
</x-layouts.app>
