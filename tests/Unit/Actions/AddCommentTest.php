<?php

use App\Actions\AddComment;
use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\AnnouncementCommented;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('adds a comment and notifies the announcement author', function () {
    Notification::fake();
    $section = ClassSection::factory()->create();
    $announcement = Announcement::factory()->for($section)->for($section->adviser, 'author')->create();
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);

    $comment = app(AddComment::class)($announcement, $intern, '  Noted, thank you!  ');

    expect($comment->body)->toBe('Noted, thank you!')->and($comment->author_id)->toBe($intern->id)->and($comment->announcement_id)->toBe($announcement->id);
    Notification::assertSentTo($section->adviser, AnnouncementCommented::class, function (AnnouncementCommented $n) use ($section) {
        $data = $n->toArray($section->adviser);

        return str_contains($data['body'], 'Maria Santos') && $data['url'] === route('adviser.classes.show', $section);
    });
});

it('does not notify authors about their own comments', function () {
    Notification::fake();
    $section = ClassSection::factory()->create();
    $announcement = Announcement::factory()->for($section)->for($section->adviser, 'author')->create();

    app(AddComment::class)($announcement, $section->adviser, 'Reminder!');

    Notification::assertNothingSent();
});
