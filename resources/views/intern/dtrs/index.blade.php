<x-layouts.app title="DTRs">
    <x-page-header title="Daily time records" subtitle="Submit your signed DTR for each period. Approved hours are added to your OJT total." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="My DTRs" class="lg:col-span-2" :padding="false">
            <x-table>
                <x-slot:head><th>Period</th><th>Company</th><th>Hours</th><th>Status</th><th>Note</th><th></th></x-slot:head>
                @forelse ($dtrs as $dtr)
                    <tr>
                        <td><a href="{{ route('files.show', ['dtr', $dtr]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">{{ $dtr->period_from->format('M j') }} – {{ $dtr->period_to->format('M j, Y') }}</a></td>
                        <td>{{ $dtr->placement->company->name }}</td>
                        <td class="tabular-nums">{{ $dtr->hours }} h<p class="text-xs text-stone-500">{{ $dtr->absences }} absences</p></td>
                        <td><x-badge :status="$dtr->status" />@if ($dtr->reviewed_at)<p class="mt-1 text-xs text-stone-500">{{ $dtr->reviewed_at->format('M j') }}</p>@endif</td>
                        <td class="max-w-xs text-sm text-stone-600 dark:text-stone-300">{{ $dtr->reviewer_note ?? '—' }}</td>
                        <td class="text-right">
                            @can('delete', $dtr)
                                <x-confirm-form :action="route('intern.dtrs.destroy', $dtr)" method="DELETE" confirm="Withdraw this DTR? The file will be deleted.">
                                    <x-button variant="ghost" icon="heroicon-o-trash" class="text-rose-600">Withdraw</x-button>
                                </x-confirm-form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state title="No DTRs yet" description="Submit your first DTR with the form." icon="heroicon-o-clock" class="py-8" /></td></tr>
                @endforelse
            </x-table>
            <x-pagination :paginator="$dtrs" class="px-5 pb-4" />
        </x-card>

        <x-card title="Submit a DTR">
            @if ($placement)
                <p class="mb-4 text-sm text-stone-500">Sending to <span class="font-medium text-stone-800 dark:text-stone-100">{{ $placement->company->name }}</span>.</p>
                <form method="POST" action="{{ route('intern.dtrs.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.input name="period_from" label="From" type="date" required />
                        <x-form.input name="period_to" label="To" type="date" required />
                        <x-form.input name="hours" label="Hours rendered" type="number" min="1" max="744" required />
                        <x-form.input name="absences" label="Absences" type="number" min="0" max="31" value="0" required />
                    </div>
                    <x-form.file name="file" label="Signed DTR (PDF)" accept="application/pdf" required :hint="'PDF only, up to '.(int) (config('wiis.uploads.max_pdf_kb') / 1024).' MB.'" />
                    <x-button class="w-full" icon="heroicon-o-arrow-up-tray">Submit DTR</x-button>
                </form>
            @else
                <x-empty-state title="You are not placed yet" description="Join a company first; your DTRs go to the company that hosts you." icon="heroicon-o-briefcase" class="py-6">
                    <x-slot:action><x-button :href="route('intern.internship.show')" variant="secondary">My internship</x-button></x-slot:action>
                </x-empty-state>
            @endif
        </x-card>
    </div>
</x-layouts.app>
