<?php

use App\Models\Placement;
use App\Models\User;

it('shows counts, the hours chart and quick links', function () {
    $user = User::factory()->company()->create();
    Placement::factory()->for($user->company)->create(['hours_rendered' => 320]);

    $this->actingAs($user)->get(route('company.dashboard'))
        ->assertOk()->assertSee('Active interns')->assertSee('Interns by hours rendered')->assertSee('x-data="chart"', false)->assertSee('301–400')
        ->assertSee(route('company.postings.index'))->assertSee(route('company.dtrs.index'))->assertSee($user->company->company_code);
});
