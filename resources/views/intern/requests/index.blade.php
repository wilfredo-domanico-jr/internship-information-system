<x-layouts.app title="Document requests">
    <x-page-header title="Document requests" subtitle="Ask your company for documents such as a certificate of completion or an evaluation form." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="My requests" class="lg:col-span-2" :padding="false">
            <x-table>
                <x-slot:head><th>Control no.</th><th>Document</th><th>Company</th><th>Status</th><th class="text-right"></th></x-slot:head>
                @forelse ($requests as $documentRequest)
                    <tr>
                        <td class="font-mono text-xs">{{ $documentRequest->control_no }}</td>
                        <td>
                            <p class="font-medium">{{ $documentRequest->document_name }}</p>
                            @if ($documentRequest->message)<p class="text-xs text-stone-500">{{ $documentRequest->message }}</p>@endif
                            <p class="text-xs text-stone-500">Requested {{ $documentRequest->created_at->format('M j, Y') }}</p>
                        </td>
                        <td>{{ $documentRequest->placement->company->name }}</td>
                        <td>
                            <x-badge :status="$documentRequest->status" />
                            @if ($documentRequest->file_path)<a href="{{ route('files.show', ['document-request', $documentRequest]) }}" target="_blank" class="mt-1 block text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">Download document</a>@endif
                            @if ($documentRequest->handled_at)<p class="mt-1 text-xs text-stone-500">{{ $documentRequest->handled_at->format('M j, Y') }}</p>@endif
                        </td>
                        <td class="text-right">
                            @can('update', $documentRequest)
                                <div class="flex items-center justify-end gap-1" x-data="{ name: 'edit-{{ $documentRequest->id }}' }">
                                    <x-button type="button" variant="ghost" icon="heroicon-o-pencil-square" @click="$dispatch('open-modal', name)">Edit</x-button>
                                    <x-confirm-form :action="route('intern.requests.destroy', $documentRequest)" method="DELETE" confirm="Withdraw this request?">
                                        <x-button variant="ghost" icon="heroicon-o-trash" class="text-rose-600">Withdraw</x-button>
                                    </x-confirm-form>
                                </div>
                                <x-modal :name="'edit-'.$documentRequest->id" title="Edit request">
                                    <form method="POST" action="{{ route('intern.requests.update', $documentRequest) }}" class="space-y-4" x-data="{ name: 'edit-{{ $documentRequest->id }}' }"
                                          x-init="@if ($errors->hasAny(['document_name', 'message']) && (string) old('request_id') === (string) $documentRequest->id) $nextTick(() => $dispatch('open-modal', name)) @endif">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="request_id" value="{{ $documentRequest->id }}">
                                        <div>
                                            <label for="name-{{ $documentRequest->id }}" class="label">Document <span class="text-rose-500">*</span></label>
                                            <input id="name-{{ $documentRequest->id }}" name="document_name" required maxlength="150" class="input" value="{{ (string) old('request_id') === (string) $documentRequest->id ? old('document_name') : $documentRequest->document_name }}">
                                        </div>
                                        <div>
                                            <label for="message-{{ $documentRequest->id }}" class="label">Message</label>
                                            <textarea id="message-{{ $documentRequest->id }}" name="message" rows="3" maxlength="1000" class="input">{{ (string) old('request_id') === (string) $documentRequest->id ? old('message') : $documentRequest->message }}</textarea>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <x-button type="button" variant="secondary" @click="$dispatch('close-modal', name)">Cancel</x-button>
                                            <x-button icon="heroicon-o-check">Save</x-button>
                                        </div>
                                    </form>
                                </x-modal>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state title="No requests yet" icon="heroicon-o-document-text" class="py-8" /></td></tr>
                @endforelse
            </x-table>
            <x-pagination :paginator="$requests" class="px-5 pb-4" />
        </x-card>

        <x-card title="New request">
            @if ($placement)
                <p class="mb-4 text-sm text-stone-500">Sending to <span class="font-medium text-stone-800 dark:text-stone-100">{{ $placement->company->name }}</span>.</p>
                <form method="POST" action="{{ route('intern.requests.store') }}" class="space-y-4">
                    @csrf
                    <x-form.input name="document_name" label="Document" placeholder="Certificate of Completion" required maxlength="150" :value="old('request_id') ? null : old('document_name')" />
                    <x-form.textarea name="message" label="Message" rows="3" placeholder="Why you need it, or any details." />
                    <x-button class="w-full" icon="heroicon-o-paper-airplane">Send request</x-button>
                </form>
            @else
                <x-empty-state title="You are not placed yet" description="Join a company first; requests go to the company that hosts you." icon="heroicon-o-briefcase" class="py-6">
                    <x-slot:action><x-button :href="route('intern.internship.show')" variant="secondary">My internship</x-button></x-slot:action>
                </x-empty-state>
            @endif
        </x-card>
    </div>
</x-layouts.app>
