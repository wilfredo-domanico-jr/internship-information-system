<x-layouts.app title="Partner companies">
    <x-page-header title="Partner companies" subtitle="Contract-of-service hosts without a portal login. Interns apply with an acceptance letter; advisers review their DTRs.">
        <x-slot:actions><x-button :href="route('admin.partners.create')" icon="heroicon-o-plus">Add partner</x-button></x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="border-b border-stone-200/80 p-4 dark:border-stone-800">
            <x-search-form :action="route('admin.partners.index')" placeholder="Name or code" />
        </div>
        <x-table>
            <x-slot:head><th>Company</th><th>Code</th><th>Active interns</th><th>Applications</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($companies as $company)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            @if ($company->logo_path)
                                <img src="{{ Storage::disk('public')->url($company->logo_path) }}" alt="" class="size-9 rounded-lg object-cover ring-1 ring-stone-200 dark:ring-stone-700">
                            @else
                                <span class="grid size-9 place-items-center rounded-lg bg-stone-100 text-stone-500 dark:bg-stone-800"><x-heroicon-o-building-storefront class="size-5" /></span>
                            @endif
                            <div><p class="font-medium">{{ $company->name }}</p><p class="text-xs text-stone-500">{{ $company->type }}</p></div>
                        </div>
                    </td>
                    <td class="font-mono text-xs">{{ $company->company_code }}</td>
                    <td class="tabular-nums">{{ $company->active_placements_count }}</td>
                    <td class="tabular-nums">{{ $company->cos_applications_count }}</td>
                    <td>
                        <div class="flex justify-end gap-1">
                            <a href="{{ route('admin.partners.edit', $company) }}" class="btn-ghost px-3 py-1.5">Edit</a>
                            <x-confirm-form :action="route('admin.partners.destroy', $company)" method="DELETE" :confirm="'Remove '.$company->name.'? This cannot be undone.'">
                                <button class="btn-ghost px-3 py-1.5 text-rose-600">Remove</button>
                            </x-confirm-form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state title="No partner companies yet" description="Add the government offices and partners that host interns under contract of service." icon="heroicon-o-building-storefront"><x-slot:action><x-button :href="route('admin.partners.create')" icon="heroicon-o-plus">Add partner</x-button></x-slot:action></x-empty-state></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$companies" /></div>
    </x-card>
</x-layouts.app>
