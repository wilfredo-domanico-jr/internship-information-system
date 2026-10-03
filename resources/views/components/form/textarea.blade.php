@props(['name', 'label' => null, 'rows' => 4, 'value' => null, 'required' => false, 'hint' => null])
<div class="{{ $attributes->get('class') }}">
    @if ($label)<x-form.label :for="$name" :required="$required">{{ $label }}</x-form.label>@endif
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
              {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($name) ? ' input-error' : '')]) }}>{{ old($name, $value) }}</textarea>
    @if ($hint)<p class="mt-1.5 text-xs text-stone-500">{{ $hint }}</p>@endif
    <x-form.error :name="$name" />
</div>
