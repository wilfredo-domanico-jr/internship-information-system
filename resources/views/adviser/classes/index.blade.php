<x-layouts.app title="My classes">
    <x-page-header title="My classes" subtitle="Classes you advise this school year.">
        <x-slot:actions>
            <x-button :href="route('adviser.classes.create')" icon="heroicon-o-plus">New class</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($classes->isEmpty())
                <x-card>
                    <x-empty-state title="You have no classes yet" description="Create a class, or claim one the office imported by entering its join code." icon="heroicon-o-rectangle-group">
                        <x-slot:action><x-button :href="route('adviser.classes.create')" icon="heroicon-o-plus">New class</x-button></x-slot:action>
                    </x-empty-state>
                </x-card>
            @else
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($classes as $class)
                        <x-card class="flex flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-display text-lg font-semibold">
                                        @if (Route::has('adviser.classes.show'))<a href="{{ route('adviser.classes.show', $class) }}" class="hover:underline">{{ $class->display_name }}</a>@else{{ $class->display_name }}@endif
                                    </p>
                                    <p class="text-sm text-stone-500">{{ $class->subject }} · {{ $class->school_year }}</p>
                                    <p class="mt-1 text-sm text-stone-500">{{ $class->schedule_label }}</p>
                                </div>
                                <x-badge :status="$class->status" />
                            </div>
                            <dl class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
                                <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-900"><dt class="text-xs text-stone-500">Interns</dt><dd class="font-semibold tabular-nums">{{ $class->intern_profiles_count }}</dd></div>
                                <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-900"><dt class="text-xs text-stone-500">Folders</dt><dd class="font-semibold tabular-nums">{{ $class->folders_count }}</dd></div>
                                <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-900"><dt class="text-xs text-stone-500">Posts</dt><dd class="font-semibold tabular-nums">{{ $class->announcements_count }}</dd></div>
                            </dl>
                            <p class="mt-4 text-xs text-stone-500">{{ $class->intern_profiles_count }} interns · Join code <span class="font-mono font-semibold text-stone-800 dark:text-stone-100">{{ $class->join_code }}</span></p>
                            <div class="mt-4 flex items-center gap-2">
                                <x-button variant="secondary" :href="route('adviser.classes.edit', $class)" icon="heroicon-o-pencil-square">Edit</x-button>
                                <x-confirm-form :action="route('adviser.classes.leave', $class)" :confirm="'Leave '.$class->display_name.'? Interns stay enrolled and another adviser can claim the class with its join code.'">
                                    <x-button variant="ghost" icon="heroicon-o-arrow-right-start-on-rectangle">Leave</x-button>
                                </x-confirm-form>
                            </div>
                        </x-card>
                    @endforeach
                </div>
            @endif

            @if ($past->isNotEmpty())
                <x-card title="Past classes" subtitle="Classes you advised before. Enter the join code again to claim one back." :padding="false">
                    <x-table>
                        <x-slot:head><th>Class</th><th>Subject</th><th>Advised</th></x-slot:head>
                        @foreach ($past as $log)
                            <tr>
                                <td class="font-medium">{{ $log->classSection->display_name }}</td>
                                <td>{{ $log->classSection->subject }} · {{ $log->classSection->school_year }}</td>
                                <td class="text-stone-500">{{ $log->joined_at->format('M j, Y') }} – {{ $log->left_at->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                </x-card>
            @endif
        </div>

        <x-card title="Claim a class" subtitle="Imported classes have no adviser until someone enters their join code.">
            <form method="POST" action="{{ route('adviser.classes.join') }}" class="space-y-4">
                @csrf
                <x-form.input name="join_code" label="Join code" placeholder="SBIT4C26" required class="font-mono uppercase" />
                <x-button class="w-full" icon="heroicon-o-key">Claim class</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.app>
