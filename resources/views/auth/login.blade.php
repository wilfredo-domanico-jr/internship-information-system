<x-layouts.auth title="Sign in">
    <h2 class="font-display text-2xl font-semibold">Welcome back</h2>
    <p class="mt-1 text-sm text-stone-500">Sign in to your {{ config('wiis.name') }} account.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf
        <x-form.input name="email" label="Email address" type="email" required autofocus autocomplete="username" />
        <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />
        <div class="flex items-center justify-between">
            <x-form.checkbox name="remember" label="Remember me" />
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">Forgot password?</a>
            @endif
        </div>
        <x-button class="w-full">Sign in</x-button>
    </form>

    <div class="mt-8 space-y-2 text-sm text-stone-600 dark:text-stone-400">
        @if (Route::has('register.intern'))
            <p>Student? <a href="{{ route('register.intern') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Create an intern account</a></p>
        @endif
        @if (Route::has('register.company'))
            <p>Company? <a href="{{ route('register.company') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Register as a partner company</a></p>
        @endif
    </div>
</x-layouts.auth>
