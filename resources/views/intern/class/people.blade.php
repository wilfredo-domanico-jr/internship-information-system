<x-layouts.app :title="'People · '.$class->display_name">
    @include('intern.class.partials.header')

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Adviser">
            @if ($class->adviser)
                <div class="flex items-center gap-3">
                    <x-avatar :user="$class->adviser" size="lg" />
                    <div><p class="font-semibold">{{ $class->adviser->name }}</p><p class="text-sm text-stone-500">{{ $class->adviser->email }}</p></div>
                </div>
            @else
                <p class="text-sm text-stone-500">No adviser has claimed this class yet.</p>
            @endif
        </x-card>

        <x-card title="Classmates" :subtitle="$profiles->count().' enrolled'" class="lg:col-span-2" :padding="false">
            <x-table>
                <x-slot:head><th>Name</th><th>Student no.</th></x-slot:head>
                @foreach ($profiles as $profile)
                    <tr>
                        <td><div class="flex items-center gap-3"><x-avatar :user="$profile->user" size="sm" /><span class="font-medium">{{ $profile->user->name }}</span></div></td>
                        <td class="font-mono text-xs">{{ $profile->student_number }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    </div>
</x-layouts.app>
