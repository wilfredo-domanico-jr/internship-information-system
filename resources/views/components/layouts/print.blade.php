@props(['title' => null, 'back' => null])
<x-layouts.base :title="$title" class="bg-white text-stone-900">
    <div class="mx-auto max-w-5xl p-8 print:p-0">
        <div class="mb-6 flex items-center justify-between gap-3 print:hidden">
            @if ($back)<a href="{{ $back }}" class="btn-secondary">Back</a>@else<span></span>@endif
            <button type="button" class="btn-primary" onclick="window.print()"><x-heroicon-o-printer class="size-4" /> Print</button>
        </div>
        {{ $slot }}
    </div>
</x-layouts.base>
