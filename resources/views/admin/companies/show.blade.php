<x-layouts.app :title="$company->name">
    <x-page-header :title="$company->name" :subtitle="($company->type ? $company->type.' · ' : '').'Code '.$company->company_code" :breadcrumbs="['Companies' => route('admin.companies.index'), $company->name => null]">
        <x-slot:actions>
            <x-badge :status="$company->approval_status" />
            @if (Route::has('admin.companies.approve') && ! $company->isApproved())
                <x-confirm-form :action="route('admin.companies.approve', $company)" :confirm="'Approve '.$company->name.'? The company will be notified.'">
                    <x-button icon="heroicon-o-check">Approve</x-button>
                </x-confirm-form>
            @endif
            @if (Route::has('admin.companies.reject') && $company->approval_status !== \App\Enums\ApprovalStatus::Rejected)
                <button type="button" class="btn-danger" @click="$dispatch('open-modal', 'reject-company')">Reject</button>
                <x-modal name="reject-company" :title="'Reject '.$company->name">
                    <form method="POST" action="{{ route('admin.companies.reject', $company) }}" class="space-y-4">
                        @csrf
                        <x-form.textarea name="reason" label="Reason (sent to the company)" rows="3" hint="Optional, up to 500 characters." />
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn-secondary" @click="$dispatch('close-modal', 'reject-company')">Cancel</button>
                            <x-button variant="danger">Reject registration</x-button>
                        </div>
                    </form>
                </x-modal>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Verification documents">
            <ul class="space-y-2 text-sm">
                <li class="flex items-center justify-between"><span>Business permit</span>
                    @if ($company->permit_path)<a href="{{ route('files.show', ['company-permit', $company]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Open PDF</a>@else<span class="text-stone-500">Missing</span>@endif</li>
                <li class="flex items-center justify-between"><span>Memorandum of Agreement</span>
                    @if ($company->moa_path)<a href="{{ route('files.show', ['company-moa', $company]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Open PDF</a>@else<span class="text-stone-500">Missing</span>@endif</li>
            </ul>
            @if ($company->approved_at)
                <p class="mt-4 text-xs text-stone-500">Reviewed {{ $company->approved_at->format('M j, Y') }}@if ($company->approver) by {{ $company->approver->name }}@endif</p>
            @endif
        </x-card>

        <x-card title="Company and contact" class="lg:col-span-2">
            <x-detail-list :items="[
                'Contact person' => $company->user?->full_name,
                'Email' => $company->user?->email,
                'Phone' => $company->user?->phone,
                'Website' => $company->website,
                'Address' => $company->address,
                'Registered' => $company->created_at->format('M j, Y'),
                'Open postings' => $company->postings_count,
                'Total placements' => $company->placements_count,
            ]" />
            @if ($company->about)<p class="mt-5 text-sm text-stone-600 dark:text-stone-400">{{ $company->about }}</p>@endif
        </x-card>

        <x-card title="Active interns" class="lg:col-span-3" :padding="false">
            <x-table>
                <x-slot:head><th>Intern</th><th>Department</th><th>Since</th><th>Hours here</th></x-slot:head>
                @forelse ($company->activePlacements as $placement)
                    <tr>
                        <td><a href="{{ route('admin.interns.show', $placement->intern) }}" class="font-medium hover:underline">{{ $placement->intern->name }}</a></td>
                        <td>{{ $placement->department?->name ?? '—' }}</td>
                        <td>{{ $placement->started_at->format('M j, Y') }}</td>
                        <td class="tabular-nums">{{ $placement->hours_rendered }} h</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state title="No active interns" class="py-8" /></td></tr>
                @endforelse
            </x-table>
        </x-card>

        @if ($company->user) @include('admin.partials.account-status', ['user' => $company->user]) @endif
    </div>
</x-layouts.app>
