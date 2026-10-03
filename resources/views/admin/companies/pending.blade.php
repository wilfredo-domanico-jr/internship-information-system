<x-layouts.app title="Pending approvals">
    <x-page-header title="Companies" subtitle="Review the business permit and MOA, then approve or reject." />
    <x-tabs :tabs="[
        ['label' => 'All', 'route' => 'admin.companies.index', 'active' => 'admin.companies.index'],
        ['label' => 'Pending approval', 'route' => 'admin.companies.pending', 'active' => 'admin.companies.pending', 'count' => $pendingCount],
    ]" />

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Company</th><th>Contact</th><th>Documents</th><th>Submitted</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($companies as $company)
                @php $modal = "reject-{$company->id}"; @endphp
                <tr>
                    <td><a href="{{ route('admin.companies.show', $company) }}" class="font-medium hover:underline">{{ $company->name }}</a><p class="text-xs text-stone-500">{{ $company->type }}</p></td>
                    <td>{{ $company->user?->name }}<p class="text-xs text-stone-500">{{ $company->user?->email }}</p></td>
                    <td class="space-x-3 text-sm">
                        <a href="{{ route('files.show', ['company-permit', $company]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Permit</a>
                        <a href="{{ route('files.show', ['company-moa', $company]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">MOA</a>
                    </td>
                    <td class="text-stone-500">{{ $company->created_at->diffForHumans() }}</td>
                    <td>
                        <div class="flex justify-end gap-2">
                            <x-confirm-form :action="route('admin.companies.approve', $company)" :confirm="'Approve '.$company->name.'? The company will be notified.'">
                                <x-button icon="heroicon-o-check">Approve</x-button>
                            </x-confirm-form>
                            <button type="button" class="btn-danger" @click="$dispatch('open-modal', '{{ $modal }}')">Reject</button>
                        </div>
                        <x-modal :name="$modal" :title="'Reject '.$company->name">
                            <form method="POST" action="{{ route('admin.companies.reject', $company) }}" class="space-y-4">
                                @csrf
                                <x-form.textarea name="reason" label="Reason (sent to the company)" rows="3" hint="Optional, up to 500 characters." />
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="btn-secondary" @click="$dispatch('close-modal', '{{ $modal }}')">Cancel</button>
                                    <x-button variant="danger">Reject registration</x-button>
                                </div>
                            </form>
                        </x-modal>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state title="Nothing to review" description="New company registrations will appear here." icon="heroicon-o-check-badge" /></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$companies" /></div>
    </x-card>
</x-layouts.app>
