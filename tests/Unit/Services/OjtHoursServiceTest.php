<?php

use App\Enums\HoursTier;
use App\Services\OjtHoursService;

beforeEach(function () {
    config()->set('wiis.hours.required', 486);
    config()->set('wiis.hours.certificate_min', 250);
    $this->hours = new OjtHoursService;
});

it('reads thresholds from config', function () {
    expect($this->hours->required())->toBe(486)
        ->and($this->hours->certificateMinimum())->toBe(250);
});

it('computes completion, eligibility, progress and remaining hours', function () {
    expect($this->hours->isComplete(485))->toBeFalse()
        ->and($this->hours->isComplete(486))->toBeTrue()
        ->and($this->hours->isCertificateEligible(249))->toBeFalse()
        ->and($this->hours->isCertificateEligible(250))->toBeTrue()
        ->and($this->hours->progressPercent(243))->toBe(50)
        ->and($this->hours->progressPercent(0))->toBe(0)
        ->and($this->hours->progressPercent(900))->toBe(100)
        ->and($this->hours->remaining(400))->toBe(86)
        ->and($this->hours->remaining(600))->toBe(0);
});

it('maps hours to tiers', function () {
    expect($this->hours->tier(0))->toBe(HoursTier::Low)
        ->and($this->hours->tier(249))->toBe(HoursTier::Low)
        ->and($this->hours->tier(250))->toBe(HoursTier::Mid)
        ->and($this->hours->tier(485))->toBe(HoursTier::Mid)
        ->and($this->hours->tier(486))->toBe(HoursTier::Complete);
});

it('maps hours to dashboard buckets', function () {
    expect($this->hours->bucketLabel(0))->toBe('0–250')
        ->and($this->hours->bucketLabel(250))->toBe('0–250')
        ->and($this->hours->bucketLabel(251))->toBe('251–300')
        ->and($this->hours->bucketLabel(350))->toBe('301–400')
        ->and($this->hours->bucketLabel(999))->toBe('401+')
        ->and($this->hours->bucketLabels())->toBe(['0–250', '251–300', '301–400', '401+']);
});
