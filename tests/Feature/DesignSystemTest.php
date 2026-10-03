<?php

use App\Enums\ApplicationStatus;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

it('renders a status badge from an enum', function () {
    $html = Blade::render('<x-badge :status="$status" />', ['status' => ApplicationStatus::ForInterview]);

    expect($html)->toContain('For Interview')->toContain('sky');
});

it('renders the auth layout with branding and title', function () {
    $html = Blade::render('<x-layouts.auth title="Sign in"><p>body-marker</p></x-layouts.auth>');

    expect($html)->toContain('<title>Sign in · WIIS</title>')
        ->toContain(config('wiis.institution.name'))
        ->toContain('body-marker')
        ->toContain("classList.add('dark')");
});

it('clamps the progress ring to 100 percent', function () {
    $html = Blade::render('<x-progress-ring :percent="140" label="rendered" />');

    expect($html)->toContain('100%')->toContain('rendered');
});

it('renders form inputs with validation errors', function () {
    $bag = new ViewErrorBag;
    $bag->put('default', new MessageBag(['email' => ['Email is required.']]));
    view()->share('errors', $bag);

    $html = Blade::render('<x-form.input name="email" label="Email" required />');

    expect($html)->toContain('name="email"')->toContain('Email is required.')->toContain('input-error');
});

it('renders an empty state with an action slot', function () {
    $html = Blade::render('<x-empty-state title="Nothing here" description="Try later"><x-slot:action><a href="#">Go</a></x-slot:action></x-empty-state>');

    expect($html)->toContain('Nothing here')->toContain('Try later')->toContain('Go');
});
