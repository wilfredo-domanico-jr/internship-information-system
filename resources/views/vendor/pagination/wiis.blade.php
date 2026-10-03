<nav role="navigation" aria-label="Pagination" class="flex items-center justify-between gap-4 text-sm">
    <p class="text-stone-500">Showing <span class="font-medium">{{ $paginator->firstItem() }}</span>–<span class="font-medium">{{ $paginator->lastItem() }}</span> of <span class="font-medium">{{ $paginator->total() }}</span></p>
    <div class="flex items-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="btn-ghost cursor-not-allowed px-3 py-1.5 opacity-50">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-ghost px-3 py-1.5">Previous</a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))<span class="px-2 text-stone-400">{{ $element }}</span>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="rounded-lg bg-brand-600 px-3 py-1.5 font-semibold text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="btn-ghost px-3 py-1.5">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-ghost px-3 py-1.5">Next</a>
        @else
            <span class="btn-ghost cursor-not-allowed px-3 py-1.5 opacity-50">Next</span>
        @endif
    </div>
</nav>
