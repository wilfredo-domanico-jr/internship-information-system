<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('shows the profile page with the change password form', function () {
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->get('/profile')->assertOk()->assertSee($user->member_no)->assertSee('Current password');
});

it('updates shared and intern fields', function () {
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->put('/profile', [
        'first_name' => 'Juan', 'middle_name' => 'S.', 'last_name' => 'Dela Cruz', 'phone' => '09170000000',
        'gender' => 'Male', 'birthdate' => '2003-05-10', 'present_address' => 'Novaliches, QC',
        'permanent_address' => 'Novaliches, QC', 'about' => 'Aspiring web developer.',
    ])->assertRedirect('/profile')->assertSessionHas('success');

    $user->refresh();
    expect($user->name)->toBe('Juan Dela Cruz')
        ->and($user->internProfile->gender)->toBe('Male')
        ->and($user->internProfile->birthdate->toDateString())->toBe('2003-05-10')
        ->and($user->internProfile->about)->toBe('Aspiring web developer.');
});

it('updates company fields for company users', function () {
    $user = User::factory()->company()->create();

    $this->actingAs($user)->put('/profile', [
        'first_name' => 'Marco', 'last_name' => 'Villanueva',
        'company_name' => 'TechNova Solutions Inc.', 'company_type' => 'IT Services',
        'website' => 'https://technova.example', 'address' => 'Pasig City', 'about' => 'Consultancy.',
    ])->assertRedirect('/profile');

    expect($user->company->fresh()->name)->toBe('TechNova Solutions Inc.')
        ->and($user->company->fresh()->website)->toBe('https://technova.example');
});

it('rejects a future birthdate and an invalid website', function () {
    $intern = User::factory()->intern()->create();
    $this->actingAs($intern)->put('/profile', ['first_name' => 'A', 'last_name' => 'B', 'birthdate' => now()->addDay()->toDateString()])
        ->assertSessionHasErrors('birthdate');

    $company = User::factory()->company()->create();
    $this->actingAs($company)->put('/profile', ['first_name' => 'A', 'last_name' => 'B', 'company_name' => 'X', 'company_type' => 'Y', 'address' => 'Z', 'website' => 'not a url'])
        ->assertSessionHasErrors('website');
});

it('uploads, replaces and removes an avatar', function () {
    Storage::fake('public');
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 300, 300)])->assertRedirect('/profile');
    $first = $user->fresh()->avatar_path;
    expect($first)->toStartWith('avatars/');
    Storage::disk('public')->assertExists($first);

    $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me2.jpg', 300, 300)]);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($user->fresh()->avatar_path);

    $this->actingAs($user)->delete('/profile/avatar')->assertRedirect('/profile');
    expect($user->fresh()->avatar_path)->toBeNull();
});

it('rejects oversized or non-image avatars', function () {
    Storage::fake('public');
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('big.png', config('wiis.uploads.max_avatar_kb') + 1, 'image/png')])
        ->assertSessionHasErrors('avatar');
    $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('avatar');
});
