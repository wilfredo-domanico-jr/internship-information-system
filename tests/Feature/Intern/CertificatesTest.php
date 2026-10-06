<?php

use App\Models\Certificate;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('lists the intern’s certificates with download links', function () {
    Storage::fake('local');
    $intern = User::factory()->intern()->create();
    $placement = Placement::factory()->for($intern, 'intern')->ended()->create();
    Storage::disk('local')->put('certificates/x/c.pdf', 'c');
    $mine = Certificate::factory()->for($placement)->create(['hours_at_issue' => 300, 'file_path' => 'certificates/x/c.pdf']);
    Certificate::factory()->create(['hours_at_issue' => 777]);

    $this->actingAs($intern)->get(route('intern.certificates.index'))
        ->assertOk()->assertSee($placement->company->name)->assertSee('300 h')->assertSee(route('files.show', ['certificate', $mine->id]))->assertDontSee('777');
    $this->actingAs($intern)->get(route('files.show', ['certificate', $mine->id]))->assertOk();

    $this->actingAs(User::factory()->intern()->create())->get(route('intern.certificates.index'))->assertOk()->assertSee('No certificates yet');
});
