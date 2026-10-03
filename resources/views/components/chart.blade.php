@props(['type' => 'doughnut', 'labels' => [], 'datasets' => [], 'height' => 220, 'options' => []])
@php
    $palette = ['#187a68', '#f59e0b', '#38bdf8', '#f43f5e', '#8b5cf6', '#84cc16'];
    $datasets = collect($datasets)->values()->map(function (array $set, int $i) use ($palette, $type) {
        $set['backgroundColor'] = $set['backgroundColor'] ?? ($type === 'bar' ? $palette[$i % count($palette)] : array_slice($palette, 0, max(1, count($set['data'] ?? []))));
        $set['borderWidth'] = $set['borderWidth'] ?? 0;
        $set['borderRadius'] = $set['borderRadius'] ?? ($type === 'bar' ? 6 : 0);
        return $set;
    })->all();
    $config = ['type' => $type, 'data' => ['labels' => array_values($labels), 'datasets' => $datasets], 'options' => (object) $options];
@endphp
<div {{ $attributes->merge(['class' => 'relative w-full']) }} style="height: {{ (int) $height }}px">
    <canvas x-data="chart" data-chart="{{ json_encode($config) }}" role="img" aria-label="{{ $type }} chart"></canvas>
</div>
