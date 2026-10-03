<?php

use App\Models\User;

it('renders the three charts with live data', function () {
    User::factory()->intern()->count(2)->create();

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Placement')
        ->assertSee('Class enrollment')
        ->assertSee('Account status')
        ->assertSee('x-data="chart"', false)
        ->assertSee('&quot;Unplaced&quot;', false);
});
