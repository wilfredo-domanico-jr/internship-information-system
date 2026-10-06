<?php

namespace App\Actions;

use App\Exceptions\DomainRuleViolation;
use App\Models\Certificate;
use App\Models\Placement;
use App\Notifications\CertificateIssued;
use App\Services\OjtHoursService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Eligibility is the hours rendered at THIS company (spec), never the intern's overall total. */
class IssueCertificate
{
    public function __construct(private readonly OjtHoursService $hours) {}

    public function __invoke(Placement $placement, UploadedFile $file): Certificate
    {
        if (! $this->hours->isCertificateEligible($placement->hours_rendered)) {
            throw new DomainRuleViolation("A certificate needs at least {$this->hours->certificateMinimum()} hours rendered at your company; this intern has {$placement->hours_rendered}.");
        }

        $path = $file->storeAs("certificates/{$placement->id}", Str::uuid().'.pdf', 'local');

        $certificate = $placement->certificates()->create([
            'file_path' => $path,
            'hours_at_issue' => $placement->hours_rendered,
            'issued_at' => now(),
        ]);

        $placement->intern->notify(new CertificateIssued($certificate->setRelation('placement', $placement)));

        return $certificate;
    }
}
