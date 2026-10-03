@props(['items' => []])
<dl {{ $attributes->merge(['class' => 'grid gap-x-6 gap-y-4 sm:grid-cols-2']) }}>
    @foreach ($items as $label => $value)
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ $label }}</dt>
            <dd class="mt-1 text-sm">{{ filled($value) ? $value : '—' }}</dd>
        </div>
    @endforeach
</dl>
