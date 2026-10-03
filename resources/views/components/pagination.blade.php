@props(['paginator'])
@if ($paginator->hasPages())
    <div {{ $attributes->merge(['class' => 'pt-2']) }}>{{ $paginator->withQueryString()->links('vendor.pagination.wiis') }}</div>
@endif
