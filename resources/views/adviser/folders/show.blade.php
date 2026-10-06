<x-layouts.app :title="$folder->name.' · '.$class->display_name">
    <x-page-header :title="$folder->name" :subtitle="$class->display_name.' · '.$class->subject" :breadcrumbs="['My classes' => route('adviser.classes.index'), $class->display_name => route('adviser.classes.show', $class), 'Documents' => route('adviser.classes.documents', $class), $folder->name => null]">
        <x-slot:actions>
            @if ($folder->is_locked)<x-badge color="amber">Locked · new uploads are marked late</x-badge>@endif
            <form method="POST" action="{{ route('adviser.folders.lock', $folder) }}">
                @csrf
                <x-button variant="secondary" :icon="$folder->is_locked ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed'">{{ $folder->is_locked ? 'Unlock folder' : 'Lock folder' }}</x-button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <nav class="flex flex-wrap gap-2" aria-label="Submission groups">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'declined' => 'Declined', 'late' => 'Late'] as $key => $label)
            <a href="{{ route('adviser.folders.show', [$folder, 'status' => $key]) }}"
               @class(['inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                       'bg-brand-600 text-white ring-brand-600' => $group === $key,
                       'bg-white text-stone-600 ring-stone-300 hover:bg-stone-50 dark:bg-stone-900 dark:text-stone-300 dark:ring-stone-700' => $group !== $key])
               @if ($group === $key) aria-current="page" @endif>
                {{ $label }} <span class="rounded-full bg-black/10 px-1.5 text-xs tabular-nums dark:bg-white/10">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Intern</th><th>Document</th><th>Submitted</th><th>Status</th><th>Note</th><th class="text-right">Actions</th></x-slot:head>
            @forelse ($submissions as $submission)
                <tr>
                    <td>
                        <p class="font-medium">{{ $submission->intern->name }}</p>
                        <p class="font-mono text-xs text-stone-500">{{ $submission->intern->internProfile?->student_number }}</p>
                    </td>
                    <td><a href="{{ route('files.show', ['class-submission', $submission]) }}" target="_blank" class="inline-flex items-center gap-1.5 font-medium text-brand-700 hover:underline dark:text-brand-300"><x-heroicon-o-document-text class="size-4" /> {{ $submission->title }}</a></td>
                    <td class="text-stone-500">
                        {{ $submission->created_at->format('M j, Y g:i A') }}
                        @if ($submission->is_late)<x-badge color="rose" class="ml-1">Late</x-badge>@endif
                    </td>
                    <td>
                        <x-badge :status="$submission->status" />
                        @if ($submission->reviewed_at)<p class="mt-1 text-xs text-stone-500">{{ $submission->reviewer?->name }} · {{ $submission->reviewed_at->format('M j') }}</p>@endif
                    </td>
                    <td class="max-w-xs text-sm text-stone-600 dark:text-stone-300">{{ $submission->reviewer_note ?? '—' }}</td>
                    <td class="text-right">
                        @can('review', $submission)
                            <div class="flex items-center justify-end gap-1"
                                 x-data="{ name: 'decline-{{ $submission->id }}' }"
                                 x-init="@if ($errors->has('note') && (string) old('submission_id') === (string) $submission->id) $nextTick(() => $dispatch('open-modal', name)) @endif">
                                @if ($submission->status !== \App\Enums\SubmissionStatus::Approved)
                                    <form method="POST" action="{{ route('adviser.submissions.approve', $submission) }}">
                                        @csrf
                                        <x-button variant="ghost" icon="heroicon-o-check" class="text-emerald-700 dark:text-emerald-300">Approve</x-button>
                                    </form>
                                @endif
                                @if ($submission->status !== \App\Enums\SubmissionStatus::Declined)
                                    <x-button type="button" variant="ghost" icon="heroicon-o-x-mark" class="text-rose-600" @click="$dispatch('open-modal', name)">Decline</x-button>
                                @endif
                            </div>
                            <x-modal :name="'decline-'.$submission->id" title="Decline submission">
                                <form method="POST" action="{{ route('adviser.submissions.decline', $submission) }}" class="space-y-4" x-data="{ name: 'decline-{{ $submission->id }}' }">
                                    @csrf
                                    <input type="hidden" name="submission_id" value="{{ $submission->id }}">
                                    <p class="text-sm text-stone-600 dark:text-stone-300">Declining <span class="font-medium">{{ $submission->title }}</span> by {{ $submission->intern->name }}. The note is sent to the intern.</p>
                                    <div>
                                        <label for="note-{{ $submission->id }}" class="label">Reason <span class="text-rose-500">*</span></label>
                                        <textarea id="note-{{ $submission->id }}" name="note" rows="3" required maxlength="500" class="input">{{ (string) old('submission_id') === (string) $submission->id ? old('note') : '' }}</textarea>
                                        @if ((string) old('submission_id') === (string) $submission->id)<x-form.error name="note" />@endif
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <x-button type="button" variant="secondary" @click="$dispatch('close-modal', name)">Cancel</x-button>
                                        <x-button variant="danger" icon="heroicon-o-x-mark">Decline</x-button>
                                    </div>
                                </form>
                            </x-modal>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state :title="'No '.$group.' submissions'" class="py-8" icon="heroicon-o-document-check" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$submissions" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
