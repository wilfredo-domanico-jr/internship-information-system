<?php

it('exposes OJT thresholds and branding defaults', function () {
    expect(config('wiis.hours.required'))->toBe(486)
        ->and(config('wiis.hours.certificate_min'))->toBe(250)
        ->and(config('wiis.hours.buckets'))->toHaveCount(4)
        ->and(config('wiis.uploads.max_pdf_kb'))->toBe(5120)
        ->and(config('wiis.uploads.max_avatar_kb'))->toBe(1024)
        ->and(config('wiis.institution.name'))->toBeString()->not->toBeEmpty()
        ->and(config('wiis.support.email'))->toContain('@')
        ->and(config('wiis.demo_mode'))->toBeFalse();
});
