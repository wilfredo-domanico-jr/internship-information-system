<x-layouts.auth title="Create intern account">
    <h2 class="font-display text-2xl font-semibold">Create your intern account</h2>
    <p class="mt-1 text-sm text-stone-500">Use the class join code from your practicum adviser.</p>

    <form method="POST" action="{{ route('register.intern.store') }}" class="mt-8 space-y-5">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="first_name" label="First name" required autofocus />
            <x-form.input name="last_name" label="Last name" required />
        </div>
        <x-form.input name="middle_name" label="Middle name" hint="Optional" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="student_number" label="Student number" placeholder="19-0842" required />
            <x-form.input name="join_code" label="Class join code" placeholder="SBIT4C26" class="font-mono uppercase" required />
        </div>
        <x-form.input name="email" label="Email address" type="email" required autocomplete="email" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="password" label="Password" type="password" required autocomplete="new-password" />
            <x-form.input name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
        </div>
        <x-form.checkbox name="terms" label="I agree to the terms and conditions of the internship program." />
        <x-button class="w-full">Create account</x-button>
    </form>

    <p class="mt-8 text-sm text-stone-600 dark:text-stone-400">Already registered? <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Sign in</a></p>
</x-layouts.auth>
