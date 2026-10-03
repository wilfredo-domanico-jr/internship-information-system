@props(['title' => null])
<x-layouts.base :title="$title">
    <div class="min-h-full lg:grid lg:grid-cols-[1.1fr_1fr]">
        <aside class="relative hidden overflow-hidden bg-brand-800 text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="absolute -right-24 -top-24 size-96 rounded-full bg-brand-600/50 blur-3xl"></div>
            <div class="absolute -bottom-32 -left-20 size-[28rem] rounded-full bg-brand-400/20 blur-3xl"></div>
            <x-brand class="relative" variant="light" />
            <div class="relative max-w-md space-y-6">
                <h1 class="font-display text-4xl font-semibold leading-tight">{{ config('wiis.tagline') }}</h1>
                <p class="text-brand-100">One place for interns, advisers and partner companies to manage applications, hours and requirements.</p>
            </div>
            <p class="relative text-sm text-brand-200">{{ config('wiis.institution.name') }} · {{ config('wiis.institution.office') }}</p>
        </aside>
        <div class="flex min-h-full flex-col px-4 py-10 sm:px-8 lg:px-16">
            <div class="mb-8 lg:hidden"><x-brand /></div>
            <div class="mx-auto w-full max-w-md flex-1">
                <x-flash />
                {{ $slot }}
            </div>
            <p class="mt-10 text-center text-xs text-stone-500">&copy; {{ date('Y') }} {{ config('wiis.name') }} · {{ config('wiis.institution.name') }}</p>
        </div>
    </div>
</x-layouts.base>
