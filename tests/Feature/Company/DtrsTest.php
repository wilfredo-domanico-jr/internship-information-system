<?php

use App\Enums\DtrStatus;
use App\Models\Dtr;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DtrReviewed;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->company()->create();
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->placement = Placement::factory()->for($this->intern, 'intern')->for($this->user->company)->create();
});

it('groups the company’s DTRs by status', function () {
    $pending = Dtr::factory()->for($this->placement)->create(['hours' => 40]);
    Dtr::factory()->for($this->placement)->approved()->create(['hours' => 32]);
    Dtr::factory()->for($this->placement)->disapproved()->create(['hours' => 8, 'reviewer_note' => 'Unsigned']);
    Dtr::factory()->create(['hours' => 99]);

    $this->actingAs($this->user)->get(route('company.dtrs.index'))
        ->assertOk()->assertSee('Maria Santos')->assertSee('40 h')->assertDontSee('32 h')->assertDontSee('99 h')
        ->assertSee(route('files.show', ['dtr', $pending->id]))->assertSee(route('company.dtrs.approve', $pending))->assertSee(route('company.dtrs.disapprove', $pending));
    $this->actingAs($this->user)->get(route('company.dtrs.index', ['status' => 'approved']))->assertSee('32 h')->assertDontSee('40 h');
    $this->actingAs($this->user)->get(route('company.dtrs.index', ['status' => 'disapproved']))->assertSee('Unsigned');
    $this->actingAs($this->user)->get(route('company.dtrs.index', ['status' => 'bogus']))->assertNotFound();
});

it('approves from the page, crediting hours once, and notifies the intern', function () {
    Notification::fake();
    $dtr = Dtr::factory()->for($this->placement)->create(['hours' => 40, 'absences' => 1]);

    $this->actingAs($this->user)->from(route('company.dtrs.index'))->post(route('company.dtrs.approve', $dtr))
        ->assertRedirect(route('company.dtrs.index'))->assertSessionHas('success');
    expect($this->placement->refresh()->hours_rendered)->toBe(40)->and($this->intern->internProfile->refresh()->total_hours)->toBe(40);
    Notification::assertSentTo($this->intern, DtrReviewed::class);

    $this->actingAs($this->user)->from(route('company.dtrs.index'))->post(route('company.dtrs.approve', $dtr))->assertRedirect()->assertSessionHas('error');
    expect($this->placement->refresh()->hours_rendered)->toBe(40);
});

it('disapproves with a required note and forbids other companies', function () {
    $dtr = Dtr::factory()->for($this->placement)->create(['hours' => 40]);

    $this->actingAs($this->user)->post(route('company.dtrs.disapprove', $dtr), ['note' => ''])->assertSessionHasErrors('note');
    $this->actingAs($this->user)->post(route('company.dtrs.disapprove', $dtr), ['note' => 'Unsigned copy'])->assertRedirect()->assertSessionHas('success');
    expect($dtr->refresh()->status)->toBe(DtrStatus::Disapproved)->and($this->placement->refresh()->hours_rendered)->toBe(0);

    $other = User::factory()->company()->create();
    $fresh = Dtr::factory()->for($this->placement)->create();
    $this->actingAs($other)->post(route('company.dtrs.approve', $fresh))->assertForbidden();
    $this->actingAs($other)->post(route('company.dtrs.disapprove', $fresh), ['note' => 'x'])->assertForbidden();
    expect($fresh->refresh()->status)->toBe(DtrStatus::Pending);
});
