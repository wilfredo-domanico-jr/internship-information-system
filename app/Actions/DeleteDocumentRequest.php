<?php

namespace App\Actions;

use App\Enums\DocumentRequestStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\DocumentRequest;

class DeleteDocumentRequest
{
    public function __invoke(DocumentRequest $request): void
    {
        if ($request->status !== DocumentRequestStatus::Pending) {
            throw new DomainRuleViolation('Only pending requests can be withdrawn.');
        }

        $request->delete();
    }
}
