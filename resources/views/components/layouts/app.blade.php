@props(['title' => null])
<x-layouts.base :title="$title">
    <div x-data="{ sidebarOpen: false }" class="min-h-full lg:flex">
        <x-layouts.partials.sidebar />
        <div class="flex min-w-0 flex-1 flex-col">
            <x-layouts.partials.topbar :title="$title" />
            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl space-y-6">
                    <x-flash />
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</x-layouts.base>
