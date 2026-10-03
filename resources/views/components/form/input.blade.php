@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])
<div class="{{ $attributes->get('class') }}">
    @if ($label)<x-form.label :for="$name" :required="$required">{{ $label }}</x-form.label>@endif
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password' && $type !== 'file') value="{{ old($name, $value) }}" @endif
           @required($required)
           {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($name) ? ' input-error' : '')]) }}>
    @if ($hint)<p class="mt-1.5 text-xs text-stone-500">{{ $hint }}</p>@endif
    <x-form.error :name="$name" />
</div>
