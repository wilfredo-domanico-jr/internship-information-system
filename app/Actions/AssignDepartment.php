<?php

namespace App\Actions;

use App\Models\Placement;

class AssignDepartment
{
    public function __invoke(Placement $placement, ?int $departmentId): Placement
    {
        $placement->update(['department_id' => $departmentId]);

        return $placement;
    }
}
