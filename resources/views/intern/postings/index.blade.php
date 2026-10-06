<x-layouts.app title="Internships">
    <x-page-header title="Internship postings" subtitle="Open positions from approved partner companies." />

    @if ($placed)
        <div class="flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
            <x-heroicon-o-information-circle class="mt-0.5 size-4 shrink-0" />
            <p>You are currently placed with a company, so you cannot apply to new postings. You can still browse.</p>
        </div>
    @endif

    <x-search-form :action="route('intern.postings.index')" placeholder="Search by title, company or city…">
        <select name="city" class="input sm:max-w-xs" aria-label="City">
            <option value="">All cities</option>
            @foreach ($cities as $city)<option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>@endforeach
        </select>
    </x-search-form>

    @if ($postings->isEmpty())
        <x-card><x-empty-state title="No open postings" description="Check back soon, or ask your adviser about partner companies." icon="heroicon-o-briefcase" /></x-card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($postings as $posting)
                <x-card class="flex flex-col">
                    <div class="flex items-start gap-3">
                        @if ($posting->company->logo_path)
                            <img src="{{ Storage::disk('public')->url($posting->company->logo_path) }}" alt="" class="size-11 rounded-xl object-cover">
                        @else
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10"><x-heroicon-o-building-office-2 class="size-5" /></span>
                        @endif
                        <div class="min-w-0">
                            <a href="{{ route('intern.postings.show', $posting) }}" class="font-display text-lg font-semibold hover:underline">{{ $posting->title }}</a>
                            <p class="text-sm text-stone-500">{{ $posting->company->name }} · {{ $posting->city }}</p>
                        </div>
                    </div>
                    <p class="mt-3 line-clamp-3 text-sm text-stone-600 dark:text-stone-300">{{ $posting->description }}</p>
                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-stone-500">
                        <x-badge color="teal">{{ $posting->vacancies }} {{ Str::plural('slot', $posting->vacancies) }}</x-badge>
                        @if ($posting->required_hours)<x-badge color="gray">{{ $posting->required_hours }} h</x-badge>@endif
                        <span class="ml-auto">{{ $posting->closing_date ? 'Closes '.$posting->closing_date->format('M j') : 'Open until filled' }}</span>
                    </div>
                    <x-button variant="secondary" :href="route('intern.postings.show', $posting)" class="mt-4 w-full">View and apply</x-button>
                </x-card>
            @endforeach
        </div>
        <x-pagination :paginator="$postings" />
    @endif
</x-layouts.app>
