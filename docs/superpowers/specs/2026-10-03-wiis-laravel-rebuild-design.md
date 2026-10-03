# WIIS Laravel Rebuild — Design Spec and Implementation Plan

## Context

WIIS (Web Based Internship Information System) was the user's university capstone, written in plain PHP for Quezon City University's placement office. The legacy code lives on branch `origin/archive/v1` of `D:\Programming_Application\xampp-7.4.1\htdocs\internship-information-system`. The `main` branch is a fresh Laravel 12 skeleton (only `laravel/ui` Bootstrap auth scaffold added, no vendor/node_modules installed yet).

**Goal:** rebuild WIIS from scratch on `main` as a **portfolio showcase**: clean idiomatic Laravel architecture, a distinctive modern UI, tests, seeded demo data, and a strong README. Full feature parity with the legacy app (all four portals plus Classroom, COS track, Excel imports, public site), with its many bugs fixed.

**Decisions already made with the user (do not re-litigate):**
- Work ONLY inside `internship-information-system`. Never touch or read the sibling `Web_Based_Internship_Information_System` folder.
- Stack: Laravel 12, Blade + Tailwind 4 + Alpine.js, Vite. No Livewire/Inertia. Pest for tests.
- Branding: product is "WIIS"; institution name/logo/colors/support email configurable via `config/wiis.php` + `.env`. Demo seed uses QCU-like sample data.
- Architecture: portal-namespaced controllers + shared core (Actions, Policies, Enums, Form Requests, Notifications).
- Scope: everything (core internship flow, Classroom, COS track, Excel imports, public landing site).
- Commits: one commit per completed task directly on `main`, conventional prefixes (`feat:`, `fix:`, `refactor:`, `chore:`), short descriptive messages, **never any Claude attribution** (no `Co-Authored-By`, no "Generated with"). This must also be written into the project `CLAUDE.md` under "Commit conventions".

**Environment:** PHP 8.2.12 (`D:\Programming_Application\xampp8.2.2\php`), Composer 2.8, Node 24, npm 11. No MySQL CLI on PATH; XAMPP 8.2 MariaDB available if needed. Local dev defaults to SQLite; MySQL documented.

## Legacy feature inventory (what must be preserved)

Source of truth: `git show origin/archive/v1:<path>`; root `app/` scripts were deleted at the branch tip and exist in commit `6ecd7be`. Schema: `wiis.sql`. UI reference screenshots: `screenshot/*.png` on that branch.

**Roles:** Admin, Adviser, Company, Intern.

**Business rules:**
- OJT completion = **486 hours**. Monitor colors: <250 red, 250–485 amber, ≥486 green.
- Certificate eligibility = **≥250 hours at that company**.
- Application flow: pending → for interview → accepted/declined (or declined directly). Accepting does NOT place the intern; intern joins by entering the company code. Intern cannot apply twice to the same posting. Only unplaced interns appear as applicants.
- COS (Contract of Service) track: admin adds partner companies (no login). Intern applies with an acceptance-letter PDF; the **class adviser** approves/declines the application and reviews the intern's DTRs.
- DTR: pending → approved/disapproved. Approval accrues hours + absences to the placement and the intern total. Must be idempotent (legacy bugs: double approval, disapprove added hours, archive updated across all companies).
- Classroom: admin imports classes; adviser claims a class via join code (blocked if it already has an adviser); adviser can create/edit/leave classes (leave logs history). Intern joins a class via join code (one class per intern). Stream with rich-text announcements + comments. Folders can be locked; uploads into locked folders are accepted but flagged late. Submissions: pending → approved/declined with a note.
- Company sign-up requires permit + MOA PDFs; account is held "pending verification" until admin approves. Disabled accounts of any role cannot log in.
- Document requests: intern requests a named document from the company with a message; company fulfils by uploading a PDF or declines.
- Certificates: company issues a PDF to an eligible intern; re-issue allowed.
- Company history: past placements with dates, hours, department. Intern leaving a company shows a warning depending on hours at that company.
- Admin Excel imports: interns (first, last, gender, email, phone, student number, section, school year), advisers (first, last, gender, email, phone), classes (section, course code, adviser member no, schedule, day, school year, subject). Generates member numbers + random passwords, emails credentials. Downloadable templates.
- Notifications (in-app): new company → admin; application/DTR/document request → company; interview scheduled/accepted/declined, certificate → intern; comment/upload → class.
- Email: imported credentials, password reset, contact form.
- Dashboards: Admin (counts; placed vs unplaced, sectioned vs not, active vs disabled per role), Company (counts; interns by hour bucket 0–250 / 251–300 / 301–400 / 401+).

## Design

### 1. Data model (migrations in `database/migrations/`)

Principles: FKs everywhere, backed enums for statuses, real date/time columns, files on the **private** `local` disk with only paths stored, one table per concept, single hours ledger.

| Table | Key columns |
|---|---|
| `users` | first_name, middle_name?, last_name, email (unique), password, `role` enum(admin, adviser, company, intern), `member_no` (unique display code e.g. `INT-2026-00001`), avatar_path?, phone?, `status` enum(active, disabled), last_login_at?, timestamps |
| `intern_profiles` | user_id FK, student_number (unique), gender?, birthdate?, present_address?, permanent_address?, about?, school_year?, resume_path?, class_section_id FK nullable, total_hours (int, cached), total_absences (int, cached) |
| `companies` | user_id FK **nullable** (null = COS partner without login), name, type?, `company_code` (unique join code), logo_path?, about?, website?, address?, permit_path?, moa_path?, `approval_status` enum(pending, approved, rejected), approved_by FK?, approved_at?, timestamps |
| `departments` | name (seeded lookup) |
| `class_sections` | adviser_id FK nullable, course_code, subject, section, day, starts_at (time), ends_at (time), school_year, `join_code` (unique), `status` enum(active, archived), timestamps |
| `class_adviser_logs` | class_section_id, adviser_id, joined_at, left_at? |
| `announcements` | class_section_id, author_id, body (sanitized HTML), timestamps |
| `announcement_comments` | announcement_id, author_id, body, timestamps |
| `class_folders` | class_section_id, name, is_locked, timestamps |
| `class_submissions` | class_folder_id, intern_id, title, file_path, `status` enum(pending, approved, declined), is_late, reviewer_note?, reviewed_by?, reviewed_at?, timestamps |
| `class_resources` | class_section_id, uploader_id, title, file_path |
| `internship_postings` | company_id, title, city, description, responsibilities, closing_date, required_hours?, vacancies, contact_name, contact_position, contact_phone, `status` enum(open, closed), timestamps |
| `applications` | internship_posting_id, intern_id, resume_path, endorsement_path, `status` enum(pending, for_interview, accepted, declined, cancelled), decline_reason?, decided_at?, timestamps; unique(posting, intern) |
| `interviews` | application_id, title, venue (platform/place), link?, scheduled_on (date), starts_at, ends_at, notes? |
| `cos_applications` | company_id (partner), intern_id, acceptance_letter_path, `status` enum(pending, approved, declined), reviewed_by?, reviewed_at? |
| `placements` | intern_id, company_id, department_id?, started_at, ended_at?, hours_rendered (cached), absences (cached), timestamps. Active placement = `ended_at IS NULL`; at most one per intern |
| `dtrs` | placement_id, file_path, period_from, period_to, hours, absences, `status` enum(pending, approved, disapproved), reviewer_id?, reviewer_note?, reviewed_at?, timestamps |
| `document_requests` | placement_id, control_no (unique), document_name, message?, `status` enum(pending, fulfilled, declined), file_path?, handled_at? |
| `certificates` | placement_id, file_path, hours_at_issue, issued_at |
| `notifications` | Laravel default (`php artisan make:notifications-table`) |
| `password_reset_tokens`, `sessions`, `cache`, `jobs` | Laravel defaults (already present) |

Hours ledger rule: `ApproveDtr` action, inside a DB transaction, only when status transitions pending→approved: `placement.hours_rendered += dtr.hours`, `placement.absences += dtr.absences`, `intern_profile.total_hours += dtr.hours`, `total_absences += dtr.absences`. Disapprove never touches hours.

Thresholds in `config/wiis.php`: `required_hours => 486`, `certificate_min_hours => 250`, `hour_buckets`, plus branding keys (`institution_name`, `institution_short`, `logo_path`, `support_email`, `support_phone`, `address`, `demo_mode`).

### 2. Auth, roles, architecture

- Remove `laravel/ui` and its generated controllers/views/sass. Hand-written `Auth` controllers: `LoginController`, `RegisterInternController` (requires valid class join code), `RegisterCompanyController` (permit + MOA PDF), `ForgotPasswordController`/`ResetPasswordController` (built-in `Password` broker, email link), `ChangePasswordController`, logout. Login blocks `status = disabled` for all roles and sends pending companies to a "pending verification" page.
- `App\Enums\Role` on users; `EnsureRole` middleware aliased `role` in `bootstrap/app.php`; route groups `/admin`, `/adviser`, `/company`, `/intern`, route names `admin.*`, `adviser.*`, `company.*`, `intern.*`. Dashboard redirect after login by role.
- Policies for every record type (Application, Interview, Dtr, Placement, DocumentRequest, Certificate, ClassSection, ClassFolder, ClassSubmission, Announcement, InternshipPosting, CosApplication, Company). Registered via model discovery.
- Single `FileController` (`files.show`, signed-or-authorized) streams any private file after a policy check. Only company logos and avatars go on the public disk.
- Layers: `App\Http\Controllers\{Admin,Adviser,Company,Intern,Auth}` (thin) → `App\Http\Requests\*` (validation, PDF mime + 5 MB) → `App\Actions\*` invokable classes (ApproveDtr, DisapproveDtr, PlaceIntern, LeaveCompany, RemoveIntern, ScheduleInterview, DecideApplication, CancelApplication, ApproveCompany, RejectCompany, DisableUser, ReactivateUser, JoinClass, LeaveClass, SubmitClassDocument, ReviewClassSubmission, ToggleFolderLock, DecideCosApplication, FulfilDocumentRequest, IssueCertificate, ImportInterns, ImportAdvisers, ImportClasses) → Models/Enums.
- `App\Enums\*` backed string enums with `label()` and `badgeColor()` helpers.
- `App\Services`: `OjtHoursService` (progress %, color tier, eligibility, bucket), `MemberNumberGenerator`, `JoinCodeGenerator`, `ExcelImportService` + template builder (phpoffice/phpspreadsheet), `HtmlSanitizer` (mews/purifier or ezyang/htmlpurifier directly).
- `App\Notifications\*` using `database` channel; `mail` added for ImportedCredentials, ContactMessage; password reset uses Laravel's built-in.
- Queue: `database` driver for mail + imports (`php artisan queue:work`; `composer dev` already runs it).
- Domain exceptions (`App\Exceptions\DomainRuleViolation`) rendered as flash errors. Custom 403/404/419/500 views.

### 3. UI / design system

- Tailwind 4 (`@tailwindcss/vite` already in package.json), Alpine.js, Chart.js, Trix, `blade-ui-kit/blade-heroicons` installed via npm/composer. Remove sass. No CDNs.
- Tokens in `resources/css/app.css` via `@theme`: evergreen-teal primary, warm off-white surfaces, amber/green/rose semantic colors, display font (Bricolage Grotesque or Instrument Sans via Google Fonts `<link>`) + Inter body. Dark mode: Tailwind `class` strategy, toggle persisted in localStorage.
- Layouts: `layouts/app` (sidebar + topbar shell), `layouts/auth` (centered card), `layouts/public` (marketing nav/footer).
- Components (`resources/views/components/`): `card`, `stat-card`, `badge` (accepts an enum), `table` + `table.empty`, `modal`, `dropdown`, `tabs`, `form.input/select/textarea/file/editor`, `progress-ring`, `timeline`, `flash`, `confirm-form` (POST/DELETE with confirm), `empty-state`, `page-header`, `avatar`, `notification-bell`.
- Signature screens: role dashboards with Chart.js; intern "My Internship" progress ring; Google-Classroom-style stream/folders; company applicant pipeline with modals; custom landing page (hero, how-it-works per audience, features, contact, terms).
- Invoke the `frontend-design` skill when building the design system in Phase 1.

### 4. Testing & tooling

- Pest 4 (`pestphp/pest`, `pestphp/pest-plugin-laravel`) replacing PHPUnit runner; `phpunit.xml` keeps in-memory SQLite. Feature tests per portal route + role isolation; lifecycle tests per flow; unit tests per Action and for `OjtHoursService`.
- Laravel Pint. GitHub Actions workflow: composer install, npm ci + build, pint --test, pest.
- Factories for every model; `DatabaseSeeder` → `DemoSeeder` with accounts: admin@wiis.test, adviser@wiis.test, company@wiis.test, partner company, intern@wiis.test (placed, with hours), intern2@wiis.test (unplaced). Password `password`. `DEMO_MODE=true` shows one-click login buttons.

## Implementation roadmap (phases)

Each phase = its own detailed plan (via the `superpowers:writing-plans` skill) executed task by task with TDD, one commit per task. Phase 1 is detailed below; later phases are scoped here and planned when reached.

### Phase 0 — Repo bootstrap (first commits)
1. Save this design as `docs/superpowers/specs/2026-10-03-wiis-laravel-rebuild-design.md`.
2. Create project `CLAUDE.md` (what it is, commands, architecture pointers, **Commit conventions** section verbatim from the user).
3. `composer install`, `npm install`, `.env` (SQLite), `php artisan key:generate`.

### Phase 1 — Foundation
- `chore:` remove laravel/ui (package, `app/Http/Controllers/Auth/*`, `HomeController`, `resources/sass`, `resources/views/{auth,home,layouts}`), add Pest, Pint config, phpspreadsheet, blade-heroicons, purifier; npm: alpinejs, chart.js, trix; drop sass/bootstrap/popper. Update `vite.config.js` inputs to `resources/css/app.css` + `resources/js/app.js`.
- `config/wiis.php` + `.env.example` keys.
- Enums (`Role`, `AccountStatus`, `ApprovalStatus`, `ApplicationStatus`, `DtrStatus`, `SubmissionStatus`, `CosApplicationStatus`, `DocumentRequestStatus`, `PostingStatus`, `ClassStatus`).
- All migrations from the table above; models with relationships, casts, scopes; factories; `DemoSeeder`.
- `EnsureRole` middleware + alias; `routes/web.php` skeleton with the four groups and per-role dashboard placeholders; `RedirectIfAuthenticated` by role.
- Design system: `app.css` tokens, three layouts, component library, dark-mode toggle.
- Auth flows (login, intern + company register, forgot/reset, change password, logout, pending-verification + disabled pages) with Form Requests and feature tests.
- Notifications infrastructure: `notifications` table, `notification-bell` component, `NotificationController` (index, mark read, mark all, delete).
- `FileController` + `files.show` route + policy-based access.
- Profile page (shared): edit names/phone/about, avatar upload (public disk, 1 MB), role-specific extra fields.
- GitHub Actions CI. README first draft.

### Phase 2 — Admin portal
Dashboard (counts + 3 charts), interns/advisers/companies/partner-companies index with search/filter/pagination + show pages, pending company approvals with permit/MOA viewer (approve/reject), disable/reactivate + history list, class sections list, departments CRUD (simple), Excel imports (3 types) with template downloads, queued credential emails.

### Phase 3 — Classroom
Adviser: my classes (create/edit/join-by-code/leave), class page tabs (stream, documents, people), announcements + comments (Trix + sanitizer), folders (create/lock/unlock/delete), submission review queue grouped pending/approved/declined/late, shared resources, interns list with hours + print view. Intern: join class, stream + comments, folder upload (late flag), my submissions, roster.

### Phase 4 — Internship core
Company: postings CRUD, applicants per posting (unplaced only), applicant profile + schedule interview / decline modals, interviews list (accept/decline), monitor interns (hours tiers, department assign, remove), DTR review, document requests (fulfil/decline), certificates (eligible list, issue/re-issue), history. Intern: browse/search postings, apply (resume + endorsement), my applications (timeline, cancel), join company by code / leave with hour-based warning, My Internship (progress ring, company card, co-interns), DTR submit/list/delete, document requests CRUD, certificates. Notifications wired for each event.

### Phase 5 — COS track
Intern: partner company list + apply with acceptance letter, my COS applications. Adviser: COS applications review (approve creates placement on partner company), COS DTR review (reuses `dtrs`, reviewer = adviser). Admin: partner companies CRUD (from Phase 2) finalized.

### Phase 6 — Public site & polish
Landing page, contact form (queued mail), terms page, custom error pages, demo-mode one-click login, accessibility/responsive pass, screenshots, final README (setup, demo accounts, architecture, what changed vs legacy, business rules), `CLAUDE.md` refresh.

## Verification (per phase and overall)

- `php artisan test` (Pest) green; `vendor/bin/pint --test` clean; `npm run build` succeeds.
- `php artisan migrate:fresh --seed` then `composer dev` (or `php artisan serve` + `npm run dev`); log in as each demo account and walk the portal.
- End-to-end lifecycle test (automated, Phase 4): intern2 applies → company schedules interview → accepts → intern joins by code → submits DTRs → company approves → hours reach 486 → certificate issued → intern sees it; plus the COS equivalent in Phase 5.
- Security checks: non-owner cannot download another user's file (403), disabled user cannot log in, pending company is held, role groups reject other roles (403), double-approving a DTR does not double hours, disapproving adds nothing.
- Visual check in browser (Chrome tools) at desktop and phone widths, light and dark.

## Files most touched in Phase 1

`composer.json`, `package.json`, `vite.config.js`, `bootstrap/app.php`, `config/wiis.php` (new), `routes/web.php`, `database/migrations/*` (new), `database/seeders/DemoSeeder.php` (new), `app/Enums/*`, `app/Models/*`, `app/Http/Middleware/EnsureRole.php`, `app/Http/Controllers/Auth/*`, `app/Http/Controllers/{FileController,NotificationController,ProfileController}.php`, `resources/css/app.css`, `resources/js/app.js`, `resources/views/{layouts,components,auth,profile,notifications}/*`, `tests/Feature/*`, `.github/workflows/ci.yml`, `CLAUDE.md`, `README.md`.
