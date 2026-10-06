<x-layouts.app title="Certificates">
    <x-page-header title="Certificates" :subtitle="'Interns with at least '.$hours->certificateMinimum().' hours rendered at your company. Re-issuing keeps the earlier copies.'" />

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Intern</th><th>Hours here</th><th>Placement</th><th>Certificates</th><th class="text-right">Issue</th></x-slot:head>
            @forelse ($placements as $placement)
                <tr>
                    <td><p class="font-medium">{{ $placement->intern->name }}</p><p class="font-mono text-xs text-stone-500">{{ $placement->intern->internProfile?->student_number }}</p></td>
                    <td class="tabular-nums">{{ $placement->hours_rendered }} h</td>
                    <td class="text-stone-500">{{ $placement->started_at->format('M j, Y') }} – {{ $placement->ended_at?->format('M j, Y') ?? 'present' }}</td>
                    <td>
                        @forelse ($placement->certificates as $certificate)
                            <a href="{{ route('files.show', ['certificate', $certificate]) }}" target="_blank" class="block text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">{{ $certificate->issued_at->format('M j, Y') }} · {{ $certificate->hours_at_issue }} h</a>
                        @empty
                            <span class="text-sm text-stone-500">None yet</span>
                        @endforelse
                    </td>
                    <td class="text-right">
                        <div x-data="{ name: 'issue-{{ $placement->id }}' }">
                            <x-button type="button" :variant="$placement->certificates->isEmpty() ? 'primary' : 'secondary'" icon="heroicon-o-trophy" @click="$dispatch('open-modal', name)">{{ $placement->certificates->isEmpty() ? 'Issue' : 'Re-issue' }}</x-button>
                        </div>
                        <x-modal :name="'issue-'.$placement->id" title="Issue certificate">
                            <form method="POST" action="{{ route('company.certificates.store', $placement) }}" enctype="multipart/form-data" class="space-y-4" x-data="{ name: 'issue-{{ $placement->id }}' }"
                                  x-init="@if ($errors->has('file') && (string) old('placement_id') === (string) $placement->id) $nextTick(() => $dispatch('open-modal', name)) @endif">
                                @csrf
                                <input type="hidden" name="placement_id" value="{{ $placement->id }}">
                                <p class="text-sm text-stone-600 dark:text-stone-300">Certificate for {{ $placement->intern->name }} · {{ $placement->hours_rendered }} hours rendered.</p>
                                <div>
                                    <label for="file-{{ $placement->id }}" class="label">Certificate PDF <span class="text-rose-500">*</span></label>
                                    <input id="file-{{ $placement->id }}" name="file" type="file" accept="application/pdf" required class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-xl file:border-0 file:bg-brand-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:text-stone-300 dark:file:bg-brand-900/40 dark:file:text-brand-200">
                                    @if ((string) old('placement_id') === (string) $placement->id)<x-form.error name="file" />@endif
                                </div>
                                <div class="flex justify-end gap-2">
                                    <x-button type="button" variant="secondary" @click="$dispatch('close-modal', name)">Cancel</x-button>
                                    <x-button icon="heroicon-o-trophy">Issue certificate</x-button>
                                </div>
                            </form>
                        </x-modal>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state title="No eligible interns yet" :description="'Interns appear here once they reach '.$hours->certificateMinimum().' approved hours with you.'" icon="heroicon-o-trophy" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$placements" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
