@props(['name', 'label' => null, 'accept' => null, 'hint' => null, 'required' => false])
<div class="{{ $attributes->get('class') }}">
    @if ($label)<x-form.label :for="$name" :required="$required">{{ $label }}</x-form.label>@endif
    <input id="{{ $name }}" name="{{ $name }}" type="file" @if ($accept) accept="{{ $accept }}" @endif @required($required)
           class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-xl file:border-0 file:bg-brand-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:text-stone-300 dark:file:bg-brand-900/40 dark:file:text-brand-200 {{ $errors->has($name) ? 'rounded-xl ring-1 ring-rose-400' : '' }}">
    @if ($hint)<p class="mt-1.5 text-xs text-stone-500">{{ $hint }}</p>@endif
    <x-form.error :name="$name" />
</div>
