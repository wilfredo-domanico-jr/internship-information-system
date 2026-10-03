<?php

use App\Models\Company;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->admin()->create();
});

it('lists only partner companies with active intern counts', function () {
    $partner = Company::factory()->partner()->create(['name' => 'City Hall ICT']);
    Placement::factory()->for($partner)->create();
    User::factory()->company()->create()->company->update(['name' => 'Registered Corp']);

    $this->actingAs($this->admin)->get(route('admin.partners.index'))
        ->assertOk()->assertSee('City Hall ICT')->assertSee('1')->assertDontSee('Registered Corp');
});

it('creates, edits and deletes a partner company through the UI', function () {
    $this->actingAs($this->admin)->get(route('admin.partners.create'))->assertOk();

    $this->actingAs($this->admin)->post(route('admin.partners.store'), [
        'name' => 'City Hall ICT', 'type' => 'Government', 'website' => 'https://qc.example', 'about' => 'ICT office',
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
    ])->assertRedirect(route('admin.partners.index'))->assertSessionHas('success');

    $company = Company::partners()->where('name', 'City Hall ICT')->firstOrFail();
    Storage::disk('public')->assertExists($company->logo_path);

    $this->actingAs($this->admin)->get(route('admin.partners.edit', $company))->assertOk()->assertSee('City Hall ICT');
    $this->actingAs($this->admin)->put(route('admin.partners.update', $company), ['name' => 'QC Hall ICT', 'type' => 'Government'])
        ->assertRedirect(route('admin.partners.index'));
    expect($company->refresh()->name)->toBe('QC Hall ICT');

    $this->actingAs($this->admin)->delete(route('admin.partners.destroy', $company))->assertRedirect(route('admin.partners.index'))->assertSessionHas('success');
    expect(Company::find($company->id))->toBeNull();
});

it('refuses to delete a partner with placements and shows a friendly error', function () {
    $partner = Company::factory()->partner()->create();
    Placement::factory()->for($partner)->create();

    $this->actingAs($this->admin)->from(route('admin.partners.index'))->delete(route('admin.partners.destroy', $partner))
        ->assertRedirect(route('admin.partners.index'))->assertSessionHas('error');
    expect(Company::find($partner->id))->not->toBeNull();
});

it('validates the form and rejects oversized logos', function () {
    $this->actingAs($this->admin)->post(route('admin.partners.store'), ['name' => '', 'website' => 'nope'])
        ->assertSessionHasErrors(['name', 'website']);
    $this->actingAs($this->admin)->post(route('admin.partners.store'), ['name' => 'X', 'logo' => UploadedFile::fake()->create('big.png', config('wiis.uploads.max_avatar_kb') + 1, 'image/png')])
        ->assertSessionHasErrors('logo');
});

it('returns 404 when editing a registered company through the partner routes', function () {
    $registered = User::factory()->company()->create()->company;

    $this->actingAs($this->admin)->get(route('admin.partners.edit', $registered))->assertNotFound();
    $this->actingAs($this->admin)->delete(route('admin.partners.destroy', $registered))->assertNotFound();
});
