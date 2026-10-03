@props(['title' => null])
<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-stone-200/80 bg-surface/80 px-4 backdrop-blur sm:px-6 lg:px-8 dark:border-stone-800 dark:bg-surface-dark/80">
    <button type="button" class="btn-ghost -ml-2 p-2 lg:hidden" @click="sidebarOpen = true" aria-label="Open menu">
        <x-heroicon-o-bars-3 class="size-6" />
    </button>
    <p class="truncate text-sm font-medium text-stone-500">{{ $title }}</p>
    <div class="ml-auto flex items-center gap-1">
        <x-notification-bell />
        <x-dropdown align="right">
            <x-slot:trigger>
                <button type="button" class="flex items-center gap-2 rounded-full p-1 pr-2 hover:bg-stone-100 dark:hover:bg-stone-800" aria-label="Account menu">
                    <x-avatar :user="auth()->user()" size="sm" />
                    <x-heroicon-o-chevron-down class="size-4 text-stone-500" />
                </button>
            </x-slot:trigger>
            @if (Route::has('profile.edit'))
                <x-dropdown.item :href="route('profile.edit')" icon="heroicon-o-user-circle">Profile</x-dropdown.item>
            @endif
            @if (Route::has('logout'))
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-stone-700 hover:bg-stone-100 dark:text-stone-200 dark:hover:bg-stone-800">
                        <x-heroicon-o-arrow-right-start-on-rectangle class="size-4 text-stone-500" /> Sign out
                    </button>
                </form>
            @endif
        </x-dropdown>
    </div>
</header>
