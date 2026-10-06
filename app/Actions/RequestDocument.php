<?php

namespace App\Actions;

use App\Enums\DocumentRequestStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Notifications\DocumentRequested;
use App\Services\ControlNumberGenerator;

class RequestDocument
{
    public function __construct(private readonly ControlNumberGenerator $controlNumbers) {}

    /** @param  array{document_name:string, message?:?string}  $data */
    public function __invoke(User $intern, array $data): DocumentRequest
    {
        $placement = $intern->activePlacement()->with('company.user')->first();

        if (! $placement) {
            throw new DomainRuleViolation('You are not placed with a company, so there is no one to request a document from.');
        }

        $request = $placement->documentRequests()->create([
            'control_no' => $this->controlNumbers->generate(),
            'document_name' => trim($data['document_name']),
            'message' => filled($data['message'] ?? null) ? trim($data['message']) : null,
            'status' => DocumentRequestStatus::Pending,
        ]);

        $placement->company->user?->notify(new DocumentRequested($request->setRelation('placement', $placement->setRelation('intern', $intern))));

        return $request;
    }
}
