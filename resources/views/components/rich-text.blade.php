@props(['html'])
<div {{ $attributes->merge(['class' => 'rich-text']) }}>{!! app(\App\Services\HtmlSanitizer::class)->clean($html) !!}</div>
