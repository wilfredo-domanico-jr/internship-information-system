<x-layouts.app title="DTRs">
    <x-page-header title="Daily time records" subtitle="Approving a DTR credits its hours to the intern once. Disapproved DTRs must be resubmitted." />

    <nav class="flex flex-wrap gap-2" aria-label="DTR groups">
        @foreach (\App\Enums\DtrStatus::cases() as $status)
            <a href="{{ route('company.dtrs.index', ['status' => $status->value]) }}"
               @class(['inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                       'bg-brand-600 text-white ring-brand-600' => $group === $status,
                       'bg-white text-stone-600 ring-stone-300 hover:bg-stone-50 dark:bg-stone-900 dark:text-stone-300 dark:ring-stone-700' => $group !== $status])
               @if ($group === $status) aria-current="page" @endif>
                {{ $status->label() }} <span class="rounded-full bg-black/10 px-1.5 text-xs tabular-nums dark:bg-white/10">{{ $counts[$status->value] }}</span>
            </a>
        @endforeach
    </nav>

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Intern</th><th>Period</th><th>Hours</th><th>Submitted</th><th>Status</th><th class="text-right">Actions</th></x-slot:head>
            @forelse ($dtrs as $dtr)
                <tr>
                    <td><p class="font-medium">{{ $dtr->placement->intern->name }}</p><p class="font-mono text-xs text-stone-500">{{ $dtr->placement->intern->internProfile?->student_number }}</p></td>
                    <td><a href="{{ route('files.show', ['dtr', $dtr]) }}" target="_blank" class="inline-flex items-center gap-1.5 font-medium text-brand-700 hover:underline dark:text-brand-300"><x-heroicon-o-document-text class="size-4" /> {{ $dtr->period_from->format('M j') }} – {{ $dtr->period_to->format('M j, Y') }}</a></td>
                    <td class="tabular-nums">{{ $dtr->hours }} h<p class="text-xs text-stone-500">{{ $dtr->absences }} absences</p></td>
                    <td class="text-stone-500">{{ $dtr->created_at->format('M j, Y') }}</td>
                    <td>
                        <x-badge :status="$dtr->status" />
                        @if ($dtr->reviewed_at)<p class="mt-1 text-xs text-stone-500">{{ $dtr->reviewer?->name }} · {{ $dtr->reviewed_at->format('M j') }}</p>@endif
                        @if ($dtr->reviewer_note)<p class="mt-1 max-w-xs text-xs text-stone-600 dark:text-stone-300">{{ $dtr->reviewer_note }}</p>@endif
                    </td>
                    <td class="text-right">
                        @if ($dtr->status === \App\Enums\DtrStatus::Pending)
                            <div class="flex items-center justify-end gap-1" x-data="{ name: 'disapprove-{{ $dtr->id }}' }">
                                <x-confirm-form :action="route('company.dtrs.approve', $dtr)" :confirm="'Approve '.$dtr->hours.' hours for '.$dtr->placement->intern->name.'? This cannot be undone.'">
                                    <x-button variant="ghost" icon="heroicon-o-check" class="text-emerald-700 dark:text-emerald-300">Approve</x-button>
                                </x-confirm-form>
                                <x-button type="button" variant="ghost" icon="heroicon-o-x-mark" class="text-rose-600" @click="$dispatch('open-modal', name)">Disapprove</x-button>
                            </div>
                            <x-modal :name="'disapprove-'.$dtr->id" title="Disapprove DTR">
                                <form method="POST" action="{{ route('company.dtrs.disapprove', $dtr) }}" class="space-y-4" x-data="{ name: 'disapprove-{{ $dtr->id }}' }"
                                      x-init="@if ($errors->has('note') && (string) old('dtr_id') === (string) $dtr->id) $nextTick(() => $dispatch('open-modal', name)) @endif">
                                    @csrf
                                    <input type="hidden" name="dtr_id" value="{{ $dtr->id }}">
                                    <p class="text-sm text-stone-600 dark:text-stone-300">The note is sent to {{ $dtr->placement->intern->name }}. No hours are credited.</p>
                                    <div>
                                        <label for="note-{{ $dtr->id }}" class="label">Reason <span class="text-rose-500">*</span></label>
                                        <textarea id="note-{{ $dtr->id }}" name="note" rows="3" required maxlength="500" class="input">{{ (string) old('dtr_id') === (string) $dtr->id ? old('note') : '' }}</textarea>
                                        @if ((string) old('dtr_id') === (string) $dtr->id)<x-form.error name="note" />@endif
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <x-button type="button" variant="secondary" @click="$dispatch('close-modal', name)">Cancel</x-button>
                                        <x-button variant="danger" icon="heroicon-o-x-mark">Disapprove</x-button>
                                    </div>
                                </form>
                            </x-modal>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state :title="'No '.$group->label().' DTRs'" icon="heroicon-o-clipboard-document-check" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$dtrs" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
