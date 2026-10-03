@php $unread = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0; @endphp
@if (Route::has('notifications.index'))
    <a href="{{ route('notifications.index') }}" class="btn-ghost relative p-2" aria-label="Notifications{{ $unread ? ", $unread unread" : '' }}">
        <x-heroicon-o-bell class="size-5" />
        @if ($unread)
            <span class="absolute right-1.5 top-1.5 grid min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-4 text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </a>
@endif
