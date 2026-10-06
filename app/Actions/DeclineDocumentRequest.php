<?php

namespace App\Actions;

use App\Enums\DocumentRequestStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\DocumentRequest;
use App\Notifications\DocumentRequestHandled;

class DeclineDocumentRequest
{
    public function __invoke(DocumentRequest $request): DocumentRequest
    {
        if ($request->status !== DocumentRequestStatus::Pending) {
            throw new DomainRuleViolation('This request has already been handled.');
        }

        $request->update(['status' => DocumentRequestStatus::Declined, 'handled_at' => now()]);

        $request->placement->intern->notify(new DocumentRequestHandled($request));

        return $request;
    }
}
