<?php

use App\Actions\ApplyToPosting;
use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\ApplicationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->posting = InternshipPosting::factory()->create();
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->pdf = fn (string $n) => UploadedFile::fake()->create($n, 100, 'application/pdf');
});

it('creates a pending application with both files and tells the company', function () {
    $application = app(ApplyToPosting::class)($this->intern, $this->posting, ($this->pdf)('resume.pdf'), ($this->pdf)('endorsement.pdf'));

    expect($application->status)->toBe(ApplicationStatus::Pending)->and($application->intern_id)->toBe($this->intern->id)
        ->and($application->resume_path)->toStartWith("applications/{$this->intern->id}/")->toEndWith('-resume.pdf')
        ->and($application->endorsement_path)->toEndWith('-endorsement.pdf');
    Storage::disk('local')->assertExists($application->resume_path);
    Storage::disk('local')->assertExists($application->endorsement_path);
    Notification::assertSentTo($this->posting->company->user, ApplicationReceived::class, fn (ApplicationReceived $n) => str_contains($n->toArray($this->posting->company->user)['body'], 'Maria Santos'));
});

it('refuses closed or expired postings, placed interns and duplicates, storing nothing', function () {
    $closed = InternshipPosting::factory()->closed()->create();
    $expired = InternshipPosting::factory()->create(['closing_date' => now()->subDay()->toDateString()]);

    expect(ApplyToPosting::blocker($this->intern, $closed))->toContain('closed');
    expect(fn () => app(ApplyToPosting::class)($this->intern, $closed, ($this->pdf)('r.pdf'), ($this->pdf)('e.pdf')))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(ApplyToPosting::class)($this->intern, $expired, ($this->pdf)('r.pdf'), ($this->pdf)('e.pdf')))->toThrow(DomainRuleViolation::class);

    Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->declined()->create();
    expect(ApplyToPosting::blocker($this->intern, $this->posting))->toContain('already applied');
    expect(fn () => app(ApplyToPosting::class)($this->intern, $this->posting, ($this->pdf)('r.pdf'), ($this->pdf)('e.pdf')))->toThrow(DomainRuleViolation::class);

    $placed = User::factory()->intern()->create();
    Placement::factory()->for($placed, 'intern')->create();
    expect(ApplyToPosting::blocker($placed, $this->posting))->toContain('already placed');
    expect(fn () => app(ApplyToPosting::class)($placed, $this->posting, ($this->pdf)('r.pdf'), ($this->pdf)('e.pdf')))->toThrow(DomainRuleViolation::class);

    expect(Storage::disk('local')->allFiles())->toBe([])->and(Application::count())->toBe(1);
    expect(ApplyToPosting::blocker(User::factory()->intern()->create(), $this->posting))->toBeNull();
});
