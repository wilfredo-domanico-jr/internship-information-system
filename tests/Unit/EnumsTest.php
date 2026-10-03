<?php

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ClassStatus;
use App\Enums\CosApplicationStatus;
use App\Enums\DocumentRequestStatus;
use App\Enums\DtrStatus;
use App\Enums\HoursTier;
use App\Enums\PostingStatus;
use App\Enums\Role;
use App\Enums\SubmissionStatus;

it('labels roles and maps them to dashboards and member prefixes', function () {
    expect(Role::Intern->label())->toBe('Intern')
        ->and(Role::from('adviser')->dashboardRoute())->toBe('adviser.dashboard')
        ->and(Role::Company->memberPrefix())->toBe('CMP')
        ->and(Role::Admin->memberPrefix())->toBe('ADM')
        ->and(Role::options())->toBe([
            'admin' => 'Admin', 'adviser' => 'Adviser', 'company' => 'Company', 'intern' => 'Intern',
        ]);
});

it('turns snake_case values into human labels', function () {
    expect(ApplicationStatus::ForInterview->label())->toBe('For Interview')
        ->and(ApplicationStatus::ForInterview->value)->toBe('for_interview')
        ->and(ApplicationStatus::ForInterview->isOpen())->toBeTrue()
        ->and(ApplicationStatus::Declined->isOpen())->toBeFalse();
});

it('gives every status a known badge color', function (string $enum) {
    $allowed = ['gray', 'amber', 'green', 'rose', 'sky', 'teal'];
    foreach ($enum::cases() as $case) {
        expect($case->badgeColor())->toBeIn($allowed);
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
})->with([
    AccountStatus::class, ApprovalStatus::class, ApplicationStatus::class, DtrStatus::class,
    SubmissionStatus::class, CosApplicationStatus::class, DocumentRequestStatus::class,
    PostingStatus::class, ClassStatus::class, HoursTier::class,
]);

it('colors hours tiers red, amber, green', function () {
    expect(HoursTier::Low->badgeColor())->toBe('rose')
        ->and(HoursTier::Mid->badgeColor())->toBe('amber')
        ->and(HoursTier::Complete->badgeColor())->toBe('green');
});
