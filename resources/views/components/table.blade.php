<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <table class="data-table min-w-full divide-y divide-stone-200 text-sm dark:divide-stone-800">
        @isset($head)<thead><tr>{{ $head }}</tr></thead>@endisset
        <tbody class="divide-y divide-stone-100 dark:divide-stone-800">{{ $slot }}</tbody>
    </table>
</div>
