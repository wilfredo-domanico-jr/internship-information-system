<x-layouts.app title="Companies">
    <x-page-header title="Companies" subtitle="Registered host companies with portal accounts." />

    @php
        $tabs = [['label' => 'All', 'route' => 'admin.companies.index', 'active' => 'admin.companies.index']];
        if (Route::has('admin.companies.pending')) {
            $tabs[] = ['label' => 'Pending approval', 'route' => 'admin.companies.pending', 'active' => 'admin.companies.pending', 'count' => $pendingCount];
        }
    @endphp
    <x-tabs :tabs="$tabs" />

    <x-card :padding="false">
        <div class="border-b border-stone-200/80 p-4 dark:border-stone-800">
            <x-search-form :action="route('admin.companies.index')" placeholder="Company, code, contact or email">
                <select name="approval" class="input sm:w-40" aria-label="Approval">
                    <option value="">Any approval</option>
                    @foreach (\App\Enums\ApprovalStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('approval') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-search-form>
        </div>
        <x-table>
            <x-slot:head><th>Company</th><th>Contact</th><th>Code</th><th>Active interns</th><th>Approval</th><th>Account</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($companies as $company)
                <tr>
                    <td class="font-medium">{{ $company->name }}<p class="text-xs font-normal text-stone-500">{{ $company->type }}</p></td>
                    <td>{{ $company->user?->name }}<p class="text-xs text-stone-500">{{ $company->user?->email }}</p></td>
                    <td class="font-mono text-xs">{{ $company->company_code }}</td>
                    <td class="tabular-nums">{{ $company->active_placements_count }}</td>
                    <td><x-badge :status="$company->approval_status" /></td>
                    <td>@if ($company->user)<x-badge :status="$company->user->status" />@endif</td>
                    <td class="text-right"><a href="{{ route('admin.companies.show', $company) }}" class="btn-ghost px-3 py-1.5">View</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty-state title="No companies match" /></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$companies" /></div>
    </x-card>
</x-layouts.app>
