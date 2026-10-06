<?php

namespace App\Http\Controllers\Company;

use App\Actions\DeclineDocumentRequest;
use App\Actions\FulfilDocumentRequest;
use App\Enums\DocumentRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\FulfilDocumentRequestRequest;
use App\Models\DocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DocumentRequestController extends Controller
{
    public function index(Request $request): View
    {
        $group = DocumentRequestStatus::tryFrom((string) $request->query('status', 'pending')) ?? abort(404);
        $companyId = $request->user()->company->id;

        $base = DocumentRequest::query()->whereHas('placement', fn ($q) => $q->where('company_id', $companyId));

        return view('company.requests.index', [
            'group' => $group,
            'counts' => collect(DocumentRequestStatus::cases())->mapWithKeys(fn (DocumentRequestStatus $s) => [$s->value => (clone $base)->where('status', $s)->count()])->all(),
            'requests' => (clone $base)->where('status', $group)->with('placement.intern.internProfile')->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function fulfil(FulfilDocumentRequestRequest $request, DocumentRequest $documentRequest, FulfilDocumentRequest $fulfil): RedirectResponse
    {
        $fulfil($documentRequest, $request->file('file'));

        return back()->with('success', "Request {$documentRequest->control_no} fulfilled. The intern has been notified.");
    }

    public function decline(DocumentRequest $documentRequest, DeclineDocumentRequest $decline): RedirectResponse
    {
        Gate::authorize('handle', $documentRequest);

        $decline($documentRequest);

        return back()->with('success', "Request {$documentRequest->control_no} declined.");
    }
}
