<?php

use App\Models\Dtr;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DtrSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->intern = User::factory()->intern()->create();
    $this->placement = Placement::factory()->for($this->intern, 'intern')->create();
    $this->data = ['period_from' => now()->subDays(5)->toDateString(), 'period_to' => now()->subDay()->toDateString(), 'hours' => 40, 'absences' => 0];
});

it('lists the intern’s DTRs with status and notes', function () {
    Dtr::factory()->for($this->placement)->approved()->create(['hours' => 40]);
    Dtr::factory()->for($this->placement)->disapproved()->create(['hours' => 8, 'reviewer_note' => 'Hours do not match the log.']);
    Dtr::factory()->create(['hours' => 99]);

    $this->actingAs($this->intern)->get(route('intern.dtrs.index'))
        ->assertOk()->assertSee($this->placement->company->name)->assertSee('Approved')->assertSee('Disapproved')->assertSee('Hours do not match the log.')->assertSee('name="file"', false)->assertDontSee('99 h');
});

it('submits a DTR and notifies the company', function () {
    Notification::fake();

    $this->actingAs($this->intern)->post(route('intern.dtrs.store'), [...$this->data, 'file' => UploadedFile::fake()->create('dtr.pdf', 120, 'application/pdf')])
        ->assertRedirect(route('intern.dtrs.index'))->assertSessionHas('success');

    $dtr = Dtr::firstOrFail();
    Storage::disk('local')->assertExists($dtr->file_path);
    Notification::assertSentTo($this->placement->company->user, DtrSubmitted::class);
    $this->actingAs($this->intern)->get(route('intern.dtrs.index'))->assertSee(route('files.show', ['dtr', $dtr->id]))->assertSee(route('intern.dtrs.destroy', $dtr));
});

it('validates the period, hours and file', function () {
    $this->actingAs($this->intern)->post(route('intern.dtrs.store'), [
        'period_from' => now()->toDateString(), 'period_to' => now()->subDays(2)->toDateString(), 'hours' => 0, 'absences' => -1,
        'file' => UploadedFile::fake()->create('dtr.txt', 1, 'text/plain'),
    ])->assertSessionHasErrors(['period_to', 'hours', 'absences', 'file']);
    $this->actingAs($this->intern)->post(route('intern.dtrs.store'), [...$this->data, 'period_to' => now()->addDay()->toDateString(), 'file' => UploadedFile::fake()->create('dtr.pdf', 1, 'application/pdf')])
        ->assertSessionHasErrors('period_to');
    expect(Dtr::count())->toBe(0);
});

it('refuses submissions without a placement and withdraws pending DTRs only', function () {
    $free = User::factory()->intern()->create();
    $this->actingAs($free)->get(route('intern.dtrs.index'))->assertOk()->assertDontSee('name="file"', false)->assertSee('not placed');
    $this->actingAs($free)->from(route('intern.dtrs.index'))->post(route('intern.dtrs.store'), [...$this->data, 'file' => UploadedFile::fake()->create('dtr.pdf', 1, 'application/pdf')])
        ->assertRedirect(route('intern.dtrs.index'))->assertSessionHas('error');

    Storage::disk('local')->put('dtrs/x/p.pdf', 'p');
    $pending = Dtr::factory()->for($this->placement)->create(['file_path' => 'dtrs/x/p.pdf']);
    $approved = Dtr::factory()->for($this->placement)->approved()->create();
    $theirs = Dtr::factory()->create();

    $this->actingAs($this->intern)->delete(route('intern.dtrs.destroy', $approved))->assertForbidden();
    $this->actingAs($this->intern)->delete(route('intern.dtrs.destroy', $theirs))->assertForbidden();
    $this->actingAs($this->intern)->delete(route('intern.dtrs.destroy', $pending))->assertRedirect(route('intern.dtrs.index'))->assertSessionHas('success');
    Storage::disk('local')->assertMissing('dtrs/x/p.pdf');
});
