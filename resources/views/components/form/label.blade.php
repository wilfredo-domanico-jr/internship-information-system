@props(['for', 'required' => false])
<label for="{{ $for }}" {{ $attributes->merge(['class' => 'label']) }}>{{ $slot }}@if ($required) <span class="text-rose-500">*</span>@endif</label>
