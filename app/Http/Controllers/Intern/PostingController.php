<?php

namespace App\Http\Controllers\Intern;

use App\Actions\ApplyToPosting;
use App\Http\Controllers\Controller;
use App\Models\InternshipPosting;
use App\Support\Search;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PostingController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $city = trim((string) $request->query('city'));

        return view('intern.postings.index', [
            'postings' => InternshipPosting::accepting()
                ->with('company')
                ->when($q !== '', fn (Builder $query) => $query->where(function (Builder $w) use ($q) {
                    Search::any($w, ['title', 'description', 'city'], $q)
                        ->orWhereHas('company', fn (Builder $c) => Search::like($c, 'name', $q));
                }))
                ->when($city !== '', fn (Builder $query) => $query->where('city', $city))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
            'cities' => InternshipPosting::accepting()->distinct()->orderBy('city')->pluck('city'),
            'placed' => $request->user()->hasActivePlacement(),
        ]);
    }

    public function show(Request $request, InternshipPosting $posting): View
    {
        Gate::authorize('view', $posting);

        return view('intern.postings.show', [
            'posting' => $posting->load('company'),
            'blocker' => ApplyToPosting::blocker($request->user(), $posting),
        ]);
    }
}
