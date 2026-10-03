<x-layouts.app title="Advisers">
    <x-page-header title="Advisers" subtitle="Practicum advisers and the classes they handle.">
        <x-slot:actions><x-button :href="route('admin.advisers.create')" icon="heroicon-o-plus">Add adviser</x-button></x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="border-b border-stone-200/80 p-4 dark:border-stone-800">
            <x-search-form :action="route('admin.advisers.index')" placeholder="Name, email or member no.">
                <select name="status" class="input sm:w-36" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach (\App\Enums\AccountStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-search-form>
        </div>
        <x-table>
            <x-slot:head><th>Adviser</th><th>Member no.</th><th>Active classes</th><th>Status</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($advisers as $adviser)
                <tr>
                    <td><div class="flex items-center gap-3"><x-avatar :user="$adviser" size="sm" /><div><p class="font-medium">{{ $adviser->name }}</p><p class="text-xs text-stone-500">{{ $adviser->email }}</p></div></div></td>
                    <td class="font-mono text-xs">{{ $adviser->member_no }}</td>
                    <td class="tabular-nums">{{ $adviser->advised_classes_count }}</td>
                    <td><x-badge :status="$adviser->status" /></td>
                    <td class="text-right"><a href="{{ route('admin.advisers.show', $adviser) }}" class="btn-ghost px-3 py-1.5">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state title="No advisers match" /></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$advisers" /></div>
    </x-card>
</x-layouts.app>
