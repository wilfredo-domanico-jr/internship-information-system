<?php

use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
    $this->intern = User::factory()->intern()->create();
    $this->intern->internProfile->update(['class_section_id' => $this->section->id]);
});

it('posts a sanitized announcement from the stream and notifies interns', function () {
    Notification::fake();

    $this->actingAs($this->adviser)->get(route('adviser.classes.show', $this->section))->assertSee('<trix-editor', false);

    $this->actingAs($this->adviser)->post(route('adviser.announcements.store', $this->section), [
        'body' => '<div>Upload by <strong>Friday</strong>.<script>alert(1)</script><a href="javascript:evil()">x</a></div>',
    ])->assertRedirect(route('adviser.classes.show', $this->section))->assertSessionHas('success');

    $announcement = Announcement::firstOrFail();
    expect($announcement->body)->toContain('<strong>Friday</strong>')->not->toContain('script')->not->toContain('javascript:');
    Notification::assertSentTo($this->intern, AnnouncementPosted::class);

    $this->actingAs($this->intern)->get(route('intern.class.show'))->assertSee('<strong>Friday</strong>', false)->assertDontSee('alert(1)');
});

it('rejects empty bodies', function () {
    $this->actingAs($this->adviser)->post(route('adviser.announcements.store', $this->section), ['body' => '<div><br></div>'])
        ->assertSessionHasErrors('body');
    $this->actingAs($this->adviser)->post(route('adviser.announcements.store', $this->section), ['body' => ''])
        ->assertSessionHasErrors('body');
    expect(Announcement::count())->toBe(0);
});

it('edits and deletes its own announcements only', function () {
    $mine = Announcement::factory()->for($this->section)->for($this->adviser, 'author')->create(['body' => '<p>Old</p>']);
    $otherAdviser = User::factory()->adviser()->create();
    $theirs = Announcement::factory()->for(ClassSection::factory()->for($otherAdviser, 'adviser'))->for($otherAdviser, 'author')->create();

    $this->actingAs($this->adviser)->get(route('adviser.announcements.edit', $mine))->assertOk()->assertSee('&lt;p&gt;Old&lt;/p&gt;', false);
    $this->actingAs($this->adviser)->put(route('adviser.announcements.update', $mine), ['body' => '<p>New</p>'])
        ->assertRedirect(route('adviser.classes.show', $this->section));
    expect($mine->refresh()->body)->toBe('<p>New</p>');

    $this->actingAs($this->adviser)->get(route('adviser.announcements.edit', $theirs))->assertForbidden();
    $this->actingAs($this->adviser)->put(route('adviser.announcements.update', $theirs), ['body' => '<p>Hacked</p>'])->assertForbidden();
    $this->actingAs($this->adviser)->delete(route('adviser.announcements.destroy', $theirs))->assertForbidden();

    $this->actingAs($this->adviser)->delete(route('adviser.announcements.destroy', $mine))->assertRedirect(route('adviser.classes.show', $this->section));
    expect(Announcement::whereKey($mine->id)->exists())->toBeFalse()->and(Announcement::whereKey($theirs->id)->exists())->toBeTrue();
});

it('cannot post to a class it does not advise', function () {
    $other = ClassSection::factory()->create();

    $this->actingAs($this->adviser)->post(route('adviser.announcements.store', $other), ['body' => '<p>Hi</p>'])->assertForbidden();
});
