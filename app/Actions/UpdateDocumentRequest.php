<?php

namespace App\Actions;

use App\Enums\DocumentRequestStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\DocumentRequest;

class UpdateDocumentRequest
{
    /** @param  array{document_name:string, message?:?string}  $data */
    public function __invoke(DocumentRequest $request, array $data): DocumentRequest
    {
        if ($request->status !== DocumentRequestStatus::Pending) {
            throw new DomainRuleViolation('Only pending requests can be edited.');
        }

        $request->update([
            'document_name' => trim($data['document_name']),
            'message' => filled($data['message'] ?? null) ? trim($data['message']) : null,
        ]);

        return $request;
    }
}
