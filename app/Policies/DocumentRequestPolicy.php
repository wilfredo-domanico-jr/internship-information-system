<?php

namespace App\Policies;

use App\Enums\DocumentRequestStatus;
use App\Models\DocumentRequest;
use App\Models\User;

class DocumentRequestPolicy
{
    public function view(User $user, DocumentRequest $request): bool
    {
        return $user->isAdmin() || $request->placement->isInternOf($user) || $request->placement->isManagedBy($user);
    }

    /** Fulfil or decline. */
    public function handle(User $user, DocumentRequest $request): bool
    {
        return $request->placement->isManagedBy($user);
    }

    public function update(User $user, DocumentRequest $request): bool
    {
        return $request->placement->isInternOf($user) && $request->status === DocumentRequestStatus::Pending;
    }

    public function delete(User $user, DocumentRequest $request): bool
    {
        return $this->update($user, $request);
    }
}
