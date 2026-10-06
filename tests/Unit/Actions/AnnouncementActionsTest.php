<?php

use App\Actions\PostAnnouncement;
use App\Actions\UpdateAnnouncement;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('stores a sanitized body and notifies the active interns of the class', function () {
    Notification::fake();
    $section = ClassSection::factory()->create();
    $interns = User::factory()->intern()->count(2)->create();
    $interns->each(fn (User $u) => $u->internProfile()->update(['class_section_id' => $section->id]));
    $disabled = User::factory()->intern()->disabled()->create();
    $disabled->internProfile()->update(['class_section_id' => $section->id]);
    $outsider = User::factory()->intern()->create();

    $announcement = app(PostAnnouncement::class)($section, $section->adviser, '<div>Hello <b>class</b><script>alert(1)</script></div>');

    expect($announcement->body)->toContain('<b>class</b>')->not->toContain('script')
        ->and($announcement->author_id)->toBe($section->adviser_id);
    Notification::assertSentTo($interns, AnnouncementPosted::class);
    Notification::assertNotSentTo([$disabled, $outsider, $section->adviser], AnnouncementPosted::class);
    expect((new AnnouncementPosted($announcement))->toArray($interns->first())['url'])->toBe(route('intern.class.show'));
});

it('updates the body through the sanitizer', function () {
    $section = ClassSection::factory()->create();
    $announcement = app(PostAnnouncement::class)($section, $section->adviser, '<p>Old</p>');

    app(UpdateAnnouncement::class)($announcement, '<p onclick="x()">New</p>');

    expect($announcement->refresh()->body)->toBe('<p>New</p>');
});
