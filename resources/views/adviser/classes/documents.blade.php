<x-layouts.app :title="'Documents · '.$class->display_name">
    @include('adviser.classes.partials.header')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card title="Folders" subtitle="Interns upload PDFs into folders. Lock a folder once the deadline passes; later uploads are flagged late." :padding="false">
                @forelse ($folders as $folder)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 px-5 py-4 last:border-0 dark:border-stone-800">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10">
                                @if ($folder->is_locked)<x-heroicon-o-lock-closed class="size-5" />@else<x-heroicon-o-folder class="size-5" />@endif
                            </span>
                            <div class="min-w-0">
                                <a href="{{ route('adviser.folders.show', $folder) }}" class="font-medium hover:underline">{{ $folder->name }}</a>
                                <p class="text-xs text-stone-500">{{ $folder->pending_submissions_count }} pending · {{ $folder->submissions_count }} total</p>
                            </div>
                            @if ($folder->is_locked)<x-badge color="amber">Locked</x-badge>@endif
                        </div>
                        @can('manage', $class)
                            <div class="flex items-center gap-1">
                                <form method="POST" action="{{ route('adviser.folders.lock', $folder) }}">
                                    @csrf
                                    <x-button variant="ghost" :icon="$folder->is_locked ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed'">{{ $folder->is_locked ? 'Unlock' : 'Lock' }}</x-button>
                                </form>
                                <x-confirm-form :action="route('adviser.folders.destroy', $folder)" method="DELETE" :confirm="'Delete “'.$folder->name.'” and all '.$folder->submissions_count.' submissions in it? Files are removed permanently.'">
                                    <x-button variant="ghost" icon="heroicon-o-trash" class="text-rose-600">Delete</x-button>
                                </x-confirm-form>
                            </div>
                        @endcan
                    </div>
                @empty
                    <x-empty-state title="No folders yet" description="Create a folder such as “Endorsement Letter” or “Weekly Report 1”." icon="heroicon-o-folder" />
                @endforelse
            </x-card>

            <x-card title="Shared resources" subtitle="Templates, guidelines and other PDFs every intern can download." :padding="false">
                @can('manage', $class)
                    <x-slot:actions>
                        <x-button type="button" variant="secondary" icon="heroicon-o-arrow-up-tray" @click="$dispatch('open-modal', 'share-resource')">Share a PDF</x-button>
                    </x-slot:actions>
                @endcan
                @forelse ($resources as $resource)
                    <div class="flex items-center justify-between gap-3 border-b border-stone-100 px-5 py-3 last:border-0 dark:border-stone-800">
                        <a href="{{ route('files.show', ['class-resource', $resource]) }}" target="_blank" class="flex min-w-0 items-center gap-3 hover:underline">
                            <x-heroicon-o-document-text class="size-5 shrink-0 text-stone-400" />
                            <span class="truncate font-medium">{{ $resource->title }}</span>
                            <span class="hidden text-xs text-stone-500 sm:inline">{{ $resource->uploader->name }} · {{ $resource->created_at->format('M j, Y') }}</span>
                        </a>
                        @can('delete', $resource)
                            <x-confirm-form :action="route('adviser.resources.destroy', $resource)" method="DELETE" :confirm="'Remove “'.$resource->title.'”?'">
                                <button class="btn-ghost p-1.5 text-stone-400 hover:text-rose-600" aria-label="Remove resource"><x-heroicon-o-trash class="size-4" /></button>
                            </x-confirm-form>
                        @endcan
                    </div>
                @empty
                    <x-empty-state title="No shared resources" description="Share templates or guidelines as PDFs." icon="heroicon-o-paper-clip" class="py-8" />
                @endforelse
            </x-card>

            @can('manage', $class)
                <x-modal name="share-resource" title="Share a PDF with the class">
                    <form method="POST" action="{{ route('adviser.resources.store', $class) }}" enctype="multipart/form-data" class="space-y-4"
                          x-init="@if ($errors->hasAny(['title', 'file'])) $nextTick(() => $dispatch('open-modal', 'share-resource')) @endif">
                        @csrf
                        <x-form.input name="title" label="Title" placeholder="OJT Guidelines" required maxlength="150" />
                        <x-form.file name="file" label="PDF file" accept="application/pdf" required />
                        <div class="flex justify-end gap-2">
                            <x-button type="button" variant="secondary" @click="$dispatch('close-modal', 'share-resource')">Cancel</x-button>
                            <x-button icon="heroicon-o-arrow-up-tray">Share</x-button>
                        </div>
                    </form>
                </x-modal>
            @endcan
        </div>

        <div class="space-y-4">
            @can('manage', $class)
                <x-card title="New folder">
                    <form method="POST" action="{{ route('adviser.folders.store', $class) }}" class="space-y-4">
                        @csrf
                        <x-form.input name="name" label="Folder name" placeholder="Weekly Report 2" required maxlength="100" />
                        <x-button class="w-full" icon="heroicon-o-folder-plus">Create folder</x-button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
</x-layouts.app>
