<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentRequestStatus;
use App\Enums\DtrStatus;
use App\Models\Application;
use App\Models\Certificate;
use App\Models\Company;
use App\Models\DocumentRequest;
use App\Models\Dtr;
use App\Models\Interview;
use App\Models\Placement;

class CompanyDashboardStats
{
    public function __construct(private readonly OjtHoursService $hours) {}

    /** @return array{active_interns:int, open_postings:int, pending_applicants:int, upcoming_interviews:int, pending_dtrs:int, pending_requests:int, certificates:int} */
    public function counts(Company $company): array
    {
        $placements = Placement::query()->where('company_id', $company->id)->select('id');
        $postings = fn ($q) => $q->where('company_id', $company->id);

        return [
            'active_interns' => $company->activePlacements()->count(),
            'open_postings' => $company->postings()->open()->count(),
            'pending_applicants' => Application::query()->where('status', ApplicationStatus::Pending)->whereHas('posting', $postings)->whereDoesntHave('intern.activePlacement')->count(),
            'upcoming_interviews' => Interview::query()->whereDate('scheduled_on', '>=', today())
                ->whereHas('application', fn ($a) => $a->where('status', ApplicationStatus::ForInterview)->whereHas('posting', $postings))->count(),
            'pending_dtrs' => Dtr::query()->whereIn('placement_id', $placements)->where('status', DtrStatus::Pending)->count(),
            'pending_requests' => DocumentRequest::query()->whereIn('placement_id', $placements)->where('status', DocumentRequestStatus::Pending)->count(),
            'certificates' => Certificate::query()->whereIn('placement_id', $placements)->count(),
        ];
    }

    /** @return array{labels: list<string>, data: list<int>} */
    public function hourBuckets(Company $company): array
    {
        $labels = $this->hours->bucketLabels();
        $data = array_fill_keys($labels, 0);

        foreach ($company->activePlacements()->pluck('hours_rendered') as $hours) {
            $data[$this->hours->bucketLabel((int) $hours)]++;
        }

        return ['labels' => $labels, 'data' => array_values($data)];
    }
}
