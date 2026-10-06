<?php

use App\Actions\DeleteDtr;
use App\Actions\SubmitDtr;
use App\Enums\DtrStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Dtr;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DtrSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->intern = User::factory()->intern()->create();
    $this->placement = Placement::factory()->for($this->intern, 'intern')->create();
    $this->data = ['period_from' => '2026-10-05', 'period_to' => '2026-10-09', 'hours' => 40, 'absences' => 1];
    $this->pdf = fn () => UploadedFile::fake()->create('dtr.pdf', 100, 'application/pdf');
});

it('stores a pending DTR on the active placement and tells the company', function () {
    $dtr = app(SubmitDtr::class)($this->intern, $this->data, ($this->pdf)());

    expect($dtr->placement_id)->toBe($this->placement->id)->and($dtr->status)->toBe(DtrStatus::Pending)->and($dtr->hours)->toBe(40)->and($dtr->absences)->toBe(1)
        ->and($dtr->period_from->toDateString())->toBe('2026-10-05')->and($dtr->file_path)->toStartWith("dtrs/{$this->placement->id}/");
    Storage::disk('local')->assertExists($dtr->file_path);
    Notification::assertSentTo($this->placement->company->user, DtrSubmitted::class);
    expect($this->placement->refresh()->hours_rendered)->toBe(0);
});

it('refuses interns without an active placement', function () {
    $free = User::factory()->intern()->create();

    expect(fn () => app(SubmitDtr::class)($free, $this->data, ($this->pdf)()))->toThrow(DomainRuleViolation::class);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('deletes a pending DTR with its file and refuses reviewed ones', function () {
    $dtr = app(SubmitDtr::class)($this->intern, $this->data, ($this->pdf)());
    $path = $dtr->file_path;

    app(DeleteDtr::class)($dtr);
    expect(Dtr::whereKey($dtr->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);

    $approved = Dtr::factory()->for($this->placement)->approved()->create();
    expect(fn () => app(DeleteDtr::class)($approved))->toThrow(DomainRuleViolation::class);
});
