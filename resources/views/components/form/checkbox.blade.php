@props(['name', 'label', 'checked' => false])
<div class="{{ $attributes->get('class') }}">
    <label class="flex items-start gap-3 text-sm">
        <input type="checkbox" name="{{ $name }}" value="1" @checked(session()->hasOldInput() ? old($name) : $checked) {{ $attributes->except('class') }} class="mt-0.5 size-4 rounded border-stone-300 text-brand-600 focus:ring-brand-500">
        <span>{{ $label }}</span>
    </label>
    <x-form.error :name="$name" />
</div>
