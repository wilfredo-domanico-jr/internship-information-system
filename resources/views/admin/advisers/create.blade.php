<x-layouts.app title="Add adviser">
    <x-page-header title="Add adviser" subtitle="A temporary password is emailed to the adviser." :breadcrumbs="['Advisers' => route('admin.advisers.index'), 'Add' => null]" />

    <form method="POST" action="{{ route('admin.advisers.store') }}" class="max-w-2xl">
        @csrf
        <x-card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="first_name" label="First name" required autofocus />
                <x-form.input name="last_name" label="Last name" required />
                <x-form.input name="middle_name" label="Middle name" />
                <x-form.input name="phone" label="Phone" type="tel" />
            </div>
            <x-form.input name="email" label="Work email" type="email" required class="mt-5" />
            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="secondary" :href="route('admin.advisers.index')">Cancel</x-button>
                <x-button>Create and email credentials</x-button>
            </div>
        </x-card>
    </form>
</x-layouts.app>
