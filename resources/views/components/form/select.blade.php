@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false])
<div class="{{ $attributes->get('class') }}">
    @if ($label)<x-form.label :for="$name" :required="$required">{{ $label }}</x-form.label>@endif
    <select id="{{ $name }}" name="{{ $name }}" @required($required) {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($name) ? ' input-error' : '')]) }}>
        @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    <x-form.error :name="$name" />
</div>
