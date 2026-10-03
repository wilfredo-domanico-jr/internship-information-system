<x-layouts.app title="Archive">
    <x-page-header title="Archive" subtitle="Disabled accounts. Reactivate to restore access; nothing here is deleted." />

    <x-card :padding="false">
        <div class="border-b border-stone-200/80 p-4 dark:border-stone-800">
            <x-search-form :action="route('admin.archive.index')" placeholder="Name, email or member no.">
                <select name="role" class="input sm:w-40" aria-label="Role">
                    <option value="">All roles</option>
                    @foreach (\App\Enums\Role::options() as $value => $label)
                        @continue($value === 'admin')
                        <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-search-form>
        </div>
        <x-table>
            <x-slot:head><th>Account</th><th>Role</th><th>Member no.</th><th>Disabled</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($users as $user)
                <tr>
                    <td><div class="flex items-center gap-3"><x-avatar :user="$user" size="sm" /><div><p class="font-medium">{{ $user->name }}@if ($user->company) <span class="text-stone-500">· {{ $user->company->name }}</span>@endif</p><p class="text-xs text-stone-500">{{ $user->email }}</p></div></div></td>
                    <td><x-badge :status="$user->role" /></td>
                    <td class="font-mono text-xs">{{ $user->member_no }}</td>
                    <td class="text-stone-500">{{ $user->updated_at->diffForHumans() }}</td>
                    <td class="text-right">
                        <x-confirm-form :action="route('admin.users.reactivate', $user)" :confirm="'Reactivate '.$user->name.'?'" class="inline">
                            <x-button variant="secondary" icon="heroicon-o-arrow-path">Reactivate</x-button>
                        </x-confirm-form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state title="No disabled accounts" icon="heroicon-o-archive-box" /></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$users" /></div>
    </x-card>
</x-layouts.app>
