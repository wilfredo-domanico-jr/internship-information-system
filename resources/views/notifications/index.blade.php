<x-layouts.app title="Notifications">
    <x-page-header title="Notifications" subtitle="Updates about your applications, classes and requests.">
        <x-slot:actions>
            @if (auth()->user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<x-button variant="secondary" icon="heroicon-o-check">Mark all as read</x-button></form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        @forelse ($notifications as $notification)
            @php $unread = $notification->read_at === null; @endphp
            <div class="flex items-start gap-4 border-b border-stone-100 px-5 py-4 last:border-0 dark:border-stone-800 {{ $unread ? 'bg-brand-50/40 dark:bg-brand-900/10' : '' }}">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-stone-100 text-stone-500 dark:bg-stone-800">
                    <x-dynamic-component :component="$notification->data['icon'] ?? 'heroicon-o-bell'" class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <a href="{{ route('notifications.open', $notification->id) }}" class="font-medium hover:underline">{{ $notification->data['title'] }}</a>
                    <p class="text-sm text-stone-600 dark:text-stone-400">{{ $notification->data['body'] }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
                <div class="flex items-center gap-1">
                    @if ($unread)<span class="size-2 rounded-full bg-brand-600" aria-label="Unread"></span>@endif
                    <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}">
                        @csrf @method('DELETE')
                        <button class="btn-ghost p-1.5" aria-label="Delete notification"><x-heroicon-o-trash class="size-4" /></button>
                    </form>
                </div>
            </div>
        @empty
            <x-empty-state title="You're all caught up" description="New activity on your account will show up here." icon="heroicon-o-bell-slash" />
        @endforelse
    </x-card>

    {{ $notifications->links() }}
</x-layouts.app>
