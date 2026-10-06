@props(['name', 'label' => null, 'value' => null, 'required' => false, 'hint' => null, 'placeholder' => 'Write something…'])
<div class="{{ $attributes->get('class') }}">
    @if ($label)<x-form.label :for="$name.'-editor'" :required="$required">{{ $label }}</x-form.label>@endif
    <input id="{{ $name }}" name="{{ $name }}" type="hidden" value="{{ old($name, $value) }}">
    <trix-editor id="{{ $name }}-editor" input="{{ $name }}" placeholder="{{ $placeholder }}"
                 {{ $attributes->except('class')->merge(['class' => 'input trix-content min-h-40'.(isset($errors) && $errors->has($name) ? ' input-error' : '')]) }}></trix-editor>
    @if ($hint)<p class="mt-1.5 text-xs text-stone-500">{{ $hint }}</p>@endif
    @isset($errors)<x-form.error :name="$name" />@endisset
</div>
