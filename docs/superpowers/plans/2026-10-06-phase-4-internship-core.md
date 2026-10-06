# WIIS Phase 4 — Internship Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the end-to-end internship flow: companies post internships and manage applicants (interview, accept, decline), interns browse and apply, accepted interns join a company by its code and become placements, interns submit DTRs that companies approve into the single hours ledger, interns request documents that companies fulfil or decline, companies issue certificates to eligible interns, and every event raises an in-app notification — proven by an automated lifecycle test from application to certificate.

**Architecture:** Same layering as Phases 1–3: thin controllers under `App\Http\Controllers\{Company,Intern}` → Form Requests (validation **and** per-record authorization in `authorize()`) → single-purpose `App\Actions` → models. Every internship record is scoped to one company and one intern, so authorization is a Policy per model (`InternshipPosting`, `Application`, `Placement`, `Dtr`, `DocumentRequest`, `Certificate`) built on two helpers: `Company::isManagedBy(User)` and `Placement::isInternOf(User)`. The hours ledger has exactly one writer, `App\Actions\ApproveDtr`, inside a transaction, on the pending → approved transition only. Uploaded PDFs live on the private `local` disk and are served through the existing `FileController` via five new `PrivateFiles` kinds. Business-rule refusals throw `DomainRuleViolation` (rendered as a flash error).

**Tech Stack:** Laravel 12, PHP 8.2, Pest 3, Blade + Tailwind 4 + Alpine (existing design system), Chart.js (existing `<x-chart>`), database notifications.

**Spec:** `docs/superpowers/specs/2026-10-03-wiis-laravel-rebuild-design.md` — section "Phase 4 — Internship core", the business rules under "Legacy feature inventory" (hours, certificates, application flow, DTR, document requests, company history, notifications, dashboards), the "Hours ledger rule" under "Data model", and the end-to-end lifecycle test under "Verification".

## Global Constraints

- Phase 3 is complete (HEAD `b4f5540`, 344 tests green). Do not rename any existing route, component prop, enum value, model method or factory state. Existing routes this phase builds on: `company.dashboard`, `intern.dashboard`, `intern.class.*`, `intern.submissions.*`, `files.show` (`kind`, `id`), `notifications.*`, `profile.*`, `admin.interns.show`.
- Hours ledger (spec, verbatim): "only `App\Actions\ApproveDtr` may add hours, inside a transaction, and only on the pending → approved transition." `placement.hours_rendered += dtr.hours`, `placement.absences += dtr.absences`, `intern_profile.total_hours += dtr.hours`, `total_absences += dtr.absences`. Disapprove never touches hours. No other code path writes `hours_rendered`, `absences`, `total_hours` or `total_absences` (seeders excepted).
- Business rules (spec, verbatim): "OJT completion = 486 hours"; "Certificate eligibility = ≥250 hours at that company"; "Application flow: pending → for interview → accepted/declined (or declined directly)"; "Accepting does NOT place the intern; intern joins by entering the company code"; "Intern cannot apply twice to the same posting"; "Only unplaced interns appear as applicants"; "Certificates: company issues a PDF to an eligible intern; re-issue allowed"; "Intern leaving a company shows a warning depending on hours at that company"; at most one active placement per intern (`ended_at IS NULL`).
- Thresholds come from `OjtHoursService` (`required()`, `certificateMinimum()`, `tier()`, `bucketLabel()`, `bucketLabels()`), never literal in `app/` or Blade.
- Uploads: PDF only (`mimetypes:application/pdf`), `max:`.`config('wiis.uploads.max_pdf_kb')` (5120 KB), stored on the private `local` disk, served only via `files.show`; delete the file whenever its record is deleted.
- Authorization: every company/intern route that touches a record checks a Policy — in the Form Request `authorize()` when there is a request, otherwise `Gate::authorize()`. Never rely on the `role:` middleware alone; company A must never reach company B's records (403), and intern A must never reach intern B's (403).
- Company portal: the signed-in user's company is `$request->user()->company` (always a registered, approved company — `account.usable` already holds pending ones). Partner (COS) companies have no login and are out of scope here (Phase 5).
- Strict Eloquent mode is on outside production: eager-load every relation used in a Blade loop; hydrate policy back-references before per-row `@can` (pattern from Phase 3: `->through()` / `setRelation`).
- Blade attributes: dynamic text inside component-tag attributes must be a bound expression (`:title="..."`), never `{{ }}` inside the attribute string.
- Route names follow `company.<resource>.<action>` / `intern.<resource>.<action>`; nav items are added through `App\Support\Navigation::for()` and guarded by `Route::has()`.
- Notifications: `App\Notifications\*` on the database channel with `title`, `body`, `url`, `icon` (a `heroicon-*` name).
- Commits: conventional prefixes (`feat:`, `fix:`, `refactor:`, `test:`, `chore:`), short descriptive messages, directly on `main`, **never any Claude attribution**. Run `vendor/bin/pint --dirty` before each commit.
- Shell: Windows Git Bash; long heredocs fail, so write files with the editor/Write tool. Work only inside `D:\Programming_Application\xampp-7.4.1\htdocs\internship-information-system`.

## Review Focus

1. **Approving the same DTR twice** must add its hours once; approving a disapproved DTR, or disapproving an approved one, must be refused; disapproving must never change any hours. Pinned in Task 11.
2. **Applying while placed, to a closed or expired posting, or a second time to the same posting** must be refused with a message, and no files may be stored. Pinned in Task 3.
3. **Joining a company by code** must require an accepted application with that company and no active placement; a code of a pending/rejected/partner company must not work. Pinned in Task 8.
4. **Cross-company isolation**: company A opening company B's application, placement, DTR, document request or certificate URL gets 403, and the same for intern A on intern B's records. Pinned in Task 1 (policy tests) and in every task's feature test.
5. **Certificate eligibility is per company**: an intern with 300 total hours but 100 at this company cannot be issued a certificate here. Pinned in Task 14.

## File Structure (what Phase 4 creates)

```
app/
  Actions/{CreatePosting,UpdatePosting,TogglePostingStatus,DeletePosting,
           ApplyToPosting,CancelApplication,ScheduleInterview,DecideApplication,
           PlaceIntern,LeaveCompany,RemoveIntern,AssignDepartment,
           SubmitDtr,DeleteDtr,ApproveDtr,DisapproveDtr,
           RequestDocument,UpdateDocumentRequest,DeleteDocumentRequest,FulfilDocumentRequest,DeclineDocumentRequest,
           IssueCertificate}.php
  Http/Controllers/Company/{PostingController,ApplicantController,ApplicationController,InterviewController,
           InternController,PlacementController,HistoryController,DtrController,DocumentRequestController,CertificateController}.php
  Http/Controllers/Intern/{PostingController,ApplicationController,InternshipController,DtrController,
           DocumentRequestController,CertificateController}.php
  Http/Requests/Company/{PostingRequest,ScheduleInterviewRequest,DeclineApplicationRequest,AssignDepartmentRequest,
           DisapproveDtrRequest,FulfilDocumentRequestRequest,IssueCertificateRequest}.php
  Http/Requests/Intern/{ApplyRequest,JoinCompanyRequest,SubmitDtrRequest,DocumentRequestRequest}.php
  Models/{Company,Placement,Application,User}.php (helpers)
  Notifications/{ApplicationReceived,InterviewScheduled,ApplicationDecided,InternJoinedCompany,InternLeftCompany,
           InternRemoved,DtrSubmitted,DtrReviewed,DocumentRequested,DocumentRequestHandled,CertificateIssued}.php
  Policies/{InternshipPostingPolicy,ApplicationPolicy,PlacementPolicy,DtrPolicy,DocumentRequestPolicy,CertificatePolicy}.php
  Services/{ControlNumberGenerator,CompanyDashboardStats}.php
  Support/{Navigation,PrivateFiles}.php (modified)
resources/views/components/timeline.blade.php
resources/views/company/{postings,applicants,applications,interviews,interns,history,dtrs,requests,certificates}/*.blade.php
resources/views/company/dashboard.blade.php (modified)
resources/views/intern/{postings,applications,internship,dtrs,requests,certificates}/*.blade.php
resources/views/intern/dashboard.blade.php (modified)
routes/web.php (company + intern groups grow)
database/seeders/DemoSeeder.php (applications, interview, document request, certificate)
tests/Feature/{PortalRouteIsolationTest,InternshipLifecycleTest,InternshipFileAccessTest}.php
tests/Feature/Company/*.php, tests/Feature/Intern/*.php, tests/Unit/Actions/*.php,
tests/Unit/Policies/InternshipPoliciesTest.php, tests/Unit/Services/*.php
README.md, CLAUDE.md
```

Task order: 1 policies, helpers, file kinds, nav, timeline, isolation test → 2 company postings → 3 intern browse + apply → 4 intern applications + cancel → 5 company applicants + profile → 6 interview + decline → 7 interviews list + accept → 8 join by code, My Internship, leave → 9 monitor interns, department, remove, history → 10 intern DTRs → 11 DTR review + hours ledger → 12 intern document requests → 13 company document requests → 14 certificates → 15 dashboards → 16 lifecycle test, seed, docs, wrap-up.

---

### Task 1: Policies, model helpers, private file kinds, navigation, timeline component and the portal isolation test

**Files:**
- Modify: `app/Models/Company.php`, `app/Models/Placement.php`, `app/Models/Application.php`, `app/Models/User.php`, `app/Support/PrivateFiles.php`, `app/Support/Navigation.php`
- Create: `app/Policies/{InternshipPostingPolicy,ApplicationPolicy,PlacementPolicy,DtrPolicy,DocumentRequestPolicy,CertificatePolicy}.php`, `resources/views/components/timeline.blade.php`
- Rename: `tests/Feature/ClassroomRouteIsolationTest.php` → `tests/Feature/PortalRouteIsolationTest.php` (extended)
- Test: `tests/Unit/Policies/InternshipPoliciesTest.php`, `tests/Feature/InternshipFileAccessTest.php`, `tests/Feature/InternshipComponentsTest.php`, `tests/Feature/Admin/NavigationTest.php`

**Interfaces:**
- Consumes: `FileController` + `PrivateFiles::registry()`, `Navigation::for()`, model factories.
- Produces: `Company::isManagedBy(User): bool`; `Placement::isInternOf(User): bool`, `Placement::isManagedBy(User): bool`; `Application::isOwnedBy(User): bool`, `Application::isManagedBy(User): bool`; `User::hasActivePlacement(): bool`; policy abilities — `InternshipPosting`: `view`, `manage`; `Application`: `view`, `decide`, `cancel`; `Placement`: `view`, `manage`, `leave`; `Dtr`: `view`, `review`, `delete`; `DocumentRequest`: `view`, `handle`, `update`, `delete`; `Certificate`: `view`. File kinds `application-resume`, `application-endorsement`, `dtr`, `document-request`, `certificate`. `<x-timeline :steps>` where each step is `['label' => string, 'description' => ?string, 'at' => ?Carbon, 'state' => 'done'|'current'|'upcoming'|'failed']`. Nav items (guarded by `Route::has`): company — Postings, Interviews, Interns, DTRs, Requests, Certificates, History; intern — Internships, My applications, My internship, DTRs, Requests, Certificates (after the Phase 3 items). The isolation test covers every `adviser.*`, `intern.*` and `company.*` route with fixtures for `posting`, `application`, `placement`, `dtr`, `documentRequest`, `certificate`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Policies/InternshipPoliciesTest.php`:

```php
<?php

use App\Enums\ApplicationStatus;
use App\Enums\DocumentRequestStatus;
use App\Enums\DtrStatus;
use App\Models\Application;
use App\Models\Certificate;
use App\Models\Company;
use App\Models\DocumentRequest;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->companyUser = User::factory()->company()->create();
    $this->company = $this->companyUser->company;
    $this->otherCompanyUser = User::factory()->company()->create();
    $this->intern = User::factory()->intern()->create();
    $this->otherIntern = User::factory()->intern()->create();
    $this->admin = User::factory()->admin()->create();
    $this->posting = InternshipPosting::factory()->for($this->company)->create();
    $this->application = Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->create();
    $this->placement = Placement::factory()->for($this->intern, 'intern')->for($this->company)->create();
});

it('knows who manages and who owns', function () {
    expect($this->company->isManagedBy($this->companyUser))->toBeTrue()
        ->and($this->company->isManagedBy($this->otherCompanyUser))->toBeFalse()
        ->and(Company::factory()->partner()->create()->isManagedBy($this->companyUser))->toBeFalse()
        ->and($this->placement->isInternOf($this->intern))->toBeTrue()
        ->and($this->placement->isInternOf($this->otherIntern))->toBeFalse()
        ->and($this->placement->isManagedBy($this->companyUser))->toBeTrue()
        ->and($this->application->isOwnedBy($this->intern))->toBeTrue()
        ->and($this->application->isManagedBy($this->companyUser))->toBeTrue()
        ->and($this->application->isManagedBy($this->otherCompanyUser))->toBeFalse()
        ->and($this->intern->hasActivePlacement())->toBeTrue()
        ->and($this->otherIntern->hasActivePlacement())->toBeFalse();
});

it('scopes postings to their company and lets interns view open ones only', function () {
    expect($this->companyUser->can('manage', $this->posting))->toBeTrue()
        ->and($this->otherCompanyUser->can('manage', $this->posting))->toBeFalse()
        ->and($this->companyUser->can('view', $this->posting))->toBeTrue()
        ->and($this->intern->can('view', $this->posting))->toBeTrue()
        ->and($this->admin->can('view', $this->posting))->toBeTrue()
        ->and($this->otherCompanyUser->can('view', $this->posting))->toBeFalse();

    $closed = InternshipPosting::factory()->for($this->company)->closed()->create();
    expect($this->intern->can('view', $closed))->toBeFalse()->and($this->companyUser->can('view', $closed))->toBeTrue();
});

it('scopes applications to the intern and the posting company', function () {
    expect($this->intern->can('view', $this->application))->toBeTrue()
        ->and($this->companyUser->can('view', $this->application))->toBeTrue()
        ->and($this->admin->can('view', $this->application))->toBeTrue()
        ->and($this->otherIntern->can('view', $this->application))->toBeFalse()
        ->and($this->otherCompanyUser->can('view', $this->application))->toBeFalse()
        ->and($this->companyUser->can('decide', $this->application))->toBeTrue()
        ->and($this->otherCompanyUser->can('decide', $this->application))->toBeFalse()
        ->and($this->intern->can('decide', $this->application))->toBeFalse()
        ->and($this->intern->can('cancel', $this->application))->toBeTrue()
        ->and($this->otherIntern->can('cancel', $this->application))->toBeFalse();

    $this->application->update(['status' => ApplicationStatus::Accepted]);
    expect($this->intern->can('cancel', $this->application->fresh()))->toBeFalse();
});

it('scopes placements, DTRs, document requests and certificates', function () {
    $dtr = Dtr::factory()->for($this->placement)->create();
    $request = DocumentRequest::factory()->for($this->placement)->create();
    $certificate = Certificate::factory()->for($this->placement)->create();

    expect($this->intern->can('view', $this->placement))->toBeTrue()
        ->and($this->companyUser->can('view', $this->placement))->toBeTrue()
        ->and($this->otherIntern->can('view', $this->placement))->toBeFalse()
        ->and($this->companyUser->can('manage', $this->placement))->toBeTrue()
        ->and($this->otherCompanyUser->can('manage', $this->placement))->toBeFalse()
        ->and($this->intern->can('manage', $this->placement))->toBeFalse()
        ->and($this->intern->can('leave', $this->placement))->toBeTrue()
        ->and($this->companyUser->can('leave', $this->placement))->toBeFalse()
        ->and($this->intern->can('view', $dtr))->toBeTrue()
        ->and($this->companyUser->can('review', $dtr))->toBeTrue()
        ->and($this->otherCompanyUser->can('review', $dtr))->toBeFalse()
        ->and($this->otherCompanyUser->can('view', $dtr))->toBeFalse()
        ->and($this->intern->can('delete', $dtr))->toBeTrue()
        ->and($this->intern->can('view', $request))->toBeTrue()
        ->and($this->companyUser->can('handle', $request))->toBeTrue()
        ->and($this->otherCompanyUser->can('handle', $request))->toBeFalse()
        ->and($this->intern->can('update', $request))->toBeTrue()
        ->and($this->intern->can('delete', $request))->toBeTrue()
        ->and($this->otherIntern->can('update', $request))->toBeFalse()
        ->and($this->intern->can('view', $certificate))->toBeTrue()
        ->and($this->companyUser->can('view', $certificate))->toBeTrue()
        ->and($this->admin->can('view', $certificate))->toBeTrue()
        ->and($this->otherIntern->can('view', $certificate))->toBeFalse();

    $dtr->update(['status' => DtrStatus::Approved]);
    $request->update(['status' => DocumentRequestStatus::Fulfilled]);
    expect($this->intern->can('delete', $dtr->fresh()))->toBeFalse()
        ->and($this->intern->can('update', $request->fresh()))->toBeFalse()
        ->and($this->intern->can('delete', $request->fresh()))->toBeFalse();

    $this->placement->update(['ended_at' => now()->toDateString()]);
    expect($this->intern->can('leave', $this->placement->fresh()))->toBeFalse();
});
```

`tests/Feature/InternshipFileAccessTest.php`:

```php
<?php

use App\Models\Application;
use App\Models\Certificate;
use App\Models\DocumentRequest;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->companyUser = User::factory()->company()->create();
    $this->intern = User::factory()->intern()->create();
    $posting = InternshipPosting::factory()->for($this->companyUser->company)->create();
    foreach (['resume', 'endorsement', 'dtr', 'request', 'cert'] as $name) {
        Storage::disk('local')->put("t/{$name}.pdf", '%PDF-1.4 fake');
    }
    $placement = Placement::factory()->for($this->intern, 'intern')->for($this->companyUser->company)->create();
    $this->application = Application::factory()->for($posting, 'posting')->for($this->intern, 'intern')->create(['resume_path' => 't/resume.pdf', 'endorsement_path' => 't/endorsement.pdf']);
    $this->dtr = Dtr::factory()->for($placement)->create(['file_path' => 't/dtr.pdf']);
    $this->request = DocumentRequest::factory()->for($placement)->create(['file_path' => 't/request.pdf']);
    $this->certificate = Certificate::factory()->for($placement)->create(['file_path' => 't/cert.pdf']);
});

it('serves every internship file kind to its intern, its company and admins', function () {
    $kinds = [
        ['application-resume', $this->application->id], ['application-endorsement', $this->application->id],
        ['dtr', $this->dtr->id], ['document-request', $this->request->id], ['certificate', $this->certificate->id],
    ];

    foreach ([$this->intern, $this->companyUser, User::factory()->admin()->create()] as $user) {
        foreach ($kinds as [$kind, $id]) {
            $this->actingAs($user)->get(route('files.show', [$kind, $id]))
                ->assertOk()->assertHeader('content-type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }
});

it('forbids other interns, other companies and advisers', function () {
    foreach ([User::factory()->intern()->create(), User::factory()->company()->create(), User::factory()->adviser()->create()] as $user) {
        $this->actingAs($user)->get(route('files.show', ['application-resume', $this->application->id]))->assertForbidden();
        $this->actingAs($user)->get(route('files.show', ['dtr', $this->dtr->id]))->assertForbidden();
        $this->actingAs($user)->get(route('files.show', ['document-request', $this->request->id]))->assertForbidden();
        $this->actingAs($user)->get(route('files.show', ['certificate', $this->certificate->id]))->assertForbidden();
    }
});

it('returns 404 for a document request that has no file yet', function () {
    $this->request->update(['file_path' => null]);

    $this->actingAs($this->intern)->get(route('files.show', ['document-request', $this->request->id]))->assertNotFound();
});
```

`tests/Feature/InternshipComponentsTest.php`:

```php
<?php

use Illuminate\Support\Facades\Blade;

it('renders a timeline with done, current, upcoming and failed steps', function () {
    $html = Blade::render('<x-timeline :steps="$steps" />', ['steps' => [
        ['label' => 'Applied', 'at' => now()->subDays(3), 'state' => 'done'],
        ['label' => 'Interview', 'description' => 'Google Meet · Oct 10', 'state' => 'current'],
        ['label' => 'Decision', 'state' => 'upcoming'],
        ['label' => 'Declined', 'description' => 'Position filled', 'state' => 'failed'],
    ]]);

    expect($html)->toContain('Applied')->toContain('Interview')->toContain('Google Meet · Oct 10')->toContain('Position filled')
        ->toContain('data-state="done"')->toContain('data-state="current"')->toContain('data-state="upcoming"')->toContain('data-state="failed"')
        ->toContain(now()->subDays(3)->format('M j'));
});
```

Add to `tests/Feature/Admin/NavigationTest.php` (replace the intern test and add a company test):

```php
it('lists the intern sections once their routes exist', function () {
    foreach (['intern.class.show', 'intern.submissions.index', 'intern.postings.index', 'intern.applications.index', 'intern.internship.show', 'intern.dtrs.index', 'intern.requests.index', 'intern.certificates.index'] as $i => $name) {
        Route::get("/_t/i{$i}", fn () => '')->name($name);
    }
    Route::getRoutes()->refreshNameLookups();

    expect(collect(Navigation::for(User::factory()->intern()->create()))->pluck('label')->all())
        ->toBe(['Dashboard', 'My class', 'My submissions', 'Internships', 'My applications', 'My internship', 'DTRs', 'Requests', 'Certificates', 'Notifications']);
});

it('lists the company sections once their routes exist', function () {
    foreach (['company.postings.index', 'company.interviews.index', 'company.interns.index', 'company.dtrs.index', 'company.requests.index', 'company.certificates.index', 'company.history.index'] as $i => $name) {
        Route::get("/_t/c{$i}", fn () => '')->name($name);
    }
    Route::getRoutes()->refreshNameLookups();

    expect(collect(Navigation::for(User::factory()->company()->create()))->pluck('label')->all())
        ->toBe(['Dashboard', 'Postings', 'Interviews', 'Interns', 'DTRs', 'Requests', 'Certificates', 'History', 'Notifications']);
});
```

and change the last test ("does not show portal sections to roles that have none") to assert the company user without those routes still gets `['Dashboard', 'Notifications']` — it already does, because the throwaway routes are registered per test; keep it as is.

Rename `tests/Feature/ClassroomRouteIsolationTest.php` to `tests/Feature/PortalRouteIsolationTest.php` and replace its contents with:

```php
<?php

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\Application;
use App\Models\Certificate;
use App\Models\ClassFolder;
use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\DocumentRequest;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/** @return array<int, LaravelRoute> */
function portalRoutes(string $prefix): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $r) => str_starts_with((string) $r->getName(), $prefix))
        ->values()->all();
}

function portalRouteUrl(LaravelRoute $route, array $params): string
{
    return route($route->getName(), array_intersect_key($params, array_flip($route->parameterNames())));
}

beforeEach(function () {
    $adviser = User::factory()->adviser()->create();
    $section = ClassSection::factory()->for($adviser, 'adviser')->create();
    $intern = User::factory()->intern()->create();
    $intern->internProfile->update(['class_section_id' => $section->id]);
    $announcement = Announcement::factory()->for($section)->for($adviser, 'author')->create();
    $folder = ClassFolder::factory()->for($section)->create();
    $companyUser = User::factory()->company()->create();
    $posting = InternshipPosting::factory()->for($companyUser->company)->create();
    $placement = Placement::factory()->for($intern, 'intern')->for($companyUser->company)->create();

    $this->params = [
        'classSection' => $section->id,
        'announcement' => $announcement->id,
        'comment' => AnnouncementComment::factory()->for($announcement)->for($intern, 'author')->create()->id,
        'folder' => $folder->id,
        'submission' => ClassSubmission::factory()->for($folder, 'folder')->for($intern, 'intern')->create()->id,
        'resource' => ClassResource::factory()->for($section)->for($adviser, 'uploader')->create()->id,
        'posting' => $posting->id,
        'application' => Application::factory()->for($posting, 'posting')->for($intern, 'intern')->create()->id,
        'placement' => $placement->id,
        'dtr' => Dtr::factory()->for($placement)->create()->id,
        'documentRequest' => DocumentRequest::factory()->for($placement)->create()->id,
        'certificate' => Certificate::factory()->for($placement)->create()->id,
    ];
});

it('discovers the portal routes', function () {
    expect(count(portalRoutes('adviser.')))->toBeGreaterThan(20)
        ->and(count(portalRoutes('intern.')))->toBeGreaterThan(8)
        ->and(count(portalRoutes('company.')))->toBeGreaterThan(0);
});

it('forbids every portal route to the other roles', function (string $prefix, array $others) {
    foreach ($others as $role) {
        $user = User::factory()->{$role}()->create();
        foreach (portalRoutes($prefix) as $route) {
            $unknown = array_diff($route->parameterNames(), array_keys($this->params));
            expect($unknown)->toBe([], "{$route->getName()} needs a fixture for ".implode(', ', $unknown));

            $this->actingAs($user)->call($route->methods()[0], portalRouteUrl($route, $this->params))
                ->assertForbidden("{$role} reached {$route->getName()}");
        }
    }
})->with([
    'adviser portal' => ['adviser.', ['admin', 'company', 'intern']],
    'intern portal' => ['intern.', ['admin', 'company', 'adviser']],
    'company portal' => ['company.', ['admin', 'adviser', 'intern']],
]);

it('redirects guests from every portal route to the login page', function () {
    foreach ([...portalRoutes('adviser.'), ...portalRoutes('intern.'), ...portalRoutes('company.')] as $route) {
        $this->call($route->methods()[0], portalRouteUrl($route, $this->params))->assertRedirect(route('login'));
    }
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="InternshipPolicies|InternshipFileAccess|InternshipComponents|Navigation|PortalRouteIsolation"`
Expected: FAIL — helpers undefined, `can()` false, file kinds 404, timeline component missing, nav labels missing. (`PortalRouteIsolation` passes already apart from the company count; it is the net for every later task.)

- [ ] **Step 3: Model helpers**

`app/Models/Company.php` — add after `isApproved()`:

```php
    /** True for the registered company's own login; partner companies have no login. */
    public function isManagedBy(User $user): bool
    {
        return $this->user_id !== null && $this->user_id === $user->id;
    }
```

`app/Models/Placement.php` — add after `isActive()`:

```php
    public function isInternOf(User $user): bool
    {
        return $this->intern_id === $user->id;
    }

    public function isManagedBy(User $user): bool
    {
        return $this->company->isManagedBy($user);
    }
```

`app/Models/Application.php` — add after the casts:

```php
    public function isOwnedBy(User $user): bool
    {
        return $this->intern_id === $user->id;
    }

    public function isManagedBy(User $user): bool
    {
        return $this->posting->company->isManagedBy($user);
    }
```

`app/Models/User.php` — add under "Role and status helpers":

```php
    public function hasActivePlacement(): bool
    {
        return $this->activePlacement()->exists();
    }
```

- [ ] **Step 4: Policies**

`app/Policies/InternshipPostingPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\PostingStatus;
use App\Models\InternshipPosting;
use App\Models\User;

class InternshipPostingPolicy
{
    /** Its company and admins always; interns only while the posting is open. */
    public function view(User $user, InternshipPosting $posting): bool
    {
        return $user->isAdmin()
            || $posting->company->isManagedBy($user)
            || ($user->isIntern() && $posting->status === PostingStatus::Open);
    }

    public function manage(User $user, InternshipPosting $posting): bool
    {
        return $posting->company->isManagedBy($user);
    }
}
```

`app/Policies/ApplicationPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        return $user->isAdmin() || $application->isOwnedBy($user) || $application->isManagedBy($user);
    }

    /** Schedule an interview, accept or decline. */
    public function decide(User $user, Application $application): bool
    {
        return $application->isManagedBy($user);
    }

    /** The intern may withdraw while the application is still open. */
    public function cancel(User $user, Application $application): bool
    {
        return $application->isOwnedBy($user) && $application->status->isOpen();
    }
}
```

`app/Policies/PlacementPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Placement;
use App\Models\User;

class PlacementPolicy
{
    public function view(User $user, Placement $placement): bool
    {
        return $user->isAdmin() || $placement->isInternOf($user) || $placement->isManagedBy($user);
    }

    /** Assign a department, remove the intern, issue a certificate. */
    public function manage(User $user, Placement $placement): bool
    {
        return $placement->isManagedBy($user);
    }

    public function leave(User $user, Placement $placement): bool
    {
        return $placement->isInternOf($user) && $placement->isActive();
    }
}
```

`app/Policies/DtrPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\DtrStatus;
use App\Models\Dtr;
use App\Models\User;

class DtrPolicy
{
    public function view(User $user, Dtr $dtr): bool
    {
        return $user->isAdmin() || $dtr->placement->isInternOf($user) || $dtr->placement->isManagedBy($user);
    }

    public function review(User $user, Dtr $dtr): bool
    {
        return $dtr->placement->isManagedBy($user);
    }

    /** Interns may withdraw a DTR only before it is reviewed. */
    public function delete(User $user, Dtr $dtr): bool
    {
        return $dtr->placement->isInternOf($user) && $dtr->status === DtrStatus::Pending;
    }
}
```

`app/Policies/DocumentRequestPolicy.php`:

```php
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
```

`app/Policies/CertificatePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    public function view(User $user, Certificate $certificate): bool
    {
        return $user->isAdmin() || $certificate->placement->isInternOf($user) || $certificate->placement->isManagedBy($user);
    }
}
```

- [ ] **Step 5: File kinds, navigation, timeline**

`app/Support/PrivateFiles.php` — add imports for `Application`, `Certificate`, `DocumentRequest`, `Dtr` and extend the registry:

```php
            'application-resume' => [Application::class, 'resume_path', 'view'],
            'application-endorsement' => [Application::class, 'endorsement_path', 'view'],
            'dtr' => [Dtr::class, 'file_path', 'view'],
            'document-request' => [DocumentRequest::class, 'file_path', 'view'],
            'certificate' => [Certificate::class, 'file_path', 'view'],
```

`app/Support/Navigation.php` — in `portalItems()` extend the intern list and add a company branch:

```php
        if ($user->isIntern()) {
            return [
                ['label' => 'My class', 'route' => 'intern.class.show', 'icon' => 'heroicon-o-rectangle-group',
                    'active' => ['intern.class.*', 'intern.comments.*', 'intern.folders.*']],
                ['label' => 'My submissions', 'route' => 'intern.submissions.index', 'icon' => 'heroicon-o-document-check', 'active' => 'intern.submissions.*'],
                ['label' => 'Internships', 'route' => 'intern.postings.index', 'icon' => 'heroicon-o-magnifying-glass', 'active' => 'intern.postings.*'],
                ['label' => 'My applications', 'route' => 'intern.applications.index', 'icon' => 'heroicon-o-paper-airplane', 'active' => 'intern.applications.*'],
                ['label' => 'My internship', 'route' => 'intern.internship.show', 'icon' => 'heroicon-o-briefcase', 'active' => 'intern.internship.*'],
                ['label' => 'DTRs', 'route' => 'intern.dtrs.index', 'icon' => 'heroicon-o-clock', 'active' => 'intern.dtrs.*'],
                ['label' => 'Requests', 'route' => 'intern.requests.index', 'icon' => 'heroicon-o-document-text', 'active' => 'intern.requests.*'],
                ['label' => 'Certificates', 'route' => 'intern.certificates.index', 'icon' => 'heroicon-o-trophy', 'active' => 'intern.certificates.*'],
            ];
        }

        if ($user->isCompany()) {
            return [
                ['label' => 'Postings', 'route' => 'company.postings.index', 'icon' => 'heroicon-o-megaphone', 'active' => ['company.postings.*', 'company.applications.*']],
                ['label' => 'Interviews', 'route' => 'company.interviews.index', 'icon' => 'heroicon-o-calendar-days', 'active' => 'company.interviews.*'],
                ['label' => 'Interns', 'route' => 'company.interns.index', 'icon' => 'heroicon-o-users', 'active' => ['company.interns.*', 'company.placements.*']],
                ['label' => 'DTRs', 'route' => 'company.dtrs.index', 'icon' => 'heroicon-o-clipboard-document-check', 'active' => 'company.dtrs.*'],
                ['label' => 'Requests', 'route' => 'company.requests.index', 'icon' => 'heroicon-o-document-text', 'active' => 'company.requests.*'],
                ['label' => 'Certificates', 'route' => 'company.certificates.index', 'icon' => 'heroicon-o-trophy', 'active' => 'company.certificates.*'],
                ['label' => 'History', 'route' => 'company.history.index', 'icon' => 'heroicon-o-archive-box', 'active' => 'company.history.*'],
            ];
        }
```

`resources/views/components/timeline.blade.php`:

```blade
@props(['steps' => []])
<ol {{ $attributes->merge(['class' => 'relative space-y-5 border-l border-stone-200 pl-6 dark:border-stone-800']) }}>
    @foreach ($steps as $step)
        @php
            $state = $step['state'] ?? 'upcoming';
            $dot = match ($state) {
                'done' => 'bg-emerald-500 ring-emerald-100 dark:ring-emerald-500/20',
                'current' => 'bg-brand-600 ring-brand-100 dark:ring-brand-500/20',
                'failed' => 'bg-rose-500 ring-rose-100 dark:ring-rose-500/20',
                default => 'bg-stone-300 ring-stone-100 dark:bg-stone-700 dark:ring-stone-800',
            };
        @endphp
        <li class="relative" data-state="{{ $state }}">
            <span class="absolute -left-[31px] top-1 size-3 rounded-full ring-4 {{ $dot }}" aria-hidden="true"></span>
            <p @class(['text-sm font-semibold', 'text-stone-400 dark:text-stone-500' => $state === 'upcoming'])>{{ $step['label'] }}</p>
            @if (! empty($step['description']))<p class="text-sm text-stone-600 dark:text-stone-300">{{ $step['description'] }}</p>@endif
            @if (! empty($step['at']))<time class="text-xs text-stone-500" datetime="{{ $step['at']->toIso8601String() }}">{{ $step['at']->format('M j, Y g:i A') }}</time>@endif
        </li>
    @endforeach
</ol>
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter="InternshipPolicies|InternshipFileAccess|InternshipComponents|Navigation|PortalRouteIsolation|FileAccess|ClassroomFileAccess"`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add internship policies, private file kinds, portal navigation and timeline component"
```

---

### Task 2: Company postings CRUD (create, edit, close/reopen, delete)

**Files:**
- Create: `app/Actions/{CreatePosting,UpdatePosting,TogglePostingStatus,DeletePosting}.php`, `app/Http/Requests/Company/PostingRequest.php`, `app/Http/Controllers/Company/PostingController.php`
- Create: `resources/views/company/postings/{index,create,edit,_form}.blade.php`
- Modify: `app/Models/InternshipPosting.php` (`isAcceptingApplications()`, `scopeAccepting()`), `routes/web.php` (company group)
- Test: `tests/Unit/Actions/PostingActionsTest.php`, `tests/Feature/Company/PostingsTest.php`

**Interfaces:**
- Consumes: `InternshipPostingPolicy::manage`, `PostingStatus`, `Company::postings()`.
- Produces: `CreatePosting(Company, array $data): InternshipPosting`, `UpdatePosting(InternshipPosting, array $data): InternshipPosting`, `TogglePostingStatus(InternshipPosting): InternshipPosting`, `DeletePosting(InternshipPosting): void` (refuses when applications exist); `InternshipPosting::isAcceptingApplications(): bool` (open and `closing_date` null or today/future), `InternshipPosting::scopeAccepting()`; routes `company.postings.index|create|store|edit|update|toggle|destroy`. `$data` keys: `title, city, description, responsibilities?, closing_date?, required_hours?, vacancies, contact_name, contact_position?, contact_phone?`. Task 5 adds an Applicants link to the index (guarded by `Route::has('company.postings.applicants')`).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/PostingActionsTest.php`:

```php
<?php

use App\Actions\CreatePosting;
use App\Actions\DeletePosting;
use App\Actions\TogglePostingStatus;
use App\Actions\UpdatePosting;
use App\Enums\PostingStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\Company;
use App\Models\InternshipPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

$payload = fn () => [
    'title' => 'QA Intern', 'city' => 'Pasig', 'description' => 'Test web apps.', 'responsibilities' => 'Write test cases.',
    'closing_date' => now()->addMonth()->toDateString(), 'required_hours' => 486, 'vacancies' => 2,
    'contact_name' => 'Ana Cruz', 'contact_position' => 'HR Officer', 'contact_phone' => '09170000000',
];

it('creates an open posting for the company and updates it', function () use ($payload) {
    $company = Company::factory()->registered()->create();

    $posting = app(CreatePosting::class)($company, $payload());
    expect($posting->company_id)->toBe($company->id)->and($posting->status)->toBe(PostingStatus::Open)
        ->and($posting->vacancies)->toBe(2)->and($posting->closing_date->toDateString())->toBe(now()->addMonth()->toDateString());

    app(UpdatePosting::class)($posting, [...$payload(), 'title' => 'Senior QA Intern', 'closing_date' => null, 'required_hours' => null]);
    expect($posting->refresh()->title)->toBe('Senior QA Intern')->and($posting->closing_date)->toBeNull()->and($posting->required_hours)->toBeNull();
});

it('toggles between open and closed and knows when it accepts applications', function () {
    $posting = InternshipPosting::factory()->create(['closing_date' => now()->addDay()->toDateString()]);
    expect($posting->isAcceptingApplications())->toBeTrue();

    app(TogglePostingStatus::class)($posting);
    expect($posting->refresh()->status)->toBe(PostingStatus::Closed)->and($posting->isAcceptingApplications())->toBeFalse();

    app(TogglePostingStatus::class)($posting);
    expect($posting->refresh()->status)->toBe(PostingStatus::Open);

    $posting->update(['closing_date' => now()->subDay()->toDateString()]);
    expect($posting->refresh()->isAcceptingApplications())->toBeFalse()
        ->and(InternshipPosting::accepting()->count())->toBe(0);

    $posting->update(['closing_date' => null]);
    expect($posting->refresh()->isAcceptingApplications())->toBeTrue()->and(InternshipPosting::accepting()->count())->toBe(1);
});

it('deletes a posting without applications but refuses one that has any', function () {
    $empty = InternshipPosting::factory()->create();
    $used = InternshipPosting::factory()->create();
    Application::factory()->for($used, 'posting')->create();

    app(DeletePosting::class)($empty);
    expect(InternshipPosting::whereKey($empty->id)->exists())->toBeFalse();

    expect(fn () => app(DeletePosting::class)($used))->toThrow(DomainRuleViolation::class);
    expect(InternshipPosting::whereKey($used->id)->exists())->toBeTrue();
});
```

`tests/Feature/Company/PostingsTest.php`:

```php
<?php

use App\Enums\PostingStatus;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->company()->create();
    $this->company = $this->user->company;
});

$payload = [
    'title' => 'QA Intern', 'city' => 'Pasig', 'description' => 'Test web apps.', 'responsibilities' => 'Write test cases.',
    'closing_date' => '', 'required_hours' => '', 'vacancies' => 2,
    'contact_name' => 'Ana Cruz', 'contact_position' => 'HR Officer', 'contact_phone' => '09170000000',
];

it('lists only the company’s postings with applicant counts', function () {
    $mine = InternshipPosting::factory()->for($this->company)->create(['title' => 'Web Dev Intern']);
    Application::factory()->for($mine, 'posting')->count(2)->create();
    Application::factory()->for($mine, 'posting')->declined()->create();
    InternshipPosting::factory()->create(['title' => 'Not Mine Intern']);

    $this->actingAs($this->user)->get(route('company.postings.index'))
        ->assertOk()->assertSee('Web Dev Intern')->assertSee('2 pending')->assertSee('3 applicants')->assertDontSee('Not Mine Intern');
});

it('creates a posting', function () use ($payload) {
    $this->actingAs($this->user)->get(route('company.postings.create'))->assertOk()->assertSee('Contact name');

    $this->actingAs($this->user)->post(route('company.postings.store'), $payload)
        ->assertRedirect(route('company.postings.index'))->assertSessionHas('success');

    $posting = InternshipPosting::firstOrFail();
    expect($posting->company_id)->toBe($this->company->id)->and($posting->status)->toBe(PostingStatus::Open)->and($posting->closing_date)->toBeNull();
});

it('validates the form', function () use ($payload) {
    $this->actingAs($this->user)->post(route('company.postings.store'), [...$payload, 'title' => '', 'vacancies' => 0, 'closing_date' => now()->subDay()->toDateString(), 'contact_phone' => str_repeat('1', 31)])
        ->assertSessionHasErrors(['title', 'vacancies', 'closing_date', 'contact_phone']);
    expect(InternshipPosting::count())->toBe(0);
});

it('edits, closes, reopens and deletes only its own postings', function () use ($payload) {
    $mine = InternshipPosting::factory()->for($this->company)->create();
    $theirs = InternshipPosting::factory()->create();

    $this->actingAs($this->user)->get(route('company.postings.edit', $theirs))->assertForbidden();
    $this->actingAs($this->user)->put(route('company.postings.update', $theirs), $payload)->assertForbidden();
    $this->actingAs($this->user)->post(route('company.postings.toggle', $theirs))->assertForbidden();
    $this->actingAs($this->user)->delete(route('company.postings.destroy', $theirs))->assertForbidden();

    $this->actingAs($this->user)->get(route('company.postings.edit', $mine))->assertOk()->assertSee($mine->title);
    $this->actingAs($this->user)->put(route('company.postings.update', $mine), [...$payload, 'title' => 'Renamed'])->assertRedirect(route('company.postings.index'));
    expect($mine->refresh()->title)->toBe('Renamed');

    $this->actingAs($this->user)->post(route('company.postings.toggle', $mine))->assertRedirect();
    expect($mine->refresh()->status)->toBe(PostingStatus::Closed);
    $this->actingAs($this->user)->post(route('company.postings.toggle', $mine))->assertRedirect();
    expect($mine->refresh()->status)->toBe(PostingStatus::Open);

    $this->actingAs($this->user)->delete(route('company.postings.destroy', $mine))->assertRedirect(route('company.postings.index'))->assertSessionHas('success');
    expect(InternshipPosting::whereKey($mine->id)->exists())->toBeFalse();
});

it('refuses to delete a posting that has applications', function () {
    $mine = InternshipPosting::factory()->for($this->company)->create();
    Application::factory()->for($mine, 'posting')->create();

    $this->actingAs($this->user)->from(route('company.postings.index'))->delete(route('company.postings.destroy', $mine))
        ->assertRedirect(route('company.postings.index'))->assertSessionHas('error');
    expect(InternshipPosting::whereKey($mine->id)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="PostingActions|Company.*PostingsTest"`
Expected: FAIL.

- [ ] **Step 3: Model helpers, request, actions**

`app/Models/InternshipPosting.php` — add:

```php
    /** Open and either without a closing date or closing today or later. */
    public function isAcceptingApplications(): bool
    {
        return $this->status === PostingStatus::Open
            && ($this->closing_date === null || $this->closing_date->greaterThanOrEqualTo(today()));
    }

    public function scopeAccepting(Builder $query): Builder
    {
        return $query->open()->where(fn (Builder $q) => $q->whereNull('closing_date')->orWhereDate('closing_date', '>=', today()));
    }
```

`app/Http/Requests/Company/PostingRequest.php`:

```php
<?php

namespace App\Http\Requests\Company;

use App\Models\InternshipPosting;
use Illuminate\Foundation\Http\FormRequest;

class PostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $posting = $this->route('posting');

        return $posting instanceof InternshipPosting
            ? $this->user()->can('manage', $posting)
            : ($this->user()?->isCompany() ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'closing_date' => $this->filled('closing_date') ? $this->input('closing_date') : null,
            'required_hours' => $this->filled('required_hours') ? $this->input('required_hours') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:5000'],
            'responsibilities' => ['nullable', 'string', 'max:5000'],
            'closing_date' => ['nullable', 'date', 'after_or_equal:today'],
            'required_hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'vacancies' => ['required', 'integer', 'min:1', 'max:100'],
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_position' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return ['closing_date.after_or_equal' => 'The closing date cannot be in the past.'];
    }
}
```

`app/Actions/CreatePosting.php`:

```php
<?php

namespace App\Actions;

use App\Enums\PostingStatus;
use App\Models\Company;
use App\Models\InternshipPosting;

class CreatePosting
{
    /** @param  array{title:string, city:string, description:string, responsibilities?:?string, closing_date?:?string, required_hours?:int|string|null, vacancies:int|string, contact_name:string, contact_position?:?string, contact_phone?:?string}  $data */
    public function __invoke(Company $company, array $data): InternshipPosting
    {
        return $company->postings()->create([...self::attributes($data), 'status' => PostingStatus::Open]);
    }

    /** @return array<string, mixed> */
    public static function attributes(array $data): array
    {
        return [
            'title' => $data['title'],
            'city' => $data['city'],
            'description' => $data['description'],
            'responsibilities' => $data['responsibilities'] ?? null,
            'closing_date' => $data['closing_date'] ?: null,
            'required_hours' => isset($data['required_hours']) && $data['required_hours'] !== '' ? (int) $data['required_hours'] : null,
            'vacancies' => (int) $data['vacancies'],
            'contact_name' => $data['contact_name'],
            'contact_position' => $data['contact_position'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
        ];
    }
}
```

`app/Actions/UpdatePosting.php`:

```php
<?php

namespace App\Actions;

use App\Models\InternshipPosting;

class UpdatePosting
{
    /** @param  array<string, mixed>  $data  Same keys as CreatePosting. */
    public function __invoke(InternshipPosting $posting, array $data): InternshipPosting
    {
        $posting->update(CreatePosting::attributes($data));

        return $posting;
    }
}
```

`app/Actions/TogglePostingStatus.php`:

```php
<?php

namespace App\Actions;

use App\Enums\PostingStatus;
use App\Models\InternshipPosting;

class TogglePostingStatus
{
    public function __invoke(InternshipPosting $posting): InternshipPosting
    {
        $posting->update(['status' => $posting->status === PostingStatus::Open ? PostingStatus::Closed : PostingStatus::Open]);

        return $posting;
    }
}
```

`app/Actions/DeletePosting.php`:

```php
<?php

namespace App\Actions;

use App\Exceptions\DomainRuleViolation;
use App\Models\InternshipPosting;

/** Postings with applications are history; close them instead of deleting. */
class DeletePosting
{
    public function __invoke(InternshipPosting $posting): void
    {
        if ($posting->applications()->exists()) {
            throw new DomainRuleViolation('This posting already has applicants. Close it instead of deleting it.');
        }

        $posting->delete();
    }
}
```

- [ ] **Step 4: Controller, routes, views**

`app/Http/Controllers/Company/PostingController.php`:

```php
<?php

namespace App\Http\Controllers\Company;

use App\Actions\CreatePosting;
use App\Actions\DeletePosting;
use App\Actions\TogglePostingStatus;
use App\Actions\UpdatePosting;
use App\Enums\ApplicationStatus;
use App\Enums\PostingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\PostingRequest;
use App\Models\InternshipPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PostingController extends Controller
{
    public function index(Request $request): View
    {
        return view('company.postings.index', [
            'postings' => $request->user()->company->postings()
                ->withCount(['applications', 'applications as pending_applications_count' => fn ($q) => $q->where('status', ApplicationStatus::Pending)])
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('company.postings.create');
    }

    public function store(PostingRequest $request, CreatePosting $create): RedirectResponse
    {
        $posting = $create($request->user()->company, $request->validated());

        return redirect()->route('company.postings.index')->with('success', "“{$posting->title}” is now open for applications.");
    }

    public function edit(InternshipPosting $posting): View
    {
        Gate::authorize('manage', $posting);

        return view('company.postings.edit', ['posting' => $posting]);
    }

    public function update(PostingRequest $request, InternshipPosting $posting, UpdatePosting $update): RedirectResponse
    {
        $update($posting, $request->validated());

        return redirect()->route('company.postings.index')->with('success', "“{$posting->title}” was updated.");
    }

    public function toggle(InternshipPosting $posting, TogglePostingStatus $toggle): RedirectResponse
    {
        Gate::authorize('manage', $posting);

        $toggle($posting);

        return back()->with('success', $posting->status === PostingStatus::Open
            ? "“{$posting->title}” is open again."
            : "“{$posting->title}” is closed to new applications.");
    }

    public function destroy(InternshipPosting $posting, DeletePosting $delete): RedirectResponse
    {
        Gate::authorize('manage', $posting);

        $delete($posting);

        return redirect()->route('company.postings.index')->with('success', "“{$posting->title}” was deleted.");
    }
}
```

`routes/web.php` — replace the company group with:

```php
    Route::prefix('company')->name('company.')->middleware('role:company')->group(function () {
        Route::get('dashboard', Company\DashboardController::class)->name('dashboard');
        Route::get('postings', [Company\PostingController::class, 'index'])->name('postings.index');
        Route::get('postings/create', [Company\PostingController::class, 'create'])->name('postings.create');
        Route::post('postings', [Company\PostingController::class, 'store'])->name('postings.store');
        Route::get('postings/{posting}/edit', [Company\PostingController::class, 'edit'])->name('postings.edit');
        Route::put('postings/{posting}', [Company\PostingController::class, 'update'])->name('postings.update');
        Route::post('postings/{posting}/toggle', [Company\PostingController::class, 'toggle'])->name('postings.toggle');
        Route::delete('postings/{posting}', [Company\PostingController::class, 'destroy'])->name('postings.destroy');
    });
```

`resources/views/company/postings/_form.blade.php` (included with `$posting` nullable):

```blade
<div class="grid gap-5 sm:grid-cols-2">
    <x-form.input name="title" label="Position title" :value="$posting?->title" required placeholder="Junior Web Developer Intern" class="sm:col-span-2" />
    <x-form.input name="city" label="City" :value="$posting?->city" required placeholder="Quezon City" />
    <x-form.input name="vacancies" label="Vacancies" type="number" min="1" max="100" :value="$posting?->vacancies ?? 1" required />
    <x-form.textarea name="description" label="Description" :value="$posting?->description" required rows="5" class="sm:col-span-2" />
    <x-form.textarea name="responsibilities" label="Responsibilities" :value="$posting?->responsibilities" rows="4" class="sm:col-span-2" hint="Optional. One per line works well." />
    <x-form.input name="closing_date" label="Closing date" type="date" :value="$posting?->closing_date?->toDateString()" hint="Leave blank to keep accepting applications." />
    <x-form.input name="required_hours" label="Required hours" type="number" min="1" max="2000" :value="$posting?->required_hours" hint="Optional. Defaults to the school requirement." />
    <x-form.input name="contact_name" label="Contact name" :value="$posting?->contact_name" required />
    <x-form.input name="contact_position" label="Contact position" :value="$posting?->contact_position" />
    <x-form.input name="contact_phone" label="Contact phone" :value="$posting?->contact_phone" />
</div>
```

`resources/views/company/postings/create.blade.php`:

```blade
<x-layouts.app title="New posting">
    <x-page-header title="New internship posting" subtitle="Interns see open postings and apply with their resume and endorsement letter." :breadcrumbs="['Postings' => route('company.postings.index'), 'New posting' => null]" />

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('company.postings.store') }}" class="space-y-6">
            @csrf
            @include('company.postings._form', ['posting' => null])
            <div class="flex items-center justify-end gap-3">
                <x-button variant="secondary" :href="route('company.postings.index')">Cancel</x-button>
                <x-button icon="heroicon-o-megaphone">Publish posting</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
```

`resources/views/company/postings/edit.blade.php`:

```blade
<x-layouts.app :title="'Edit · '.$posting->title">
    <x-page-header :title="'Edit '.$posting->title" :breadcrumbs="['Postings' => route('company.postings.index'), $posting->title => null]">
        <x-slot:actions><x-badge :status="$posting->status" /></x-slot:actions>
    </x-page-header>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('company.postings.update', $posting) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('company.postings._form', ['posting' => $posting])
            <div class="flex items-center justify-end gap-3">
                <x-button variant="secondary" :href="route('company.postings.index')">Cancel</x-button>
                <x-button icon="heroicon-o-check">Save changes</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
```

`resources/views/company/postings/index.blade.php`:

```blade
<x-layouts.app title="Postings">
    <x-page-header title="Internship postings" subtitle="Open postings are visible to every intern.">
        <x-slot:actions><x-button :href="route('company.postings.create')" icon="heroicon-o-plus">New posting</x-button></x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Position</th><th>City</th><th>Closes</th><th>Applicants</th><th>Status</th><th class="text-right">Actions</th></x-slot:head>
            @forelse ($postings as $posting)
                <tr>
                    <td>
                        <p class="font-medium">{{ $posting->title }}</p>
                        <p class="text-xs text-stone-500">{{ $posting->vacancies }} {{ Str::plural('vacancy', $posting->vacancies) }} · posted {{ $posting->created_at->format('M j, Y') }}</p>
                    </td>
                    <td>{{ $posting->city }}</td>
                    <td class="text-stone-500">{{ $posting->closing_date?->format('M j, Y') ?? 'No closing date' }}</td>
                    <td>
                        @if (Route::has('company.postings.applicants'))
                            <a href="{{ route('company.postings.applicants', $posting) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">{{ $posting->applications_count }} applicants</a>
                        @else
                            <span class="font-medium">{{ $posting->applications_count }} applicants</span>
                        @endif
                        <p class="text-xs text-stone-500">{{ $posting->pending_applications_count }} pending</p>
                    </td>
                    <td>
                        <x-badge :status="$posting->status" />
                        @if ($posting->status === \App\Enums\PostingStatus::Open && ! $posting->isAcceptingApplications())<p class="mt-1 text-xs text-amber-600">Closing date passed</p>@endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            <x-button variant="ghost" :href="route('company.postings.edit', $posting)" icon="heroicon-o-pencil-square">Edit</x-button>
                            <form method="POST" action="{{ route('company.postings.toggle', $posting) }}">
                                @csrf
                                <x-button variant="ghost" :icon="$posting->status === \App\Enums\PostingStatus::Open ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open'">{{ $posting->status === \App\Enums\PostingStatus::Open ? 'Close' : 'Reopen' }}</x-button>
                            </form>
                            @if ($posting->applications_count === 0)
                                <x-confirm-form :action="route('company.postings.destroy', $posting)" method="DELETE" :confirm="'Delete “'.$posting->title.'”?'">
                                    <x-button variant="ghost" icon="heroicon-o-trash" class="text-rose-600">Delete</x-button>
                                </x-confirm-form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state title="No postings yet" description="Publish a posting so interns can apply." icon="heroicon-o-megaphone"><x-slot:action><x-button :href="route('company.postings.create')" icon="heroicon-o-plus">New posting</x-button></x-slot:action></x-empty-state></td></tr>
            @endforelse
        </x-table>
    </x-card>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="PostingActions|Company.*PostingsTest|PortalRouteIsolation|InternshipModels"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add company internship postings"
```

---

### Task 3: Interns browse postings and apply with resume and endorsement

**Files:**
- Create: `app/Actions/ApplyToPosting.php`, `app/Http/Requests/Intern/ApplyRequest.php`, `app/Http/Controllers/Intern/PostingController.php`, `app/Http/Controllers/Intern/ApplicationController.php` (store only; Task 4 adds index/cancel), `app/Notifications/ApplicationReceived.php`
- Create: `resources/views/intern/postings/{index,show}.blade.php`
- Modify: `routes/web.php` (intern group)
- Test: `tests/Unit/Actions/ApplyToPostingTest.php`, `tests/Feature/Intern/PostingsTest.php`

**Interfaces:**
- Consumes: `InternshipPosting::accepting()`, `InternshipPostingPolicy::view`, `User::hasActivePlacement()`, `Search::any`.
- Produces: `ApplyToPosting::blocker(User $intern, InternshipPosting): ?string` (the reason the intern cannot apply, or null) and `ApplyToPosting::__invoke(User $intern, InternshipPosting, UploadedFile $resume, UploadedFile $endorsement): Application` (stores `applications/{intern}/{uuid}-resume.pdf` and `-endorsement.pdf`); routes `intern.postings.index` (GET `/intern/internships`, query `q`, `city`), `intern.postings.show` (GET `/intern/internships/{posting}`), `intern.applications.store` (POST `/intern/internships/{posting}/apply`); `ApplicationReceived` notification to the company user (url `company.postings.index` until Task 5 switches it to the applicants page).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/ApplyToPostingTest.php`:

```php
<?php

use App\Actions\ApplyToPosting;
use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\ApplicationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->posting = InternshipPosting::factory()->create();
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->pdf = fn (string $n) => UploadedFile::fake()->create($n, 100, 'application/pdf');
});

it('creates a pending application with both files and tells the company', function () {
    $application = app(ApplyToPosting::class)($this->intern, $this->posting, ($this->pdf)('resume.pdf'), ($this->pdf)('endorsement.pdf'));

    expect($application->status)->toBe(ApplicationStatus::Pending)->and($application->intern_id)->toBe($this->intern->id)
        ->and($application->resume_path)->toStartWith("applications/{$this->intern->id}/")->toEndWith('-resume.pdf')
        ->and($application->endorsement_path)->toEndWith('-endorsement.pdf');
    Storage::disk('local')->assertExists($application->resume_path);
    Storage::disk('local')->assertExists($application->endorsement_path);
    Notification::assertSentTo($this->posting->company->user, ApplicationReceived::class, fn (ApplicationReceived $n) => str_contains($n->toArray($this->posting->company->user)['body'], 'Maria Santos'));
});

it('refuses closed or expired postings, placed interns and duplicates, storing nothing', function () {
    $closed = InternshipPosting::factory()->closed()->create();
    $expired = InternshipPosting::factory()->create(['closing_date' => now()->subDay()->toDateString()]);

    expect(ApplyToPosting::blocker($this->intern, $closed))->toContain('closed');
    expect(fn () => app(ApplyToPosting::class)($this->intern, $closed, ($this->pdf)('r.pdf'), ($this->pdf)('e.pdf')))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(ApplyToPosting::class)($this->intern, $expired, ($this->pdf)('r.pdf'), ($this->pdf)('e.pdf')))->toThrow(DomainRuleViolation::class);

    Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->declined()->create();
    expect(ApplyToPosting::blocker($this->intern, $this->posting))->toContain('already applied');
    expect(fn () => app(ApplyToPosting::class)($this->intern, $this->posting, ($this->pdf)('r.pdf'), ($this->pdf)('e.pdf')))->toThrow(DomainRuleViolation::class);

    $placed = User::factory()->intern()->create();
    Placement::factory()->for($placed, 'intern')->create();
    expect(ApplyToPosting::blocker($placed, $this->posting))->toContain('already placed');
    expect(fn () => app(ApplyToPosting::class)($placed, $this->posting, ($this->pdf)('r.pdf'), ($this->pdf)('e.pdf')))->toThrow(DomainRuleViolation::class);

    expect(Storage::disk('local')->allFiles())->toBe([])->and(Application::count())->toBe(1);
    expect(ApplyToPosting::blocker(User::factory()->intern()->create(), $this->posting))->toBeNull();
});
```

`tests/Feature/Intern/PostingsTest.php`:

```php
<?php

use App\Models\Application;
use App\Models\Company;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\ApplicationReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->intern = User::factory()->intern()->create();
    $this->company = Company::factory()->registered()->create(['name' => 'TechNova']);
    $this->posting = InternshipPosting::factory()->for($this->company)->create(['title' => 'Web Dev Intern', 'city' => 'Pasig']);
});

it('lists postings that accept applications, with search and city filter', function () {
    InternshipPosting::factory()->closed()->create(['title' => 'Closed Intern']);
    InternshipPosting::factory()->create(['title' => 'Expired Intern', 'closing_date' => now()->subDay()->toDateString()]);
    InternshipPosting::factory()->create(['title' => 'Data Intern', 'city' => 'Makati']);

    $this->actingAs($this->intern)->get(route('intern.postings.index'))
        ->assertOk()->assertSee('Web Dev Intern')->assertSee('TechNova')->assertSee('Data Intern')->assertDontSee('Closed Intern')->assertDontSee('Expired Intern');
    $this->actingAs($this->intern)->get(route('intern.postings.index', ['q' => 'data']))->assertSee('Data Intern')->assertDontSee('Web Dev Intern');
    $this->actingAs($this->intern)->get(route('intern.postings.index', ['city' => 'Pasig']))->assertSee('Web Dev Intern')->assertDontSee('Data Intern');
});

it('shows a posting with the apply form, and hides closed ones', function () {
    $this->actingAs($this->intern)->get(route('intern.postings.show', $this->posting))
        ->assertOk()->assertSee('Web Dev Intern')->assertSee($this->posting->contact_name)->assertSee('name="resume"', false)->assertSee('name="endorsement"', false);

    $closed = InternshipPosting::factory()->closed()->create();
    $this->actingAs($this->intern)->get(route('intern.postings.show', $closed))->assertForbidden();
});

it('applies with two PDFs and notifies the company', function () {
    Notification::fake();

    $this->actingAs($this->intern)->post(route('intern.applications.store', $this->posting), [
        'resume' => UploadedFile::fake()->create('resume.pdf', 200, 'application/pdf'),
        'endorsement' => UploadedFile::fake()->create('endorsement.pdf', 200, 'application/pdf'),
    ])->assertRedirect(route('intern.postings.show', $this->posting))->assertSessionHas('success');

    $application = Application::firstOrFail();
    Storage::disk('local')->assertExists($application->resume_path);
    Notification::assertSentTo($this->company->user, ApplicationReceived::class);

    $this->actingAs($this->intern)->get(route('intern.postings.show', $this->posting))->assertSee('already applied')->assertDontSee('name="resume"', false);
});

it('validates the files and explains why an intern cannot apply', function () {
    $this->actingAs($this->intern)->post(route('intern.applications.store', $this->posting), [
        'resume' => UploadedFile::fake()->create('resume.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ])->assertSessionHasErrors(['resume', 'endorsement']);

    Placement::factory()->for($this->intern, 'intern')->create();
    $this->actingAs($this->intern)->get(route('intern.postings.show', $this->posting))->assertSee('already placed')->assertDontSee('name="resume"', false);
    $this->actingAs($this->intern)->from(route('intern.postings.show', $this->posting))->post(route('intern.applications.store', $this->posting), [
        'resume' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
        'endorsement' => UploadedFile::fake()->create('e.pdf', 10, 'application/pdf'),
    ])->assertRedirect(route('intern.postings.show', $this->posting))->assertSessionHas('error');
    expect(Application::count())->toBe(0);
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="ApplyToPosting|Intern.*PostingsTest"`
Expected: FAIL.

- [ ] **Step 3: Action, request, notification**

`app/Actions/ApplyToPosting.php`:

```php
<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\User;
use App\Notifications\ApplicationReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplyToPosting
{
    /** Why this intern cannot apply right now, or null when they can. */
    public static function blocker(User $intern, InternshipPosting $posting): ?string
    {
        if (! $posting->isAcceptingApplications()) {
            return 'This posting is closed to new applications.';
        }

        if ($intern->hasActivePlacement()) {
            return 'You are already placed with a company. Leave it first if you want to apply elsewhere.';
        }

        if ($posting->applications()->where('intern_id', $intern->id)->exists()) {
            return 'You have already applied to this posting.';
        }

        return null;
    }

    public function __invoke(User $intern, InternshipPosting $posting, UploadedFile $resume, UploadedFile $endorsement): Application
    {
        if ($reason = self::blocker($intern, $posting)) {
            throw new DomainRuleViolation($reason);
        }

        $id = (string) Str::uuid();
        $resumePath = $resume->storeAs("applications/{$intern->id}", "{$id}-resume.pdf", 'local');
        $endorsementPath = $endorsement->storeAs("applications/{$intern->id}", "{$id}-endorsement.pdf", 'local');

        $application = DB::transaction(fn () => $posting->applications()->create([
            'intern_id' => $intern->id,
            'resume_path' => $resumePath,
            'endorsement_path' => $endorsementPath,
            'status' => ApplicationStatus::Pending,
        ]));

        $posting->company->user?->notify(new ApplicationReceived($application->load('intern')));

        return $application;
    }
}
```

`app/Http/Requests/Intern/ApplyRequest.php`:

```php
<?php

namespace App\Http\Requests\Intern;

use Illuminate\Foundation\Http\FormRequest;

class ApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isIntern() && $this->user()->can('view', $this->route('posting'));
    }

    public function rules(): array
    {
        $pdf = ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('wiis.uploads.max_pdf_kb')];

        return ['resume' => $pdf, 'endorsement' => $pdf];
    }

    public function messages(): array
    {
        return [
            'resume.mimetypes' => 'Your resume must be a PDF file.',
            'endorsement.mimetypes' => 'The endorsement letter must be a PDF file.',
        ];
    }
}
```

`app/Notifications/ApplicationReceived.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class ApplicationReceived extends Notification
{
    use Queueable;

    public function __construct(public Application $application) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $posting = $this->application->posting;

        return [
            'title' => "New applicant for {$posting->title}",
            'body' => "{$this->application->intern->name} applied with a resume and endorsement letter.",
            'url' => Route::has('company.postings.applicants') ? route('company.postings.applicants', $posting) : route('company.postings.index'),
            'icon' => 'heroicon-o-user-plus',
        ];
    }
}
```

- [ ] **Step 4: Controllers, routes, views**

`app/Http/Controllers/Intern/PostingController.php`:

```php
<?php

namespace App\Http\Controllers\Intern;

use App\Actions\ApplyToPosting;
use App\Http\Controllers\Controller;
use App\Models\InternshipPosting;
use App\Support\Search;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PostingController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $city = trim((string) $request->query('city'));

        return view('intern.postings.index', [
            'postings' => InternshipPosting::accepting()
                ->with('company')
                ->when($q !== '', fn (Builder $query) => $query->where(function (Builder $w) use ($q) {
                    Search::any($w, ['title', 'description', 'city'], $q)
                        ->orWhereHas('company', fn (Builder $c) => Search::like($c, 'name', $q));
                }))
                ->when($city !== '', fn (Builder $query) => $query->where('city', $city))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
            'cities' => InternshipPosting::accepting()->distinct()->orderBy('city')->pluck('city'),
            'placed' => $request->user()->hasActivePlacement(),
        ]);
    }

    public function show(Request $request, InternshipPosting $posting): View
    {
        Gate::authorize('view', $posting);

        return view('intern.postings.show', [
            'posting' => $posting->load('company'),
            'blocker' => ApplyToPosting::blocker($request->user(), $posting),
        ]);
    }
}
```

`app/Http/Controllers/Intern/ApplicationController.php` (Task 4 adds `index` and `cancel`):

```php
<?php

namespace App\Http\Controllers\Intern;

use App\Actions\ApplyToPosting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intern\ApplyRequest;
use App\Models\InternshipPosting;
use Illuminate\Http\RedirectResponse;

class ApplicationController extends Controller
{
    public function store(ApplyRequest $request, InternshipPosting $posting, ApplyToPosting $apply): RedirectResponse
    {
        $apply($request->user(), $posting, $request->file('resume'), $request->file('endorsement'));

        return redirect()->route('intern.postings.show', $posting)->with('success', "Application sent to {$posting->company->name}. You will be notified about the next step.");
    }
}
```

Routes — add inside the intern group:

```php
        Route::get('internships', [Intern\PostingController::class, 'index'])->name('postings.index');
        Route::get('internships/{posting}', [Intern\PostingController::class, 'show'])->name('postings.show');
        Route::post('internships/{posting}/apply', [Intern\ApplicationController::class, 'store'])->name('applications.store');
```

`resources/views/intern/postings/index.blade.php`:

```blade
<x-layouts.app title="Internships">
    <x-page-header title="Internship postings" subtitle="Open positions from approved partner companies." />

    @if ($placed)
        <div class="flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
            <x-heroicon-o-information-circle class="mt-0.5 size-4 shrink-0" />
            <p>You are currently placed with a company, so you cannot apply to new postings. You can still browse.</p>
        </div>
    @endif

    <x-search-form :action="route('intern.postings.index')" placeholder="Search by title, company or city…">
        <select name="city" class="input sm:max-w-xs" aria-label="City">
            <option value="">All cities</option>
            @foreach ($cities as $city)<option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>@endforeach
        </select>
    </x-search-form>

    @if ($postings->isEmpty())
        <x-card><x-empty-state title="No open postings" description="Check back soon, or ask your adviser about partner companies." icon="heroicon-o-briefcase" /></x-card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($postings as $posting)
                <x-card class="flex flex-col">
                    <div class="flex items-start gap-3">
                        @if ($posting->company->logo_path)
                            <img src="{{ Storage::disk('public')->url($posting->company->logo_path) }}" alt="" class="size-11 rounded-xl object-cover">
                        @else
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10"><x-heroicon-o-building-office-2 class="size-5" /></span>
                        @endif
                        <div class="min-w-0">
                            <a href="{{ route('intern.postings.show', $posting) }}" class="font-display text-lg font-semibold hover:underline">{{ $posting->title }}</a>
                            <p class="text-sm text-stone-500">{{ $posting->company->name }} · {{ $posting->city }}</p>
                        </div>
                    </div>
                    <p class="mt-3 line-clamp-3 text-sm text-stone-600 dark:text-stone-300">{{ $posting->description }}</p>
                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-stone-500">
                        <x-badge color="teal">{{ $posting->vacancies }} {{ Str::plural('slot', $posting->vacancies) }}</x-badge>
                        @if ($posting->required_hours)<x-badge color="gray">{{ $posting->required_hours }} h</x-badge>@endif
                        <span class="ml-auto">{{ $posting->closing_date ? 'Closes '.$posting->closing_date->format('M j') : 'Open until filled' }}</span>
                    </div>
                    <x-button variant="secondary" :href="route('intern.postings.show', $posting)" class="mt-4 w-full">View and apply</x-button>
                </x-card>
            @endforeach
        </div>
        <x-pagination :paginator="$postings" />
    @endif
</x-layouts.app>
```

`resources/views/intern/postings/show.blade.php`:

```blade
<x-layouts.app :title="$posting->title">
    <x-page-header :title="$posting->title" :subtitle="$posting->company->name.' · '.$posting->city" :breadcrumbs="['Internships' => route('intern.postings.index'), $posting->title => null]">
        <x-slot:actions>
            <x-badge color="teal">{{ $posting->vacancies }} {{ Str::plural('slot', $posting->vacancies) }}</x-badge>
            @if ($posting->closing_date)<x-badge color="gray">Closes {{ $posting->closing_date->format('M j, Y') }}</x-badge>@endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card title="About the role">
                <p class="whitespace-pre-line text-sm leading-6 text-stone-700 dark:text-stone-200">{{ $posting->description }}</p>
            </x-card>
            @if ($posting->responsibilities)
                <x-card title="Responsibilities">
                    <p class="whitespace-pre-line text-sm leading-6 text-stone-700 dark:text-stone-200">{{ $posting->responsibilities }}</p>
                </x-card>
            @endif
            <x-card title="About the company">
                <p class="text-sm leading-6 text-stone-700 dark:text-stone-200">{{ $posting->company->about ?? 'No description provided.' }}</p>
                <x-detail-list class="mt-4" :items="[
                    'Type' => $posting->company->type,
                    'Address' => $posting->company->address,
                    'Website' => $posting->company->website,
                    'Required hours' => $posting->required_hours ? $posting->required_hours.' h' : 'School requirement',
                ]" />
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Apply">
                @if ($blocker)
                    <p class="text-sm text-stone-600 dark:text-stone-300">{{ $blocker }}</p>
                    @if (Route::has('intern.applications.index'))<x-button variant="secondary" :href="route('intern.applications.index')" class="mt-4 w-full">My applications</x-button>@endif
                @else
                    <form method="POST" action="{{ route('intern.applications.store', $posting) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <x-form.file name="resume" label="Resume (PDF)" accept="application/pdf" required />
                        <x-form.file name="endorsement" label="Endorsement letter (PDF)" accept="application/pdf" required :hint="'PDF only, up to '.(int) (config('wiis.uploads.max_pdf_kb') / 1024).' MB each.'" />
                        <x-button class="w-full" icon="heroicon-o-paper-airplane">Send application</x-button>
                    </form>
                @endif
            </x-card>
            <x-card title="Contact">
                <x-detail-list class="sm:grid-cols-1" :items="['Name' => $posting->contact_name, 'Position' => $posting->contact_position, 'Phone' => $posting->contact_phone]" />
            </x-card>
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="ApplyToPosting|Intern.*PostingsTest|PortalRouteIsolation|Navigation"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: let interns browse postings and apply with resume and endorsement"
```

---

### Task 4: Intern "My applications" with a status timeline and cancel

**Files:**
- Create: `app/Actions/CancelApplication.php`, `app/Support/ApplicationTimeline.php`, `resources/views/intern/applications/index.blade.php`
- Modify: `app/Http/Controllers/Intern/ApplicationController.php` (`index`, `cancel`), `routes/web.php`
- Test: `tests/Unit/Actions/CancelApplicationTest.php`, `tests/Unit/Support/ApplicationTimelineTest.php`, `tests/Feature/Intern/ApplicationsTest.php`

**Interfaces:**
- Consumes: `ApplicationPolicy::cancel`, `ApplicationStatus::isOpen()`, `<x-timeline>`, `Application::interview()`.
- Produces: `CancelApplication(Application): Application` (open → cancelled, `decided_at` now); `ApplicationTimeline::steps(Application): array` (steps for `<x-timeline>`; requires `interview` loaded or null); routes `intern.applications.index` (GET `/intern/applications`), `intern.applications.cancel` (POST `/intern/applications/{application}/cancel`).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/CancelApplicationTest.php`:

```php
<?php

use App\Actions\CancelApplication;
use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cancels a pending or for-interview application and refuses decided ones', function () {
    $pending = Application::factory()->create();
    $forInterview = Application::factory()->forInterview()->create();
    $accepted = Application::factory()->accepted()->create();
    $declined = Application::factory()->declined()->create();

    app(CancelApplication::class)($pending);
    app(CancelApplication::class)($forInterview);
    expect($pending->refresh()->status)->toBe(ApplicationStatus::Cancelled)->and($pending->decided_at)->not->toBeNull()
        ->and($forInterview->refresh()->status)->toBe(ApplicationStatus::Cancelled);

    expect(fn () => app(CancelApplication::class)($accepted))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(CancelApplication::class)($declined))->toThrow(DomainRuleViolation::class);
    expect($accepted->refresh()->status)->toBe(ApplicationStatus::Accepted);
});
```

`tests/Unit/Support/ApplicationTimelineTest.php`:

```php
<?php

use App\Models\Application;
use App\Models\Interview;
use App\Support\ApplicationTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function timelineStates(Application $application): array
{
    return collect(ApplicationTimeline::steps($application->load('interview')))->pluck('state', 'label')->all();
}

it('walks pending → interview → decision', function () {
    $pending = Application::factory()->create();
    expect(timelineStates($pending))->toBe(['Applied' => 'done', 'Interview' => 'upcoming', 'Decision' => 'upcoming']);

    $interview = Interview::factory()->create(['venue' => 'Google Meet']);
    $steps = ApplicationTimeline::steps($interview->application->load('interview'));
    expect($steps[1]['state'])->toBe('current')->and($steps[1]['description'])->toContain('Google Meet');

    $accepted = Application::factory()->accepted()->create(['decided_at' => now()]);
    expect(timelineStates($accepted))->toBe(['Applied' => 'done', 'Interview' => 'done', 'Decision' => 'done']);
});

it('marks declined and cancelled applications as failed with the reason', function () {
    $declined = Application::factory()->declined()->create(['decline_reason' => 'Position filled', 'decided_at' => now()]);
    $steps = ApplicationTimeline::steps($declined->load('interview'));
    expect($steps[2]['state'])->toBe('failed')->and($steps[2]['label'])->toBe('Declined')->and($steps[2]['description'])->toBe('Position filled');

    $cancelled = Application::factory()->create(['status' => \App\Enums\ApplicationStatus::Cancelled, 'decided_at' => now()]);
    $steps = ApplicationTimeline::steps($cancelled->load('interview'));
    expect($steps[2]['state'])->toBe('failed')->and($steps[2]['label'])->toBe('Cancelled');
});
```

`tests/Feature/Intern/ApplicationsTest.php`:

```php
<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\Interview;
use App\Models\User;

beforeEach(fn () => $this->intern = User::factory()->intern()->create());

it('lists the intern’s applications with their timelines and cancel buttons', function () {
    $posting = InternshipPosting::factory()->create(['title' => 'Web Dev Intern']);
    $mine = Application::factory()->for($posting, 'posting')->for($this->intern, 'intern')->forInterview()->create();
    Interview::factory()->for($mine)->create(['venue' => 'Zoom']);
    $declined = Application::factory()->for($this->intern, 'intern')->declined()->create(['decline_reason' => 'Position filled']);
    Application::factory()->create(['intern_id' => User::factory()->intern()->create()->id]);

    $this->actingAs($this->intern)->get(route('intern.applications.index'))
        ->assertOk()->assertSee('Web Dev Intern')->assertSee($posting->company->name)->assertSee('Zoom')->assertSee('Position filled')
        ->assertSee(route('intern.applications.cancel', $mine))->assertDontSee(route('intern.applications.cancel', $declined))
        ->assertSee(route('files.show', ['application-resume', $mine->id]));
});

it('cancels an open application and refuses others', function () {
    $mine = Application::factory()->for($this->intern, 'intern')->create();
    $theirs = Application::factory()->create();
    $accepted = Application::factory()->for($this->intern, 'intern')->accepted()->create();

    $this->actingAs($this->intern)->post(route('intern.applications.cancel', $theirs))->assertForbidden();
    $this->actingAs($this->intern)->post(route('intern.applications.cancel', $accepted))->assertForbidden();
    $this->actingAs($this->intern)->post(route('intern.applications.cancel', $mine))->assertRedirect(route('intern.applications.index'))->assertSessionHas('success');

    expect($mine->refresh()->status)->toBe(ApplicationStatus::Cancelled);
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="CancelApplication|ApplicationTimeline|Intern.*ApplicationsTest"`
Expected: FAIL.

- [ ] **Step 3: Action and timeline helper**

`app/Actions/CancelApplication.php`:

```php
<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;

class CancelApplication
{
    public function __invoke(Application $application): Application
    {
        if (! $application->status->isOpen()) {
            throw new DomainRuleViolation('Only pending or for-interview applications can be cancelled.');
        }

        $application->update(['status' => ApplicationStatus::Cancelled, 'decided_at' => now()]);

        return $application;
    }
}
```

`app/Support/ApplicationTimeline.php`:

```php
<?php

namespace App\Support;

use App\Enums\ApplicationStatus;
use App\Models\Application;

/** Builds the steps for <x-timeline> from an application (with `interview` loaded). */
class ApplicationTimeline
{
    /** @return array<int, array{label:string, description?:string, at?:\Illuminate\Support\Carbon, state:string}> */
    public static function steps(Application $application): array
    {
        $interview = $application->interview;
        $status = $application->status;

        $interviewStep = match (true) {
            $interview !== null => [
                'label' => 'Interview',
                'description' => "{$interview->title} · {$interview->venue} · {$interview->scheduled_on->format('M j, Y')} ".
                    \Illuminate\Support\Carbon::parse($interview->starts_at)->format('g:i A'),
                'state' => $status === ApplicationStatus::ForInterview ? 'current' : 'done',
            ],
            $status === ApplicationStatus::Pending => ['label' => 'Interview', 'description' => 'Waiting for the company to review your application.', 'state' => 'upcoming'],
            $status === ApplicationStatus::Accepted => ['label' => 'Interview', 'description' => 'Accepted without an interview.', 'state' => 'done'],
            default => ['label' => 'Interview', 'description' => 'No interview was scheduled.', 'state' => 'upcoming'],
        };

        $decisionStep = match ($status) {
            ApplicationStatus::Accepted => ['label' => 'Accepted', 'description' => 'Join the company with its company code on My internship.', 'at' => $application->decided_at, 'state' => 'done'],
            ApplicationStatus::Declined => ['label' => 'Declined', 'description' => $application->decline_reason ?? 'The company declined your application.', 'at' => $application->decided_at, 'state' => 'failed'],
            ApplicationStatus::Cancelled => ['label' => 'Cancelled', 'description' => 'You withdrew this application.', 'at' => $application->decided_at, 'state' => 'failed'],
            default => ['label' => 'Decision', 'state' => 'upcoming'],
        };

        return [
            ['label' => 'Applied', 'at' => $application->created_at, 'state' => 'done'],
            $interviewStep,
            $decisionStep,
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, view**

Add to `Intern\ApplicationController` (imports: `App\Actions\CancelApplication`, `App\Models\Application`, `App\Support\ApplicationTimeline`, `Illuminate\Http\Request`, `Illuminate\Support\Facades\Gate`, `Illuminate\View\View`):

```php
    public function index(Request $request): View
    {
        $applications = $request->user()->applications()->with(['posting.company', 'interview'])->get();

        return view('intern.applications.index', [
            'applications' => $applications,
            'timelines' => $applications->mapWithKeys(fn (Application $a) => [$a->id => ApplicationTimeline::steps($a)]),
        ]);
    }

    public function cancel(Application $application, CancelApplication $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $application);

        $cancel($application);

        return redirect()->route('intern.applications.index')->with('success', 'Application cancelled.');
    }
```

Routes — add inside the intern group:

```php
        Route::get('applications', [Intern\ApplicationController::class, 'index'])->name('applications.index');
        Route::post('applications/{application}/cancel', [Intern\ApplicationController::class, 'cancel'])->name('applications.cancel');
```

`resources/views/intern/applications/index.blade.php`:

```blade
<x-layouts.app title="My applications">
    <x-page-header title="My applications" subtitle="Track every application from submission to decision.">
        <x-slot:actions><x-button variant="secondary" :href="route('intern.postings.index')" icon="heroicon-o-magnifying-glass">Browse postings</x-button></x-slot:actions>
    </x-page-header>

    @if ($applications->isEmpty())
        <x-card><x-empty-state title="No applications yet" description="Find an internship posting and apply with your resume and endorsement letter." icon="heroicon-o-paper-airplane"><x-slot:action><x-button :href="route('intern.postings.index')">Browse postings</x-button></x-slot:action></x-empty-state></x-card>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($applications as $application)
                <x-card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-display text-lg font-semibold">{{ $application->posting->title }}</p>
                            <p class="text-sm text-stone-500">{{ $application->posting->company->name }} · {{ $application->posting->city }}</p>
                        </div>
                        <x-badge :status="$application->status" />
                    </div>
                    <x-timeline :steps="$timelines[$application->id]" class="mt-5" />
                    <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-stone-100 pt-4 dark:border-stone-800">
                        <a href="{{ route('files.show', ['application-resume', $application]) }}" target="_blank" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">Resume</a>
                        <span class="text-stone-300">·</span>
                        <a href="{{ route('files.show', ['application-endorsement', $application]) }}" target="_blank" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">Endorsement</a>
                        @can('cancel', $application)
                            <x-confirm-form :action="route('intern.applications.cancel', $application)" confirm="Withdraw this application?" class="ml-auto">
                                <x-button variant="ghost" icon="heroicon-o-x-mark" class="text-rose-600">Cancel application</x-button>
                            </x-confirm-form>
                        @endcan
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="CancelApplication|ApplicationTimeline|Intern.*ApplicationsTest|Intern.*PostingsTest|PortalRouteIsolation"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add intern application tracking with timeline and cancel"
```

---

### Task 5: Company applicants per posting (unplaced only) and the applicant profile page

**Files:**
- Create: `app/Http/Controllers/Company/ApplicantController.php`, `app/Http/Controllers/Company/ApplicationController.php` (show only; Task 6 adds interview/decline, Task 7 adds accept)
- Create: `resources/views/company/applicants/index.blade.php`, `resources/views/company/applications/show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Company/ApplicantsTest.php`

**Interfaces:**
- Consumes: `InternshipPostingPolicy::manage`, `ApplicationPolicy::view`, `OjtHoursService`.
- Produces: routes `company.postings.applicants` (GET `/company/postings/{posting}/applicants?status=pending|for_interview|accepted|declined`), `company.applications.show` (GET `/company/applications/{application}`). The profile view keeps the placeholder `{{-- Task 6 adds the interview and decline controls here --}}`. Only applicants without an active placement are listed (spec).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Company/ApplicantsTest.php`:

```php
<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ClassSection;
use App\Models\InternshipPosting;
use App\Models\Interview;
use App\Models\Placement;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->company()->create();
    $this->posting = InternshipPosting::factory()->for($this->user->company)->create(['title' => 'Web Dev Intern']);
});

it('lists unplaced applicants grouped by status', function () {
    $pending = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $pending->internProfile()->update(['student_number' => '21-0001', 'class_section_id' => ClassSection::factory()->create(['course_code' => 'CC101', 'section' => 'SBIT-4C'])->id]);
    Application::factory()->for($this->posting, 'posting')->for($pending, 'intern')->create();
    $placed = User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Placed']);
    Placement::factory()->for($placed, 'intern')->create();
    Application::factory()->for($this->posting, 'posting')->for($placed, 'intern')->create();
    $interviewee = User::factory()->intern()->create(['first_name' => 'Ana', 'last_name' => 'Interview']);
    Application::factory()->for($this->posting, 'posting')->for($interviewee, 'intern')->forInterview()->create();

    $this->actingAs($this->user)->get(route('company.postings.applicants', $this->posting))
        ->assertOk()->assertSee('Web Dev Intern')->assertSee('Maria Santos')->assertSee('21-0001')->assertSee('CC101 · SBIT-4C')
        ->assertDontSee('Juan Placed')->assertDontSee('Ana Interview');
    $this->actingAs($this->user)->get(route('company.postings.applicants', [$this->posting, 'status' => 'for_interview']))
        ->assertSee('Ana Interview')->assertDontSee('Maria Santos');
    $this->actingAs($this->user)->get(route('company.postings.applicants', [$this->posting, 'status' => 'bogus']))->assertNotFound();
    $this->actingAs(User::factory()->company()->create())->get(route('company.postings.applicants', $this->posting))->assertForbidden();
});

it('shows an applicant profile with documents, hours and interview details', function () {
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com']);
    $intern->internProfile()->update(['student_number' => '21-0001', 'about' => 'Eager to learn.', 'total_hours' => 120]);
    $application = Application::factory()->for($this->posting, 'posting')->for($intern, 'intern')->forInterview()->create();
    Interview::factory()->for($application)->create(['venue' => 'Google Meet']);

    $this->actingAs($this->user)->get(route('company.applications.show', $application))
        ->assertOk()->assertSee('Maria Santos')->assertSee('maria@example.com')->assertSee('21-0001')->assertSee('Eager to learn.')->assertSee('120')
        ->assertSee(route('files.show', ['application-resume', $application->id]))->assertSee(route('files.show', ['application-endorsement', $application->id]))
        ->assertSee('Google Meet')->assertSee('For Interview');

    $this->actingAs(User::factory()->company()->create())->get(route('company.applications.show', $application))->assertForbidden();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter="Company.*ApplicantsTest"`
Expected: FAIL — routes not defined.

- [ ] **Step 3: Controllers and routes**

`app/Http/Controllers/Company/ApplicantController.php`:

```php
<?php

namespace App\Http\Controllers\Company;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\InternshipPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApplicantController extends Controller
{
    public const GROUPS = ['pending', 'for_interview', 'accepted', 'declined'];

    public function __invoke(Request $request, InternshipPosting $posting): View
    {
        Gate::authorize('manage', $posting);

        $group = (string) $request->query('status', 'pending');
        abort_unless(in_array($group, self::GROUPS, true), 404);

        // Spec: only unplaced interns appear as applicants.
        $base = Application::query()->where('internship_posting_id', $posting->id)->whereDoesntHave('intern.activePlacement');

        $counts = collect(self::GROUPS)->mapWithKeys(fn (string $g) => [$g => (clone $base)->where('status', ApplicationStatus::from($g))->count()])->all();

        return view('company.applicants.index', [
            'posting' => $posting,
            'group' => $group,
            'counts' => $counts,
            'applications' => (clone $base)->where('status', ApplicationStatus::from($group))
                ->with(['intern.internProfile.classSection', 'interview'])
                ->latest()->paginate(20)->withQueryString(),
        ]);
    }
}
```

`app/Http/Controllers/Company/ApplicationController.php`:

```php
<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\OjtHoursService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function show(Application $application, OjtHoursService $hours): View
    {
        Gate::authorize('view', $application);

        $application->load(['intern.internProfile.classSection.adviser', 'posting', 'interview']);

        return view('company.applications.show', [
            'application' => $application,
            'intern' => $application->intern,
            'profile' => $application->intern->internProfile,
            'hours' => $hours,
        ]);
    }
}
```

Routes — add inside the company group:

```php
        Route::get('postings/{posting}/applicants', Company\ApplicantController::class)->name('postings.applicants');
        Route::get('applications/{application}', [Company\ApplicationController::class, 'show'])->name('applications.show');
```

- [ ] **Step 4: Views**

`resources/views/company/applicants/index.blade.php`:

```blade
<x-layouts.app :title="'Applicants · '.$posting->title">
    <x-page-header :title="$posting->title" :subtitle="'Applicants · '.$posting->city" :breadcrumbs="['Postings' => route('company.postings.index'), $posting->title => null]">
        <x-slot:actions><x-badge :status="$posting->status" /><x-button variant="secondary" :href="route('company.postings.edit', $posting)" icon="heroicon-o-pencil-square">Edit posting</x-button></x-slot:actions>
    </x-page-header>

    <nav class="flex flex-wrap gap-2" aria-label="Applicant groups">
        @foreach (\App\Enums\ApplicationStatus::cases() as $status)
            @continue(! in_array($status->value, \App\Http\Controllers\Company\ApplicantController::GROUPS, true))
            <a href="{{ route('company.postings.applicants', [$posting, 'status' => $status->value]) }}"
               @class(['inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                       'bg-brand-600 text-white ring-brand-600' => $group === $status->value,
                       'bg-white text-stone-600 ring-stone-300 hover:bg-stone-50 dark:bg-stone-900 dark:text-stone-300 dark:ring-stone-700' => $group !== $status->value])
               @if ($group === $status->value) aria-current="page" @endif>
                {{ $status->label() }} <span class="rounded-full bg-black/10 px-1.5 text-xs tabular-nums dark:bg-white/10">{{ $counts[$status->value] }}</span>
            </a>
        @endforeach
    </nav>

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Applicant</th><th>Class</th><th>Applied</th><th>Documents</th><th>Status</th><th class="text-right"></th></x-slot:head>
            @forelse ($applications as $application)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$application->intern" size="sm" />
                            <div class="min-w-0"><p class="font-medium">{{ $application->intern->name }}</p><p class="font-mono text-xs text-stone-500">{{ $application->intern->internProfile?->student_number }}</p></div>
                        </div>
                    </td>
                    <td>{{ $application->intern->internProfile?->classSection?->display_name ?? '—' }}</td>
                    <td class="text-stone-500">{{ $application->created_at->format('M j, Y') }}</td>
                    <td class="space-x-2 text-sm">
                        <a href="{{ route('files.show', ['application-resume', $application]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Resume</a>
                        <a href="{{ route('files.show', ['application-endorsement', $application]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Endorsement</a>
                    </td>
                    <td>
                        <x-badge :status="$application->status" />
                        @if ($application->interview)<p class="mt-1 text-xs text-stone-500">{{ $application->interview->scheduled_on->format('M j') }} · {{ $application->interview->venue }}</p>@endif
                    </td>
                    <td class="text-right"><x-button variant="secondary" :href="route('company.applications.show', $application)">View applicant</x-button></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state :title="'No '.str_replace('_', ' ', $group).' applicants'" description="Only interns who are not yet placed appear here." icon="heroicon-o-user-group" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$applications" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
```

`resources/views/company/applications/show.blade.php`:

```blade
<x-layouts.app :title="$intern->name">
    <x-page-header :title="$intern->full_name" :subtitle="'Applied for '.$application->posting->title.' · '.$application->created_at->format('M j, Y')" :breadcrumbs="['Postings' => route('company.postings.index'), $application->posting->title => route('company.postings.applicants', $application->posting), $intern->name => null]">
        <x-slot:actions><x-badge :status="$application->status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card title="Applicant">
                <div class="flex items-start gap-4">
                    <x-avatar :user="$intern" size="lg" />
                    <div class="min-w-0 flex-1">
                        <x-detail-list :items="[
                            'Email' => $intern->email,
                            'Phone' => $intern->phone,
                            'Student no.' => $profile?->student_number,
                            'Class' => $profile?->classSection?->display_name,
                            'Adviser' => $profile?->classSection?->adviser?->name,
                            'School year' => $profile?->school_year,
                            'OJT hours so far' => ($profile?->total_hours ?? 0).' / '.$hours->required().' h',
                            'Address' => $profile?->present_address,
                        ]" />
                        @if ($profile?->about)<p class="mt-4 text-sm leading-6 text-stone-700 dark:text-stone-200">{{ $profile->about }}</p>@endif
                    </div>
                </div>
            </x-card>

            <x-card title="Documents">
                <div class="flex flex-wrap gap-2">
                    <x-button variant="secondary" :href="route('files.show', ['application-resume', $application])" target="_blank" icon="heroicon-o-document-text">Resume</x-button>
                    <x-button variant="secondary" :href="route('files.show', ['application-endorsement', $application])" target="_blank" icon="heroicon-o-document-text">Endorsement letter</x-button>
                </div>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Interview">
                @if ($application->interview)
                    <x-detail-list class="sm:grid-cols-1" :items="[
                        'Title' => $application->interview->title,
                        'When' => $application->interview->scheduled_on->format('M j, Y').' · '.\Illuminate\Support\Carbon::parse($application->interview->starts_at)->format('g:i A').' – '.\Illuminate\Support\Carbon::parse($application->interview->ends_at)->format('g:i A'),
                        'Venue' => $application->interview->venue,
                        'Link' => $application->interview->link,
                        'Notes' => $application->interview->notes,
                    ]" />
                @else
                    <p class="text-sm text-stone-500">No interview scheduled yet.</p>
                @endif
            </x-card>

            @if ($application->decline_reason)
                <x-card title="Decline reason"><p class="text-sm text-stone-700 dark:text-stone-200">{{ $application->decline_reason }}</p></x-card>
            @endif

            {{-- Task 6 adds the interview and decline controls here --}}
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="Company.*ApplicantsTest|Company.*PostingsTest|PortalRouteIsolation|ApplyToPosting"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: list unplaced applicants per posting and show applicant profiles"
```

---

### Task 6: Schedule an interview or decline an applicant

**Files:**
- Create: `app/Actions/ScheduleInterview.php`, `app/Actions/DecideApplication.php`, `app/Http/Requests/Company/ScheduleInterviewRequest.php`, `app/Http/Requests/Company/DeclineApplicationRequest.php`, `app/Notifications/InterviewScheduled.php`, `app/Notifications/ApplicationDecided.php`
- Modify: `app/Http/Controllers/Company/ApplicationController.php`, `routes/web.php`, `resources/views/company/applications/show.blade.php`
- Test: `tests/Unit/Actions/ApplicationDecisionActionsTest.php`, `tests/Feature/Company/ApplicationDecisionsTest.php`

**Interfaces:**
- Consumes: `ApplicationPolicy::decide`, `Application::interview()` (HasOne), `x-modal`.
- Produces: `ScheduleInterview(Application, array $data): Interview` (`title, venue, link?, scheduled_on (Y-m-d), starts_at (H:i), ends_at (H:i), notes?`; pending → for_interview, re-scheduling allowed while for_interview); `DecideApplication(Application, ApplicationStatus $decision, ?string $reason = null): Application` (accepted|declined from an open status; declined needs a reason; sets `decided_at`); routes `company.applications.interview` (POST `/company/applications/{application}/interview`), `company.applications.decline` (POST `/company/applications/{application}/decline`); notifications `InterviewScheduled` and `ApplicationDecided` to the intern (accepted body names the company code; url `intern.internship.show` once Task 8 exists, else `intern.applications.index`). Task 7 adds `company.applications.accept`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/ApplicationDecisionActionsTest.php`:

```php
<?php

use App\Actions\DecideApplication;
use App\Actions\ScheduleInterview;
use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Notifications\ApplicationDecided;
use App\Notifications\InterviewScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

$slot = ['title' => 'Initial interview', 'venue' => 'Google Meet', 'link' => 'https://meet.google.com/abc', 'scheduled_on' => '2026-10-20', 'starts_at' => '10:00', 'ends_at' => '10:30', 'notes' => 'Bring your portfolio.'];

it('schedules an interview, moves the application to for-interview and notifies the intern', function () use ($slot) {
    Notification::fake();
    $application = Application::factory()->create();

    $interview = app(ScheduleInterview::class)($application, $slot);

    expect($application->refresh()->status)->toBe(ApplicationStatus::ForInterview)
        ->and($interview->scheduled_on->toDateString())->toBe('2026-10-20')->and($interview->starts_at)->toBe('10:00:00')->and($interview->venue)->toBe('Google Meet');
    Notification::assertSentTo($application->intern, InterviewScheduled::class, fn (InterviewScheduled $n) => str_contains($n->toArray($application->intern)['body'], 'Google Meet'));

    app(ScheduleInterview::class)($application, [...$slot, 'venue' => 'Zoom']);
    expect($application->interview()->count())->toBe(1)->and($application->interview->refresh()->venue)->toBe('Zoom');
});

it('refuses to schedule for decided applications', function () use ($slot) {
    foreach ([Application::factory()->accepted(), Application::factory()->declined()] as $factory) {
        $application = $factory->create();
        expect(fn () => app(ScheduleInterview::class)($application, $slot))->toThrow(DomainRuleViolation::class);
    }
});

it('accepts or declines an open application exactly once and notifies the intern', function () {
    Notification::fake();
    $application = Application::factory()->forInterview()->create();

    app(DecideApplication::class)($application, ApplicationStatus::Accepted);
    expect($application->refresh()->status)->toBe(ApplicationStatus::Accepted)->and($application->decided_at)->not->toBeNull();
    Notification::assertSentTo($application->intern, ApplicationDecided::class, fn (ApplicationDecided $n) => str_contains($n->toArray($application->intern)['body'], $application->posting->company->company_code));

    expect(fn () => app(DecideApplication::class)($application, ApplicationStatus::Declined, 'Changed our mind'))->toThrow(DomainRuleViolation::class);

    $other = Application::factory()->create();
    expect(fn () => app(DecideApplication::class)($other, ApplicationStatus::Declined, ''))->toThrow(DomainRuleViolation::class);
    app(DecideApplication::class)($other, ApplicationStatus::Declined, 'Position filled');
    expect($other->refresh()->status)->toBe(ApplicationStatus::Declined)->and($other->decline_reason)->toBe('Position filled');

    expect(fn () => app(DecideApplication::class)(Application::factory()->create(), ApplicationStatus::Pending))->toThrow(InvalidArgumentException::class);
});
```

`tests/Feature/Company/ApplicationDecisionsTest.php`:

```php
<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\User;
use App\Notifications\ApplicationDecided;
use App\Notifications\InterviewScheduled;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->company()->create();
    $this->posting = InternshipPosting::factory()->for($this->user->company)->create();
    $this->application = Application::factory()->for($this->posting, 'posting')->create();
    $this->slot = ['title' => 'Initial interview', 'venue' => 'Google Meet', 'link' => 'https://meet.google.com/abc', 'scheduled_on' => now()->addDays(3)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '10:30', 'notes' => ''];
});

it('schedules an interview from the profile page', function () {
    Notification::fake();

    $this->actingAs($this->user)->get(route('company.applications.show', $this->application))->assertSee(route('company.applications.interview', $this->application))->assertSee(route('company.applications.decline', $this->application));

    $this->actingAs($this->user)->post(route('company.applications.interview', $this->application), $this->slot)
        ->assertRedirect(route('company.applications.show', $this->application))->assertSessionHas('success');

    expect($this->application->refresh()->status)->toBe(ApplicationStatus::ForInterview)->and($this->application->interview->venue)->toBe('Google Meet');
    Notification::assertSentTo($this->application->intern, InterviewScheduled::class);
});

it('validates the interview slot', function () {
    $this->actingAs($this->user)->post(route('company.applications.interview', $this->application), [...$this->slot, 'title' => '', 'scheduled_on' => now()->subDay()->toDateString(), 'ends_at' => '09:00', 'link' => 'not-a-url'])
        ->assertSessionHasErrors(['title', 'scheduled_on', 'ends_at', 'link']);
    expect($this->application->refresh()->status)->toBe(ApplicationStatus::Pending);
});

it('declines with a reason and notifies the intern', function () {
    Notification::fake();

    $this->actingAs($this->user)->post(route('company.applications.decline', $this->application), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($this->user)->post(route('company.applications.decline', $this->application), ['reason' => 'Position filled'])
        ->assertRedirect(route('company.postings.applicants', $this->posting))->assertSessionHas('success');

    expect($this->application->refresh()->status)->toBe(ApplicationStatus::Declined)->and($this->application->decline_reason)->toBe('Position filled');
    Notification::assertSentTo($this->application->intern, ApplicationDecided::class);
});

it('forbids other companies', function () {
    $other = User::factory()->company()->create();

    $this->actingAs($other)->post(route('company.applications.interview', $this->application), $this->slot)->assertForbidden();
    $this->actingAs($other)->post(route('company.applications.decline', $this->application), ['reason' => 'x'])->assertForbidden();
    expect($this->application->refresh()->status)->toBe(ApplicationStatus::Pending);
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="ApplicationDecisionActions|Company.*ApplicationDecisionsTest"`
Expected: FAIL.

- [ ] **Step 3: Actions, requests, notifications**

`app/Actions/ScheduleInterview.php`:

```php
<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\Interview;
use App\Notifications\InterviewScheduled;
use Illuminate\Support\Facades\DB;

/** Moves a pending application to "for interview"; re-running reschedules the single interview. */
class ScheduleInterview
{
    /** @param  array{title:string, venue:string, link?:?string, scheduled_on:string, starts_at:string, ends_at:string, notes?:?string}  $data */
    public function __invoke(Application $application, array $data): Interview
    {
        if (! $application->status->isOpen()) {
            throw new DomainRuleViolation('This application has already been decided.');
        }

        $interview = DB::transaction(function () use ($application, $data) {
            $interview = $application->interview()->updateOrCreate([], [
                'title' => $data['title'],
                'venue' => $data['venue'],
                'link' => $data['link'] ?? null,
                'scheduled_on' => $data['scheduled_on'],
                'starts_at' => self::dbTime($data['starts_at']),
                'ends_at' => self::dbTime($data['ends_at']),
                'notes' => $data['notes'] ?? null,
            ]);

            $application->update(['status' => ApplicationStatus::ForInterview]);

            return $interview;
        });

        $application->intern->notify(new InterviewScheduled($interview->load('application.posting.company')));

        return $interview;
    }

    private static function dbTime(string $time): string
    {
        return strlen($time) === 5 ? "{$time}:00" : $time;
    }
}
```

`app/Actions/DecideApplication.php`:

```php
<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Notifications\ApplicationDecided;
use InvalidArgumentException;

/** Accept or decline. Accepting never places the intern; they join with the company code. */
class DecideApplication
{
    public function __invoke(Application $application, ApplicationStatus $decision, ?string $reason = null): Application
    {
        if (! in_array($decision, [ApplicationStatus::Accepted, ApplicationStatus::Declined], true)) {
            throw new InvalidArgumentException('A decision must accept or decline the application.');
        }

        if (! $application->status->isOpen()) {
            throw new DomainRuleViolation('This application has already been decided.');
        }

        $reason = trim((string) $reason) ?: null;

        if ($decision === ApplicationStatus::Declined && $reason === null) {
            throw new DomainRuleViolation('Tell the applicant why the application was declined.');
        }

        $application->update([
            'status' => $decision,
            'decline_reason' => $decision === ApplicationStatus::Declined ? $reason : null,
            'decided_at' => now(),
        ]);

        $application->intern->notify(new ApplicationDecided($application->load('posting.company')));

        return $application;
    }
}
```

`app/Http/Requests/Company/ScheduleInterviewRequest.php`:

```php
<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('decide', $this->route('application'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['link' => $this->filled('link') ? trim((string) $this->input('link')) : null, 'notes' => $this->filled('notes') ? $this->input('notes') : null]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'venue' => ['required', 'string', 'max:255'],
            'link' => ['nullable', 'url', 'max:255'],
            'scheduled_on' => ['required', 'date', 'after_or_equal:today'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['scheduled_on.after_or_equal' => 'The interview date cannot be in the past.', 'ends_at.after' => 'The end time must be after the start time.'];
    }
}
```

`app/Http/Requests/Company/DeclineApplicationRequest.php`:

```php
<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class DeclineApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('decide', $this->route('application'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason'))]);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Tell the applicant why the application was declined.'];
    }
}
```

`app/Notifications/InterviewScheduled.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Interview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class InterviewScheduled extends Notification
{
    use Queueable;

    public function __construct(public Interview $interview) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $posting = $this->interview->application->posting;
        $when = $this->interview->scheduled_on->format('M j, Y').' at '.Carbon::parse($this->interview->starts_at)->format('g:i A');

        return [
            'title' => "Interview scheduled with {$posting->company->name}",
            'body' => "{$this->interview->title} for {$posting->title}: {$when} · {$this->interview->venue}.",
            'url' => route('intern.applications.index'),
            'icon' => 'heroicon-o-calendar-days',
        ];
    }
}
```

`app/Notifications/ApplicationDecided.php`:

```php
<?php

namespace App\Notifications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class ApplicationDecided extends Notification
{
    use Queueable;

    public function __construct(public Application $application) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $posting = $this->application->posting;
        $company = $posting->company;
        $accepted = $this->application->status === ApplicationStatus::Accepted;

        return [
            'title' => $accepted ? "You were accepted by {$company->name}" : "{$company->name} declined your application",
            'body' => $accepted
                ? "Congratulations! Join {$company->name} with the company code {$company->company_code} on My internship."
                : "{$posting->title}: ".($this->application->decline_reason ?? 'No reason given.'),
            'url' => $accepted && Route::has('intern.internship.show') ? route('intern.internship.show') : route('intern.applications.index'),
            'icon' => $accepted ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle',
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, view**

Add to `Company\ApplicationController` (imports: `App\Actions\DecideApplication`, `App\Actions\ScheduleInterview`, `App\Enums\ApplicationStatus`, `App\Http\Requests\Company\DeclineApplicationRequest`, `App\Http\Requests\Company\ScheduleInterviewRequest`, `Illuminate\Http\RedirectResponse`):

```php
    public function interview(ScheduleInterviewRequest $request, Application $application, ScheduleInterview $schedule): RedirectResponse
    {
        $schedule($application, $request->validated());

        return redirect()->route('company.applications.show', $application)->with('success', 'Interview scheduled. The applicant has been notified.');
    }

    public function decline(DeclineApplicationRequest $request, Application $application, DecideApplication $decide): RedirectResponse
    {
        $decide($application, ApplicationStatus::Declined, $request->validated('reason'));

        return redirect()->route('company.postings.applicants', $application->posting)->with('success', "{$application->intern->name}'s application was declined.");
    }
```

Routes — add inside the company group:

```php
        Route::post('applications/{application}/interview', [Company\ApplicationController::class, 'interview'])->name('applications.interview');
        Route::post('applications/{application}/decline', [Company\ApplicationController::class, 'decline'])->name('applications.decline');
```

In `resources/views/company/applications/show.blade.php` replace `{{-- Task 6 adds the interview and decline controls here --}}` with:

```blade
            @can('decide', $application)
                @if ($application->status->isOpen())
                    <x-card title="Decision">
                        <div class="flex flex-col gap-2">
                            <x-button type="button" variant="secondary" icon="heroicon-o-calendar-days" @click="$dispatch('open-modal', 'schedule-interview')">{{ $application->interview ? 'Reschedule interview' : 'Schedule interview' }}</x-button>
                            @if (Route::has('company.applications.accept'))
                                <x-confirm-form :action="route('company.applications.accept', $application)" :confirm="'Accept '.$intern->name.'? They will be told to join with your company code.'">
                                    <x-button class="w-full" icon="heroicon-o-check">Accept applicant</x-button>
                                </x-confirm-form>
                            @endif
                            <x-button type="button" variant="danger" icon="heroicon-o-x-mark" @click="$dispatch('open-modal', 'decline-application')">Decline</x-button>
                        </div>
                    </x-card>

                    <x-modal name="schedule-interview" :title="$application->interview ? 'Reschedule interview' : 'Schedule interview'">
                        <form method="POST" action="{{ route('company.applications.interview', $application) }}" class="space-y-4"
                              x-init="@if ($errors->hasAny(['title', 'venue', 'link', 'scheduled_on', 'starts_at', 'ends_at', 'notes'])) $nextTick(() => $dispatch('open-modal', 'schedule-interview')) @endif">
                            @csrf
                            <x-form.input name="title" label="Title" :value="$application->interview?->title ?? 'Initial interview'" required maxlength="100" />
                            <x-form.input name="venue" label="Venue or platform" :value="$application->interview?->venue" required placeholder="Google Meet, Zoom, or your office address" />
                            <x-form.input name="link" label="Meeting link" type="url" :value="$application->interview?->link" placeholder="https://" />
                            <div class="grid gap-4 sm:grid-cols-3">
                                <x-form.input name="scheduled_on" label="Date" type="date" :value="$application->interview?->scheduled_on?->toDateString()" required />
                                <x-form.input name="starts_at" label="From" type="time" :value="$application->interview ? substr($application->interview->starts_at, 0, 5) : null" required />
                                <x-form.input name="ends_at" label="To" type="time" :value="$application->interview ? substr($application->interview->ends_at, 0, 5) : null" required />
                            </div>
                            <x-form.textarea name="notes" label="Notes for the applicant" :value="$application->interview?->notes" rows="3" />
                            <div class="flex justify-end gap-2">
                                <x-button type="button" variant="secondary" @click="$dispatch('close-modal', 'schedule-interview')">Cancel</x-button>
                                <x-button icon="heroicon-o-calendar-days">Save interview</x-button>
                            </div>
                        </form>
                    </x-modal>

                    <x-modal name="decline-application" title="Decline application">
                        <form method="POST" action="{{ route('company.applications.decline', $application) }}" class="space-y-4"
                              x-init="@if ($errors->has('reason')) $nextTick(() => $dispatch('open-modal', 'decline-application')) @endif">
                            @csrf
                            <p class="text-sm text-stone-600 dark:text-stone-300">The reason is sent to {{ $intern->name }}.</p>
                            <x-form.textarea name="reason" label="Reason" rows="3" required maxlength="500" />
                            <div class="flex justify-end gap-2">
                                <x-button type="button" variant="secondary" @click="$dispatch('close-modal', 'decline-application')">Cancel</x-button>
                                <x-button variant="danger" icon="heroicon-o-x-mark">Decline</x-button>
                            </div>
                        </form>
                    </x-modal>
                @endif
            @endcan
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="ApplicationDecisionActions|Company.*ApplicationDecisionsTest|Company.*ApplicantsTest|PortalRouteIsolation"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: schedule interviews and decline applicants with notifications"
```

---

### Task 7: Interviews list and accepting applicants

**Files:**
- Create: `app/Http/Controllers/Company/InterviewController.php`, `resources/views/company/interviews/index.blade.php`
- Modify: `app/Http/Controllers/Company/ApplicationController.php` (`accept`), `routes/web.php`
- Test: `tests/Feature/Company/InterviewsTest.php`

**Interfaces:**
- Consumes: `DecideApplication`, `ApplicationPolicy::decide`, `Interview` model.
- Produces: routes `company.interviews.index` (GET `/company/interviews?when=upcoming|past`), `company.applications.accept` (POST `/company/applications/{application}/accept`). Accepting notifies the intern with the company code (Task 6's `ApplicationDecided`) and never creates a placement (spec).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Company/InterviewsTest.php`:

```php
<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\Interview;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\ApplicationDecided;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->company()->create();
    $this->posting = InternshipPosting::factory()->for($this->user->company)->create(['title' => 'Web Dev Intern']);
});

it('lists upcoming and past interviews for the company', function () {
    $soon = Application::factory()->for($this->posting, 'posting')->for(User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']), 'intern')->forInterview()->create();
    Interview::factory()->for($soon)->create(['scheduled_on' => now()->addDays(2)->toDateString(), 'venue' => 'Google Meet']);
    $past = Application::factory()->for($this->posting, 'posting')->for(User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Cruz']), 'intern')->forInterview()->create();
    Interview::factory()->for($past)->create(['scheduled_on' => now()->subDays(2)->toDateString()]);
    $foreign = Application::factory()->forInterview()->create(['intern_id' => User::factory()->intern()->create(['first_name' => 'Not', 'last_name' => 'Mine'])->id]);
    Interview::factory()->for($foreign)->create();

    $this->actingAs($this->user)->get(route('company.interviews.index'))
        ->assertOk()->assertSee('Maria Santos')->assertSee('Google Meet')->assertSee('Web Dev Intern')->assertDontSee('Juan Cruz')->assertDontSee('Not Mine')
        ->assertSee(route('company.applications.accept', $soon))->assertSee(route('company.applications.decline', $soon));
    $this->actingAs($this->user)->get(route('company.interviews.index', ['when' => 'past']))->assertSee('Juan Cruz')->assertDontSee('Maria Santos');
});

it('accepts an applicant without placing them and tells them the company code', function () {
    Notification::fake();
    $application = Application::factory()->for($this->posting, 'posting')->forInterview()->create();

    $this->actingAs($this->user)->from(route('company.interviews.index'))->post(route('company.applications.accept', $application))
        ->assertRedirect(route('company.interviews.index'))->assertSessionHas('success');

    expect($application->refresh()->status)->toBe(ApplicationStatus::Accepted)->and(Placement::count())->toBe(0);
    Notification::assertSentTo($application->intern, ApplicationDecided::class, fn (ApplicationDecided $n) => str_contains($n->toArray($application->intern)['body'], $this->user->company->company_code));

    $this->actingAs($this->user)->from(route('company.interviews.index'))->post(route('company.applications.accept', $application))->assertRedirect()->assertSessionHas('error');
    $this->actingAs(User::factory()->company()->create())->post(route('company.applications.accept', $application))->assertForbidden();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter="Company.*InterviewsTest"`
Expected: FAIL.

- [ ] **Step 3: Controller, routes, view**

`app/Http/Controllers/Company/InterviewController.php`:

```php
<?php

namespace App\Http\Controllers\Company;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Interview;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InterviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $when = $request->query('when') === 'past' ? 'past' : 'upcoming';
        $companyId = $request->user()->company->id;

        $base = Interview::query()
            ->whereHas('application', fn (Builder $a) => $a->where('status', ApplicationStatus::ForInterview)
                ->whereHas('posting', fn (Builder $p) => $p->where('company_id', $companyId)));

        return view('company.interviews.index', [
            'when' => $when,
            'counts' => [
                'upcoming' => (clone $base)->whereDate('scheduled_on', '>=', today())->count(),
                'past' => (clone $base)->whereDate('scheduled_on', '<', today())->count(),
            ],
            'interviews' => (clone $base)
                ->when($when === 'upcoming', fn (Builder $q) => $q->whereDate('scheduled_on', '>=', today())->orderBy('scheduled_on')->orderBy('starts_at'))
                ->when($when === 'past', fn (Builder $q) => $q->whereDate('scheduled_on', '<', today())->orderByDesc('scheduled_on'))
                ->with(['application.intern.internProfile', 'application.posting'])
                ->paginate(20)->withQueryString(),
        ]);
    }
}
```

Add to `Company\ApplicationController`:

```php
    public function accept(Application $application, DecideApplication $decide): RedirectResponse
    {
        Gate::authorize('decide', $application);

        $decide($application, ApplicationStatus::Accepted);

        return back()->with('success', "{$application->intern->name} was accepted and told to join with your company code.");
    }
```

(`Gate` import: `Illuminate\Support\Facades\Gate` is already there from `show`.)

Routes — add inside the company group:

```php
        Route::get('interviews', Company\InterviewController::class)->name('interviews.index');
        Route::post('applications/{application}/accept', [Company\ApplicationController::class, 'accept'])->name('applications.accept');
```

`resources/views/company/interviews/index.blade.php`:

```blade
<x-layouts.app title="Interviews">
    <x-page-header title="Interviews" subtitle="Applicants you have invited. Accept or decline after the interview." />

    <nav class="flex flex-wrap gap-2" aria-label="Interview groups">
        @foreach (['upcoming' => 'Upcoming', 'past' => 'Past'] as $key => $label)
            <a href="{{ route('company.interviews.index', ['when' => $key]) }}"
               @class(['inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                       'bg-brand-600 text-white ring-brand-600' => $when === $key,
                       'bg-white text-stone-600 ring-stone-300 hover:bg-stone-50 dark:bg-stone-900 dark:text-stone-300 dark:ring-stone-700' => $when !== $key])>
                {{ $label }} <span class="rounded-full bg-black/10 px-1.5 text-xs tabular-nums dark:bg-white/10">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>When</th><th>Applicant</th><th>Position</th><th>Venue</th><th class="text-right">Decision</th></x-slot:head>
            @forelse ($interviews as $interview)
                @php $application = $interview->application; @endphp
                <tr>
                    <td>
                        <p class="font-medium">{{ $interview->scheduled_on->format('D, M j') }}</p>
                        <p class="text-xs text-stone-500">{{ \Illuminate\Support\Carbon::parse($interview->starts_at)->format('g:i A') }} – {{ \Illuminate\Support\Carbon::parse($interview->ends_at)->format('g:i A') }}</p>
                    </td>
                    <td>
                        <a href="{{ route('company.applications.show', $application) }}" class="font-medium hover:underline">{{ $application->intern->name }}</a>
                        <p class="font-mono text-xs text-stone-500">{{ $application->intern->internProfile?->student_number }}</p>
                    </td>
                    <td>{{ $application->posting->title }}</td>
                    <td>
                        {{ $interview->venue }}
                        @if ($interview->link)<a href="{{ $interview->link }}" target="_blank" rel="noopener" class="ml-1 text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">Open link</a>@endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1" x-data="{ name: 'decline-{{ $application->id }}' }">
                            <x-confirm-form :action="route('company.applications.accept', $application)" :confirm="'Accept '.$application->intern->name.'?'">
                                <x-button variant="ghost" icon="heroicon-o-check" class="text-emerald-700 dark:text-emerald-300">Accept</x-button>
                            </x-confirm-form>
                            <x-button type="button" variant="ghost" icon="heroicon-o-x-mark" class="text-rose-600" @click="$dispatch('open-modal', name)">Decline</x-button>
                        </div>
                        <x-modal :name="'decline-'.$application->id" title="Decline applicant">
                            <form method="POST" action="{{ route('company.applications.decline', $application) }}" class="space-y-4" x-data="{ name: 'decline-{{ $application->id }}' }"
                                  x-init="@if ($errors->has('reason') && (string) old('application_id') === (string) $application->id) $nextTick(() => $dispatch('open-modal', name)) @endif">
                                @csrf
                                <input type="hidden" name="application_id" value="{{ $application->id }}">
                                <p class="text-sm text-stone-600 dark:text-stone-300">The reason is sent to {{ $application->intern->name }}.</p>
                                <div>
                                    <label for="reason-{{ $application->id }}" class="label">Reason <span class="text-rose-500">*</span></label>
                                    <textarea id="reason-{{ $application->id }}" name="reason" rows="3" required maxlength="500" class="input">{{ (string) old('application_id') === (string) $application->id ? old('reason') : '' }}</textarea>
                                    @if ((string) old('application_id') === (string) $application->id)<x-form.error name="reason" />@endif
                                </div>
                                <div class="flex justify-end gap-2">
                                    <x-button type="button" variant="secondary" @click="$dispatch('close-modal', name)">Cancel</x-button>
                                    <x-button variant="danger" icon="heroicon-o-x-mark">Decline</x-button>
                                </div>
                            </form>
                        </x-modal>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state :title="'No '.$when.' interviews'" description="Schedule interviews from an applicant's profile." icon="heroicon-o-calendar-days" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$interviews" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter="Company.*InterviewsTest|Company.*ApplicationDecisionsTest|PortalRouteIsolation"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: list interviews and accept applicants"
```

---

### Task 8: Join a company by code, "My internship", and leaving with an hours-based warning

**Files:**
- Create: `app/Actions/PlaceIntern.php`, `app/Actions/LeaveCompany.php`, `app/Http/Requests/Intern/JoinCompanyRequest.php`, `app/Http/Controllers/Intern/InternshipController.php`, `app/Notifications/InternJoinedCompany.php`, `app/Notifications/InternLeftCompany.php`, `app/Support/LeaveWarning.php`
- Create: `resources/views/intern/internship/show.blade.php`
- Modify: `routes/web.php`, `resources/views/intern/dashboard.blade.php`
- Test: `tests/Unit/Actions/PlacementActionsTest.php` (PlaceIntern + LeaveCompany; Task 9 appends RemoveIntern), `tests/Unit/Support/LeaveWarningTest.php`, `tests/Feature/Intern/InternshipTest.php`

**Interfaces:**
- Consumes: `Company::registered()->approved()`, `PlacementPolicy::leave`, `OjtHoursService`, `<x-progress-ring>`.
- Produces: `PlaceIntern(User $intern, string $companyCode): Placement` (needs an accepted application with that company, no active placement; `started_at` today); `LeaveCompany(User $intern): Placement` (ends the active placement today); `LeaveWarning::message(int $hoursHere, OjtHoursService): string`; routes `intern.internship.show` (GET `/intern/internship`), `intern.internship.join` (POST `/intern/internship/join`), `intern.internship.leave` (POST `/intern/internship/leave`); notifications `InternJoinedCompany`, `InternLeftCompany` to the company user.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/PlacementActionsTest.php`:

```php
<?php

use App\Actions\LeaveCompany;
use App\Actions\PlaceIntern;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\Company;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\InternJoinedCompany;
use App\Notifications\InternLeftCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->company = Company::factory()->registered()->create(['company_code' => 'TECHNOVA']);
    $this->intern = User::factory()->intern()->create();
    $this->posting = InternshipPosting::factory()->for($this->company)->create();
});

it('places an intern who has an accepted application with the company and tells the company', function () {
    Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->accepted()->create();

    $placement = app(PlaceIntern::class)($this->intern, ' technova ');

    expect($placement->company_id)->toBe($this->company->id)->and($placement->intern_id)->toBe($this->intern->id)
        ->and($placement->started_at->toDateString())->toBe(today()->toDateString())->and($placement->ended_at)->toBeNull()
        ->and($placement->hours_rendered)->toBe(0)->and($this->intern->hasActivePlacement())->toBeTrue();
    Notification::assertSentTo($this->company->user, InternJoinedCompany::class);
});

it('refuses unknown, unapproved or partner codes, missing acceptance, and a second placement', function () {
    expect(fn () => app(PlaceIntern::class)($this->intern, 'NOPE0000'))->toThrow(DomainRuleViolation::class, 'No company');

    $pending = Company::factory()->registered()->pending()->create(['company_code' => 'PENDING1']);
    Application::factory()->for(InternshipPosting::factory()->for($pending), 'posting')->for($this->intern, 'intern')->accepted()->create();
    expect(fn () => app(PlaceIntern::class)($this->intern, 'PENDING1'))->toThrow(DomainRuleViolation::class, 'No company');

    Company::factory()->partner()->create(['company_code' => 'PARTNER1']);
    expect(fn () => app(PlaceIntern::class)($this->intern, 'PARTNER1'))->toThrow(DomainRuleViolation::class, 'No company');

    Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->forInterview()->create();
    expect(fn () => app(PlaceIntern::class)($this->intern, 'TECHNOVA'))->toThrow(DomainRuleViolation::class, 'accepted application');

    Application::where('intern_id', $this->intern->id)->update(['status' => 'accepted']);
    Placement::factory()->for($this->intern, 'intern')->create();
    expect(fn () => app(PlaceIntern::class)($this->intern, 'TECHNOVA'))->toThrow(DomainRuleViolation::class, 'already placed');
    expect(Placement::where('company_id', $this->company->id)->count())->toBe(0);
});

it('leaving ends the active placement and tells the company', function () {
    $placement = Placement::factory()->for($this->intern, 'intern')->for($this->company)->create(['hours_rendered' => 120]);

    $ended = app(LeaveCompany::class)($this->intern);

    expect($ended->is($placement))->toBeTrue()->and($ended->ended_at->toDateString())->toBe(today()->toDateString())
        ->and($ended->hours_rendered)->toBe(120)->and($this->intern->hasActivePlacement())->toBeFalse();
    Notification::assertSentTo($this->company->user, InternLeftCompany::class);

    expect(fn () => app(LeaveCompany::class)($this->intern))->toThrow(DomainRuleViolation::class);
});
```

`tests/Unit/Support/LeaveWarningTest.php`:

```php
<?php

use App\Services\OjtHoursService;
use App\Support\LeaveWarning;

it('warns according to the hours rendered at this company', function () {
    $hours = app(OjtHoursService::class);

    expect(LeaveWarning::message(100, $hours))->toContain((string) $hours->certificateMinimum())->toContain('certificate')
        ->and(LeaveWarning::message($hours->certificateMinimum(), $hours))->toContain('eligible')
        ->and(LeaveWarning::message($hours->required(), $hours))->toContain('completed');
});
```

`tests/Feature/Intern/InternshipTest.php`:

```php
<?php

use App\Models\Application;
use App\Models\Company;
use App\Models\Department;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\InternJoinedCompany;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->intern = User::factory()->intern()->create();
    $this->company = Company::factory()->registered()->create(['name' => 'TechNova', 'company_code' => 'TECHNOVA', 'address' => 'Ortigas Center']);
    $this->posting = InternshipPosting::factory()->for($this->company)->create();
});

it('shows the join form with accepted companies when unplaced', function () {
    Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->accepted()->create();

    $this->actingAs($this->intern)->get(route('intern.internship.show'))
        ->assertOk()->assertSee('name="company_code"', false)->assertSee('TechNova')->assertSee('accepted');
});

it('joins a company by code and then shows the placement with co-interns and the leave warning', function () {
    Notification::fake();
    Application::factory()->for($this->posting, 'posting')->for($this->intern, 'intern')->accepted()->create();
    $mate = User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Cruz']);
    Placement::factory()->for($mate, 'intern')->for($this->company)->create();

    $this->actingAs($this->intern)->post(route('intern.internship.join'), ['company_code' => 'technova'])
        ->assertRedirect(route('intern.internship.show'))->assertSessionHas('success');
    Notification::assertSentTo($this->company->user, InternJoinedCompany::class);

    $this->actingAs($this->intern)->get(route('intern.internship.show'))
        ->assertOk()->assertSee('TechNova')->assertSee('Ortigas Center')->assertSee('Juan Cruz')->assertSee('0 h')
        ->assertSee((string) config('wiis.hours.certificate_min'))->assertSee(route('intern.internship.leave'))->assertDontSee('name="company_code"', false);
});

it('flashes an error for bad codes and leaves with a notification', function () {
    $this->actingAs($this->intern)->from(route('intern.internship.show'))->post(route('intern.internship.join'), ['company_code' => 'TECHNOVA'])
        ->assertRedirect(route('intern.internship.show'))->assertSessionHas('error');
    $this->actingAs($this->intern)->post(route('intern.internship.join'), ['company_code' => ''])->assertSessionHasErrors('company_code');

    $placement = Placement::factory()->for($this->intern, 'intern')->for($this->company)->create(['department_id' => Department::factory()->create(['name' => 'Web Team'])->id, 'hours_rendered' => 300]);
    $this->actingAs($this->intern)->get(route('intern.internship.show'))->assertSee('Web Team')->assertSee('300 h')->assertSee('eligible');

    $this->actingAs($this->intern)->post(route('intern.internship.leave'))->assertRedirect(route('intern.internship.show'))->assertSessionHas('success');
    expect($placement->refresh()->ended_at)->not->toBeNull();
    $this->actingAs($this->intern)->post(route('intern.internship.leave'))->assertForbidden();
});

it('links the dashboard to My internship', function () {
    $this->actingAs($this->intern)->get(route('intern.dashboard'))->assertSee(route('intern.internship.show'));
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="PlacementActions|LeaveWarning|Intern.*InternshipTest"`
Expected: FAIL.

- [ ] **Step 3: Actions, support, request, notifications**

`app/Actions/PlaceIntern.php`:

```php
<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\Company;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\InternJoinedCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Acceptance never places an intern; entering the company code does (spec). */
class PlaceIntern
{
    public function __invoke(User $intern, string $companyCode): Placement
    {
        $company = Company::registered()->approved()->where('company_code', Str::upper(trim($companyCode)))->first();

        if (! $company) {
            throw new DomainRuleViolation('No company has that code.');
        }

        if ($intern->hasActivePlacement()) {
            throw new DomainRuleViolation('You are already placed with a company. Leave it before joining another.');
        }

        $accepted = Application::query()->where('intern_id', $intern->id)->where('status', ApplicationStatus::Accepted)
            ->whereHas('posting', fn ($q) => $q->where('company_id', $company->id))->exists();

        if (! $accepted) {
            throw new DomainRuleViolation("You need an accepted application from {$company->name} before you can join it.");
        }

        $placement = DB::transaction(fn () => Placement::create([
            'intern_id' => $intern->id,
            'company_id' => $company->id,
            'department_id' => null,
            'started_at' => today(),
            'ended_at' => null,
            'hours_rendered' => 0,
            'absences' => 0,
        ]));

        $company->user?->notify(new InternJoinedCompany($placement->load('intern')));

        return $placement;
    }
}
```

`app/Actions/LeaveCompany.php`:

```php
<?php

namespace App\Actions;

use App\Exceptions\DomainRuleViolation;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\InternLeftCompany;

/** Ends the active placement; hours already approved stay on the record and in the intern's total. */
class LeaveCompany
{
    public function __invoke(User $intern): Placement
    {
        $placement = $intern->activePlacement()->with('company.user')->first();

        if (! $placement) {
            throw new DomainRuleViolation('You are not placed with a company right now.');
        }

        $placement->update(['ended_at' => today()]);

        $placement->company->user?->notify(new InternLeftCompany($placement->load('intern')));

        return $placement;
    }
}
```

`app/Support/LeaveWarning.php`:

```php
<?php

namespace App\Support;

use App\Enums\HoursTier;
use App\Services\OjtHoursService;

/** The warning an intern sees before leaving a company, based on hours rendered there. */
class LeaveWarning
{
    public static function message(int $hoursHere, OjtHoursService $hours): string
    {
        return match ($hours->tier($hoursHere)) {
            HoursTier::Low => "You have rendered {$hoursHere} of the {$hours->certificateMinimum()} hours this company needs before it can issue you a certificate. If you leave now, those hours still count toward your OJT total, but you will not get a certificate from this company.",
            HoursTier::Mid => "You have rendered {$hoursHere} hours here, so you are eligible for a certificate from this company. Ask for it before you leave.",
            HoursTier::Complete => "You have completed your required hours here. Make sure your certificate has been issued before you leave.",
        };
    }
}
```

`app/Http/Requests/Intern/JoinCompanyRequest.php`:

```php
<?php

namespace App\Http\Requests\Intern;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class JoinCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isIntern() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['company_code' => Str::upper(trim((string) $this->input('company_code')))]);
    }

    public function rules(): array
    {
        return ['company_code' => ['required', 'string', 'max:20']];
    }
}
```

`app/Notifications/InternJoinedCompany.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Placement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class InternJoinedCompany extends Notification
{
    use Queueable;

    public function __construct(public Placement $placement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->placement->intern->name} joined your company",
            'body' => 'They entered your company code and are now an active intern. Assign them a department from Interns.',
            'url' => Route::has('company.interns.index') ? route('company.interns.index') : route('company.dashboard'),
            'icon' => 'heroicon-o-user-plus',
        ];
    }
}
```

`app/Notifications/InternLeftCompany.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Placement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class InternLeftCompany extends Notification
{
    use Queueable;

    public function __construct(public Placement $placement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->placement->intern->name} left your company",
            'body' => "Their placement ended with {$this->placement->hours_rendered} hours rendered.",
            'url' => Route::has('company.history.index') ? route('company.history.index') : route('company.dashboard'),
            'icon' => 'heroicon-o-arrow-right-start-on-rectangle',
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, views**

`app/Http/Controllers/Intern/InternshipController.php`:

```php
<?php

namespace App\Http\Controllers\Intern;

use App\Actions\LeaveCompany;
use App\Actions\PlaceIntern;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intern\JoinCompanyRequest;
use App\Models\Placement;
use App\Services\OjtHoursService;
use App\Support\LeaveWarning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InternshipController extends Controller
{
    public function show(Request $request, OjtHoursService $hours): View
    {
        $user = $request->user()->load('internProfile');
        $placement = $user->activePlacement()->with(['company.user', 'department'])->first();
        $total = $user->internProfile?->total_hours ?? 0;

        return view('intern.internship.show', [
            'placement' => $placement,
            'coInterns' => $placement
                ? Placement::active()->where('company_id', $placement->company_id)->whereKeyNot($placement->id)->with('intern')->get()
                : collect(),
            'acceptedCompanies' => $placement
                ? collect()
                : $user->applications()->where('status', ApplicationStatus::Accepted)->with('posting.company')->get()->map(fn ($a) => $a->posting->company)->unique('id')->values(),
            'warning' => $placement ? LeaveWarning::message($placement->hours_rendered, $hours) : null,
            'progress' => [
                'hours' => $total,
                'required' => $hours->required(),
                'percent' => $hours->progressPercent($total),
                'remaining' => $hours->remaining($total),
                'tier' => $hours->tier($total),
            ],
            'hoursService' => $hours,
        ]);
    }

    public function join(JoinCompanyRequest $request, PlaceIntern $place): RedirectResponse
    {
        $placement = $place($request->user(), $request->validated('company_code'));

        return redirect()->route('intern.internship.show')->with('success', "Welcome to {$placement->company->name}! Your DTRs will now be reviewed by them.");
    }

    public function leave(Request $request, LeaveCompany $leave): RedirectResponse
    {
        $placement = $request->user()->activePlacement()->first();

        Gate::authorize('leave', $placement ?? new Placement);

        $leave($request->user());

        return redirect()->route('intern.internship.show')->with('success', "You left {$placement->company->name}. Your approved hours are kept.");
    }
}
```

Routes — add inside the intern group:

```php
        Route::get('internship', [Intern\InternshipController::class, 'show'])->name('internship.show');
        Route::post('internship/join', [Intern\InternshipController::class, 'join'])->name('internship.join');
        Route::post('internship/leave', [Intern\InternshipController::class, 'leave'])->name('internship.leave');
```

`resources/views/intern/internship/show.blade.php`:

```blade
<x-layouts.app title="My internship">
    <x-page-header title="My internship" subtitle="Your placement, hours and the people you work with." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="OJT progress">
            <div class="flex flex-col items-center gap-4 text-center">
                <x-progress-ring :percent="$progress['percent']" :color="$progress['tier']->badgeColor()" label="of required hours" :size="160" />
                <div>
                    <p class="font-display text-3xl font-semibold tabular-nums">{{ $progress['hours'] }} <span class="text-base font-normal text-stone-500">/ {{ $progress['required'] }} h</span></p>
                    <p class="mt-1 text-sm text-stone-500">{{ $progress['remaining'] }} hours remaining</p>
                </div>
                <x-badge :status="$progress['tier']" />
            </div>
        </x-card>

        @if ($placement)
            <x-card :title="$placement->company->name" subtitle="Current placement" class="lg:col-span-2">
                <x-slot:actions><x-badge color="green">Active</x-badge></x-slot:actions>
                <x-detail-list :items="[
                    'Since' => $placement->started_at->format('M j, Y'),
                    'Department' => $placement->department?->name ?? 'Not assigned yet',
                    'Hours rendered here' => $placement->hours_rendered.' h',
                    'Absences' => $placement->absences,
                    'Address' => $placement->company->address,
                    'Website' => $placement->company->website,
                    'Contact' => $placement->company->user?->name,
                    'Contact email' => $placement->company->user?->email,
                ]" />
            </x-card>

            <x-card title="Co-interns" :subtitle="$coInterns->count().' other interns at '.$placement->company->name" class="lg:col-span-2" :padding="false">
                @forelse ($coInterns as $other)
                    <div class="flex items-center gap-3 border-b border-stone-100 px-5 py-3 last:border-0 dark:border-stone-800">
                        <x-avatar :user="$other->intern" size="sm" />
                        <div><p class="font-medium">{{ $other->intern->name }}</p><p class="text-xs text-stone-500">{{ $other->department?->name ?? 'No department' }} · since {{ $other->started_at->format('M j') }}</p></div>
                    </div>
                @empty
                    <x-empty-state title="You are the only intern here" icon="heroicon-o-users" class="py-8" />
                @endforelse
            </x-card>

            <x-card title="Leave this company">
                <p class="text-sm text-stone-600 dark:text-stone-300">{{ $warning }}</p>
                <x-confirm-form :action="route('intern.internship.leave')" :confirm="'Leave '.$placement->company->name.'? '.$warning" class="mt-4">
                    <x-button variant="danger" class="w-full" icon="heroicon-o-arrow-right-start-on-rectangle">Leave company</x-button>
                </x-confirm-form>
            </x-card>
        @else
            <x-card title="Join a company" subtitle="Once a company accepts your application, enter its company code here." class="lg:col-span-2">
                <form method="POST" action="{{ route('intern.internship.join') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    @csrf
                    <x-form.input name="company_code" label="Company code" placeholder="TECHNOVA" required class="flex-1 font-mono uppercase" />
                    <x-button icon="heroicon-o-key">Join company</x-button>
                </form>
                @if ($acceptedCompanies->isNotEmpty())
                    <div class="mt-6">
                        <p class="text-sm font-medium">Companies that accepted you</p>
                        <ul class="mt-2 space-y-2">
                            @foreach ($acceptedCompanies as $company)
                                <li class="flex items-center justify-between rounded-xl bg-stone-50 px-4 py-3 text-sm dark:bg-stone-900">
                                    <span class="font-medium">{{ $company->name }}</span>
                                    <span class="text-stone-500">Ask them for their company code if you did not get it in the acceptance notice.</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <p class="mt-6 text-sm text-stone-500">No company has accepted you yet. <a href="{{ route('intern.applications.index') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Check your applications</a>.</p>
                @endif
            </x-card>
        @endif
    </div>
</x-layouts.app>
```

In `resources/views/intern/dashboard.blade.php` replace the "Current placement" card body with:

```blade
            @if ($placement)
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-display text-lg font-semibold"><a href="{{ route('intern.internship.show') }}" class="hover:underline">{{ $placement->company->name }}</a></p>
                        <p class="text-sm text-stone-500">Since {{ $placement->started_at->format('M j, Y') }} · {{ $placement->hours_rendered }} h rendered here</p>
                    </div>
                    <x-badge color="green">Active</x-badge>
                </div>
                <x-button variant="secondary" :href="route('intern.internship.show')" icon="heroicon-o-arrow-right" class="mt-4">My internship</x-button>
            @else
                <x-empty-state title="Not placed yet" description="Apply to an internship posting and join a company with its code once you are accepted." icon="heroicon-o-briefcase" class="py-8">
                    <x-slot:action><x-button :href="route('intern.internship.show')" icon="heroicon-o-key">Join a company</x-button></x-slot:action>
                </x-empty-state>
            @endif
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="PlacementActions|LeaveWarning|Intern.*InternshipTest|RoleAccess|PortalRouteIsolation|ApplicationDecisionActions"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: let accepted interns join a company by code and leave with an hours warning"
```

---

### Task 9: Monitor interns (hours tiers, department, remove) and company history

**Files:**
- Create: `app/Actions/AssignDepartment.php`, `app/Actions/RemoveIntern.php`, `app/Http/Requests/Company/AssignDepartmentRequest.php`, `app/Http/Controllers/Company/InternController.php`, `app/Http/Controllers/Company/PlacementController.php`, `app/Http/Controllers/Company/HistoryController.php`, `app/Notifications/InternRemoved.php`
- Create: `resources/views/company/interns/index.blade.php`, `resources/views/company/history/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Unit/Actions/PlacementManagementActionsTest.php`, `tests/Feature/Company/InternsTest.php`

**Interfaces:**
- Consumes: `PlacementPolicy::manage`, `OjtHoursService::tier|required`, `Department`, `Search::any`.
- Produces: `AssignDepartment(Placement, ?int $departmentId): Placement`; `RemoveIntern(Placement): Placement` (active only; ends today; notifies the intern); routes `company.interns.index` (GET `/company/interns?q=`), `company.placements.department` (PUT `/company/placements/{placement}/department`), `company.placements.remove` (POST `/company/placements/{placement}/remove`), `company.history.index` (GET `/company/history`); `InternRemoved` notification (url `intern.internship.show`).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/PlacementManagementActionsTest.php`:

```php
<?php

use App\Actions\AssignDepartment;
use App\Actions\RemoveIntern;
use App\Exceptions\DomainRuleViolation;
use App\Models\Department;
use App\Models\Placement;
use App\Notifications\InternRemoved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('assigns and clears a department', function () {
    $placement = Placement::factory()->create();
    $department = Department::factory()->create(['name' => 'Web Team']);

    app(AssignDepartment::class)($placement, $department->id);
    expect($placement->refresh()->department->name)->toBe('Web Team');

    app(AssignDepartment::class)($placement, null);
    expect($placement->refresh()->department_id)->toBeNull();
});

it('removes an active intern, keeps their hours and tells them; refuses ended placements', function () {
    Notification::fake();
    $placement = Placement::factory()->create(['hours_rendered' => 80]);

    app(RemoveIntern::class)($placement);

    expect($placement->refresh()->ended_at->toDateString())->toBe(today()->toDateString())->and($placement->hours_rendered)->toBe(80)
        ->and($placement->intern->hasActivePlacement())->toBeFalse();
    Notification::assertSentTo($placement->intern, InternRemoved::class);

    expect(fn () => app(RemoveIntern::class)($placement))->toThrow(DomainRuleViolation::class);
});
```

`tests/Feature/Company/InternsTest.php`:

```php
<?php

use App\Models\Certificate;
use App\Models\Department;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\InternRemoved;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->company()->create();
    $this->company = $this->user->company;
});

it('lists active interns with hours tiers, departments and search', function () {
    $maria = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $maria->internProfile()->update(['student_number' => '21-0001', 'total_hours' => 300]);
    $dept = Department::factory()->create(['name' => 'Web Team']);
    Placement::factory()->for($maria, 'intern')->for($this->company)->create(['hours_rendered' => 300, 'department_id' => $dept->id]);
    $juan = User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Cruz']);
    Placement::factory()->for($juan, 'intern')->for($this->company)->create();
    Placement::factory()->for($this->company)->ended()->create(['intern_id' => User::factory()->intern()->create(['first_name' => 'Old', 'last_name' => 'Intern'])->id]);
    Placement::factory()->create(['intern_id' => User::factory()->intern()->create(['first_name' => 'Other', 'last_name' => 'Company'])->id]);

    $this->actingAs($this->user)->get(route('company.interns.index'))
        ->assertOk()->assertSee('Maria Santos')->assertSee('21-0001')->assertSee('Certificate eligible')->assertSee('Web Team')->assertSee('300 h')
        ->assertSee('Juan Cruz')->assertSee('Below certificate threshold')->assertDontSee('Old Intern')->assertDontSee('Other Company');
    $this->actingAs($this->user)->get(route('company.interns.index', ['q' => 'juan']))->assertSee('Juan Cruz')->assertDontSee('Maria Santos');
});

it('assigns a department and removes an intern from its own placements only', function () {
    Notification::fake();
    $mine = Placement::factory()->for($this->company)->create();
    $theirs = Placement::factory()->create();
    $dept = Department::factory()->create(['name' => 'QA']);

    $this->actingAs($this->user)->put(route('company.placements.department', $theirs), ['department_id' => $dept->id])->assertForbidden();
    $this->actingAs($this->user)->post(route('company.placements.remove', $theirs))->assertForbidden();

    $this->actingAs($this->user)->put(route('company.placements.department', $mine), ['department_id' => $dept->id])->assertRedirect()->assertSessionHas('success');
    expect($mine->refresh()->department_id)->toBe($dept->id);
    $this->actingAs($this->user)->put(route('company.placements.department', $mine), ['department_id' => 999])->assertSessionHasErrors('department_id');

    $this->actingAs($this->user)->post(route('company.placements.remove', $mine))->assertRedirect(route('company.interns.index'))->assertSessionHas('success');
    expect($mine->refresh()->ended_at)->not->toBeNull();
    Notification::assertSentTo($mine->intern, InternRemoved::class);
});

it('shows past placements in history with dates, hours and department', function () {
    $old = User::factory()->intern()->create(['first_name' => 'Old', 'last_name' => 'Intern']);
    $placement = Placement::factory()->for($old, 'intern')->for($this->company)->ended()->create(['hours_rendered' => 486, 'department_id' => Department::factory()->create(['name' => 'Web Team'])->id, 'started_at' => '2026-01-10', 'ended_at' => '2026-05-30']);
    Certificate::factory()->for($placement)->create();
    Placement::factory()->for($this->company)->create(['intern_id' => User::factory()->intern()->create(['first_name' => 'Still', 'last_name' => 'Here'])->id]);

    $this->actingAs($this->user)->get(route('company.history.index'))
        ->assertOk()->assertSee('Old Intern')->assertSee('Web Team')->assertSee('486 h')->assertSee('Jan 10, 2026')->assertSee('May 30, 2026')->assertSee('1 certificate')->assertDontSee('Still Here');
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="PlacementManagementActions|Company.*InternsTest"`
Expected: FAIL.

- [ ] **Step 3: Actions, request, notification**

`app/Actions/AssignDepartment.php`:

```php
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
```

`app/Actions/RemoveIntern.php`:

```php
<?php

namespace App\Actions;

use App\Exceptions\DomainRuleViolation;
use App\Models\Placement;
use App\Notifications\InternRemoved;

/** The company ends the placement; approved hours stay on the record and in the intern's total. */
class RemoveIntern
{
    public function __invoke(Placement $placement): Placement
    {
        if (! $placement->isActive()) {
            throw new DomainRuleViolation('This placement has already ended.');
        }

        $placement->update(['ended_at' => today()]);

        $placement->intern->notify(new InternRemoved($placement->load('company')));

        return $placement;
    }
}
```

`app/Http/Requests/Company/AssignDepartmentRequest.php`:

```php
<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class AssignDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('placement'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['department_id' => $this->filled('department_id') ? $this->input('department_id') : null]);
    }

    public function rules(): array
    {
        return ['department_id' => ['nullable', 'integer', 'exists:departments,id']];
    }
}
```

`app/Notifications/InternRemoved.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Placement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InternRemoved extends Notification
{
    use Queueable;

    public function __construct(public Placement $placement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Your placement at {$this->placement->company->name} has ended",
            'body' => "The company ended your placement with {$this->placement->hours_rendered} hours rendered. Those hours stay in your total.",
            'url' => route('intern.internship.show'),
            'icon' => 'heroicon-o-arrow-right-start-on-rectangle',
        ];
    }
}
```

- [ ] **Step 4: Controllers, routes, views**

`app/Http/Controllers/Company/InternController.php`:

```php
<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Placement;
use App\Services\OjtHoursService;
use App\Support\Search;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternController extends Controller
{
    public function index(Request $request, OjtHoursService $hours): View
    {
        $q = trim((string) $request->query('q'));

        return view('company.interns.index', [
            'placements' => Placement::active()->where('company_id', $request->user()->company->id)
                ->with(['intern.internProfile.classSection', 'department'])
                ->when($q !== '', fn (Builder $query) => $query->whereHas('intern', fn (Builder $u) => Search::any($u, ['first_name', 'last_name', 'email'], $q)))
                ->orderBy('started_at')
                ->paginate(20)->withQueryString(),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'hours' => $hours,
        ]);
    }
}
```

`app/Http/Controllers/Company/PlacementController.php`:

```php
<?php

namespace App\Http\Controllers\Company;

use App\Actions\AssignDepartment;
use App\Actions\RemoveIntern;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\AssignDepartmentRequest;
use App\Models\Placement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PlacementController extends Controller
{
    public function department(AssignDepartmentRequest $request, Placement $placement, AssignDepartment $assign): RedirectResponse
    {
        $assign($placement, $request->validated('department_id') !== null ? (int) $request->validated('department_id') : null);

        return back()->with('success', "{$placement->intern->name}'s department was updated.");
    }

    public function remove(Placement $placement, RemoveIntern $remove): RedirectResponse
    {
        Gate::authorize('manage', $placement);

        $remove($placement);

        return redirect()->route('company.interns.index')->with('success', "{$placement->intern->name} was removed. Their record is in History.");
    }
}
```

`app/Http/Controllers/Company/HistoryController.php`:

```php
<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Placement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('company.history.index', [
            'placements' => Placement::query()->where('company_id', $request->user()->company->id)->whereNotNull('ended_at')
                ->with(['intern.internProfile', 'department'])->withCount('certificates')
                ->orderByDesc('ended_at')
                ->paginate(20),
        ]);
    }
}
```

Routes — add inside the company group:

```php
        Route::get('interns', [Company\InternController::class, 'index'])->name('interns.index');
        Route::put('placements/{placement}/department', [Company\PlacementController::class, 'department'])->name('placements.department');
        Route::post('placements/{placement}/remove', [Company\PlacementController::class, 'remove'])->name('placements.remove');
        Route::get('history', Company\HistoryController::class)->name('history.index');
```

`resources/views/company/interns/index.blade.php`:

```blade
<x-layouts.app title="Interns">
    <x-page-header title="Interns" subtitle="Everyone currently placed with you. Hours come from approved DTRs." />

    <x-search-form :action="route('company.interns.index')" placeholder="Search by name or email…" />

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Intern</th><th>Class</th><th>Hours here</th><th>OJT total</th><th>Department</th><th class="text-right"></th></x-slot:head>
            @forelse ($placements as $placement)
                @php $total = $placement->intern->internProfile?->total_hours ?? 0; @endphp
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$placement->intern" size="sm" />
                            <div class="min-w-0"><p class="font-medium">{{ $placement->intern->name }}</p><p class="font-mono text-xs text-stone-500">{{ $placement->intern->internProfile?->student_number }} · since {{ $placement->started_at->format('M j') }}</p></div>
                        </div>
                    </td>
                    <td>{{ $placement->intern->internProfile?->classSection?->display_name ?? '—' }}</td>
                    <td class="tabular-nums">{{ $placement->hours_rendered }} h<p class="text-xs text-stone-500">{{ $placement->absences }} absences</p></td>
                    <td><p class="tabular-nums">{{ $total }} / {{ $hours->required() }} h</p><x-badge :status="$hours->tier($total)" class="mt-1" /></td>
                    <td>
                        <form method="POST" action="{{ route('company.placements.department', $placement) }}" class="flex items-center gap-1">
                            @csrf
                            @method('PUT')
                            <select name="department_id" class="input py-1.5 text-sm" aria-label="Department" onchange="this.form.submit()">
                                <option value="">No department</option>
                                @foreach ($departments as $id => $name)<option value="{{ $id }}" @selected($placement->department_id === $id)>{{ $name }}</option>@endforeach
                            </select>
                            <noscript><x-button variant="ghost">Save</x-button></noscript>
                        </form>
                    </td>
                    <td class="text-right">
                        <x-confirm-form :action="route('company.placements.remove', $placement)" :confirm="'Remove '.$placement->intern->name.' from your company? Their approved hours are kept.'">
                            <x-button variant="ghost" icon="heroicon-o-user-minus" class="text-rose-600">Remove</x-button>
                        </x-confirm-form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state title="No active interns" description="Accepted applicants appear here once they join with your company code." icon="heroicon-o-users" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$placements" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
```

`resources/views/company/history/index.blade.php`:

```blade
<x-layouts.app title="History">
    <x-page-header title="Placement history" subtitle="Interns who completed or left their placement with you." />

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Intern</th><th>Department</th><th>From</th><th>To</th><th>Hours</th><th>Certificates</th></x-slot:head>
            @forelse ($placements as $placement)
                <tr>
                    <td><p class="font-medium">{{ $placement->intern->name }}</p><p class="font-mono text-xs text-stone-500">{{ $placement->intern->internProfile?->student_number }}</p></td>
                    <td>{{ $placement->department?->name ?? '—' }}</td>
                    <td>{{ $placement->started_at->format('M j, Y') }}</td>
                    <td>{{ $placement->ended_at->format('M j, Y') }}</td>
                    <td class="tabular-nums">{{ $placement->hours_rendered }} h<p class="text-xs text-stone-500">{{ $placement->absences }} absences</p></td>
                    <td>{{ $placement->certificates_count }} {{ Str::plural('certificate', $placement->certificates_count) }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state title="No past placements" icon="heroicon-o-archive-box" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$placements" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="PlacementManagementActions|Company.*InternsTest|PlacementActions|PortalRouteIsolation"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: monitor active interns, assign departments, remove interns and show placement history"
```

---

### Task 10: Intern DTRs — submit, list, withdraw

**Files:**
- Create: `app/Actions/SubmitDtr.php`, `app/Actions/DeleteDtr.php`, `app/Http/Requests/Intern/SubmitDtrRequest.php`, `app/Http/Controllers/Intern/DtrController.php`, `app/Notifications/DtrSubmitted.php`, `resources/views/intern/dtrs/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Unit/Actions/SubmitDtrTest.php`, `tests/Feature/Intern/DtrsTest.php`

**Interfaces:**
- Consumes: `DtrPolicy::delete`, `User::activePlacement()`, `files.show` kind `dtr`.
- Produces: `SubmitDtr(User $intern, array $data, UploadedFile $file): Dtr` (`period_from, period_to, hours, absences`; requires an active placement; stores `dtrs/{placement}/{uuid}.pdf`); `DeleteDtr(Dtr): void` (pending only, removes the file); routes `intern.dtrs.index` (GET `/intern/dtrs`), `intern.dtrs.store` (POST `/intern/dtrs`), `intern.dtrs.destroy` (DELETE `/intern/dtrs/{dtr}`); `DtrSubmitted` notification to the company user (url `company.dtrs.index` once Task 11 exists, else dashboard).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/SubmitDtrTest.php`:

```php
<?php

use App\Actions\DeleteDtr;
use App\Actions\SubmitDtr;
use App\Enums\DtrStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Dtr;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DtrSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->intern = User::factory()->intern()->create();
    $this->placement = Placement::factory()->for($this->intern, 'intern')->create();
    $this->data = ['period_from' => '2026-10-05', 'period_to' => '2026-10-09', 'hours' => 40, 'absences' => 1];
    $this->pdf = fn () => UploadedFile::fake()->create('dtr.pdf', 100, 'application/pdf');
});

it('stores a pending DTR on the active placement and tells the company', function () {
    $dtr = app(SubmitDtr::class)($this->intern, $this->data, ($this->pdf)());

    expect($dtr->placement_id)->toBe($this->placement->id)->and($dtr->status)->toBe(DtrStatus::Pending)->and($dtr->hours)->toBe(40)->and($dtr->absences)->toBe(1)
        ->and($dtr->period_from->toDateString())->toBe('2026-10-05')->and($dtr->file_path)->toStartWith("dtrs/{$this->placement->id}/");
    Storage::disk('local')->assertExists($dtr->file_path);
    Notification::assertSentTo($this->placement->company->user, DtrSubmitted::class);
    expect($this->placement->refresh()->hours_rendered)->toBe(0);
});

it('refuses interns without an active placement', function () {
    $free = User::factory()->intern()->create();

    expect(fn () => app(SubmitDtr::class)($free, $this->data, ($this->pdf)()))->toThrow(DomainRuleViolation::class);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('deletes a pending DTR with its file and refuses reviewed ones', function () {
    $dtr = app(SubmitDtr::class)($this->intern, $this->data, ($this->pdf)());
    $path = $dtr->file_path;

    app(DeleteDtr::class)($dtr);
    expect(Dtr::whereKey($dtr->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);

    $approved = Dtr::factory()->for($this->placement)->approved()->create();
    expect(fn () => app(DeleteDtr::class)($approved))->toThrow(DomainRuleViolation::class);
});
```

`tests/Feature/Intern/DtrsTest.php`:

```php
<?php

use App\Models\Dtr;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DtrSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->intern = User::factory()->intern()->create();
    $this->placement = Placement::factory()->for($this->intern, 'intern')->create();
    $this->data = ['period_from' => now()->subDays(5)->toDateString(), 'period_to' => now()->subDay()->toDateString(), 'hours' => 40, 'absences' => 0];
});

it('lists the intern’s DTRs with status and notes', function () {
    Dtr::factory()->for($this->placement)->approved()->create(['hours' => 40]);
    Dtr::factory()->for($this->placement)->disapproved()->create(['hours' => 8, 'reviewer_note' => 'Hours do not match the log.']);
    Dtr::factory()->create(['hours' => 99]);

    $this->actingAs($this->intern)->get(route('intern.dtrs.index'))
        ->assertOk()->assertSee($this->placement->company->name)->assertSee('Approved')->assertSee('Disapproved')->assertSee('Hours do not match the log.')->assertSee('name="file"', false)->assertDontSee('99 h');
});

it('submits a DTR and notifies the company', function () {
    Notification::fake();

    $this->actingAs($this->intern)->post(route('intern.dtrs.store'), [...$this->data, 'file' => UploadedFile::fake()->create('dtr.pdf', 120, 'application/pdf')])
        ->assertRedirect(route('intern.dtrs.index'))->assertSessionHas('success');

    $dtr = Dtr::firstOrFail();
    Storage::disk('local')->assertExists($dtr->file_path);
    Notification::assertSentTo($this->placement->company->user, DtrSubmitted::class);
    $this->actingAs($this->intern)->get(route('intern.dtrs.index'))->assertSee(route('files.show', ['dtr', $dtr->id]))->assertSee(route('intern.dtrs.destroy', $dtr));
});

it('validates the period, hours and file', function () {
    $this->actingAs($this->intern)->post(route('intern.dtrs.store'), [
        'period_from' => now()->toDateString(), 'period_to' => now()->subDays(2)->toDateString(), 'hours' => 0, 'absences' => -1,
        'file' => UploadedFile::fake()->create('dtr.txt', 1, 'text/plain'),
    ])->assertSessionHasErrors(['period_to', 'hours', 'absences', 'file']);
    $this->actingAs($this->intern)->post(route('intern.dtrs.store'), [...$this->data, 'period_to' => now()->addDay()->toDateString(), 'file' => UploadedFile::fake()->create('dtr.pdf', 1, 'application/pdf')])
        ->assertSessionHasErrors('period_to');
    expect(Dtr::count())->toBe(0);
});

it('refuses submissions without a placement and withdraws pending DTRs only', function () {
    $free = User::factory()->intern()->create();
    $this->actingAs($free)->get(route('intern.dtrs.index'))->assertOk()->assertDontSee('name="file"', false)->assertSee('not placed');
    $this->actingAs($free)->from(route('intern.dtrs.index'))->post(route('intern.dtrs.store'), [...$this->data, 'file' => UploadedFile::fake()->create('dtr.pdf', 1, 'application/pdf')])
        ->assertRedirect(route('intern.dtrs.index'))->assertSessionHas('error');

    Storage::disk('local')->put('dtrs/x/p.pdf', 'p');
    $pending = Dtr::factory()->for($this->placement)->create(['file_path' => 'dtrs/x/p.pdf']);
    $approved = Dtr::factory()->for($this->placement)->approved()->create();
    $theirs = Dtr::factory()->create();

    $this->actingAs($this->intern)->delete(route('intern.dtrs.destroy', $approved))->assertForbidden();
    $this->actingAs($this->intern)->delete(route('intern.dtrs.destroy', $theirs))->assertForbidden();
    $this->actingAs($this->intern)->delete(route('intern.dtrs.destroy', $pending))->assertRedirect(route('intern.dtrs.index'))->assertSessionHas('success');
    Storage::disk('local')->assertMissing('dtrs/x/p.pdf');
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="SubmitDtr|Intern.*DtrsTest"`
Expected: FAIL.

- [ ] **Step 3: Actions, request, notification**

`app/Actions/SubmitDtr.php`:

```php
<?php

namespace App\Actions;

use App\Enums\DtrStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Dtr;
use App\Models\User;
use App\Notifications\DtrSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** A DTR is always pending on submission; only ApproveDtr turns it into hours. */
class SubmitDtr
{
    /** @param  array{period_from:string, period_to:string, hours:int|string, absences:int|string}  $data */
    public function __invoke(User $intern, array $data, UploadedFile $file): Dtr
    {
        $placement = $intern->activePlacement()->with('company.user')->first();

        if (! $placement) {
            throw new DomainRuleViolation('You are not placed with a company, so there is nowhere to send a DTR.');
        }

        $path = $file->storeAs("dtrs/{$placement->id}", Str::uuid().'.pdf', 'local');

        $dtr = $placement->dtrs()->create([
            'file_path' => $path,
            'period_from' => $data['period_from'],
            'period_to' => $data['period_to'],
            'hours' => (int) $data['hours'],
            'absences' => (int) $data['absences'],
            'status' => DtrStatus::Pending,
        ]);

        $placement->company->user?->notify(new DtrSubmitted($dtr->setRelation('placement', $placement->setRelation('intern', $intern))));

        return $dtr;
    }
}
```

`app/Actions/DeleteDtr.php`:

```php
<?php

namespace App\Actions;

use App\Enums\DtrStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Dtr;
use Illuminate\Support\Facades\Storage;

class DeleteDtr
{
    public function __invoke(Dtr $dtr): void
    {
        if ($dtr->status !== DtrStatus::Pending) {
            throw new DomainRuleViolation('Only pending DTRs can be withdrawn.');
        }

        $path = $dtr->file_path;

        $dtr->delete();

        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
```

`app/Http/Requests/Intern/SubmitDtrRequest.php`:

```php
<?php

namespace App\Http\Requests\Intern;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDtrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isIntern() ?? false;
    }

    public function rules(): array
    {
        return [
            'period_from' => ['required', 'date', 'before_or_equal:today'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from', 'before_or_equal:today'],
            'hours' => ['required', 'integer', 'min:1', 'max:744'],
            'absences' => ['required', 'integer', 'min:0', 'max:31'],
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('wiis.uploads.max_pdf_kb')],
        ];
    }

    public function messages(): array
    {
        return [
            'period_to.after_or_equal' => 'The period must end on or after it starts.',
            'period_to.before_or_equal' => 'You can only submit DTRs for days that have passed.',
            'file.mimetypes' => 'The DTR must be a PDF file.',
        ];
    }
}
```

`app/Notifications/DtrSubmitted.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Dtr;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class DtrSubmitted extends Notification
{
    use Queueable;

    public function __construct(public Dtr $dtr) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->dtr->placement->intern->name} submitted a DTR",
            'body' => "{$this->dtr->hours} hours for {$this->dtr->period_from->format('M j')} – {$this->dtr->period_to->format('M j, Y')}. Review it to credit the hours.",
            'url' => Route::has('company.dtrs.index') ? route('company.dtrs.index') : route('company.dashboard'),
            'icon' => 'heroicon-o-clipboard-document-check',
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, view**

`app/Http/Controllers/Intern/DtrController.php`:

```php
<?php

namespace App\Http\Controllers\Intern;

use App\Actions\DeleteDtr;
use App\Actions\SubmitDtr;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intern\SubmitDtrRequest;
use App\Models\Dtr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DtrController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('intern.dtrs.index', [
            'placement' => $user->activePlacement()->with('company')->first(),
            'dtrs' => Dtr::query()->whereHas('placement', fn ($q) => $q->where('intern_id', $user->id))
                ->with(['placement.company', 'reviewer'])
                ->latest('period_to')->latest()
                ->paginate(20),
        ]);
    }

    public function store(SubmitDtrRequest $request, SubmitDtr $submit): RedirectResponse
    {
        $submit($request->user(), $request->safe()->except('file'), $request->file('file'));

        return redirect()->route('intern.dtrs.index')->with('success', 'DTR submitted. Your company will review it.');
    }

    public function destroy(Dtr $dtr, DeleteDtr $delete): RedirectResponse
    {
        Gate::authorize('delete', $dtr);

        $delete($dtr);

        return redirect()->route('intern.dtrs.index')->with('success', 'DTR withdrawn.');
    }
}
```

Routes — add inside the intern group:

```php
        Route::get('dtrs', [Intern\DtrController::class, 'index'])->name('dtrs.index');
        Route::post('dtrs', [Intern\DtrController::class, 'store'])->name('dtrs.store');
        Route::delete('dtrs/{dtr}', [Intern\DtrController::class, 'destroy'])->name('dtrs.destroy');
```

`resources/views/intern/dtrs/index.blade.php`:

```blade
<x-layouts.app title="DTRs">
    <x-page-header title="Daily time records" subtitle="Submit your signed DTR for each period. Approved hours are added to your OJT total." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="My DTRs" class="lg:col-span-2" :padding="false">
            <x-table>
                <x-slot:head><th>Period</th><th>Company</th><th>Hours</th><th>Status</th><th>Note</th><th></th></x-slot:head>
                @forelse ($dtrs as $dtr)
                    <tr>
                        <td><a href="{{ route('files.show', ['dtr', $dtr]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">{{ $dtr->period_from->format('M j') }} – {{ $dtr->period_to->format('M j, Y') }}</a></td>
                        <td>{{ $dtr->placement->company->name }}</td>
                        <td class="tabular-nums">{{ $dtr->hours }} h<p class="text-xs text-stone-500">{{ $dtr->absences }} absences</p></td>
                        <td><x-badge :status="$dtr->status" />@if ($dtr->reviewed_at)<p class="mt-1 text-xs text-stone-500">{{ $dtr->reviewed_at->format('M j') }}</p>@endif</td>
                        <td class="max-w-xs text-sm text-stone-600 dark:text-stone-300">{{ $dtr->reviewer_note ?? '—' }}</td>
                        <td class="text-right">
                            @can('delete', $dtr)
                                <x-confirm-form :action="route('intern.dtrs.destroy', $dtr)" method="DELETE" confirm="Withdraw this DTR? The file will be deleted.">
                                    <x-button variant="ghost" icon="heroicon-o-trash" class="text-rose-600">Withdraw</x-button>
                                </x-confirm-form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state title="No DTRs yet" description="Submit your first DTR with the form." icon="heroicon-o-clock" class="py-8" /></td></tr>
                @endforelse
            </x-table>
            <x-pagination :paginator="$dtrs" class="px-5 pb-4" />
        </x-card>

        <x-card title="Submit a DTR">
            @if ($placement)
                <p class="mb-4 text-sm text-stone-500">Sending to <span class="font-medium text-stone-800 dark:text-stone-100">{{ $placement->company->name }}</span>.</p>
                <form method="POST" action="{{ route('intern.dtrs.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.input name="period_from" label="From" type="date" required />
                        <x-form.input name="period_to" label="To" type="date" required />
                        <x-form.input name="hours" label="Hours rendered" type="number" min="1" max="744" required />
                        <x-form.input name="absences" label="Absences" type="number" min="0" max="31" value="0" required />
                    </div>
                    <x-form.file name="file" label="Signed DTR (PDF)" accept="application/pdf" required :hint="'PDF only, up to '.(int) (config('wiis.uploads.max_pdf_kb') / 1024).' MB.'" />
                    <x-button class="w-full" icon="heroicon-o-arrow-up-tray">Submit DTR</x-button>
                </form>
            @else
                <x-empty-state title="You are not placed yet" description="Join a company first; your DTRs go to the company that hosts you." icon="heroicon-o-briefcase" class="py-6">
                    <x-slot:action><x-button :href="route('intern.internship.show')" variant="secondary">My internship</x-button></x-slot:action>
                </x-empty-state>
            @endif
        </x-card>
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="SubmitDtr|Intern.*DtrsTest|PortalRouteIsolation"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: let interns submit, track and withdraw DTRs"
```

---

### Task 11: Company DTR review — the hours ledger

**Files:**
- Create: `app/Actions/ApproveDtr.php`, `app/Actions/DisapproveDtr.php`, `app/Http/Requests/Company/DisapproveDtrRequest.php`, `app/Http/Controllers/Company/DtrController.php`, `app/Notifications/DtrReviewed.php`, `resources/views/company/dtrs/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Unit/Actions/DtrReviewActionsTest.php`, `tests/Feature/Company/DtrsTest.php`

**Interfaces:**
- Consumes: `DtrPolicy::review`, `Placement`, `InternProfile`.
- Produces: `ApproveDtr(Dtr, User $reviewer): Dtr` — the ONLY writer of `placements.hours_rendered/absences` and `intern_profiles.total_hours/total_absences`, inside a transaction, pending → approved only; `DisapproveDtr(Dtr, User $reviewer, string $note): Dtr` (pending → disapproved, no hours); routes `company.dtrs.index` (GET `/company/dtrs?status=pending|approved|disapproved`), `company.dtrs.approve` (POST `/company/dtrs/{dtr}/approve`), `company.dtrs.disapprove` (POST `/company/dtrs/{dtr}/disapprove`, `note` required); `DtrReviewed` notification to the intern (url `intern.dtrs.index`).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/DtrReviewActionsTest.php`:

```php
<?php

use App\Actions\ApproveDtr;
use App\Actions\DisapproveDtr;
use App\Enums\DtrStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Dtr;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DtrReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->intern = User::factory()->intern()->create();
    $this->intern->internProfile()->update(['total_hours' => 100, 'total_absences' => 2]);
    $this->placement = Placement::factory()->for($this->intern, 'intern')->create(['hours_rendered' => 60, 'absences' => 1]);
    $this->reviewer = $this->placement->company->user;
});

it('adds the hours and absences to the placement and the intern total exactly once', function () {
    $dtr = Dtr::factory()->for($this->placement)->create(['hours' => 40, 'absences' => 1]);

    app(ApproveDtr::class)($dtr, $this->reviewer);

    expect($dtr->refresh()->status)->toBe(DtrStatus::Approved)->and($dtr->reviewer_id)->toBe($this->reviewer->id)->and($dtr->reviewed_at)->not->toBeNull()
        ->and($this->placement->refresh()->hours_rendered)->toBe(100)->and($this->placement->absences)->toBe(2)
        ->and($this->intern->internProfile->refresh()->total_hours)->toBe(140)->and($this->intern->internProfile->total_absences)->toBe(3);
    Notification::assertSentTo($this->intern, DtrReviewed::class);

    expect(fn () => app(ApproveDtr::class)($dtr, $this->reviewer))->toThrow(DomainRuleViolation::class);
    expect($this->placement->refresh()->hours_rendered)->toBe(100)->and($this->intern->internProfile->refresh()->total_hours)->toBe(140);
});

it('disapproving never touches hours and cannot be approved afterwards', function () {
    $dtr = Dtr::factory()->for($this->placement)->create(['hours' => 40]);

    app(DisapproveDtr::class)($dtr, $this->reviewer, 'Hours do not match the log.');

    expect($dtr->refresh()->status)->toBe(DtrStatus::Disapproved)->and($dtr->reviewer_note)->toBe('Hours do not match the log.')
        ->and($this->placement->refresh()->hours_rendered)->toBe(60)->and($this->intern->internProfile->refresh()->total_hours)->toBe(100);
    Notification::assertSentTo($this->intern, DtrReviewed::class, fn (DtrReviewed $n) => str_contains($n->toArray($this->intern)['body'], 'Hours do not match'));

    expect(fn () => app(ApproveDtr::class)($dtr, $this->reviewer))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(DisapproveDtr::class)($dtr, $this->reviewer, 'again'))->toThrow(DomainRuleViolation::class);
    expect($this->placement->refresh()->hours_rendered)->toBe(60);
});

it('refuses to disapprove an approved DTR and to disapprove without a note', function () {
    $dtr = Dtr::factory()->for($this->placement)->create(['hours' => 10]);
    expect(fn () => app(DisapproveDtr::class)($dtr, $this->reviewer, '   '))->toThrow(DomainRuleViolation::class);

    app(ApproveDtr::class)($dtr, $this->reviewer);
    expect(fn () => app(DisapproveDtr::class)($dtr, $this->reviewer, 'late'))->toThrow(DomainRuleViolation::class);
    expect($this->placement->refresh()->hours_rendered)->toBe(70);
});

it('credits hours even after the placement ended', function () {
    $this->placement->update(['ended_at' => today()]);
    $dtr = Dtr::factory()->for($this->placement)->create(['hours' => 8]);

    app(ApproveDtr::class)($dtr, $this->reviewer);

    expect($this->placement->refresh()->hours_rendered)->toBe(68)->and($this->intern->internProfile->refresh()->total_hours)->toBe(108);
});
```

`tests/Feature/Company/DtrsTest.php`:

```php
<?php

use App\Enums\DtrStatus;
use App\Models\Dtr;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DtrReviewed;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->company()->create();
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->placement = Placement::factory()->for($this->intern, 'intern')->for($this->user->company)->create();
});

it('groups the company’s DTRs by status', function () {
    $pending = Dtr::factory()->for($this->placement)->create(['hours' => 40]);
    Dtr::factory()->for($this->placement)->approved()->create(['hours' => 32]);
    Dtr::factory()->for($this->placement)->disapproved()->create(['hours' => 8, 'reviewer_note' => 'Unsigned']);
    Dtr::factory()->create(['hours' => 99]);

    $this->actingAs($this->user)->get(route('company.dtrs.index'))
        ->assertOk()->assertSee('Maria Santos')->assertSee('40 h')->assertDontSee('32 h')->assertDontSee('99 h')
        ->assertSee(route('files.show', ['dtr', $pending->id]))->assertSee(route('company.dtrs.approve', $pending))->assertSee(route('company.dtrs.disapprove', $pending));
    $this->actingAs($this->user)->get(route('company.dtrs.index', ['status' => 'approved']))->assertSee('32 h')->assertDontSee('40 h');
    $this->actingAs($this->user)->get(route('company.dtrs.index', ['status' => 'disapproved']))->assertSee('Unsigned');
    $this->actingAs($this->user)->get(route('company.dtrs.index', ['status' => 'bogus']))->assertNotFound();
});

it('approves from the page, crediting hours once, and notifies the intern', function () {
    Notification::fake();
    $dtr = Dtr::factory()->for($this->placement)->create(['hours' => 40, 'absences' => 1]);

    $this->actingAs($this->user)->from(route('company.dtrs.index'))->post(route('company.dtrs.approve', $dtr))
        ->assertRedirect(route('company.dtrs.index'))->assertSessionHas('success');
    expect($this->placement->refresh()->hours_rendered)->toBe(40)->and($this->intern->internProfile->refresh()->total_hours)->toBe(40);
    Notification::assertSentTo($this->intern, DtrReviewed::class);

    $this->actingAs($this->user)->from(route('company.dtrs.index'))->post(route('company.dtrs.approve', $dtr))->assertRedirect()->assertSessionHas('error');
    expect($this->placement->refresh()->hours_rendered)->toBe(40);
});

it('disapproves with a required note and forbids other companies', function () {
    $dtr = Dtr::factory()->for($this->placement)->create(['hours' => 40]);

    $this->actingAs($this->user)->post(route('company.dtrs.disapprove', $dtr), ['note' => ''])->assertSessionHasErrors('note');
    $this->actingAs($this->user)->post(route('company.dtrs.disapprove', $dtr), ['note' => 'Unsigned copy'])->assertRedirect()->assertSessionHas('success');
    expect($dtr->refresh()->status)->toBe(DtrStatus::Disapproved)->and($this->placement->refresh()->hours_rendered)->toBe(0);

    $other = User::factory()->company()->create();
    $fresh = Dtr::factory()->for($this->placement)->create();
    $this->actingAs($other)->post(route('company.dtrs.approve', $fresh))->assertForbidden();
    $this->actingAs($other)->post(route('company.dtrs.disapprove', $fresh), ['note' => 'x'])->assertForbidden();
    expect($fresh->refresh()->status)->toBe(DtrStatus::Pending);
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="DtrReviewActions|Company.*DtrsTest"`
Expected: FAIL.

- [ ] **Step 3: Actions, request, notification**

`app/Actions/ApproveDtr.php`:

```php
<?php

namespace App\Actions;

use App\Enums\DtrStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Dtr;
use App\Models\InternProfile;
use App\Models\User;
use App\Notifications\DtrReviewed;
use Illuminate\Support\Facades\DB;

/**
 * The hours ledger's only writer. Runs in a transaction and credits hours exactly once,
 * on the pending → approved transition. Disapproved DTRs can never be approved later.
 */
class ApproveDtr
{
    public function __invoke(Dtr $dtr, User $reviewer): Dtr
    {
        DB::transaction(function () use ($dtr, $reviewer) {
            $locked = Dtr::query()->whereKey($dtr->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DtrStatus::Pending) {
                throw new DomainRuleViolation('This DTR has already been reviewed.');
            }

            $locked->update([
                'status' => DtrStatus::Approved,
                'reviewer_id' => $reviewer->id,
                'reviewer_note' => null,
                'reviewed_at' => now(),
            ]);

            $placement = $locked->placement;
            $placement->increment('hours_rendered', $locked->hours);
            $placement->increment('absences', $locked->absences);

            InternProfile::query()->where('user_id', $placement->intern_id)->increment('total_hours', $locked->hours);
            InternProfile::query()->where('user_id', $placement->intern_id)->increment('total_absences', $locked->absences);
        });

        $dtr->refresh()->placement->intern->notify(new DtrReviewed($dtr));

        return $dtr;
    }
}
```

`app/Actions/DisapproveDtr.php`:

```php
<?php

namespace App\Actions;

use App\Enums\DtrStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Dtr;
use App\Models\User;
use App\Notifications\DtrReviewed;

/** Never touches hours. */
class DisapproveDtr
{
    public function __invoke(Dtr $dtr, User $reviewer, string $note): Dtr
    {
        if ($dtr->status !== DtrStatus::Pending) {
            throw new DomainRuleViolation('This DTR has already been reviewed.');
        }

        $note = trim($note);

        if ($note === '') {
            throw new DomainRuleViolation('Tell the intern why the DTR was disapproved.');
        }

        $dtr->update([
            'status' => DtrStatus::Disapproved,
            'reviewer_id' => $reviewer->id,
            'reviewer_note' => $note,
            'reviewed_at' => now(),
        ]);

        $dtr->placement->intern->notify(new DtrReviewed($dtr));

        return $dtr;
    }
}
```

`app/Http/Requests/Company/DisapproveDtrRequest.php`:

```php
<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class DisapproveDtrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('dtr'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['note' => trim((string) $this->input('note'))]);
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return ['note.required' => 'Tell the intern why the DTR was disapproved.'];
    }
}
```

`app/Notifications/DtrReviewed.php`:

```php
<?php

namespace App\Notifications;

use App\Enums\DtrStatus;
use App\Models\Dtr;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DtrReviewed extends Notification
{
    use Queueable;

    public function __construct(public Dtr $dtr) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $approved = $this->dtr->status === DtrStatus::Approved;
        $period = "{$this->dtr->period_from->format('M j')} – {$this->dtr->period_to->format('M j')}";

        return [
            'title' => $approved ? "DTR approved: {$this->dtr->hours} hours credited" : "DTR disapproved for {$period}",
            'body' => $approved
                ? "Your DTR for {$period} was approved. {$this->dtr->hours} hours were added to your OJT total."
                : ($this->dtr->reviewer_note ?? 'Please resubmit a corrected DTR.'),
            'url' => route('intern.dtrs.index'),
            'icon' => $approved ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle',
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, view**

`app/Http/Controllers/Company/DtrController.php`:

```php
<?php

namespace App\Http\Controllers\Company;

use App\Actions\ApproveDtr;
use App\Actions\DisapproveDtr;
use App\Enums\DtrStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\DisapproveDtrRequest;
use App\Models\Dtr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DtrController extends Controller
{
    public function index(Request $request): View
    {
        $group = DtrStatus::tryFrom((string) $request->query('status', 'pending')) ?? abort(404);
        $companyId = $request->user()->company->id;

        $base = Dtr::query()->whereHas('placement', fn ($q) => $q->where('company_id', $companyId));

        return view('company.dtrs.index', [
            'group' => $group,
            'counts' => collect(DtrStatus::cases())->mapWithKeys(fn (DtrStatus $s) => [$s->value => (clone $base)->where('status', $s)->count()])->all(),
            'dtrs' => (clone $base)->where('status', $group)
                ->with(['placement.intern.internProfile', 'placement.company', 'reviewer'])
                ->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function approve(Request $request, Dtr $dtr, ApproveDtr $approve): RedirectResponse
    {
        Gate::authorize('review', $dtr);

        $approve($dtr, $request->user());

        return back()->with('success', "{$dtr->hours} hours credited to {$dtr->placement->intern->name}.");
    }

    public function disapprove(DisapproveDtrRequest $request, Dtr $dtr, DisapproveDtr $disapprove): RedirectResponse
    {
        $disapprove($dtr, $request->user(), $request->validated('note'));

        return back()->with('success', 'DTR disapproved. The intern has been notified.');
    }
}
```

Routes — add inside the company group:

```php
        Route::get('dtrs', [Company\DtrController::class, 'index'])->name('dtrs.index');
        Route::post('dtrs/{dtr}/approve', [Company\DtrController::class, 'approve'])->name('dtrs.approve');
        Route::post('dtrs/{dtr}/disapprove', [Company\DtrController::class, 'disapprove'])->name('dtrs.disapprove');
```

`resources/views/company/dtrs/index.blade.php`:

```blade
<x-layouts.app title="DTRs">
    <x-page-header title="Daily time records" subtitle="Approving a DTR credits its hours to the intern once. Disapproved DTRs must be resubmitted." />

    <nav class="flex flex-wrap gap-2" aria-label="DTR groups">
        @foreach (\App\Enums\DtrStatus::cases() as $status)
            <a href="{{ route('company.dtrs.index', ['status' => $status->value]) }}"
               @class(['inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                       'bg-brand-600 text-white ring-brand-600' => $group === $status,
                       'bg-white text-stone-600 ring-stone-300 hover:bg-stone-50 dark:bg-stone-900 dark:text-stone-300 dark:ring-stone-700' => $group !== $status])
               @if ($group === $status) aria-current="page" @endif>
                {{ $status->label() }} <span class="rounded-full bg-black/10 px-1.5 text-xs tabular-nums dark:bg-white/10">{{ $counts[$status->value] }}</span>
            </a>
        @endforeach
    </nav>

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Intern</th><th>Period</th><th>Hours</th><th>Submitted</th><th>Status</th><th class="text-right">Actions</th></x-slot:head>
            @forelse ($dtrs as $dtr)
                <tr>
                    <td><p class="font-medium">{{ $dtr->placement->intern->name }}</p><p class="font-mono text-xs text-stone-500">{{ $dtr->placement->intern->internProfile?->student_number }}</p></td>
                    <td><a href="{{ route('files.show', ['dtr', $dtr]) }}" target="_blank" class="inline-flex items-center gap-1.5 font-medium text-brand-700 hover:underline dark:text-brand-300"><x-heroicon-o-document-text class="size-4" /> {{ $dtr->period_from->format('M j') }} – {{ $dtr->period_to->format('M j, Y') }}</a></td>
                    <td class="tabular-nums">{{ $dtr->hours }} h<p class="text-xs text-stone-500">{{ $dtr->absences }} absences</p></td>
                    <td class="text-stone-500">{{ $dtr->created_at->format('M j, Y') }}</td>
                    <td>
                        <x-badge :status="$dtr->status" />
                        @if ($dtr->reviewed_at)<p class="mt-1 text-xs text-stone-500">{{ $dtr->reviewer?->name }} · {{ $dtr->reviewed_at->format('M j') }}</p>@endif
                        @if ($dtr->reviewer_note)<p class="mt-1 max-w-xs text-xs text-stone-600 dark:text-stone-300">{{ $dtr->reviewer_note }}</p>@endif
                    </td>
                    <td class="text-right">
                        @if ($dtr->status === \App\Enums\DtrStatus::Pending)
                            <div class="flex items-center justify-end gap-1" x-data="{ name: 'disapprove-{{ $dtr->id }}' }">
                                <x-confirm-form :action="route('company.dtrs.approve', $dtr)" :confirm="'Approve '.$dtr->hours.' hours for '.$dtr->placement->intern->name.'? This cannot be undone.'">
                                    <x-button variant="ghost" icon="heroicon-o-check" class="text-emerald-700 dark:text-emerald-300">Approve</x-button>
                                </x-confirm-form>
                                <x-button type="button" variant="ghost" icon="heroicon-o-x-mark" class="text-rose-600" @click="$dispatch('open-modal', name)">Disapprove</x-button>
                            </div>
                            <x-modal :name="'disapprove-'.$dtr->id" title="Disapprove DTR">
                                <form method="POST" action="{{ route('company.dtrs.disapprove', $dtr) }}" class="space-y-4" x-data="{ name: 'disapprove-{{ $dtr->id }}' }"
                                      x-init="@if ($errors->has('note') && (string) old('dtr_id') === (string) $dtr->id) $nextTick(() => $dispatch('open-modal', name)) @endif">
                                    @csrf
                                    <input type="hidden" name="dtr_id" value="{{ $dtr->id }}">
                                    <p class="text-sm text-stone-600 dark:text-stone-300">The note is sent to {{ $dtr->placement->intern->name }}. No hours are credited.</p>
                                    <div>
                                        <label for="note-{{ $dtr->id }}" class="label">Reason <span class="text-rose-500">*</span></label>
                                        <textarea id="note-{{ $dtr->id }}" name="note" rows="3" required maxlength="500" class="input">{{ (string) old('dtr_id') === (string) $dtr->id ? old('note') : '' }}</textarea>
                                        @if ((string) old('dtr_id') === (string) $dtr->id)<x-form.error name="note" />@endif
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <x-button type="button" variant="secondary" @click="$dispatch('close-modal', name)">Cancel</x-button>
                                        <x-button variant="danger" icon="heroicon-o-x-mark">Disapprove</x-button>
                                    </div>
                                </form>
                            </x-modal>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state :title="'No '.$group->label().' DTRs'" icon="heroicon-o-clipboard-document-check" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$dtrs" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="DtrReviewActions|Company.*DtrsTest|SubmitDtr|Intern.*DtrsTest|PortalRouteIsolation"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: review DTRs with a single transactional hours ledger"
```

---

### Task 12: Intern document requests (create, edit, withdraw)

**Files:**
- Create: `app/Actions/RequestDocument.php`, `app/Actions/UpdateDocumentRequest.php`, `app/Actions/DeleteDocumentRequest.php`, `app/Services/ControlNumberGenerator.php`, `app/Http/Requests/Intern/DocumentRequestRequest.php`, `app/Http/Controllers/Intern/DocumentRequestController.php`, `app/Notifications/DocumentRequested.php`, `resources/views/intern/requests/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Unit/Services/ControlNumberGeneratorTest.php`, `tests/Unit/Actions/DocumentRequestActionsTest.php` (intern part; Task 13 appends the company part), `tests/Feature/Intern/DocumentRequestsTest.php`

**Interfaces:**
- Consumes: `DocumentRequestPolicy::update|delete`, `User::activePlacement()`.
- Produces: `ControlNumberGenerator::generate(): string` (`DR-YYYY-NNNNN`, unique); `RequestDocument(User $intern, array{document_name:string, message?:?string}): DocumentRequest` (active placement required); `UpdateDocumentRequest(DocumentRequest, array): DocumentRequest` (pending only); `DeleteDocumentRequest(DocumentRequest): void` (pending only); routes `intern.requests.index|store|update|destroy` (`/intern/requests`, `/intern/requests/{documentRequest}`); `DocumentRequested` notification to the company user (url `company.requests.index` once Task 13 exists, else dashboard).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Services/ControlNumberGeneratorTest.php`:

```php
<?php

use App\Models\DocumentRequest;
use App\Services\ControlNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates year-prefixed sequential control numbers that skip collisions', function () {
    $generator = app(ControlNumberGenerator::class);
    $year = now()->year;

    expect($generator->generate())->toBe("DR-{$year}-00001");

    DocumentRequest::factory()->create(['control_no' => "DR-{$year}-00001"]);
    DocumentRequest::factory()->create(['control_no' => "DR-{$year}-00002"]);

    expect($generator->generate())->toBe("DR-{$year}-00003");
});
```

`tests/Unit/Actions/DocumentRequestActionsTest.php`:

```php
<?php

use App\Actions\DeleteDocumentRequest;
use App\Actions\RequestDocument;
use App\Actions\UpdateDocumentRequest;
use App\Enums\DocumentRequestStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\DocumentRequest;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DocumentRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->placement = Placement::factory()->for($this->intern, 'intern')->create();
});

it('creates a pending request with a control number and tells the company', function () {
    $request = app(RequestDocument::class)($this->intern, ['document_name' => ' Certificate of Completion ', 'message' => 'For my portfolio.']);

    expect($request->placement_id)->toBe($this->placement->id)->and($request->status)->toBe(DocumentRequestStatus::Pending)
        ->and($request->document_name)->toBe('Certificate of Completion')->and($request->control_no)->toMatch('/^DR-\d{4}-\d{5}$/');
    Notification::assertSentTo($this->placement->company->user, DocumentRequested::class, fn (DocumentRequested $n) => str_contains($n->toArray($this->placement->company->user)['body'], 'Certificate of Completion'));

    expect(fn () => app(RequestDocument::class)(User::factory()->intern()->create(), ['document_name' => 'X']))->toThrow(DomainRuleViolation::class);
});

it('edits and deletes pending requests only', function () {
    $request = app(RequestDocument::class)($this->intern, ['document_name' => 'Acceptance Letter']);

    app(UpdateDocumentRequest::class)($request, ['document_name' => 'Evaluation Form', 'message' => 'Needed by Friday.']);
    expect($request->refresh()->document_name)->toBe('Evaluation Form')->and($request->message)->toBe('Needed by Friday.');

    $request->update(['status' => DocumentRequestStatus::Fulfilled]);
    expect(fn () => app(UpdateDocumentRequest::class)($request, ['document_name' => 'Nope']))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(DeleteDocumentRequest::class)($request))->toThrow(DomainRuleViolation::class);

    $pending = DocumentRequest::factory()->for($this->placement)->create();
    app(DeleteDocumentRequest::class)($pending);
    expect(DocumentRequest::whereKey($pending->id)->exists())->toBeFalse();
});
```

`tests/Feature/Intern/DocumentRequestsTest.php`:

```php
<?php

use App\Models\DocumentRequest;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DocumentRequested;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->intern = User::factory()->intern()->create();
    $this->placement = Placement::factory()->for($this->intern, 'intern')->create();
});

it('lists requests with status, file links and edit controls', function () {
    Storage::fake('local');
    Storage::disk('local')->put('document-requests/x/f.pdf', 'f');
    $pending = DocumentRequest::factory()->for($this->placement)->create(['document_name' => 'Acceptance Letter']);
    $fulfilled = DocumentRequest::factory()->for($this->placement)->create(['document_name' => 'Evaluation Form', 'status' => 'fulfilled', 'file_path' => 'document-requests/x/f.pdf', 'handled_at' => now()]);
    DocumentRequest::factory()->create(['document_name' => 'Someone Else Doc']);

    $this->actingAs($this->intern)->get(route('intern.requests.index'))
        ->assertOk()->assertSee('Acceptance Letter')->assertSee($pending->control_no)->assertSee('Evaluation Form')->assertSee(route('files.show', ['document-request', $fulfilled->id]))
        ->assertSee(route('intern.requests.update', $pending))->assertSee(route('intern.requests.destroy', $pending))->assertDontSee(route('intern.requests.destroy', $fulfilled))
        ->assertDontSee('Someone Else Doc')->assertSee('name="document_name"', false);
});

it('creates, edits and withdraws a request, notifying the company on creation', function () {
    Notification::fake();

    $this->actingAs($this->intern)->post(route('intern.requests.store'), ['document_name' => '', 'message' => str_repeat('x', 1001)])->assertSessionHasErrors(['document_name', 'message']);
    $this->actingAs($this->intern)->post(route('intern.requests.store'), ['document_name' => 'Certificate of Completion', 'message' => 'For my portfolio.'])
        ->assertRedirect(route('intern.requests.index'))->assertSessionHas('success');
    $request = DocumentRequest::firstOrFail();
    Notification::assertSentTo($this->placement->company->user, DocumentRequested::class);

    $this->actingAs($this->intern)->put(route('intern.requests.update', $request), ['document_name' => 'Evaluation Form', 'message' => ''])->assertRedirect(route('intern.requests.index'));
    expect($request->refresh()->document_name)->toBe('Evaluation Form')->and($request->message)->toBeNull();

    $this->actingAs($this->intern)->delete(route('intern.requests.destroy', $request))->assertRedirect(route('intern.requests.index'));
    expect(DocumentRequest::count())->toBe(0);
});

it('keeps other interns out and blocks requests without a placement', function () {
    $theirs = DocumentRequest::factory()->create();
    $this->actingAs($this->intern)->put(route('intern.requests.update', $theirs), ['document_name' => 'Hack'])->assertForbidden();
    $this->actingAs($this->intern)->delete(route('intern.requests.destroy', $theirs))->assertForbidden();

    $free = User::factory()->intern()->create();
    $this->actingAs($free)->get(route('intern.requests.index'))->assertOk()->assertDontSee('name="document_name"', false)->assertSee('not placed');
    $this->actingAs($free)->from(route('intern.requests.index'))->post(route('intern.requests.store'), ['document_name' => 'X'])->assertRedirect(route('intern.requests.index'))->assertSessionHas('error');
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="ControlNumberGenerator|DocumentRequestActions|Intern.*DocumentRequestsTest"`
Expected: FAIL.

- [ ] **Step 3: Service, actions, request, notification**

`app/Services/ControlNumberGenerator.php`:

```php
<?php

namespace App\Services;

use App\Models\DocumentRequest;

/** Control numbers like DR-2026-00001: current year and a zero-padded sequence that skips collisions. */
class ControlNumberGenerator
{
    public function generate(): string
    {
        $year = now()->year;
        $sequence = DocumentRequest::query()->where('control_no', 'like', "DR-{$year}-%")->count();

        do {
            $sequence++;
            $candidate = sprintf('DR-%d-%05d', $year, $sequence);
        } while (DocumentRequest::query()->where('control_no', $candidate)->exists());

        return $candidate;
    }
}
```

`app/Actions/RequestDocument.php`:

```php
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
```

`app/Actions/UpdateDocumentRequest.php`:

```php
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
```

`app/Actions/DeleteDocumentRequest.php`:

```php
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
```

`app/Http/Requests/Intern/DocumentRequestRequest.php`:

```php
<?php

namespace App\Http\Requests\Intern;

use App\Models\DocumentRequest;
use Illuminate\Foundation\Http\FormRequest;

class DocumentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('documentRequest');

        return $request instanceof DocumentRequest
            ? $this->user()->can('update', $request)
            : ($this->user()?->isIntern() ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['document_name' => trim((string) $this->input('document_name'))]);
    }

    public function rules(): array
    {
        return [
            'document_name' => ['required', 'string', 'max:150'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

`app/Notifications/DocumentRequested.php`:

```php
<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class DocumentRequested extends Notification
{
    use Queueable;

    public function __construct(public DocumentRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->request->placement->intern->name} requested a document",
            'body' => "{$this->request->control_no}: {$this->request->document_name}".($this->request->message ? " — {$this->request->message}" : '.'),
            'url' => Route::has('company.requests.index') ? route('company.requests.index') : route('company.dashboard'),
            'icon' => 'heroicon-o-document-text',
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, view**

`app/Http/Controllers/Intern/DocumentRequestController.php`:

```php
<?php

namespace App\Http\Controllers\Intern;

use App\Actions\DeleteDocumentRequest;
use App\Actions\RequestDocument;
use App\Actions\UpdateDocumentRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intern\DocumentRequestRequest;
use App\Models\DocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DocumentRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('intern.requests.index', [
            'placement' => $user->activePlacement()->with('company')->first(),
            'requests' => DocumentRequest::query()->whereHas('placement', fn ($q) => $q->where('intern_id', $user->id))
                ->with('placement.company')->latest()->paginate(20),
        ]);
    }

    public function store(DocumentRequestRequest $request, RequestDocument $requestDocument): RedirectResponse
    {
        $created = $requestDocument($request->user(), $request->validated());

        return redirect()->route('intern.requests.index')->with('success', "Request {$created->control_no} sent to {$created->placement->company->name}.");
    }

    public function update(DocumentRequestRequest $request, DocumentRequest $documentRequest, UpdateDocumentRequest $update): RedirectResponse
    {
        $update($documentRequest, $request->validated());

        return redirect()->route('intern.requests.index')->with('success', "Request {$documentRequest->control_no} updated.");
    }

    public function destroy(DocumentRequest $documentRequest, DeleteDocumentRequest $delete): RedirectResponse
    {
        Gate::authorize('delete', $documentRequest);

        $delete($documentRequest);

        return redirect()->route('intern.requests.index')->with('success', 'Request withdrawn.');
    }
}
```

Routes — add inside the intern group:

```php
        Route::get('requests', [Intern\DocumentRequestController::class, 'index'])->name('requests.index');
        Route::post('requests', [Intern\DocumentRequestController::class, 'store'])->name('requests.store');
        Route::put('requests/{documentRequest}', [Intern\DocumentRequestController::class, 'update'])->name('requests.update');
        Route::delete('requests/{documentRequest}', [Intern\DocumentRequestController::class, 'destroy'])->name('requests.destroy');
```

`resources/views/intern/requests/index.blade.php`:

```blade
<x-layouts.app title="Document requests">
    <x-page-header title="Document requests" subtitle="Ask your company for documents such as a certificate of completion or an evaluation form." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="My requests" class="lg:col-span-2" :padding="false">
            <x-table>
                <x-slot:head><th>Control no.</th><th>Document</th><th>Company</th><th>Status</th><th class="text-right"></th></x-slot:head>
                @forelse ($requests as $documentRequest)
                    <tr>
                        <td class="font-mono text-xs">{{ $documentRequest->control_no }}</td>
                        <td>
                            <p class="font-medium">{{ $documentRequest->document_name }}</p>
                            @if ($documentRequest->message)<p class="text-xs text-stone-500">{{ $documentRequest->message }}</p>@endif
                            <p class="text-xs text-stone-500">Requested {{ $documentRequest->created_at->format('M j, Y') }}</p>
                        </td>
                        <td>{{ $documentRequest->placement->company->name }}</td>
                        <td>
                            <x-badge :status="$documentRequest->status" />
                            @if ($documentRequest->file_path)<a href="{{ route('files.show', ['document-request', $documentRequest]) }}" target="_blank" class="mt-1 block text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">Download document</a>@endif
                            @if ($documentRequest->handled_at)<p class="mt-1 text-xs text-stone-500">{{ $documentRequest->handled_at->format('M j, Y') }}</p>@endif
                        </td>
                        <td class="text-right">
                            @can('update', $documentRequest)
                                <div class="flex items-center justify-end gap-1" x-data="{ name: 'edit-{{ $documentRequest->id }}' }">
                                    <x-button type="button" variant="ghost" icon="heroicon-o-pencil-square" @click="$dispatch('open-modal', name)">Edit</x-button>
                                    <x-confirm-form :action="route('intern.requests.destroy', $documentRequest)" method="DELETE" confirm="Withdraw this request?">
                                        <x-button variant="ghost" icon="heroicon-o-trash" class="text-rose-600">Withdraw</x-button>
                                    </x-confirm-form>
                                </div>
                                <x-modal :name="'edit-'.$documentRequest->id" title="Edit request">
                                    <form method="POST" action="{{ route('intern.requests.update', $documentRequest) }}" class="space-y-4" x-data="{ name: 'edit-{{ $documentRequest->id }}' }"
                                          x-init="@if ($errors->hasAny(['document_name', 'message']) && (string) old('request_id') === (string) $documentRequest->id) $nextTick(() => $dispatch('open-modal', name)) @endif">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="request_id" value="{{ $documentRequest->id }}">
                                        <div>
                                            <label for="name-{{ $documentRequest->id }}" class="label">Document <span class="text-rose-500">*</span></label>
                                            <input id="name-{{ $documentRequest->id }}" name="document_name" required maxlength="150" class="input" value="{{ (string) old('request_id') === (string) $documentRequest->id ? old('document_name') : $documentRequest->document_name }}">
                                        </div>
                                        <div>
                                            <label for="message-{{ $documentRequest->id }}" class="label">Message</label>
                                            <textarea id="message-{{ $documentRequest->id }}" name="message" rows="3" maxlength="1000" class="input">{{ (string) old('request_id') === (string) $documentRequest->id ? old('message') : $documentRequest->message }}</textarea>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <x-button type="button" variant="secondary" @click="$dispatch('close-modal', name)">Cancel</x-button>
                                            <x-button icon="heroicon-o-check">Save</x-button>
                                        </div>
                                    </form>
                                </x-modal>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state title="No requests yet" icon="heroicon-o-document-text" class="py-8" /></td></tr>
                @endforelse
            </x-table>
            <x-pagination :paginator="$requests" class="px-5 pb-4" />
        </x-card>

        <x-card title="New request">
            @if ($placement)
                <p class="mb-4 text-sm text-stone-500">Sending to <span class="font-medium text-stone-800 dark:text-stone-100">{{ $placement->company->name }}</span>.</p>
                <form method="POST" action="{{ route('intern.requests.store') }}" class="space-y-4">
                    @csrf
                    <x-form.input name="document_name" label="Document" placeholder="Certificate of Completion" required maxlength="150" :value="old('request_id') ? null : old('document_name')" />
                    <x-form.textarea name="message" label="Message" rows="3" placeholder="Why you need it, or any details." />
                    <x-button class="w-full" icon="heroicon-o-paper-airplane">Send request</x-button>
                </form>
            @else
                <x-empty-state title="You are not placed yet" description="Join a company first; requests go to the company that hosts you." icon="heroicon-o-briefcase" class="py-6">
                    <x-slot:action><x-button :href="route('intern.internship.show')" variant="secondary">My internship</x-button></x-slot:action>
                </x-empty-state>
            @endif
        </x-card>
    </div>
</x-layouts.app>
```

(The hidden `request_id` scopes old input and errors to the edit modal that was submitted; the create form only repopulates when no `request_id` was posted.)

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="ControlNumberGenerator|DocumentRequestActions|Intern.*DocumentRequestsTest|PortalRouteIsolation"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: let interns request, edit and withdraw document requests"
```

---

### Task 13: Company document requests — fulfil with a PDF or decline

**Files:**
- Create: `app/Actions/FulfilDocumentRequest.php`, `app/Actions/DeclineDocumentRequest.php`, `app/Http/Requests/Company/FulfilDocumentRequestRequest.php`, `app/Http/Controllers/Company/DocumentRequestController.php`, `app/Notifications/DocumentRequestHandled.php`, `resources/views/company/requests/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Unit/Actions/DocumentRequestHandlingActionsTest.php`, `tests/Feature/Company/DocumentRequestsTest.php`

**Interfaces:**
- Consumes: `DocumentRequestPolicy::handle`, `files.show` kind `document-request`.
- Produces: `FulfilDocumentRequest(DocumentRequest, UploadedFile): DocumentRequest` (pending only; stores `document-requests/{placement}/{uuid}.pdf`; `handled_at`); `DeclineDocumentRequest(DocumentRequest): DocumentRequest` (pending only); routes `company.requests.index` (GET `/company/requests?status=pending|fulfilled|declined`), `company.requests.fulfil` (POST `/company/requests/{documentRequest}/fulfil`), `company.requests.decline` (POST `/company/requests/{documentRequest}/decline`); `DocumentRequestHandled` notification to the intern (url `intern.requests.index`).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/DocumentRequestHandlingActionsTest.php`:

```php
<?php

use App\Actions\DeclineDocumentRequest;
use App\Actions\FulfilDocumentRequest;
use App\Enums\DocumentRequestStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\DocumentRequest;
use App\Notifications\DocumentRequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->request = DocumentRequest::factory()->create();
    $this->intern = $this->request->placement->intern;
});

it('fulfils a pending request with a PDF and tells the intern', function () {
    $fulfilled = app(FulfilDocumentRequest::class)($this->request, UploadedFile::fake()->create('cert.pdf', 50, 'application/pdf'));

    expect($fulfilled->status)->toBe(DocumentRequestStatus::Fulfilled)->and($fulfilled->handled_at)->not->toBeNull()
        ->and($fulfilled->file_path)->toStartWith("document-requests/{$this->request->placement_id}/");
    Storage::disk('local')->assertExists($fulfilled->file_path);
    Notification::assertSentTo($this->intern, DocumentRequestHandled::class, fn (DocumentRequestHandled $n) => str_contains($n->toArray($this->intern)['title'], 'ready'));

    expect(fn () => app(FulfilDocumentRequest::class)($this->request, UploadedFile::fake()->create('x.pdf', 1, 'application/pdf')))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(DeclineDocumentRequest::class)($this->request))->toThrow(DomainRuleViolation::class);
});

it('declines a pending request without storing anything', function () {
    app(DeclineDocumentRequest::class)($this->request);

    expect($this->request->refresh()->status)->toBe(DocumentRequestStatus::Declined)->and($this->request->file_path)->toBeNull()->and($this->request->handled_at)->not->toBeNull();
    Notification::assertSentTo($this->intern, DocumentRequestHandled::class, fn (DocumentRequestHandled $n) => str_contains($n->toArray($this->intern)['title'], 'declined'));
    expect(Storage::disk('local')->allFiles())->toBe([]);
});
```

`tests/Feature/Company/DocumentRequestsTest.php`:

```php
<?php

use App\Enums\DocumentRequestStatus;
use App\Models\DocumentRequest;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\DocumentRequestHandled;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->company()->create();
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->placement = Placement::factory()->for($this->intern, 'intern')->for($this->user->company)->create();
});

it('groups requests by status and shows the intern and message', function () {
    $pending = DocumentRequest::factory()->for($this->placement)->create(['document_name' => 'Acceptance Letter', 'message' => 'For enrollment.']);
    DocumentRequest::factory()->for($this->placement)->create(['document_name' => 'Old Form', 'status' => 'declined', 'handled_at' => now()]);
    DocumentRequest::factory()->create(['document_name' => 'Not Mine']);

    $this->actingAs($this->user)->get(route('company.requests.index'))
        ->assertOk()->assertSee('Maria Santos')->assertSee('Acceptance Letter')->assertSee('For enrollment.')->assertSee($pending->control_no)
        ->assertSee(route('company.requests.fulfil', $pending))->assertSee(route('company.requests.decline', $pending))->assertDontSee('Old Form')->assertDontSee('Not Mine');
    $this->actingAs($this->user)->get(route('company.requests.index', ['status' => 'declined']))->assertSee('Old Form');
    $this->actingAs($this->user)->get(route('company.requests.index', ['status' => 'bogus']))->assertNotFound();
});

it('fulfils with a PDF, declines, and keeps other companies out', function () {
    Notification::fake();
    $a = DocumentRequest::factory()->for($this->placement)->create();
    $b = DocumentRequest::factory()->for($this->placement)->create();

    $this->actingAs($this->user)->post(route('company.requests.fulfil', $a), ['file' => UploadedFile::fake()->create('f.txt', 1, 'text/plain')])->assertSessionHasErrors('file');
    $this->actingAs($this->user)->post(route('company.requests.fulfil', $a), ['file' => UploadedFile::fake()->create('f.pdf', 50, 'application/pdf')])->assertRedirect()->assertSessionHas('success');
    expect($a->refresh()->status)->toBe(DocumentRequestStatus::Fulfilled);
    Storage::disk('local')->assertExists($a->file_path);
    Notification::assertSentTo($this->intern, DocumentRequestHandled::class);
    $this->actingAs($this->intern)->get(route('files.show', ['document-request', $a->id]))->assertOk();

    $this->actingAs($this->user)->post(route('company.requests.decline', $b))->assertRedirect()->assertSessionHas('success');
    expect($b->refresh()->status)->toBe(DocumentRequestStatus::Declined);

    $other = User::factory()->company()->create();
    $c = DocumentRequest::factory()->for($this->placement)->create();
    $this->actingAs($other)->post(route('company.requests.fulfil', $c), ['file' => UploadedFile::fake()->create('f.pdf', 1, 'application/pdf')])->assertForbidden();
    $this->actingAs($other)->post(route('company.requests.decline', $c))->assertForbidden();
    expect($c->refresh()->status)->toBe(DocumentRequestStatus::Pending);
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="DocumentRequestHandlingActions|Company.*DocumentRequestsTest"`
Expected: FAIL.

- [ ] **Step 3: Actions, request, notification**

`app/Actions/FulfilDocumentRequest.php`:

```php
<?php

namespace App\Actions;

use App\Enums\DocumentRequestStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\DocumentRequest;
use App\Notifications\DocumentRequestHandled;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class FulfilDocumentRequest
{
    public function __invoke(DocumentRequest $request, UploadedFile $file): DocumentRequest
    {
        if ($request->status !== DocumentRequestStatus::Pending) {
            throw new DomainRuleViolation('This request has already been handled.');
        }

        $path = $file->storeAs("document-requests/{$request->placement_id}", Str::uuid().'.pdf', 'local');

        $request->update(['status' => DocumentRequestStatus::Fulfilled, 'file_path' => $path, 'handled_at' => now()]);

        $request->placement->intern->notify(new DocumentRequestHandled($request));

        return $request;
    }
}
```

`app/Actions/DeclineDocumentRequest.php`:

```php
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
```

`app/Http/Requests/Company/FulfilDocumentRequestRequest.php`:

```php
<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class FulfilDocumentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('handle', $this->route('documentRequest'));
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('wiis.uploads.max_pdf_kb')]];
    }

    public function messages(): array
    {
        return ['file.mimetypes' => 'The document must be a PDF file.'];
    }
}
```

`app/Notifications/DocumentRequestHandled.php`:

```php
<?php

namespace App\Notifications;

use App\Enums\DocumentRequestStatus;
use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentRequestHandled extends Notification
{
    use Queueable;

    public function __construct(public DocumentRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $fulfilled = $this->request->status === DocumentRequestStatus::Fulfilled;
        $company = $this->request->placement->company->name;

        return [
            'title' => $fulfilled ? "Your {$this->request->document_name} is ready" : "{$company} declined your request for {$this->request->document_name}",
            'body' => $fulfilled ? "{$company} uploaded the document for request {$this->request->control_no}. Download it from Requests." : "Request {$this->request->control_no} was declined. Ask your company contact if you need more details.",
            'url' => route('intern.requests.index'),
            'icon' => $fulfilled ? 'heroicon-o-document-check' : 'heroicon-o-x-circle',
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, view**

`app/Http/Controllers/Company/DocumentRequestController.php`:

```php
<?php

namespace App\Http\Controllers\Company;

use App\Actions\DeclineDocumentRequest;
use App\Actions\FulfilDocumentRequest;
use App\Enums\DocumentRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\FulfilDocumentRequestRequest;
use App\Models\DocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DocumentRequestController extends Controller
{
    public function index(Request $request): View
    {
        $group = DocumentRequestStatus::tryFrom((string) $request->query('status', 'pending')) ?? abort(404);
        $companyId = $request->user()->company->id;

        $base = DocumentRequest::query()->whereHas('placement', fn ($q) => $q->where('company_id', $companyId));

        return view('company.requests.index', [
            'group' => $group,
            'counts' => collect(DocumentRequestStatus::cases())->mapWithKeys(fn (DocumentRequestStatus $s) => [$s->value => (clone $base)->where('status', $s)->count()])->all(),
            'requests' => (clone $base)->where('status', $group)->with('placement.intern.internProfile')->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function fulfil(FulfilDocumentRequestRequest $request, DocumentRequest $documentRequest, FulfilDocumentRequest $fulfil): RedirectResponse
    {
        $fulfil($documentRequest, $request->file('file'));

        return back()->with('success', "Request {$documentRequest->control_no} fulfilled. The intern has been notified.");
    }

    public function decline(DocumentRequest $documentRequest, DeclineDocumentRequest $decline): RedirectResponse
    {
        Gate::authorize('handle', $documentRequest);

        $decline($documentRequest);

        return back()->with('success', "Request {$documentRequest->control_no} declined.");
    }
}
```

Routes — add inside the company group:

```php
        Route::get('requests', [Company\DocumentRequestController::class, 'index'])->name('requests.index');
        Route::post('requests/{documentRequest}/fulfil', [Company\DocumentRequestController::class, 'fulfil'])->name('requests.fulfil');
        Route::post('requests/{documentRequest}/decline', [Company\DocumentRequestController::class, 'decline'])->name('requests.decline');
```

`resources/views/company/requests/index.blade.php`:

```blade
<x-layouts.app title="Document requests">
    <x-page-header title="Document requests" subtitle="Interns ask for documents here. Upload the PDF to fulfil a request, or decline it." />

    <nav class="flex flex-wrap gap-2" aria-label="Request groups">
        @foreach (\App\Enums\DocumentRequestStatus::cases() as $status)
            <a href="{{ route('company.requests.index', ['status' => $status->value]) }}"
               @class(['inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                       'bg-brand-600 text-white ring-brand-600' => $group === $status,
                       'bg-white text-stone-600 ring-stone-300 hover:bg-stone-50 dark:bg-stone-900 dark:text-stone-300 dark:ring-stone-700' => $group !== $status])
               @if ($group === $status) aria-current="page" @endif>
                {{ $status->label() }} <span class="rounded-full bg-black/10 px-1.5 text-xs tabular-nums dark:bg-white/10">{{ $counts[$status->value] }}</span>
            </a>
        @endforeach
    </nav>

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Control no.</th><th>Intern</th><th>Document</th><th>Requested</th><th>Status</th><th class="text-right">Actions</th></x-slot:head>
            @forelse ($requests as $documentRequest)
                <tr>
                    <td class="font-mono text-xs">{{ $documentRequest->control_no }}</td>
                    <td><p class="font-medium">{{ $documentRequest->placement->intern->name }}</p><p class="font-mono text-xs text-stone-500">{{ $documentRequest->placement->intern->internProfile?->student_number }}</p></td>
                    <td><p class="font-medium">{{ $documentRequest->document_name }}</p>@if ($documentRequest->message)<p class="max-w-xs text-xs text-stone-500">{{ $documentRequest->message }}</p>@endif</td>
                    <td class="text-stone-500">{{ $documentRequest->created_at->format('M j, Y') }}</td>
                    <td>
                        <x-badge :status="$documentRequest->status" />
                        @if ($documentRequest->file_path)<a href="{{ route('files.show', ['document-request', $documentRequest]) }}" target="_blank" class="mt-1 block text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">Uploaded file</a>@endif
                        @if ($documentRequest->handled_at)<p class="mt-1 text-xs text-stone-500">{{ $documentRequest->handled_at->format('M j, Y') }}</p>@endif
                    </td>
                    <td class="text-right">
                        @if ($documentRequest->status === \App\Enums\DocumentRequestStatus::Pending)
                            <div class="flex items-center justify-end gap-1" x-data="{ name: 'fulfil-{{ $documentRequest->id }}' }">
                                <x-button type="button" variant="ghost" icon="heroicon-o-arrow-up-tray" class="text-emerald-700 dark:text-emerald-300" @click="$dispatch('open-modal', name)">Upload</x-button>
                                <x-confirm-form :action="route('company.requests.decline', $documentRequest)" :confirm="'Decline request '.$documentRequest->control_no.'?'">
                                    <x-button variant="ghost" icon="heroicon-o-x-mark" class="text-rose-600">Decline</x-button>
                                </x-confirm-form>
                            </div>
                            <x-modal :name="'fulfil-'.$documentRequest->id" title="Upload the document">
                                <form method="POST" action="{{ route('company.requests.fulfil', $documentRequest) }}" enctype="multipart/form-data" class="space-y-4" x-data="{ name: 'fulfil-{{ $documentRequest->id }}' }"
                                      x-init="@if ($errors->has('file') && (string) old('request_id') === (string) $documentRequest->id) $nextTick(() => $dispatch('open-modal', name)) @endif">
                                    @csrf
                                    <input type="hidden" name="request_id" value="{{ $documentRequest->id }}">
                                    <p class="text-sm text-stone-600 dark:text-stone-300">{{ $documentRequest->document_name }} for {{ $documentRequest->placement->intern->name }} ({{ $documentRequest->control_no }}).</p>
                                    <div>
                                        <label for="file-{{ $documentRequest->id }}" class="label">PDF file <span class="text-rose-500">*</span></label>
                                        <input id="file-{{ $documentRequest->id }}" name="file" type="file" accept="application/pdf" required class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-xl file:border-0 file:bg-brand-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:text-stone-300 dark:file:bg-brand-900/40 dark:file:text-brand-200">
                                        @if ((string) old('request_id') === (string) $documentRequest->id)<x-form.error name="file" />@endif
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <x-button type="button" variant="secondary" @click="$dispatch('close-modal', name)">Cancel</x-button>
                                        <x-button icon="heroicon-o-arrow-up-tray">Fulfil request</x-button>
                                    </div>
                                </form>
                            </x-modal>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state :title="'No '.$group->label().' requests'" icon="heroicon-o-document-text" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$requests" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="DocumentRequestHandlingActions|Company.*DocumentRequestsTest|Intern.*DocumentRequestsTest|PortalRouteIsolation"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: fulfil or decline intern document requests"
```

---

### Task 14: Certificates — eligible interns, issue and re-issue, intern certificates page

**Files:**
- Create: `app/Actions/IssueCertificate.php`, `app/Http/Requests/Company/IssueCertificateRequest.php`, `app/Http/Controllers/Company/CertificateController.php`, `app/Http/Controllers/Intern/CertificateController.php`, `app/Notifications/CertificateIssued.php`
- Create: `resources/views/company/certificates/index.blade.php`, `resources/views/intern/certificates/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Unit/Actions/IssueCertificateTest.php`, `tests/Feature/Company/CertificatesTest.php`, `tests/Feature/Intern/CertificatesTest.php`

**Interfaces:**
- Consumes: `PlacementPolicy::manage`, `CertificatePolicy::view`, `OjtHoursService::certificateMinimum()`, `files.show` kind `certificate`.
- Produces: `IssueCertificate(Placement, UploadedFile): Certificate` (refused when `placement.hours_rendered` < certificate minimum — eligibility is per company; stores `certificates/{placement}/{uuid}.pdf`; `hours_at_issue` = hours rendered now; re-issue creates another record); routes `company.certificates.index` (GET `/company/certificates`), `company.certificates.store` (POST `/company/placements/{placement}/certificates`), `intern.certificates.index` (GET `/intern/certificates`); `CertificateIssued` notification to the intern.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/IssueCertificateTest.php`:

```php
<?php

use App\Actions\IssueCertificate;
use App\Exceptions\DomainRuleViolation;
use App\Models\Certificate;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\CertificateIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->min = (int) config('wiis.hours.certificate_min');
    $this->pdf = fn () => UploadedFile::fake()->create('certificate.pdf', 80, 'application/pdf');
});

it('issues and re-issues a certificate to an eligible placement and tells the intern', function () {
    $placement = Placement::factory()->create(['hours_rendered' => $this->min]);

    $first = app(IssueCertificate::class)($placement, ($this->pdf)());
    expect($first->hours_at_issue)->toBe($this->min)->and($first->issued_at)->not->toBeNull()->and($first->file_path)->toStartWith("certificates/{$placement->id}/");
    Storage::disk('local')->assertExists($first->file_path);
    Notification::assertSentTo($placement->intern, CertificateIssued::class);

    $placement->update(['hours_rendered' => $this->min + 50]);
    $second = app(IssueCertificate::class)($placement->refresh(), ($this->pdf)());
    expect($second->hours_at_issue)->toBe($this->min + 50)->and(Certificate::where('placement_id', $placement->id)->count())->toBe(2);
});

it('judges eligibility by hours at this company, not the intern total', function () {
    $intern = User::factory()->intern()->create();
    $intern->internProfile()->update(['total_hours' => 300]);
    $here = Placement::factory()->for($intern, 'intern')->create(['hours_rendered' => $this->min - 1]);

    expect(fn () => app(IssueCertificate::class)($here, ($this->pdf)()))->toThrow(DomainRuleViolation::class);
    expect(Certificate::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('may issue to an ended placement that reached the minimum', function () {
    $ended = Placement::factory()->ended()->create(['hours_rendered' => $this->min + 10]);

    expect(app(IssueCertificate::class)($ended, ($this->pdf)())->placement_id)->toBe($ended->id);
});
```

`tests/Feature/Company/CertificatesTest.php`:

```php
<?php

use App\Models\Certificate;
use App\Models\Placement;
use App\Models\User;
use App\Notifications\CertificateIssued;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->company()->create();
    $this->company = $this->user->company;
    $this->min = (int) config('wiis.hours.certificate_min');
});

it('lists eligible interns with their existing certificates', function () {
    $eligible = Placement::factory()->for($this->company)->create(['hours_rendered' => $this->min, 'intern_id' => User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos'])->id]);
    Certificate::factory()->for($eligible)->create(['hours_at_issue' => $this->min]);
    Placement::factory()->for($this->company)->create(['hours_rendered' => $this->min - 1, 'intern_id' => User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Short'])->id]);
    Placement::factory()->create(['hours_rendered' => 400, 'intern_id' => User::factory()->intern()->create(['first_name' => 'Other', 'last_name' => 'Company'])->id]);

    $this->actingAs($this->user)->get(route('company.certificates.index'))
        ->assertOk()->assertSee('Maria Santos')->assertSee('Re-issue')->assertSee(route('company.certificates.store', $eligible))
        ->assertDontSee('Juan Short')->assertDontSee('Other Company');
});

it('issues a certificate from the page and forbids other companies and ineligible placements', function () {
    Notification::fake();
    $eligible = Placement::factory()->for($this->company)->create(['hours_rendered' => $this->min]);
    $short = Placement::factory()->for($this->company)->create(['hours_rendered' => 10]);

    $this->actingAs($this->user)->post(route('company.certificates.store', $eligible), ['file' => UploadedFile::fake()->create('c.txt', 1, 'text/plain')])->assertSessionHasErrors('file');
    $this->actingAs($this->user)->post(route('company.certificates.store', $eligible), ['file' => UploadedFile::fake()->create('c.pdf', 80, 'application/pdf')])
        ->assertRedirect(route('company.certificates.index'))->assertSessionHas('success');
    $certificate = Certificate::firstOrFail();
    Storage::disk('local')->assertExists($certificate->file_path);
    Notification::assertSentTo($eligible->intern, CertificateIssued::class);

    $this->actingAs($this->user)->from(route('company.certificates.index'))->post(route('company.certificates.store', $short), ['file' => UploadedFile::fake()->create('c.pdf', 1, 'application/pdf')])
        ->assertRedirect(route('company.certificates.index'))->assertSessionHas('error');
    $this->actingAs(User::factory()->company()->create())->post(route('company.certificates.store', $eligible), ['file' => UploadedFile::fake()->create('c.pdf', 1, 'application/pdf')])->assertForbidden();
    expect(Certificate::count())->toBe(1);
});
```

`tests/Feature/Intern/CertificatesTest.php`:

```php
<?php

use App\Models\Certificate;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('lists the intern’s certificates with download links', function () {
    Storage::fake('local');
    $intern = User::factory()->intern()->create();
    $placement = Placement::factory()->for($intern, 'intern')->ended()->create();
    Storage::disk('local')->put('certificates/x/c.pdf', 'c');
    $mine = Certificate::factory()->for($placement)->create(['hours_at_issue' => 300, 'file_path' => 'certificates/x/c.pdf']);
    Certificate::factory()->create(['hours_at_issue' => 777]);

    $this->actingAs($intern)->get(route('intern.certificates.index'))
        ->assertOk()->assertSee($placement->company->name)->assertSee('300 h')->assertSee(route('files.show', ['certificate', $mine->id]))->assertDontSee('777');
    $this->actingAs($intern)->get(route('files.show', ['certificate', $mine->id]))->assertOk();

    $this->actingAs(User::factory()->intern()->create())->get(route('intern.certificates.index'))->assertOk()->assertSee('No certificates yet');
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="IssueCertificate|Company.*CertificatesTest|Intern.*CertificatesTest"`
Expected: FAIL.

- [ ] **Step 3: Action, request, notification**

`app/Actions/IssueCertificate.php`:

```php
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
```

`app/Http/Requests/Company/IssueCertificateRequest.php`:

```php
<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class IssueCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('placement'));
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('wiis.uploads.max_pdf_kb')]];
    }

    public function messages(): array
    {
        return ['file.mimetypes' => 'The certificate must be a PDF file.'];
    }
}
```

`app/Notifications/CertificateIssued.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CertificateIssued extends Notification
{
    use Queueable;

    public function __construct(public Certificate $certificate) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->certificate->placement->company->name} issued your certificate",
            'body' => "Certificate for {$this->certificate->hours_at_issue} hours rendered. Download it from Certificates.",
            'url' => route('intern.certificates.index'),
            'icon' => 'heroicon-o-trophy',
        ];
    }
}
```

- [ ] **Step 4: Controllers, routes, views**

`app/Http/Controllers/Company/CertificateController.php`:

```php
<?php

namespace App\Http\Controllers\Company;

use App\Actions\IssueCertificate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\IssueCertificateRequest;
use App\Models\Placement;
use App\Services\OjtHoursService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function index(Request $request, OjtHoursService $hours): View
    {
        return view('company.certificates.index', [
            'placements' => Placement::query()->where('company_id', $request->user()->company->id)
                ->where('hours_rendered', '>=', $hours->certificateMinimum())
                ->with(['intern.internProfile', 'certificates' => fn ($q) => $q->latest('issued_at')])
                ->orderByDesc('hours_rendered')
                ->paginate(20),
            'hours' => $hours,
        ]);
    }

    public function store(IssueCertificateRequest $request, Placement $placement, IssueCertificate $issue): RedirectResponse
    {
        $issue($placement, $request->file('file'));

        return redirect()->route('company.certificates.index')->with('success', "Certificate issued to {$placement->intern->name}.");
    }
}
```

`app/Http/Controllers/Intern/CertificateController.php`:

```php
<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('intern.certificates.index', [
            'certificates' => Certificate::query()->whereHas('placement', fn ($q) => $q->where('intern_id', $request->user()->id))
                ->with('placement.company')->latest('issued_at')->get(),
        ]);
    }
}
```

Routes — company group:

```php
        Route::get('certificates', [Company\CertificateController::class, 'index'])->name('certificates.index');
        Route::post('placements/{placement}/certificates', [Company\CertificateController::class, 'store'])->name('certificates.store');
```

intern group:

```php
        Route::get('certificates', Intern\CertificateController::class)->name('certificates.index');
```

`resources/views/company/certificates/index.blade.php`:

```blade
<x-layouts.app title="Certificates">
    <x-page-header title="Certificates" :subtitle="'Interns with at least '.$hours->certificateMinimum().' hours rendered at your company. Re-issuing keeps the earlier copies.'" />

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Intern</th><th>Hours here</th><th>Placement</th><th>Certificates</th><th class="text-right">Issue</th></x-slot:head>
            @forelse ($placements as $placement)
                <tr>
                    <td><p class="font-medium">{{ $placement->intern->name }}</p><p class="font-mono text-xs text-stone-500">{{ $placement->intern->internProfile?->student_number }}</p></td>
                    <td class="tabular-nums">{{ $placement->hours_rendered }} h</td>
                    <td class="text-stone-500">{{ $placement->started_at->format('M j, Y') }} – {{ $placement->ended_at?->format('M j, Y') ?? 'present' }}</td>
                    <td>
                        @forelse ($placement->certificates as $certificate)
                            <a href="{{ route('files.show', ['certificate', $certificate]) }}" target="_blank" class="block text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">{{ $certificate->issued_at->format('M j, Y') }} · {{ $certificate->hours_at_issue }} h</a>
                        @empty
                            <span class="text-sm text-stone-500">None yet</span>
                        @endforelse
                    </td>
                    <td class="text-right">
                        <div x-data="{ name: 'issue-{{ $placement->id }}' }">
                            <x-button type="button" :variant="$placement->certificates->isEmpty() ? 'primary' : 'secondary'" icon="heroicon-o-trophy" @click="$dispatch('open-modal', name)">{{ $placement->certificates->isEmpty() ? 'Issue' : 'Re-issue' }}</x-button>
                        </div>
                        <x-modal :name="'issue-'.$placement->id" title="Issue certificate">
                            <form method="POST" action="{{ route('company.certificates.store', $placement) }}" enctype="multipart/form-data" class="space-y-4" x-data="{ name: 'issue-{{ $placement->id }}' }"
                                  x-init="@if ($errors->has('file') && (string) old('placement_id') === (string) $placement->id) $nextTick(() => $dispatch('open-modal', name)) @endif">
                                @csrf
                                <input type="hidden" name="placement_id" value="{{ $placement->id }}">
                                <p class="text-sm text-stone-600 dark:text-stone-300">Certificate for {{ $placement->intern->name }} · {{ $placement->hours_rendered }} hours rendered.</p>
                                <div>
                                    <label for="file-{{ $placement->id }}" class="label">Certificate PDF <span class="text-rose-500">*</span></label>
                                    <input id="file-{{ $placement->id }}" name="file" type="file" accept="application/pdf" required class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-xl file:border-0 file:bg-brand-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:text-stone-300 dark:file:bg-brand-900/40 dark:file:text-brand-200">
                                    @if ((string) old('placement_id') === (string) $placement->id)<x-form.error name="file" />@endif
                                </div>
                                <div class="flex justify-end gap-2">
                                    <x-button type="button" variant="secondary" @click="$dispatch('close-modal', name)">Cancel</x-button>
                                    <x-button icon="heroicon-o-trophy">Issue certificate</x-button>
                                </div>
                            </form>
                        </x-modal>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state title="No eligible interns yet" :description="'Interns appear here once they reach '.$hours->certificateMinimum().' approved hours with you.'" icon="heroicon-o-trophy" class="py-8" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$placements" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
```

`resources/views/intern/certificates/index.blade.php`:

```blade
<x-layouts.app title="Certificates">
    <x-page-header title="Certificates" subtitle="Certificates of completion issued by the companies you interned with." />

    @if ($certificates->isEmpty())
        <x-card><x-empty-state title="No certificates yet" :description="'A company can issue one once you have rendered '.config('wiis.hours.certificate_min').' hours with them.'" icon="heroicon-o-trophy" /></x-card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($certificates as $certificate)
                <x-card class="flex flex-col">
                    <div class="flex items-start gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10"><x-heroicon-o-trophy class="size-5" /></span>
                        <div class="min-w-0">
                            <p class="font-display text-lg font-semibold">{{ $certificate->placement->company->name }}</p>
                            <p class="text-sm text-stone-500">Issued {{ $certificate->issued_at->format('M j, Y') }} · {{ $certificate->hours_at_issue }} h</p>
                        </div>
                    </div>
                    <x-button variant="secondary" :href="route('files.show', ['certificate', $certificate])" target="_blank" icon="heroicon-o-arrow-down-tray" class="mt-4 w-full">Download PDF</x-button>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="IssueCertificate|Company.*CertificatesTest|Intern.*CertificatesTest|PortalRouteIsolation|Navigation"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: issue certificates to eligible interns and list them for interns"
```

---

### Task 15: Company dashboard — counts and the interns-by-hours chart

**Files:**
- Create: `app/Services/CompanyDashboardStats.php`
- Modify: `app/Http/Controllers/Company/DashboardController.php`, `resources/views/company/dashboard.blade.php`
- Test: `tests/Unit/Services/CompanyDashboardStatsTest.php`, `tests/Feature/Company/DashboardTest.php`

**Interfaces:**
- Consumes: `OjtHoursService::bucketLabel|bucketLabels`, `<x-chart>`, `<x-stat-card>`.
- Produces: `CompanyDashboardStats::counts(Company): array{active_interns:int, open_postings:int, pending_applicants:int, upcoming_interviews:int, pending_dtrs:int, pending_requests:int, certificates:int}` and `CompanyDashboardStats::hourBuckets(Company): array{labels: list<string>, data: list<int>}` (active placements by hours rendered at the company, using the spec buckets 0–250 / 251–300 / 301–400 / 401+).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Services/CompanyDashboardStatsTest.php`:

```php
<?php

use App\Models\Application;
use App\Models\Certificate;
use App\Models\Company;
use App\Models\DocumentRequest;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\Interview;
use App\Models\Placement;
use App\Services\CompanyDashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts the company’s own pending work', function () {
    $company = Company::factory()->registered()->create();
    $posting = InternshipPosting::factory()->for($company)->create();
    InternshipPosting::factory()->for($company)->closed()->create();
    Application::factory()->for($posting, 'posting')->count(2)->create();
    $forInterview = Application::factory()->for($posting, 'posting')->forInterview()->create();
    Interview::factory()->for($forInterview)->create(['scheduled_on' => now()->addDay()->toDateString()]);
    $active = Placement::factory()->for($company)->create();
    Placement::factory()->for($company)->ended()->create();
    Dtr::factory()->for($active)->create();
    Dtr::factory()->for($active)->approved()->create();
    DocumentRequest::factory()->for($active)->create();
    Certificate::factory()->for($active)->create();
    Placement::factory()->create(); // another company

    expect(app(CompanyDashboardStats::class)->counts($company))->toBe([
        'active_interns' => 1, 'open_postings' => 1, 'pending_applicants' => 2, 'upcoming_interviews' => 1,
        'pending_dtrs' => 1, 'pending_requests' => 1, 'certificates' => 1,
    ]);
});

it('buckets active interns by hours rendered at the company', function () {
    $company = Company::factory()->registered()->create();
    foreach ([0, 250, 251, 300, 350, 401, 486] as $hours) {
        Placement::factory()->for($company)->create(['hours_rendered' => $hours]);
    }
    Placement::factory()->for($company)->ended()->create(['hours_rendered' => 486]);

    $buckets = app(CompanyDashboardStats::class)->hourBuckets($company);

    expect($buckets['labels'])->toBe(['0–250', '251–300', '301–400', '401+'])->and($buckets['data'])->toBe([2, 2, 1, 2]);
});
```

`tests/Feature/Company/DashboardTest.php`:

```php
<?php

use App\Models\Placement;
use App\Models\User;

it('shows counts, the hours chart and quick links', function () {
    $user = User::factory()->company()->create();
    Placement::factory()->for($user->company)->create(['hours_rendered' => 320]);

    $this->actingAs($user)->get(route('company.dashboard'))
        ->assertOk()->assertSee('Active interns')->assertSee('Interns by hours rendered')->assertSee('x-data="chart"', false)->assertSee('301–400')
        ->assertSee(route('company.postings.index'))->assertSee(route('company.dtrs.index'))->assertSee($user->company->company_code);
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="CompanyDashboardStats|Company.*DashboardTest"`
Expected: FAIL.

- [ ] **Step 3: Service, controller, view**

`app/Services/CompanyDashboardStats.php`:

```php
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
```

Replace `app/Http/Controllers/Company/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\CompanyDashboardStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CompanyDashboardStats $stats): View
    {
        $company = $request->user()->company;

        return view('company.dashboard', [
            'company' => $company,
            'stats' => $stats->counts($company),
            'buckets' => $stats->hourBuckets($company),
        ]);
    }
}
```

Replace `resources/views/company/dashboard.blade.php`:

```blade
<x-layouts.app title="Dashboard">
    <x-page-header :title="$company->name" :subtitle="'Hello, '.auth()->user()->first_name.'. Here is your internship program at a glance.'">
        <x-slot:actions><x-badge :status="$company->approval_status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('company.interns.index') }}"><x-stat-card label="Active interns" :value="$stats['active_interns']" icon="heroicon-o-users" /></a>
        <a href="{{ route('company.postings.index') }}"><x-stat-card label="Open postings" :value="$stats['open_postings']" icon="heroicon-o-megaphone" color="sky" :hint="$stats['pending_applicants'].' pending applicants'" /></a>
        <a href="{{ route('company.dtrs.index') }}"><x-stat-card label="DTRs to review" :value="$stats['pending_dtrs']" icon="heroicon-o-clipboard-document-check" color="amber" /></a>
        <a href="{{ route('company.certificates.index') }}"><x-stat-card label="Certificates issued" :value="$stats['certificates']" icon="heroicon-o-trophy" color="green" /></a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Interns by hours rendered" subtitle="Active interns, grouped by the hours they have rendered with you." class="lg:col-span-2">
            <x-chart type="bar" :labels="$buckets['labels']" :datasets="[['label' => 'Interns', 'data' => $buckets['data']]]" :height="240" />
            <dl class="mt-4 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                @foreach ($buckets['labels'] as $i => $label)
                    <div class="rounded-xl bg-stone-50 p-3 text-center dark:bg-stone-900"><dt class="text-xs text-stone-500">{{ $label }} h</dt><dd class="font-semibold tabular-nums">{{ $buckets['data'][$i] }}</dd></div>
                @endforeach
            </dl>
        </x-card>

        <div class="space-y-4">
            <x-card title="Needs attention">
                <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                    <li class="flex items-center justify-between py-2"><a href="{{ route('company.postings.index') }}" class="hover:underline">Pending applicants</a><span class="font-semibold tabular-nums">{{ $stats['pending_applicants'] }}</span></li>
                    <li class="flex items-center justify-between py-2"><a href="{{ route('company.interviews.index') }}" class="hover:underline">Upcoming interviews</a><span class="font-semibold tabular-nums">{{ $stats['upcoming_interviews'] }}</span></li>
                    <li class="flex items-center justify-between py-2"><a href="{{ route('company.dtrs.index') }}" class="hover:underline">DTRs to review</a><span class="font-semibold tabular-nums">{{ $stats['pending_dtrs'] }}</span></li>
                    <li class="flex items-center justify-between py-2"><a href="{{ route('company.requests.index') }}" class="hover:underline">Document requests</a><span class="font-semibold tabular-nums">{{ $stats['pending_requests'] }}</span></li>
                </ul>
            </x-card>

            <x-card title="Company code" subtitle="Accepted interns enter this code to join your company.">
                <div x-data="{ copied: false, code: @js($company->company_code) }" class="flex flex-wrap items-center gap-3">
                    <code class="rounded-xl bg-stone-100 px-4 py-2 font-mono text-lg font-semibold tracking-widest dark:bg-stone-800">{{ $company->company_code }}</code>
                    <x-button type="button" variant="secondary" icon="heroicon-o-clipboard"
                              @click="navigator.clipboard.writeText(code).then(() => { copied = true; setTimeout(() => copied = false, 1500) })">
                        <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                    </x-button>
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter="CompanyDashboardStats|Company.*DashboardTest|RoleAccess"`
Expected: PASS (`RoleAccessTest` still finds the company code copy handler compiled).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add company dashboard counts and interns-by-hours chart"
```

---

### Task 16: Lifecycle test, demo data, documentation and phase wrap-up

**Files:**
- Create: `tests/Feature/InternshipLifecycleTest.php`
- Modify: `database/seeders/DemoSeeder.php`, `tests/Feature/SeederTest.php`, `README.md`, `CLAUDE.md`
- Verify: full suite, Pint, build, route lists, browser walk.

**Interfaces:**
- Consumes: every route and Action from Tasks 2–15.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/InternshipLifecycleTest.php` (the spec's end-to-end test, over HTTP):

```php
<?php

use App\Enums\ApplicationStatus;
use App\Enums\HoursTier;
use App\Models\Certificate;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\User;
use App\Services\OjtHoursService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('runs the whole internship lifecycle from application to certificate', function () {
    Storage::fake('local');
    $hours = app(OjtHoursService::class);
    $companyUser = User::factory()->company()->create();
    $company = $companyUser->company;
    $intern = User::factory()->intern()->create();
    $posting = InternshipPosting::factory()->for($company)->create();
    $pdf = fn (string $name) => UploadedFile::fake()->create($name, 50, 'application/pdf');

    // 1. intern2 applies
    $this->actingAs($intern)->post(route('intern.applications.store', $posting), ['resume' => $pdf('resume.pdf'), 'endorsement' => $pdf('endorsement.pdf')])->assertSessionHas('success');
    $application = $posting->applications()->firstOrFail();
    $this->actingAs($companyUser)->get(route('company.postings.applicants', $posting))->assertSee($intern->name);

    // 2. company schedules an interview, 3. accepts
    $this->actingAs($companyUser)->post(route('company.applications.interview', $application), ['title' => 'Initial interview', 'venue' => 'Google Meet', 'link' => '', 'scheduled_on' => now()->addDays(2)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '10:30', 'notes' => ''])->assertSessionHas('success');
    $this->actingAs($intern)->get(route('intern.applications.index'))->assertSee('Google Meet');
    $this->actingAs($companyUser)->post(route('company.applications.accept', $application))->assertSessionHas('success');
    expect($application->refresh()->status)->toBe(ApplicationStatus::Accepted)->and($intern->hasActivePlacement())->toBeFalse();

    // 4. intern joins by code
    $this->actingAs($intern)->post(route('intern.internship.join'), ['company_code' => $company->company_code])->assertSessionHas('success');
    $placement = $intern->activePlacement()->firstOrFail();
    $this->actingAs($companyUser)->get(route('company.interns.index'))->assertSee($intern->name);

    // 5. submits DTRs, 6. company approves, 7. hours reach the requirement
    $chunk = (int) ceil($hours->required() / 3);
    foreach (range(1, 3) as $i) {
        $this->actingAs($intern)->post(route('intern.dtrs.store'), ['period_from' => now()->subDays(10 * $i + 5)->toDateString(), 'period_to' => now()->subDays(10 * $i)->toDateString(), 'hours' => $chunk, 'absences' => 0, 'file' => $pdf("dtr{$i}.pdf")])->assertSessionHas('success');
    }
    foreach (Dtr::where('placement_id', $placement->id)->get() as $dtr) {
        $this->actingAs($companyUser)->post(route('company.dtrs.approve', $dtr))->assertSessionHas('success');
        $this->actingAs($companyUser)->from(route('company.dtrs.index'))->post(route('company.dtrs.approve', $dtr))->assertSessionHas('error');
    }
    $total = $intern->internProfile->refresh()->total_hours;
    expect($total)->toBeGreaterThanOrEqual($hours->required())->and($placement->refresh()->hours_rendered)->toBe($total)->and($hours->tier($total))->toBe(HoursTier::Complete);
    $this->actingAs($intern)->get(route('intern.internship.show'))->assertSee('Complete')->assertSee('completed');

    // 8. certificate issued, 9. intern sees it
    $this->actingAs($companyUser)->get(route('company.certificates.index'))->assertSee($intern->name);
    $this->actingAs($companyUser)->post(route('company.certificates.store', $placement), ['file' => $pdf('certificate.pdf')])->assertSessionHas('success');
    $certificate = Certificate::firstOrFail();
    expect($certificate->hours_at_issue)->toBe($total);
    $this->actingAs($intern)->get(route('intern.certificates.index'))->assertSee($company->name)->assertSee(route('files.show', ['certificate', $certificate->id]));
    $this->actingAs($intern)->get(route('files.show', ['certificate', $certificate->id]))->assertOk();
    expect($intern->notifications()->count())->toBeGreaterThanOrEqual(6);
});
```

Update `tests/Feature/SeederTest.php` — extend the expectation chain with:

```php
        ->and(\App\Models\Application::count())->toBe(2)
        ->and(\App\Models\Interview::count())->toBe(1)
        ->and(\App\Models\DocumentRequest::count())->toBe(1)
        ->and(\App\Models\Certificate::count())->toBe(1)
```

and after the chain:

```php
    foreach (\App\Models\Dtr::all() as $dtr) {
        Storage::disk('local')->assertExists($dtr->file_path);
    }
    Storage::disk('local')->assertExists(\App\Models\Certificate::firstOrFail()->file_path);
    Storage::disk('local')->assertExists(\App\Models\Application::firstOrFail()->resume_path);
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="InternshipLifecycle|SeederTest"`
Expected: the lifecycle test PASSES already if Tasks 2–15 are correct (it is the spec's acceptance test — if it fails, the failing step names the task to fix); `SeederTest` FAILS on the new counts.

- [ ] **Step 3: Demo data**

In `database/seeders/DemoSeeder.php`:
- Change `demoPdf()` to take a directory: `private function demoPdf(string $dir = 'classroom/demo'): string` and build the path as `"{$dir}/".Str::uuid().'.pdf'`.
- Give every seeded DTR a real file: in the `foreach ([[120, 8], [100, 6], [80, 4]] ...)` loop and the pending `Dtr::factory()->for($placement)->create(['hours' => 40])` call, add `'file_path' => $this->demoPdf('dtrs/demo')`.
- Capture the two postings: `$webDev = InternshipPosting::factory()->for($company)->create([...])` and `$support = ...`.
- Append after the classroom block (imports: `App\Models\Application`, `App\Models\Interview`, `App\Models\DocumentRequest`, `App\Models\Certificate`):

```php
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
```

- [ ] **Step 4: README and CLAUDE.md**

`README.md`:
- Roadmap: tick Phase 4 — "Internship core: postings, applications, interviews, placements, DTRs, certificates".
- Status line: "Phases 1–4 complete — … the classroom module and the internship flow."
- Add an **Internship flow** subsection under Features (after Classroom): companies post internships; interns apply with a resume and endorsement letter; the company schedules an interview, then accepts or declines; an accepted intern joins by entering the company code, which creates the placement; interns submit DTRs that the company approves (hours are credited exactly once) or disapproves with a note; interns request documents the company fulfils or declines; once an intern reaches 250 hours at a company it can issue a certificate; the company dashboard charts interns by hour bucket; history keeps past placements.
- In "Architecture notes" add: the hours ledger has one writer, `App\Actions\ApproveDtr`, transactional and idempotent; internship records are authorized by policies built on `Company::isManagedBy` and `Placement::isInternOf`.

`CLAUDE.md` — under Architecture, after the Classroom bullet:

```
- **Internship core**: company/intern controllers under their portals; policies on InternshipPosting, Application,
  Placement, Dtr, DocumentRequest and Certificate built on `Company::isManagedBy` and `Placement::isInternOf`.
  Acceptance never places an intern — `PlaceIntern` (company code + accepted application) creates the placement.
  Private PDFs are served through `files.show` kinds `application-resume`, `application-endorsement`, `dtr`,
  `document-request` and `certificate`.
```

- [ ] **Step 5: Final verification**

Run:

```bash
php artisan test
vendor/bin/pint --test
npm run build
php artisan route:list --except-vendor --name=company.
php artisan route:list --except-vendor --name=intern.
```

Expected: all green; the company list contains `dashboard, postings.index/create/store/edit/update/toggle/destroy/applicants, applications.show/interview/decline/accept, interviews.index, interns.index, placements.department/remove, history.index, dtrs.index/approve/disapprove, requests.index/fulfil/decline, certificates.index/store`; the intern list adds `postings.index/show, applications.store/index/cancel, internship.show/join/leave, dtrs.index/store/destroy, requests.index/store/update/destroy, certificates.index` to the Phase 3 routes.

Then seed a scratch database (never the developer's own without asking), start the server and walk:
- as `company@wiis.test`: dashboard chart and counts; Postings (edit, close/reopen); Applicants for "Junior Web Developer Intern" shows intern2 under For Interview; open the profile; Interviews lists the demo interview; accept intern2; Interns shows Wilfredo (300 h, Certificate eligible), assign a department; DTRs shows the pending 40 h DTR — approve it and see 340 h; Requests — fulfil the pending request with a PDF; Certificates — re-issue to Wilfredo; History empty until someone leaves.
- as `intern2@wiis.test`: Internships lists both postings (one shows "already applied"); My applications shows the timeline with the accepted step; My internship — join with `TECHNOVA`; submit a DTR; request a document; check notifications.
- as `intern@wiis.test`: My internship shows TechNova, 340 h, co-intern intern2, the certificate-eligible leave warning; Certificates shows the download.
- Check phone width and dark mode on the dashboard and the applicants page.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "docs: document the internship flow and seed internship demo data"
```

---

## Spec coverage check (Phase 4 scope)

| Spec item | Task |
|---|---|
| Company: postings CRUD | 2 |
| Company: applicants per posting (unplaced only), applicant profile | 5 |
| Company: schedule interview / decline modals | 6 |
| Company: interviews list (accept/decline) | 7 |
| Company: monitor interns (hours tiers, department assign, remove), history | 9 |
| Company: DTR review (idempotent ledger, disapprove adds nothing) | 11 |
| Company: document requests (fulfil/decline) | 13 |
| Company: certificates (eligible list, issue/re-issue, ≥250 h at that company) | 14 |
| Company dashboard: counts + interns by hour bucket | 15 |
| Intern: browse/search postings, apply (resume + endorsement), cannot apply twice | 3 |
| Intern: my applications (timeline, cancel) | 4 |
| Intern: join company by code (accepting does not place) / leave with hour-based warning | 8 |
| Intern: My Internship (progress ring, company card, co-interns) | 8 |
| Intern: DTR submit/list/delete | 10 |
| Intern: document requests CRUD | 12 |
| Intern: certificates | 14 |
| Notifications for each event (application, interview, decision, join/leave/remove, DTR submitted/reviewed, request made/handled, certificate) | 3, 6, 8, 9, 10, 11, 12, 13, 14 |
| Policies: Application, Interview (via Application), Dtr, Placement, DocumentRequest, Certificate, InternshipPosting | 1 |
| Private files for every upload | 1 |
| End-to-end lifecycle test | 16 |
| Role isolation for every new route | 1 (automatic for Tasks 2–15) |

Decisions recorded: joining a company requires an accepted application with that company (prevents joining by a leaked code); applying is refused while placed (in addition to the company-side filter); a cancelled or declined application blocks re-applying to the same posting (unique index, spec rule); disapproved DTRs cannot be approved later — the intern resubmits; certificates may be issued to ended placements; document requests have no decline note; postings with applicants cannot be deleted, only closed.
