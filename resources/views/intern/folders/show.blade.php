<x-layouts.app :title="$folder->name.' · '.$class->display_name">
    @include('intern.class.partials.header')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="$folder->name" :subtitle="$submissions->count().' uploaded by you'" :padding="false">
                <x-slot:actions>
                    <x-button variant="ghost" :href="route('intern.class.documents')" icon="heroicon-o-arrow-left">All folders</x-button>
                </x-slot:actions>
                <x-table>
                    <x-slot:head><th>Document</th><th>Uploaded</th><th>Status</th><th>Note</th><th></th></x-slot:head>
                    @forelse ($submissions as $submission)
                        <tr>
                            <td><a href="{{ route('files.show', ['class-submission', $submission]) }}" target="_blank" class="inline-flex items-center gap-1.5 font-medium text-brand-700 hover:underline dark:text-brand-300"><x-heroicon-o-document-text class="size-4" /> {{ $submission->title }}</a></td>
                            <td class="text-stone-500">{{ $submission->created_at->format('M j, Y g:i A') }} @if ($submission->is_late)<x-badge color="rose" class="ml-1">Late</x-badge>@endif</td>
                            <td><x-badge :status="$submission->status" /></td>
                            <td class="max-w-xs text-sm text-stone-600 dark:text-stone-300">{{ $submission->reviewer_note ?? '—' }}</td>
                            <td class="text-right">
                                @can('delete', $submission)
                                    <x-confirm-form :action="route('intern.submissions.destroy', $submission)" method="DELETE" confirm="Withdraw this upload? The file will be deleted.">
                                        <x-button variant="ghost" icon="heroicon-o-trash" class="text-rose-600">Withdraw</x-button>
                                    </x-confirm-form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state title="Nothing uploaded yet" description="Use the form to upload your PDF." class="py-8" icon="heroicon-o-document-arrow-up" /></td></tr>
                    @endforelse
                </x-table>
            </x-card>
        </div>

        <x-card title="Upload a document">
            @if ($folder->is_locked)
                <div class="mb-4 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    <x-heroicon-o-lock-closed class="mt-0.5 size-4 shrink-0" />
                    <p>This folder is locked. You can still upload, but the submission will be marked <strong>late</strong>.</p>
                </div>
            @endif
            <form method="POST" action="{{ route('intern.submissions.store', $folder) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <x-form.input name="title" label="Title" placeholder="Endorsement letter" required maxlength="150" />
                <x-form.file name="file" label="PDF file" accept="application/pdf" required :hint="'PDF only, up to '.(int) (config('wiis.uploads.max_pdf_kb') / 1024).' MB.'" />
                <x-button class="w-full" icon="heroicon-o-arrow-up-tray">Upload</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.app>
