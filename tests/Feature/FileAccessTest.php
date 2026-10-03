<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->company = Company::factory()->registered()->create();
    Storage::disk('local')->put("companies/{$this->company->id}/permit.pdf", '%PDF-1.4 fake');
    $this->company->update(['permit_path' => "companies/{$this->company->id}/permit.pdf", 'moa_path' => null]);
});

it('lets admins download a company permit', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('files.show', ['company-permit', $this->company->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('lets the owning company download its own permit', function () {
    $this->actingAs($this->company->user)
        ->get(route('files.show', ['company-permit', $this->company->id]))
        ->assertOk();
});

it('forbids other users', function () {
    $this->actingAs(User::factory()->company()->create())
        ->get(route('files.show', ['company-permit', $this->company->id]))
        ->assertForbidden();

    $this->actingAs(User::factory()->intern()->create())
        ->get(route('files.show', ['company-permit', $this->company->id]))
        ->assertForbidden();
});

it('returns 404 for unknown kinds, missing records and missing files', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/files/not-a-kind/1')->assertNotFound();
    $this->actingAs($admin)->get(route('files.show', ['company-permit', 999]))->assertNotFound();
    $this->actingAs($admin)->get(route('files.show', ['company-moa', $this->company->id]))->assertNotFound();
});

it('requires authentication', function () {
    $this->get(route('files.show', ['company-permit', $this->company->id]))->assertRedirect(route('login'));
});
