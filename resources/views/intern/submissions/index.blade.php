<x-layouts.app title="My submissions">
    <x-page-header title="My submissions" subtitle="Everything you have uploaded to your class folders." />

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Document</th><th>Folder</th><th>Uploaded</th><th>Status</th><th>Note</th></x-slot:head>
            @forelse ($submissions as $submission)
                <tr>
                    <td><a href="{{ route('files.show', ['class-submission', $submission]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">{{ $submission->title }}</a></td>
                    <td>@can('view', $submission->folder)<a href="{{ route('intern.folders.show', $submission->folder) }}" class="hover:underline">{{ $submission->folder->name }}</a>@else{{ $submission->folder->name }}@endcan<p class="text-xs text-stone-500">{{ $submission->folder->classSection->display_name }}</p></td>
                    <td class="text-stone-500">{{ $submission->created_at->format('M j, Y') }} @if ($submission->is_late)<x-badge color="rose" class="ml-1">Late</x-badge>@endif</td>
                    <td><x-badge :status="$submission->status" />@if ($submission->reviewed_at)<p class="mt-1 text-xs text-stone-500">{{ $submission->reviewer?->name }} · {{ $submission->reviewed_at->format('M j') }}</p>@endif</td>
                    <td class="max-w-xs text-sm text-stone-600 dark:text-stone-300">{{ $submission->reviewer_note ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state title="No submissions yet" description="Open your class's Documents tab to upload." icon="heroicon-o-document-check" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$submissions" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
