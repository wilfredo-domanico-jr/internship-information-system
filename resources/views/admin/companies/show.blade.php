<x-layouts.app :title="$company->name">
    <x-page-header :title="$company->name" :subtitle="($company->type ? $company->type.' · ' : '').'Code '.$company->company_code" :breadcrumbs="['Companies' => route('admin.companies.index'), $company->name => null]">
        <x-slot:actions>
            <x-badge :status="$company->approval_status" />
            {{-- Task 6 adds Approve / Reject buttons here when the company is pending --}}
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

        {{-- Task 7 adds the account-status "Danger zone" card here --}}
    </div>
</x-layouts.app>
