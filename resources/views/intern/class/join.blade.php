<x-layouts.app title="My class">
    <x-page-header title="My class" subtitle="You are not enrolled in a class yet." />

    <x-card title="Join a class" subtitle="Enter the join code your adviser gave you." class="max-w-lg">
        <form method="POST" action="{{ route('intern.class.join') }}" class="space-y-4">
            @csrf
            <x-form.input name="join_code" label="Join code" placeholder="SBIT4C26" required class="font-mono uppercase" />
            <x-button class="w-full" icon="heroicon-o-key">Join class</x-button>
        </form>
    </x-card>
</x-layouts.app>
