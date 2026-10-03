<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

it('renders a chart canvas with its config', function () {
    $html = Blade::render('<x-chart type="doughnut" :labels="[\'Placed\', \'Unplaced\']" :datasets="[[\'data\' => [3, 5]]]" />');

    expect($html)->toContain('<canvas')->toContain('x-data="chart"')->toContain('&quot;labels&quot;:[&quot;Placed&quot;,&quot;Unplaced&quot;]');
});

it('renders a search form that keeps the current query', function () {
    request()->merge(['q' => 'juan']);
    $html = Blade::render('<x-search-form action="/x" placeholder="Find"><select name="status"></select></x-search-form>');

    expect($html)->toContain('method="GET"')->toContain('value="juan"')->toContain('name="status"')->toContain('Reset');
});

it('renders tabs with counts and marks the active one', function () {
    Route::get('/_t/a', fn () => '')->name('t.a');
    Route::get('/_t/b', fn () => '')->name('t.b');
    Route::getRoutes()->refreshNameLookups();
    $html = Blade::render('<x-tabs :tabs="[[\'label\' => \'All\', \'route\' => \'t.a\', \'active\' => \'t.a\'], [\'label\' => \'Pending\', \'route\' => \'t.b\', \'active\' => \'t.b\', \'count\' => 4]]" />');

    expect($html)->toContain('All')->toContain('Pending')->toContain('>4<');
});

it('renders a detail list and a styled paginator', function () {
    User::factory()->intern()->count(3)->create();
    $html = Blade::render('<x-detail-list :items="[\'Email\' => \'a@b.c\', \'Phone\' => null]" />');
    expect($html)->toContain('Email')->toContain('a@b.c')->toContain('—');

    $paginator = User::query()->paginate(2);
    $html = Blade::render('<x-pagination :paginator="$p" />', ['p' => $paginator]);
    expect($html)->toContain('Next')->toContain('page=2');
});
