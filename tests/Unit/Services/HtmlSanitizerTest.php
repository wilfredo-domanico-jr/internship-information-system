<?php

use App\Services\HtmlSanitizer;

it('keeps the formatting Trix produces', function () {
    $html = '<div><strong>Reminder</strong> — upload by <em>Friday</em>.<br>See <a href="https://example.com/guide">the guide</a>.</div><ul><li>One</li></ul>';

    $clean = app(HtmlSanitizer::class)->clean($html);

    expect($clean)->toContain('<strong>Reminder</strong>')->toContain('<em>Friday</em>')->toContain('<br')
        ->toContain('href="https://example.com/guide"')->toContain('<li>One</li>');
});

it('strips scripts, event handlers, images and javascript links', function () {
    $html = '<p onclick="steal()">Hi<script>alert(1)</script></p><a href="javascript:alert(1)">x</a><img src=x onerror=alert(1)>';

    $clean = app(HtmlSanitizer::class)->clean($html);

    expect($clean)->not->toContain('<script')->not->toContain('onclick')->not->toContain('javascript:')->not->toContain('<img')
        ->toContain('<p>Hi</p>');
});

it('opens links in a new tab without a referrer', function () {
    $clean = app(HtmlSanitizer::class)->clean('<a href="https://example.com">x</a>');

    expect($clean)->toContain('target="_blank"')->toContain('noopener')->toContain('noreferrer')->toContain('nofollow');
});

it('detects bodies that are only whitespace or empty tags', function () {
    $sanitizer = app(HtmlSanitizer::class);

    expect($sanitizer->isBlank('<div><br></div>'))->toBeTrue()
        ->and($sanitizer->isBlank('<p>&nbsp;</p>'))->toBeTrue()
        ->and($sanitizer->isBlank(null))->toBeTrue()
        ->and($sanitizer->isBlank('<p>Hello</p>'))->toBeFalse();
});

it('is registered as a singleton', function () {
    expect(app(HtmlSanitizer::class))->toBe(app(HtmlSanitizer::class));
});
