<x-layouts.app title="My internship">
    <x-page-header title="My internship" subtitle="Your placement, hours and the people you work with." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="OJT progress">
            <div class="flex flex-col items-center gap-4 text-center">
                <x-progress-ring :percent="$progress['percent']" :color="$progress['tier']->badgeColor()" label="of required hours" :size="160" />
                <div>
                    <p class="font-display text-3xl font-semibold tabular-nums">{{ $progress['hours'] }} <span class="text-base font-normal text-stone-500">/ {{ $progress['required'] }} h</span></p>
                    <p class="mt-1 text-sm text-stone-500">{{ $progress['remaining'] }} hours remaining</p>
                </div>
                <x-badge :status="$progress['tier']" />
            </div>
        </x-card>

        @if ($placement)
            <x-card :title="$placement->company->name" subtitle="Current placement" class="lg:col-span-2">
                <x-slot:actions><x-badge color="green">Active</x-badge></x-slot:actions>
                <x-detail-list :items="[
                    'Since' => $placement->started_at->format('M j, Y'),
                    'Department' => $placement->department?->name ?? 'Not assigned yet',
                    'Hours rendered here' => $placement->hours_rendered.' h',
                    'Absences' => $placement->absences,
                    'Address' => $placement->company->address,
                    'Website' => $placement->company->website,
                    'Contact' => $placement->company->user?->name,
                    'Contact email' => $placement->company->user?->email,
                ]" />
            </x-card>

            <x-card title="Co-interns" :subtitle="$coInterns->count().' other interns at '.$placement->company->name" class="lg:col-span-2" :padding="false">
                @forelse ($coInterns as $other)
                    <div class="flex items-center gap-3 border-b border-stone-100 px-5 py-3 last:border-0 dark:border-stone-800">
                        <x-avatar :user="$other->intern" size="sm" />
                        <div><p class="font-medium">{{ $other->intern->name }}</p><p class="text-xs text-stone-500">{{ $other->department?->name ?? 'No department' }} · since {{ $other->started_at->format('M j') }}</p></div>
                    </div>
                @empty
                    <x-empty-state title="You are the only intern here" icon="heroicon-o-users" class="py-8" />
                @endforelse
            </x-card>

            <x-card title="Leave this company">
                <p class="text-sm text-stone-600 dark:text-stone-300">{{ $warning }}</p>
                <x-confirm-form :action="route('intern.internship.leave')" :confirm="'Leave '.$placement->company->name.'? '.$warning" class="mt-4">
                    <x-button variant="danger" class="w-full" icon="heroicon-o-arrow-right-start-on-rectangle">Leave company</x-button>
                </x-confirm-form>
            </x-card>
        @else
            <x-card title="Join a company" subtitle="Once a company accepts your application, enter its company code here." class="lg:col-span-2">
                <form method="POST" action="{{ route('intern.internship.join') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    @csrf
                    <x-form.input name="company_code" label="Company code" placeholder="TECHNOVA" required class="flex-1 font-mono uppercase" />
                    <x-button icon="heroicon-o-key">Join company</x-button>
                </form>
                @if ($acceptedCompanies->isNotEmpty())
                    <div class="mt-6">
                        <p class="text-sm font-medium">Companies that accepted you</p>
                        <ul class="mt-2 space-y-2">
                            @foreach ($acceptedCompanies as $company)
                                <li class="flex items-center justify-between rounded-xl bg-stone-50 px-4 py-3 text-sm dark:bg-stone-900">
                                    <span class="font-medium">{{ $company->name }}</span>
                                    <span class="text-stone-500">Ask them for their company code if you did not get it in the acceptance notice.</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <p class="mt-6 text-sm text-stone-500">No company has accepted you yet. <a href="{{ route('intern.applications.index') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Check your applications</a>.</p>
                @endif
            </x-card>
        @endif
    </div>
</x-layouts.app>
