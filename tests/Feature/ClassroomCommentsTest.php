<?php

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\ClassSection;
use App\Models\User;

beforeEach(function () {
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->intern->internProfile->update(['class_section_id' => $this->section->id]);
    $this->announcement = Announcement::factory()->for($this->section)->for($this->adviser, 'author')->create();
});

it('lets an enrolled intern comment from the stream', function () {
    $this->actingAs($this->intern)->get(route('intern.class.show'))->assertSee(route('intern.comments.store', $this->announcement));

    $this->actingAs($this->intern)->from(route('intern.class.show'))
        ->post(route('intern.comments.store', $this->announcement), ['body' => 'Noted, thank you!'])
        ->assertRedirect(route('intern.class.show'))->assertSessionHas('success');

    $this->actingAs($this->intern)->get(route('intern.class.show'))->assertSee('Noted, thank you!')->assertSee('Maria Santos');
    $this->actingAs($this->adviser)->get(route('adviser.classes.show', $this->section))->assertSee('Noted, thank you!');
});

it('lets the adviser comment and rejects blank comments', function () {
    $this->actingAs($this->adviser)->post(route('adviser.comments.store', $this->announcement), ['body' => 'Reminder: Friday!'])->assertRedirect();
    expect(AnnouncementComment::count())->toBe(1);

    $this->actingAs($this->adviser)->post(route('adviser.comments.store', $this->announcement), ['body' => '   '])->assertSessionHasErrors('body', null, 'comment');
    $this->actingAs($this->intern)->post(route('intern.comments.store', $this->announcement), ['body' => str_repeat('x', 1001)])->assertSessionHasErrors('body', null, 'comment');
});

it('shows a failed comment error at the comment box, not the announcement composer', function () {
    $this->actingAs($this->adviser)->from(route('adviser.classes.show', $this->section))
        ->post(route('adviser.comments.store', $this->announcement), ['announcement_id' => $this->announcement->id, 'body' => '   '])
        ->assertRedirect(route('adviser.classes.show', $this->section));

    $html = $this->actingAs($this->adviser)->get(route('adviser.classes.show', $this->section))->assertOk()->getContent();
    expect($html)->toContain('The body field is required.')->and(substr_count($html, 'input-error'))->toBe(1)->and($html)->not->toContain('Write something before posting.');

    $this->actingAs($this->intern)->from(route('intern.class.show'))
        ->post(route('intern.comments.store', $this->announcement), ['announcement_id' => $this->announcement->id, 'body' => '   '])
        ->assertRedirect(route('intern.class.show'));
    $this->actingAs($this->intern)->get(route('intern.class.show'))->assertOk()->assertSee('The body field is required.');
});

it('forbids outsiders', function () {
    $outsider = User::factory()->intern()->create();
    $otherAdviser = User::factory()->adviser()->create();

    $this->actingAs($outsider)->post(route('intern.comments.store', $this->announcement), ['body' => 'Hi'])->assertForbidden();
    $this->actingAs($otherAdviser)->post(route('adviser.comments.store', $this->announcement), ['body' => 'Hi'])->assertForbidden();
});

it('lets authors and the class adviser delete comments', function () {
    $mine = AnnouncementComment::factory()->for($this->announcement)->for($this->intern, 'author')->create();
    $advisers = AnnouncementComment::factory()->for($this->announcement)->for($this->adviser, 'author')->create();

    $this->actingAs($this->intern)->delete(route('intern.comments.destroy', $advisers))->assertForbidden();
    $this->actingAs($this->intern)->delete(route('intern.comments.destroy', $mine))->assertRedirect();
    expect(AnnouncementComment::whereKey($mine->id)->exists())->toBeFalse();

    $another = AnnouncementComment::factory()->for($this->announcement)->for($this->intern, 'author')->create();
    $this->actingAs($this->adviser)->delete(route('adviser.comments.destroy', $another))->assertRedirect();
    expect(AnnouncementComment::whereKey($another->id)->exists())->toBeFalse();
});
