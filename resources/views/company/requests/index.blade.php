<x-layouts.app title="Document requests">
    <x-page-header title="Document requests" subtitle="Interns ask for documents here. Upload the PDF to fulfil a request, or decline it." />

    <nav class="flex flex-wrap gap-2" aria-label="Request groups">
        @foreach (\App\Enums\DocumentRequestStatus::cases() as $status)
            <a href="{{ route('company.requests.index', ['status' => $status->value]) }}"
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
            <x-slot:head><th>Control no.</th><th>Intern</th><th>Document</th><th>Requested</th><th>Status</th><th class="text-right">Actions</th></x-slot:head>
            @forelse ($requests as $documentRequest)
                <tr>
                    <td class="font-mono text-xs">{{ $documentRequest->control_no }}</td>
                    <td><p class="font-medium">{{ $documentRequest->placement->intern->name }}</p><p class="font-mono text-xs text-stone-500">{{ $documentRequest->placement->intern->internProfile?->student_number }}</p></td>
                    <td><p class="font-medium">{{ $documentRequest->document_name }}</p>@if ($documentRequest->message)<p class="max-w-xs text-xs text-stone-500">{{ $documentRequest->message }}</p>@endif</td>
                    <td class="text-stone-500">{{ $documentRequest->created_at->format('M j, Y') }}</td>
                    <td>
                        <x-badge :status="$documentRequest->status" />
                        @if ($documentRequest->file_path)<a href="{{ route('files.show', ['document-request', $documentRequest]) }}" target="_blank" class="mt-1 block text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">Uploaded file</a>@endif
                        @if ($documentRequest->handled_at)<p class="mt-1 text-xs text-stone-500">{{ $documentRequest->handled_at->format('M j, Y') }}</p>@endif
                    </td>
                    <td class="text-right">
                        @if ($documentRequest->status === \App\Enums\DocumentRequestStatus::Pending)
                            <div class="flex items-center justify-end gap-1" x-data="{ name: 'fulfil-{{ $documentRequest->id }}' }">
                                <x-button type="button" variant="ghost" icon="heroicon-o-arrow-up-tray" class="text-emerald-700 dark:text-emerald-300" @click="$dispatch('open-modal', name)">Upload</x-button>
                                <x-confirm-form :action="route('company.requests.decline', $documentRequest)" :confirm="'Decline request '.$documentRequest->control_no.'?'">
                                    <x-button variant="ghost" icon="heroicon-o-x-mark" class="text-rose-600">Decline</x-button>
                                </x-confirm-form>
                            </div>
                            <x-modal :name="'fulfil-'.$documentRequest->id" title="Upload the document">
                                <form method="POST" action="{{ route('company.requests.fulfil', $documentRequest) }}" enctype="multipart/form-data" class="space-y-4" x-data="{ name: 'fulfil-{{ $documentRequest->id }}' }"
                                      x-init="@if ($errors->has('file') && (string) old('request_id') === (string) $documentRequest->id) $nextTick(() => $dispatch('open-modal', name)) @endif">
                                    @csrf
                                    <input type="hidden" name="request_id" value="{{ $documentRequest->id }}">
                                    <p class="text-sm text-stone-600 dark:text-stone-300">{{ $documentRequest->document_name }} for {{ $documentRequest->placement->intern->name }} ({{ $documentRequest->control_no }}).</p>
                                    <div>
                                        <label for="file-{{ $documentRequest->id }}" class="label">PDF file <span class="text-rose-500">*</span></label>
                                        <input id="file-{{ $documentRequest->id }}" name="file" type="file" accept="application/pdf" required class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-xl file:border-0 file:bg-brand-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:text-stone-300 dark:file:bg-brand-900/40 dark:file:text-brand-200">
                                        @if ((string) old('request_id') === (string) $documentRequest->id)<x-form.error name="file" />@endif
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <x-button type="button" variant="secondary" @click="$dispatch('close-modal', name)">Cancel</x-button>
                                        <x-button icon="heroicon-o-arrow-up-tray">Fulfil request</x-button>
                                    </div>
                                </form>
                            </x-modal>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state :title="'No '.$group->label().' requests'" icon="heroicon-o-document-text" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$requests" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
