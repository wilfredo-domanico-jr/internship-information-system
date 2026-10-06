<x-card>
    <div class="flex items-start gap-3">
        <x-avatar :user="$announcement->author" size="sm" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                <p class="font-semibold">{{ $announcement->author->name }}</p>
                <time class="text-xs text-stone-500" datetime="{{ $announcement->created_at->toIso8601String() }}" title="{{ $announcement->created_at->format('M j, Y g:i A') }}">{{ $announcement->created_at->diffForHumans() }}</time>
            </div>
            <x-rich-text :html="$announcement->body" class="mt-2" />
        </div>
        @can('update', $announcement)
            <x-dropdown width="w-44">
                <x-slot:trigger><button type="button" class="btn-ghost -mr-2 p-1.5" aria-label="Announcement actions"><x-heroicon-o-ellipsis-vertical class="size-5" /></button></x-slot:trigger>
                <x-dropdown.item :href="route('adviser.announcements.edit', $announcement)" icon="heroicon-o-pencil-square">Edit</x-dropdown.item>
                <x-confirm-form :action="route('adviser.announcements.destroy', $announcement)" method="DELETE" confirm="Delete this announcement and its comments?">
                    <button class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"><x-heroicon-o-trash class="size-4" /> Delete</button>
                </x-confirm-form>
            </x-dropdown>
        @endcan
    </div>

    @if ($announcement->comments->isNotEmpty())
        <div class="mt-4 space-y-3 border-t border-stone-100 pt-4 dark:border-stone-800">
            @foreach ($announcement->comments as $comment)
                <div class="flex items-start gap-3">
                    <x-avatar :user="$comment->author" size="xs" class="mt-0.5" />
                    <div class="min-w-0 flex-1 text-sm">
                        <span class="font-medium">{{ $comment->author->name }}</span>
                        <span class="ml-2 text-xs text-stone-500">{{ $comment->created_at->diffForHumans() }}</span>
                        <p class="mt-0.5 whitespace-pre-line text-stone-700 dark:text-stone-200">{{ $comment->body }}</p>
                    </div>
                    {{-- Task 8 adds the delete button here --}}
                </div>
            @endforeach
        </div>
    @endif
    {{-- Task 8 adds the comment form here --}}
</x-card>
