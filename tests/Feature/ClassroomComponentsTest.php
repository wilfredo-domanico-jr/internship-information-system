<?php

use Illuminate\Support\Facades\Blade;

it('renders a Trix editor bound to a hidden input', function () {
    $html = Blade::render('<x-form.editor name="body" label="Announcement" value="<p>Hi</p>" />');

    expect($html)->toContain('<trix-editor')->toContain('input="body"')->toContain('type="hidden"')->toContain('name="body"')
        ->toContain('value="&lt;p&gt;Hi&lt;/p&gt;"')->toContain('Announcement');
});

it('renders rich text after sanitizing it', function () {
    $html = Blade::render('<x-rich-text :html="$body" />', ['body' => '<p>Hi</p><script>alert(1)</script>']);

    expect($html)->toContain('class="rich-text"')->toContain('<p>Hi</p>')->not->toContain('<script');
});

it('renders the print layout with a print button', function () {
    $html = Blade::render('<x-layouts.print title="Roster" back="/x">Body</x-layouts.print>');

    expect($html)->toContain('window.print()')->toContain('Body')->toContain('href="/x"')->toContain('Roster');
});

it('marks a nav item active for any of several route patterns', function () {
    $html = Blade::render('<x-layouts.partials.nav-item :item="$item" />', [
        'item' => ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'heroicon-o-bell', 'active' => ['nope.*', 'notifications.*']],
    ]);

    expect($html)->toContain('Notifications');
});
