<?php

namespace App\Http\Controllers\Company;

use App\Actions\CreatePosting;
use App\Actions\DeletePosting;
use App\Actions\TogglePostingStatus;
use App\Actions\UpdatePosting;
use App\Enums\ApplicationStatus;
use App\Enums\PostingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\PostingRequest;
use App\Models\InternshipPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PostingController extends Controller
{
    public function index(Request $request): View
    {
        return view('company.postings.index', [
            'postings' => $request->user()->company->postings()
                ->withCount(['applications', 'applications as pending_applications_count' => fn ($q) => $q->where('status', ApplicationStatus::Pending)])
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('company.postings.create');
    }

    public function store(PostingRequest $request, CreatePosting $create): RedirectResponse
    {
        $posting = $create($request->user()->company, $request->validated());

        return redirect()->route('company.postings.index')->with('success', "“{$posting->title}” is now open for applications.");
    }

    public function edit(InternshipPosting $posting): View
    {
        Gate::authorize('manage', $posting);

        return view('company.postings.edit', ['posting' => $posting]);
    }

    public function update(PostingRequest $request, InternshipPosting $posting, UpdatePosting $update): RedirectResponse
    {
        $update($posting, $request->validated());

        return redirect()->route('company.postings.index')->with('success', "“{$posting->title}” was updated.");
    }

    public function toggle(InternshipPosting $posting, TogglePostingStatus $toggle): RedirectResponse
    {
        Gate::authorize('manage', $posting);

        $toggle($posting);

        return back()->with('success', $posting->status === PostingStatus::Open
            ? "“{$posting->title}” is open again."
            : "“{$posting->title}” is closed to new applications.");
    }

    public function destroy(InternshipPosting $posting, DeletePosting $delete): RedirectResponse
    {
        Gate::authorize('manage', $posting);

        $delete($posting);

        return redirect()->route('company.postings.index')->with('success', "“{$posting->title}” was deleted.");
    }
}
