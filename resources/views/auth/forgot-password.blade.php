<x-layouts.auth title="Forgot password">
    <h2 class="font-display text-2xl font-semibold">Reset your password</h2>
    <p class="mt-1 text-sm text-stone-500">Enter your email and we will send you a link to choose a new password.</p>

    @if (session('status'))
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <x-form.input name="email" label="Email address" type="email" required autofocus autocomplete="email" />
        <x-button class="w-full">Email reset link</x-button>
    </form>

    <p class="mt-8 text-sm text-stone-600 dark:text-stone-400"><a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Back to sign in</a></p>
</x-layouts.auth>
