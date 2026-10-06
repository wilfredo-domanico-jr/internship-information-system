<?php

namespace App\Http\Controllers\Classroom;

use App\Actions\AddComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Classroom\CommentRequest;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(CommentRequest $request, Announcement $announcement, AddComment $add): RedirectResponse
    {
        $add($announcement, $request->user(), $request->validated('body'));

        return back()->with('success', 'Comment posted.');
    }

    public function destroy(AnnouncementComment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return back()->with('success', 'Comment removed.');
    }
}
