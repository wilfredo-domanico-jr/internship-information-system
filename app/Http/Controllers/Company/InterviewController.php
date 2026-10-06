<?php

namespace App\Http\Controllers\Company;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Interview;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InterviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $when = $request->query('when') === 'past' ? 'past' : 'upcoming';
        $companyId = $request->user()->company->id;

        $base = Interview::query()
            ->whereHas('application', fn (Builder $a) => $a->where('status', ApplicationStatus::ForInterview)
                ->whereHas('posting', fn (Builder $p) => $p->where('company_id', $companyId)));

        return view('company.interviews.index', [
            'when' => $when,
            'counts' => [
                'upcoming' => (clone $base)->whereDate('scheduled_on', '>=', today())->count(),
                'past' => (clone $base)->whereDate('scheduled_on', '<', today())->count(),
            ],
            'interviews' => (clone $base)
                ->when($when === 'upcoming', fn (Builder $q) => $q->whereDate('scheduled_on', '>=', today())->orderBy('scheduled_on')->orderBy('starts_at'))
                ->when($when === 'past', fn (Builder $q) => $q->whereDate('scheduled_on', '<', today())->orderByDesc('scheduled_on'))
                ->with(['application.intern.internProfile', 'application.posting'])
                ->paginate(20)->withQueryString(),
        ]);
    }
}
