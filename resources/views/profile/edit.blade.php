<x-layouts.app title="Profile">
    <x-page-header title="My profile" subtitle="Keep your contact details current. Your email and member number are managed by the system." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-card title="Profile photo">
                <div class="flex flex-col items-center gap-4 text-center">
                    <x-avatar :user="$user" size="xl" />
                    <div>
                        <p class="font-semibold">{{ $user->full_name }}</p>
                        <p class="text-sm text-stone-500">{{ $user->member_no }} · {{ $user->role->label() }}</p>
                    </div>
                    <form method="POST" action="{{ route('profile.avatar.store') }}" enctype="multipart/form-data" class="w-full space-y-3">
                        @csrf
                        <x-form.file name="avatar" accept="image/png,image/jpeg,image/webp" hint="PNG, JPG or WebP up to {{ config('wiis.uploads.max_avatar_kb') / 1024 }} MB." />
                        <x-button variant="secondary" class="w-full">Upload photo</x-button>
                    </form>
                    @if ($user->avatar_path)
                        <form method="POST" action="{{ route('profile.avatar.destroy') }}">
                            @csrf @method('DELETE')
                            <button class="text-sm font-medium text-rose-600 hover:underline">Remove photo</button>
                        </form>
                    @endif
                </div>
            </x-card>

            <x-card title="Account">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-stone-500">Email</dt><dd class="font-medium">{{ $user->email }}</dd></div>
                    <div><dt class="text-stone-500">Member number</dt><dd class="font-mono">{{ $user->member_no }}</dd></div>
                    <div><dt class="text-stone-500">Status</dt><dd><x-badge :status="$user->status" /></dd></div>
                    @if ($user->isIntern() && $user->internProfile)
                        <div><dt class="text-stone-500">Student number</dt><dd class="font-mono">{{ $user->internProfile->student_number }}</dd></div>
                        <div><dt class="text-stone-500">Class</dt><dd>{{ $user->internProfile->classSection?->display_name ?? 'Not enrolled' }}</dd></div>
                    @endif
                </dl>
            </x-card>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf @method('PUT')
                <x-card title="Personal details">
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-form.input name="first_name" label="First name" :value="$user->first_name" required />
                        <x-form.input name="middle_name" label="Middle name" :value="$user->middle_name" />
                        <x-form.input name="last_name" label="Last name" :value="$user->last_name" required />
                    </div>
                    <x-form.input name="phone" label="Phone" type="tel" :value="$user->phone" class="mt-5 sm:max-w-xs" />

                    @if ($user->isIntern() && $user->internProfile)
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <x-form.select name="gender" label="Gender" :options="['Male' => 'Male', 'Female' => 'Female', 'Prefer not to say' => 'Prefer not to say']" :value="$user->internProfile->gender" placeholder="Select" />
                            <x-form.input name="birthdate" label="Birthdate" type="date" :value="$user->internProfile->birthdate?->toDateString()" />
                            <x-form.input name="present_address" label="Present address" :value="$user->internProfile->present_address" />
                            <x-form.input name="permanent_address" label="Permanent address" :value="$user->internProfile->permanent_address" />
                        </div>
                        <x-form.textarea name="about" label="About me" :value="$user->internProfile->about" rows="4" class="mt-5" hint="Shown to companies reviewing your applications." />
                    @endif

                    @if ($user->isCompany() && $user->company)
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <x-form.input name="company_name" label="Company name" :value="$user->company->name" required />
                            <x-form.input name="company_type" label="Industry / type" :value="$user->company->type" required />
                            <x-form.input name="website" label="Website" type="url" :value="$user->company->website" />
                            <x-form.input name="address" label="Address" :value="$user->company->address" required />
                        </div>
                        <x-form.textarea name="about" label="About the company" :value="$user->company->about" rows="4" class="mt-5" />
                    @endif

                    <div class="mt-6 flex justify-end"><x-button>Save changes</x-button></div>
                </x-card>
            </form>

            <form method="POST" action="{{ route('password.update') }}">
                @csrf @method('PUT')
                <x-card title="Change password">
                    @if ($errors->updatePassword->any())
                        <ul class="mb-4 list-inside list-disc rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
                            @foreach ($errors->updatePassword->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    @endif
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-form.input name="current_password" label="Current password" type="password" autocomplete="current-password" required />
                        <x-form.input name="password" label="New password" type="password" autocomplete="new-password" required />
                        <x-form.input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
                    </div>
                    <div class="mt-6 flex justify-end"><x-button variant="secondary">Update password</x-button></div>
                </x-card>
            </form>
        </div>
    </div>
</x-layouts.app>
