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
        {{-- Task 7 adds the edit/delete menu here --}}
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
