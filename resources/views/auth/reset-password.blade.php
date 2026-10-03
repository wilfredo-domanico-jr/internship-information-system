<x-layouts.auth title="Choose a new password">
    <h2 class="font-display text-2xl font-semibold">Choose a new password</h2>

    <form method="POST" action="{{ route('password.store') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.input name="email" label="Email address" type="email" :value="$email" required autocomplete="email" />
        <x-form.input name="password" label="New password" type="password" required autofocus autocomplete="new-password" />
        <x-form.input name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
        <x-button class="w-full">Reset password</x-button>
    </form>
</x-layouts.auth>
