@php $user = auth()->user(); @endphp
<div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-stone-900/50 lg:hidden" @click="sidebarOpen = false"></div>

<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-stone-200 bg-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 dark:border-stone-800 dark:bg-surface-dark-raised">
    <div class="flex h-16 items-center justify-between px-5">
        <x-brand />
        <button type="button" class="btn-ghost -mr-2 p-2 lg:hidden" @click="sidebarOpen = false" aria-label="Close menu">
            <x-heroicon-o-x-mark class="size-5" />
        </button>
    </div>

    <div class="mx-4 flex items-center gap-3 rounded-2xl bg-stone-50 p-3 dark:bg-stone-900">
        <x-avatar :user="$user" size="md" />
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold">{{ $user->name }}</p>
            <p class="truncate text-xs text-stone-500">{{ $user->member_no }} · {{ $user->role->label() }}</p>
        </div>
    </div>

    <nav class="mt-6 flex-1 space-y-1 overflow-y-auto px-3" aria-label="Main">
        @foreach (\App\Support\Navigation::for($user) as $item)
            <x-layouts.partials.nav-item :item="$item" />
        @endforeach
    </nav>

    <div class="space-y-1 border-t border-stone-200 p-3 dark:border-stone-800">
        <x-layouts.partials.nav-item :item="['label' => 'Profile', 'route' => 'profile.edit', 'icon' => 'heroicon-o-user-circle', 'active' => 'profile.*']" />
        <button type="button" @click="$store.theme.toggle()"
                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-stone-600 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800">
            <x-heroicon-o-moon class="size-5 dark:hidden" />
            <x-heroicon-o-sun class="hidden size-5 dark:block" />
            <span x-text="$store.theme.dark ? 'Light mode' : 'Dark mode'">Dark mode</span>
        </button>
        @if (Route::has('logout'))
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-stone-600 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800">
                    <x-heroicon-o-arrow-right-start-on-rectangle class="size-5" /> Sign out
                </button>
            </form>
        @endif
    </div>
</aside>
