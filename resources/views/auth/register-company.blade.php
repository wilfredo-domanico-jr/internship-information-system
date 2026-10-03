<x-layouts.auth title="Register your company">
    <h2 class="font-display text-2xl font-semibold">Partner with {{ config('wiis.institution.short') }}</h2>
    <p class="mt-1 text-sm text-stone-500">Register your company to post internships. Upload your business permit and MOA for verification.</p>

    <form method="POST" action="{{ route('register.company.store') }}" enctype="multipart/form-data" class="mt-8 space-y-6">
        @csrf
        <fieldset class="space-y-5">
            <legend class="text-xs font-semibold uppercase tracking-wider text-stone-500">Company</legend>
            <x-form.input name="company_name" label="Company name" required autofocus />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="company_type" label="Industry / type" placeholder="IT Services" required />
                <x-form.input name="website" label="Website" type="url" placeholder="https://" />
            </div>
            <x-form.input name="address" label="Address" required />
            <x-form.textarea name="about" label="About the company" rows="3" hint="Optional. Shown to interns browsing your postings." />
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="text-xs font-semibold uppercase tracking-wider text-stone-500">Contact person</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="first_name" label="First name" required />
                <x-form.input name="last_name" label="Last name" required />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="email" label="Work email" type="email" required autocomplete="email" />
                <x-form.input name="phone" label="Phone" type="tel" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="password" label="Password" type="password" required autocomplete="new-password" />
                <x-form.input name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
            </div>
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="text-xs font-semibold uppercase tracking-wider text-stone-500">Verification documents</legend>
            <x-form.file name="permit" label="Business permit (PDF)" accept="application/pdf" hint="PDF up to {{ config('wiis.uploads.max_pdf_kb') / 1024 }} MB." required />
            <x-form.file name="moa" label="Memorandum of Agreement (PDF)" accept="application/pdf" hint="PDF up to {{ config('wiis.uploads.max_pdf_kb') / 1024 }} MB." required />
        </fieldset>

        <x-form.checkbox name="terms" label="I agree to the terms and conditions of the internship program." />
        <x-button class="w-full">Submit for verification</x-button>
    </form>

    <p class="mt-8 text-sm text-stone-600 dark:text-stone-400">Already registered? <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Sign in</a></p>
</x-layouts.auth>
