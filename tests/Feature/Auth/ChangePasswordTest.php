<?php

use App\Models\User;

it('changes the password when the current one is correct', function () {
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->from('/profile')->put('/password', [
        'current_password' => 'password',
        'password' => 'New-Secret-123',
        'password_confirmation' => 'New-Secret-123',
    ])->assertRedirect('/profile')->assertSessionHasNoErrors();

    expect(password_verify('New-Secret-123', $user->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function () {
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->put('/password', [
        'current_password' => 'nope',
        'password' => 'New-Secret-123',
        'password_confirmation' => 'New-Secret-123',
    ])->assertSessionHasErrorsIn('updatePassword', ['current_password']);

    expect(password_verify('password', $user->fresh()->password))->toBeTrue();
});
