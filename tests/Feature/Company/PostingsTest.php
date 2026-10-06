<?php

use App\Enums\PostingStatus;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->company()->create();
    $this->company = $this->user->company;
});

$payload = [
    'title' => 'QA Intern', 'city' => 'Pasig', 'description' => 'Test web apps.', 'responsibilities' => 'Write test cases.',
    'closing_date' => '', 'required_hours' => '', 'vacancies' => 2,
    'contact_name' => 'Ana Cruz', 'contact_position' => 'HR Officer', 'contact_phone' => '09170000000',
];

it('lists only the company’s postings with applicant counts', function () {
    $mine = InternshipPosting::factory()->for($this->company)->create(['title' => 'Web Dev Intern']);
    Application::factory()->for($mine, 'posting')->count(2)->create();
    Application::factory()->for($mine, 'posting')->declined()->create();
    InternshipPosting::factory()->create(['title' => 'Not Mine Intern']);

    $this->actingAs($this->user)->get(route('company.postings.index'))
        ->assertOk()->assertSee('Web Dev Intern')->assertSee('2 pending')->assertSee('3 applicants')->assertDontSee('Not Mine Intern');
});

it('creates a posting', function () use ($payload) {
    $this->actingAs($this->user)->get(route('company.postings.create'))->assertOk()->assertSee('Contact name');

    $this->actingAs($this->user)->post(route('company.postings.store'), $payload)
        ->assertRedirect(route('company.postings.index'))->assertSessionHas('success');

    $posting = InternshipPosting::firstOrFail();
    expect($posting->company_id)->toBe($this->company->id)->and($posting->status)->toBe(PostingStatus::Open)->and($posting->closing_date)->toBeNull();
});

it('validates the form', function () use ($payload) {
    $this->actingAs($this->user)->post(route('company.postings.store'), [...$payload, 'title' => '', 'vacancies' => 0, 'closing_date' => now()->subDay()->toDateString(), 'contact_phone' => str_repeat('1', 31)])
        ->assertSessionHasErrors(['title', 'vacancies', 'closing_date', 'contact_phone']);
    expect(InternshipPosting::count())->toBe(0);
});

it('edits, closes, reopens and deletes only its own postings', function () use ($payload) {
    $mine = InternshipPosting::factory()->for($this->company)->create();
    $theirs = InternshipPosting::factory()->create();

    $this->actingAs($this->user)->get(route('company.postings.edit', $theirs))->assertForbidden();
    $this->actingAs($this->user)->put(route('company.postings.update', $theirs), $payload)->assertForbidden();
    $this->actingAs($this->user)->post(route('company.postings.toggle', $theirs))->assertForbidden();
    $this->actingAs($this->user)->delete(route('company.postings.destroy', $theirs))->assertForbidden();

    $this->actingAs($this->user)->get(route('company.postings.edit', $mine))->assertOk()->assertSee($mine->title);
    $this->actingAs($this->user)->put(route('company.postings.update', $mine), [...$payload, 'title' => 'Renamed'])->assertRedirect(route('company.postings.index'));
    expect($mine->refresh()->title)->toBe('Renamed');

    $this->actingAs($this->user)->post(route('company.postings.toggle', $mine))->assertRedirect();
    expect($mine->refresh()->status)->toBe(PostingStatus::Closed);
    $this->actingAs($this->user)->post(route('company.postings.toggle', $mine))->assertRedirect();
    expect($mine->refresh()->status)->toBe(PostingStatus::Open);

    $this->actingAs($this->user)->delete(route('company.postings.destroy', $mine))->assertRedirect(route('company.postings.index'))->assertSessionHas('success');
    expect(InternshipPosting::whereKey($mine->id)->exists())->toBeFalse();
});

it('refuses to delete a posting that has applications', function () {
    $mine = InternshipPosting::factory()->for($this->company)->create();
    Application::factory()->for($mine, 'posting')->create();

    $this->actingAs($this->user)->from(route('company.postings.index'))->delete(route('company.postings.destroy', $mine))
        ->assertRedirect(route('company.postings.index'))->assertSessionHas('error');
    expect(InternshipPosting::whereKey($mine->id)->exists())->toBeTrue();
});
