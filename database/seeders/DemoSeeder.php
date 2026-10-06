<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\ClassAdviserLog;
use App\Models\ClassFolder;
use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\Company;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

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
            ]);
        }
        Dtr::factory()->for($placement)->create(['hours' => 40]);

        $intern2 = User::factory()->intern()->create([
            'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'intern2@wiis.test',
            'member_no' => "INT-{$year}-00002",
        ]);
        $intern2->internProfile->update(['student_number' => '19-1133', 'class_section_id' => $section->id]);

        User::factory()->intern()->count(6)->create()->each(
            fn (User $user) => $user->internProfile()->update(['class_section_id' => $section->id])
        );

        InternshipPosting::factory()->for($company)->create(['title' => 'Junior Web Developer Intern']);
        InternshipPosting::factory()->for($company)->create(['title' => 'IT Support Intern']);

        Storage::disk('local')->put('classroom/demo/placeholder.pdf', $this->placeholderPdf());

        $endorsement = ClassFolder::factory()->for($section)->create(['name' => 'Endorsement Letter']);
        $weekly = ClassFolder::factory()->for($section)->create(['name' => 'Weekly Report 1', 'is_locked' => true]);

        $announcement = Announcement::factory()->for($section)->for($adviser, 'author')->create([
            'body' => '<p>Welcome to Practicum! Upload your endorsement letter to the Documents tab before Friday.</p>',
        ]);
        AnnouncementComment::factory()->for($announcement)->for($intern2, 'author')->create(['body' => 'Noted, thank you Ma’am!']);

        ClassSubmission::factory()->for($endorsement, 'folder')->for($intern, 'intern')->approved()->create([
            'title' => 'Endorsement letter – TechNova', 'file_path' => 'classroom/demo/placeholder.pdf', 'reviewed_by' => $adviser->id,
        ]);
        ClassSubmission::factory()->for($endorsement, 'folder')->for($intern2, 'intern')->create([
            'title' => 'Endorsement letter', 'file_path' => 'classroom/demo/placeholder.pdf',
        ]);
        ClassSubmission::factory()->for($weekly, 'folder')->for($intern, 'intern')->create([
            'title' => 'Week 1 report', 'file_path' => 'classroom/demo/placeholder.pdf', 'is_late' => true,
        ]);

        ClassResource::factory()->for($section)->for($adviser, 'uploader')->create([
            'title' => 'OJT Guidelines and Templates', 'file_path' => 'classroom/demo/placeholder.pdf',
        ]);
    }

    /** A one-page blank PDF so demo downloads open in a viewer. */
    private function placeholderPdf(): string
    {
        return "%PDF-1.4\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >> endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";
    }
}
