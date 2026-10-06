# CLAUDE.md

Guidance for Claude Code when working in this repository.

## What this is

WIIS — Web Based Internship Information System. A from-scratch Laravel 12 rebuild of a plain-PHP capstone project
(legacy code lives on branch `archive/v1`; read it with `git show origin/archive/v1:<path>`, never check it out).
Four role portals — Admin, Adviser, Company, Intern — covering internship postings and applications, interviews,
placement by company code, DTR/hours tracking (486 h to complete, 250 h for a certificate), a Google-Classroom-style
class module, the COS (Contract of Service) partner-company track, document requests, certificates, Excel imports,
in-app notifications and a public landing site.

The design spec and phased roadmap live in `docs/superpowers/specs/2026-10-03-wiis-laravel-rebuild-design.md`.
Read it before starting any feature work; decisions recorded there are settled.

## Commands

- PHP 8.2 is `D:\Programming_Application\xampp8.2.2\php\php.exe` (first on PATH). Node 24 / npm 11.
- Run everything: `composer dev` (serve + queue + logs + vite). Or `php artisan serve` and `npm run dev` separately.
- Fresh DB with demo data: `php artisan migrate:fresh --seed` (SQLite by default; MySQL supported via `.env`).
- Queue worker for credential emails: `php artisan queue:work` (or `composer dev`).
- Tests: `php artisan test` (Pest, in-memory SQLite). Single file: `php artisan test --filter=<Name>`.
- Code style: `vendor/bin/pint` (check with `--test`).
- Front-end build: `npm run build`. No CDN assets; everything goes through Vite.

## Architecture

- **Roles**: single `users` table with a `role` enum (`App\Enums\Role`) and `status` (active/disabled).
  Role data in `intern_profiles` and `companies` (`companies.user_id` is null for COS partner companies).
- **Routes are the contract**: `routes/web.php` has one group per portal (`/admin`, `/adviser`, `/company`,
  `/intern`) guarded by the `role:` middleware; route names `portal.resource.action`.
- **Layers**: thin controllers under `App\Http\Controllers\{Admin,Adviser,Company,Intern,Auth}` → Form Requests
  for validation → single-purpose `App\Actions\*` classes for business operations → Eloquent models.
  Authorization is always a Policy. Statuses are backed enums in `App\Enums` with `label()`/`badgeColor()`.
- **Hours ledger**: only `App\Actions\ApproveDtr` may add hours, inside a transaction, and only on the
  pending → approved transition. Thresholds come from `config/wiis.php`, never hard-coded.
- **Files are private**: uploads go to the `local` disk and are served through `FileController` after a policy
  check. Only avatars and company logos use the `public` disk.
- **Notifications**: Laravel database notifications (`App\Notifications\*`); the topbar bell reads them.
- **Admin portal**: controllers in `App\Http\Controllers\Admin`, admin-wide access via the `role:admin` group
  (no per-record policy needed); imports are `App\Actions\Import*` returning `App\Support\ImportResult`,
  all-or-nothing; spreadsheet I/O in `App\Services\Excel`.
- **Classroom**: adviser/intern controllers under their portals plus the shared `App\Http\Controllers\Classroom\CommentController`;
  access is decided by Policies on each classroom model, all built on `ClassSection::isAdvisedBy|enrolls|hasMember`.
  Rich text goes through `App\Services\HtmlSanitizer` on save and is rendered only via `<x-rich-text>`. Private PDFs are
  served through `files.show` kinds `class-submission` and `class-resource`.
- **Search**: search inputs go through `App\Support\Search::any|like` (escaped LIKE).
- **Blade attributes**: dynamic text inside Blade component-tag attributes must be a bound expression
  (`:title="..."`), never `{{ }}` inside the attribute string.
- **UI**: Blade + Tailwind 4 + Alpine.js. Shared Blade components in `resources/views/components`; layouts in
  `resources/views/layouts` (`app`, `auth`, `public`). Branding (institution name, logo, colors, support
  contact) comes from `config/wiis.php`.

## Working conventions

- TDD: write the failing Pest test first, then the code. Every Action has a unit test; every portal route has a
  feature test including role isolation.
- Keep business rules out of controllers and Blade.
- Do not read from or reference any sibling folder in `htdocs`; this repo is self-contained.

## Commit conventions

Use conventional commit prefixes (`feat:`, `fix:`, `refactor:`, `chore:`), keep the message short but
descriptive, and never include Claude attribution (no name, no `Co-Authored-By` trailer). Commit each completed,
tested task directly on `main`.
