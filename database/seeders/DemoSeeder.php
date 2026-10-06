<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\Application;
use App\Models\Certificate;
use App\Models\ClassAdviserLog;
use App\Models\ClassFolder;
use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\Company;
use App\Models\DocumentRequest;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\Interview;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $year = now()->year;

        $admin = User::factory()->admin()->create([
            'first_name' => 'Ana', 'last_name' => 'Reyes', 'email' => 'admin@wiis.test',
            'member_no' => "ADM-{$year}-00001",
        ]);

        $adviser = User::factory()->adviser()->create([
            'first_name' => 'Elsie', 'last_name' => 'Isip', 'email' => 'adviser@wiis.test',
            'member_no' => "ADV-{$year}-00001",
        ]);

        $section = ClassSection::factory()->for($adviser, 'adviser')->create([
            'course_code' => 'CC101', 'subject' => 'Practicum', 'section' => 'SBIT-4C',
            'day' => 'Monday', 'starts_at' => '08:00:00', 'ends_at' => '12:00:00',
            'school_year' => "{$year}-".($year + 1), 'join_code' => 'SBIT4C26',
        ]);
        ClassAdviserLog::create(['class_section_id' => $section->id, 'adviser_id' => $adviser->id, 'joined_at' => now()->subMonths(3)]);

        $companyUser = User::factory()->company()->create([
            'first_name' => 'Marco', 'last_name' => 'Villanueva', 'email' => 'company@wiis.test',
            'member_no' => "CMP-{$year}-00001",
        ]);
        $company = $companyUser->company;
        $company->update([
            'name' => 'TechNova Solutions Inc.', 'type' => 'IT Services', 'company_code' => 'TECHNOVA',
            'website' => 'https://technova.example', 'address' => 'Ortigas Center, Pasig City',
            'about' => 'A software consultancy that hosts interns in web development, QA and IT support.',
            'approved_by' => $admin->id, 'approved_at' => now()->subMonths(4),
        ]);

        $pendingUser = User::factory()->company()->create([
            'first_name' => 'Liza', 'last_name' => 'Tan', 'email' => 'pending@wiis.test',
            'member_no' => "CMP-{$year}-00002",
        ]);
        $pendingUser->company->update([
            'name' => 'BlueOrbit Analytics', 'type' => 'BPO',
            'approval_status' => ApprovalStatus::Pending, 'approved_at' => null, 'approved_by' => null,
        ]);

        Company::factory()->partner()->create([
            'name' => 'Quezon City Hall – ICT Office', 'type' => 'Government', 'company_code' => 'QCHALL01',
            'about' => 'Contract-of-service placements reviewed by the class adviser.',
        ]);

        $intern = User::factory()->intern()->create([
            'first_name' => 'Wilfredo', 'last_name' => 'Domanico', 'email' => 'intern@wiis.test',
            'member_no' => "INT-{$year}-00001",
        ]);
        $intern->internProfile->update(['student_number' => '19-0842', 'class_section_id' => $section->id, 'total_hours' => 300]);

        $placement = Placement::factory()->for($intern, 'intern')->for($company)->create([
            'started_at' => now()->subMonths(2)->toDateString(), 'hours_rendered' => 300,
        ]);

        foreach ([[120, 8], [100, 6], [80, 4]] as [$hours, $weeksAgo]) {
            Dtr::factory()->for($placement)->approved()->create([
                'hours' => $hours,
                'period_from' => now()->subWeeks($weeksAgo)->startOfWeek()->toDateString(),
                'period_to' => now()->subWeeks($weeksAgo - 1)->endOfWeek()->toDateString(),
                'reviewer_id' => $companyUser->id,
                'file_path' => $this->demoPdf('dtrs/demo'),
            ]);
        }
        Dtr::factory()->for($placement)->create(['hours' => 40, 'file_path' => $this->demoPdf('dtrs/demo')]);

        $intern2 = User::factory()->intern()->create([
            'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'intern2@wiis.test',
            'member_no' => "INT-{$year}-00002",
        ]);
        $intern2->internProfile->update(['student_number' => '19-1133', 'class_section_id' => $section->id]);

        User::factory()->intern()->count(6)->create()->each(
            fn (User $user) => $user->internProfile()->update(['class_section_id' => $section->id])
        );

        $webDev = InternshipPosting::factory()->for($company)->create(['title' => 'Junior Web Developer Intern']);
        $support = InternshipPosting::factory()->for($company)->create(['title' => 'IT Support Intern']);

        $endorsement = ClassFolder::factory()->for($section)->create(['name' => 'Endorsement Letter']);
        $weekly = ClassFolder::factory()->for($section)->create(['name' => 'Weekly Report 1', 'is_locked' => true]);

        $announcement = Announcement::factory()->for($section)->for($adviser, 'author')->create([
            'body' => '<p>Welcome to Practicum! Upload your endorsement letter to the Documents tab before Friday.</p>',
        ]);
        AnnouncementComment::factory()->for($announcement)->for($intern2, 'author')->create(['body' => 'Noted, thank you Ma’am!']);

        ClassSubmission::factory()->for($endorsement, 'folder')->for($intern, 'intern')->approved()->create([
            'title' => 'Endorsement letter – TechNova', 'file_path' => $this->demoPdf(), 'reviewed_by' => $adviser->id,
        ]);
        ClassSubmission::factory()->for($endorsement, 'folder')->for($intern2, 'intern')->create([
            'title' => 'Endorsement letter', 'file_path' => $this->demoPdf(),
        ]);
        ClassSubmission::factory()->for($weekly, 'folder')->for($intern, 'intern')->create([
            'title' => 'Week 1 report', 'file_path' => $this->demoPdf(), 'is_late' => true,
        ]);

        ClassResource::factory()->for($section)->for($adviser, 'uploader')->create([
            'title' => 'OJT Guidelines and Templates', 'file_path' => $this->demoPdf(),
        ]);

        $forInterview = Application::factory()->for($webDev, 'posting')->for($intern2, 'intern')->forInterview()->create([
            'resume_path' => $this->demoPdf('applications/demo'), 'endorsement_path' => $this->demoPdf('applications/demo'),
        ]);
        Interview::factory()->for($forInterview)->create([
            'title' => 'Initial interview', 'venue' => 'Google Meet', 'link' => 'https://meet.google.com/wiis-demo',
            'scheduled_on' => now()->addDays(3)->toDateString(), 'starts_at' => '10:00:00', 'ends_at' => '10:30:00',
        ]);
        Application::factory()->for($support, 'posting')->for($intern2, 'intern')->create([
            'resume_path' => $this->demoPdf('applications/demo'), 'endorsement_path' => $this->demoPdf('applications/demo'),
        ]);

        DocumentRequest::factory()->for($placement)->create([
            'control_no' => 'DR-'.now()->year.'-00001', 'document_name' => 'Certificate of Completion', 'message' => 'For my OJT portfolio.',
        ]);
        Certificate::factory()->for($placement)->create([
            'hours_at_issue' => 300, 'issued_at' => now()->subWeek(), 'file_path' => $this->demoPdf('certificates/demo'),
        ]);
    }

    /** Stores a fresh private copy of the placeholder PDF (one per record, so deleting one never breaks another). */
    private function demoPdf(string $dir = 'classroom/demo'): string
    {
        $path = "{$dir}/".Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $this->placeholderPdf());

        return $path;
    }

    /** A valid one-page blank PDF (correct xref offsets) so demo downloads open in any viewer. */
    private function placeholderPdf(): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
