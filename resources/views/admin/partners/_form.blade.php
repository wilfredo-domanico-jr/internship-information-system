@props(['company' => null])
<div class="grid gap-5 sm:grid-cols-2">
    <x-form.input name="name" label="Company name" :value="$company?->name" required autofocus />
    <x-form.input name="type" label="Type / sector" :value="$company?->type" placeholder="Government" />
    <x-form.input name="website" label="Website" type="url" :value="$company?->website" placeholder="https://" />
    <x-form.input name="address" label="Address" :value="$company?->address" />
</div>
<x-form.textarea name="about" label="About" :value="$company?->about" rows="3" class="mt-5" hint="Shown to interns browsing partner companies." />
<div class="mt-5 flex items-end gap-4">
    @if ($company?->logo_path)
        <img src="{{ Storage::disk('public')->url($company->logo_path) }}" alt="" class="size-14 rounded-xl object-cover ring-1 ring-stone-200 dark:ring-stone-700">
    @endif
    <x-form.file name="logo" label="Logo" accept="image/png,image/jpeg,image/webp" :hint="'PNG, JPG or WebP up to '.(config('wiis.uploads.max_avatar_kb') / 1024).' MB.'" class="flex-1" />
</div>
