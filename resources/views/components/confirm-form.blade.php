@props(['action', 'method' => 'POST', 'confirm' => 'Are you sure?'])
<form method="POST" action="{{ $action }}" x-data @submit.prevent="if (window.confirm(@js($confirm))) $el.submit()" {{ $attributes }}>
    @csrf
    @if (! in_array(strtoupper($method), ['GET', 'POST'])) @method($method) @endif
    {{ $slot }}
</form>
