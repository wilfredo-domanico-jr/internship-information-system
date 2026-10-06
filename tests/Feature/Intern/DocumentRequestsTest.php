<?php

use App\Models\DocumentRequest;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DocumentRequested;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->intern = User::factory()->intern()->create();
    $this->placement = Placement::factory()->for($this->intern, 'intern')->create();
});

it('lists requests with status, file links and edit controls', function () {
    Storage::fake('local');
    Storage::disk('local')->put('document-requests/x/f.pdf', 'f');
    $pending = DocumentRequest::factory()->for($this->placement)->create(['document_name' => 'Acceptance Letter']);
    $fulfilled = DocumentRequest::factory()->for($this->placement)->create(['document_name' => 'Evaluation Form', 'status' => 'fulfilled', 'file_path' => 'document-requests/x/f.pdf', 'handled_at' => now()]);
    DocumentRequest::factory()->create(['document_name' => 'Someone Else Doc']);

    $this->actingAs($this->intern)->get(route('intern.requests.index'))
        ->assertOk()->assertSee('Acceptance Letter')->assertSee($pending->control_no)->assertSee('Evaluation Form')->assertSee(route('files.show', ['document-request', $fulfilled->id]))
        ->assertSee(route('intern.requests.update', $pending))->assertSee(route('intern.requests.destroy', $pending))->assertDontSee(route('intern.requests.destroy', $fulfilled))
        ->assertDontSee('Someone Else Doc')->assertSee('name="document_name"', false);
});

it('creates, edits and withdraws a request, notifying the company on creation', function () {
    Notification::fake();

    $this->actingAs($this->intern)->post(route('intern.requests.store'), ['document_name' => '', 'message' => str_repeat('x', 1001)])->assertSessionHasErrors(['document_name', 'message']);
    $this->actingAs($this->intern)->post(route('intern.requests.store'), ['document_name' => 'Certificate of Completion', 'message' => 'For my portfolio.'])
        ->assertRedirect(route('intern.requests.index'))->assertSessionHas('success');
    $request = DocumentRequest::firstOrFail();
    Notification::assertSentTo($this->placement->company->user, DocumentRequested::class);

    $this->actingAs($this->intern)->put(route('intern.requests.update', $request), ['document_name' => 'Evaluation Form', 'message' => ''])->assertRedirect(route('intern.requests.index'));
    expect($request->refresh()->document_name)->toBe('Evaluation Form')->and($request->message)->toBeNull();

    $this->actingAs($this->intern)->delete(route('intern.requests.destroy', $request))->assertRedirect(route('intern.requests.index'));
    expect(DocumentRequest::count())->toBe(0);
});

it('keeps other interns out and blocks requests without a placement', function () {
    $theirs = DocumentRequest::factory()->create();
    $this->actingAs($this->intern)->put(route('intern.requests.update', $theirs), ['document_name' => 'Hack'])->assertForbidden();
    $this->actingAs($this->intern)->delete(route('intern.requests.destroy', $theirs))->assertForbidden();

    $free = User::factory()->intern()->create();
    $this->actingAs($free)->get(route('intern.requests.index'))->assertOk()->assertDontSee('name="document_name"', false)->assertSee('not placed');
    $this->actingAs($free)->from(route('intern.requests.index'))->post(route('intern.requests.store'), ['document_name' => 'X'])->assertRedirect(route('intern.requests.index'))->assertSessionHas('error');
});
