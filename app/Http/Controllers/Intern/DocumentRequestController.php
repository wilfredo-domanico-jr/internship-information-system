<?php

namespace App\Http\Controllers\Intern;

use App\Actions\DeleteDocumentRequest;
use App\Actions\RequestDocument;
use App\Actions\UpdateDocumentRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intern\DocumentRequestRequest;
use App\Models\DocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DocumentRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('intern.requests.index', [
            'placement' => $user->activePlacement()->with('company')->first(),
            'requests' => DocumentRequest::query()->whereHas('placement', fn ($q) => $q->where('intern_id', $user->id))
                ->with('placement.company')->latest()->paginate(20),
        ]);
    }

    public function store(DocumentRequestRequest $request, RequestDocument $requestDocument): RedirectResponse
    {
        $created = $requestDocument($request->user(), $request->validated());

        return redirect()->route('intern.requests.index')->with('success', "Request {$created->control_no} sent to {$created->placement->company->name}.");
    }

    public function update(DocumentRequestRequest $request, DocumentRequest $documentRequest, UpdateDocumentRequest $update): RedirectResponse
    {
        $update($documentRequest, $request->validated());

        return redirect()->route('intern.requests.index')->with('success', "Request {$documentRequest->control_no} updated.");
    }

    public function destroy(DocumentRequest $documentRequest, DeleteDocumentRequest $delete): RedirectResponse
    {
        Gate::authorize('delete', $documentRequest);

        $delete($documentRequest);

        return redirect()->route('intern.requests.index')->with('success', 'Request withdrawn.');
    }
}
