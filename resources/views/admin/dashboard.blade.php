<x-layouts.app title="Dashboard">
    <x-page-header :title="'Welcome back, '.auth()->user()->first_name" :subtitle="'An overview of '.config('wiis.institution.short').' internship activity.'" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Active interns" :value="$stats['interns']" icon="heroicon-o-academic-cap" />
        <x-stat-card label="Advisers" :value="$stats['advisers']" icon="heroicon-o-user-group" color="sky" />
        <x-stat-card label="Verified companies" :value="$stats['companies']" icon="heroicon-o-building-office-2" color="green" />
        <x-stat-card label="Awaiting approval" :value="$stats['pending_companies']" icon="heroicon-o-clock" color="amber" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card title="Placement" subtitle="Active interns with and without a company">
            <x-chart type="doughnut" :labels="['Placed', 'Unplaced']" :datasets="[['data' => [$placement['placed'], $placement['unplaced']]]]" />
        </x-card>
        <x-card title="Class enrollment" subtitle="Active interns in a class section">
            <x-chart type="doughnut" :labels="['In a class', 'No class yet']" :datasets="[['data' => [$sectioning['with_class'], $sectioning['without_class']]]]" />
        </x-card>
        <x-card title="Account status" subtitle="Active vs disabled per role">
            <x-chart type="bar" :labels="$statusLabels" :datasets="[['label' => 'Active', 'data' => $statusActive], ['label' => 'Disabled', 'data' => $statusDisabled]]" :options="['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]]" />
        </x-card>
    </div>

    <x-card title="Recent accounts" subtitle="Newest registrations across all roles" :padding="false">
        <x-table>
            <x-slot:head><th>Name</th><th>Role</th><th>Member no.</th><th>Joined</th></x-slot:head>
            @forelse ($recentUsers as $user)
                <tr>
                    <td><div class="flex items-center gap-3"><x-avatar :user="$user" size="sm" /><span class="font-medium">{{ $user->name }}</span></div></td>
                    <td><x-badge :status="$user->role" /></td>
                    <td class="font-mono text-xs">{{ $user->member_no }}</td>
                    <td class="text-stone-500">{{ $user->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty-state title="No accounts yet" /></td></tr>
            @endforelse
        </x-table>
    </x-card>
</x-layouts.app>
