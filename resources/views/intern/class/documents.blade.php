<x-layouts.app :title="'Documents · '.$class->display_name">
    @include('intern.class.partials.header')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card title="Folders" subtitle="Upload the PDF your adviser asked for into its folder." :padding="false">
                @forelse ($folders as $folder)
                    @php $latest = $folder->submissions->first(); @endphp
                    <a href="{{ route('intern.folders.show', $folder) }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 px-5 py-4 last:border-0 hover:bg-stone-50 dark:border-stone-800 dark:hover:bg-stone-900">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10">
                                @if ($folder->is_locked)<x-heroicon-o-lock-closed class="size-5" />@else<x-heroicon-o-folder class="size-5" />@endif
                            </span>
                            <div class="min-w-0">
                                <p class="font-medium">{{ $folder->name }}</p>
                                <p class="text-xs text-stone-500">{{ $folder->submissions->count() }} of yours{{ $folder->is_locked ? ' · new uploads are marked late' : '' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($folder->is_locked)<x-badge color="amber">Locked</x-badge>@endif
                            @if ($latest)<x-badge :status="$latest->status" />@else<span class="text-xs text-stone-500">Nothing uploaded</span>@endif
                        </div>
                    </a>
                @empty
                    <x-empty-state title="No folders yet" description="Your adviser has not created any folders." icon="heroicon-o-folder" />
                @endforelse
            </x-card>

            {{-- Task 12 adds the shared resources card here --}}
        </div>

        <x-card title="How it works">
            <ul class="list-disc space-y-2 pl-5 text-sm text-stone-600 dark:text-stone-300">
                <li>Only PDF files up to {{ (int) (config('wiis.uploads.max_pdf_kb') / 1024) }} MB are accepted.</li>
                <li>Your adviser approves or declines each upload and may leave a note.</li>
                <li>You can withdraw an upload until it is reviewed.</li>
                <li>Locked folders still accept uploads, but they are marked late.</li>
            </ul>
        </x-card>
    </div>
</x-layouts.app>
