@php
    $user = auth()->user();
    $unread = $user?->unreadNotifications()->count() ?? 0;
    $latest = $user?->unreadNotifications()->latest()->limit(5)->get() ?? collect();
@endphp
@if ($user && Route::has('notifications.index'))
    <x-dropdown align="right" width="w-80">
        <x-slot:trigger>
            <button type="button" class="btn-ghost relative p-2" aria-label="Notifications{{ $unread ? ", $unread unread" : '' }}">
                <x-heroicon-o-bell class="size-5" />
                @if ($unread)
                    <span class="absolute right-1.5 top-1.5 grid min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-4 text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                @endif
            </button>
        </x-slot:trigger>
        <div class="flex items-center justify-between px-3 py-2">
            <p class="text-sm font-semibold">Notifications</p>
            <span class="text-xs text-stone-500">{{ $unread }} unread</span>
        </div>
        @forelse ($latest as $notification)
            <a href="{{ route('notifications.open', $notification->id) }}" class="block rounded-xl px-3 py-2 hover:bg-stone-100 dark:hover:bg-stone-800">
                <p class="truncate text-sm font-medium">{{ $notification->data['title'] }}</p>
                <p class="line-clamp-2 text-xs text-stone-500">{{ $notification->data['body'] }}</p>
            </a>
        @empty
            <p class="px-3 py-4 text-center text-sm text-stone-500">No unread notifications</p>
        @endforelse
        <a href="{{ route('notifications.index') }}" class="mt-1 block rounded-xl border-t border-stone-100 px-3 py-2 text-center text-sm font-medium text-brand-700 hover:bg-stone-100 dark:border-stone-800 dark:text-brand-300 dark:hover:bg-stone-800">View all</a>
    </x-dropdown>
@endif
