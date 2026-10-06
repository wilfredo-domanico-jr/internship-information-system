<?php

namespace App\Actions;

use App\Enums\DocumentRequestStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\DocumentRequest;
use App\Notifications\DocumentRequestHandled;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class FulfilDocumentRequest
{
    public function __invoke(DocumentRequest $request, UploadedFile $file): DocumentRequest
    {
        if ($request->status !== DocumentRequestStatus::Pending) {
            throw new DomainRuleViolation('This request has already been handled.');
        }

        $path = $file->storeAs("document-requests/{$request->placement_id}", Str::uuid().'.pdf', 'local');

        $request->update(['status' => DocumentRequestStatus::Fulfilled, 'file_path' => $path, 'handled_at' => now()]);

        $request->placement->intern->notify(new DocumentRequestHandled($request));

        return $request;
    }
}
