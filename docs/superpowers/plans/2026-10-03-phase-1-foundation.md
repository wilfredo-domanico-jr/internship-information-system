# WIIS Phase 1 — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the bare Laravel 12 skeleton into the WIIS foundation: toolchain, config, full schema with models and factories, seeded demo data, role-aware shell UI, authentication for all four roles, notifications, private file delivery, and profile management, all under Pest tests and CI.

**Architecture:** Thin portal-namespaced controllers call Form Requests for validation and single-purpose `App\Actions` classes for business operations; all statuses are PHP backed enums stored as short strings; authorization is Policies; files live on the private `local` disk and are streamed through one `FileController`. The UI is Blade + Tailwind 4 + Alpine with anonymous component layouts and a small component library.

**Tech Stack:** PHP 8.2, Laravel 12.62, Pest 3 (PHPUnit 11), Tailwind 4 via `@tailwindcss/vite`, Alpine.js, `blade-ui-kit/blade-heroicons`, SQLite (dev/test), Laravel Pint, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-10-03-wiis-laravel-rebuild-design.md` (sections 1–4 and "Phase 1 — Foundation").

## Global Constraints

- PHP is **8.2.12**, so Pest must be `^3.0` (Pest 4 needs PHP 8.3). PHPUnit stays at the installed `^11.5`.
- Laravel **12.62**. Middleware aliases and redirects are configured in `bootstrap/app.php`, not a Kernel.
- No CDN scripts or styles. Only exception: the Google Fonts `<link>` for Bricolage Grotesque + Inter.
- Every status column is a `string(20)` cast to an `App\Enums\*` backed enum (portable to SQLite and MySQL). Never a DB-level enum.
- Thresholds and branding come from `config/wiis.php`; `486` and `250` never appear in application code or Blade.
- Uploaded documents go to the `local` (private) disk and are served only by `FileController`. Avatars go to the `public` disk.
- Tests run on in-memory SQLite (already configured in `phpunit.xml`). Feature tests use `RefreshDatabase`.
- Commits: conventional prefixes (`feat:`, `fix:`, `refactor:`, `chore:`, `docs:`, `test:`), short descriptive messages, directly on `main`, **never any Claude attribution** (no `Co-Authored-By`, no "Generated with").
- Work only inside `D:\Programming_Application\xampp-7.4.1\htdocs\internship-information-system`. Legacy reference: `git show origin/archive/v1:<path>`.
- Run `vendor/bin/pint --dirty` before each commit.
- Shell note: this machine runs Git Bash on Windows. Very long heredocs fail; write files with the editor/Write tool.

## Review Focus

1. **Email with capitals or surrounding spaces** at login and registration (`" Juan@Example.com "`) must authenticate/register as `juan@example.com`. Pinned in Task 11 (login) and Task 12 (registration) via `prepareForValidation` normalization.
2. **Join code of an archived class** at intern registration must be rejected with a field error, not create an orphaned intern. Pinned in Task 12.
3. **A non-PDF renamed to `.pdf`** (permit/MOA) must be rejected by server-side MIME sniffing (`mimetypes:application/pdf`), not by extension. Pinned in Task 13.
4. **A user disabled while already signed in** must be logged out on their next request, not keep a working session. Pinned in Task 10 (`EnsureAccountUsable`) and tested in Task 11.
5. **Marking or deleting another user's notification by guessing its UUID** must 404 (query scoped to the current user). Pinned in Task 15.

## File Structure (what Phase 1 creates)

```
app/
  Actions/RegisterIntern.php, RegisterCompany.php
  Enums/Concerns/HasLabel.php
  Enums/{Role,AccountStatus,ApprovalStatus,ApplicationStatus,DtrStatus,SubmissionStatus,
         CosApplicationStatus,DocumentRequestStatus,PostingStatus,ClassStatus,HoursTier}.php
  Http/Controllers/Auth/{LoginController,RegisterInternController,RegisterCompanyController,
         ForgotPasswordController,ResetPasswordController,ChangePasswordController,AccountStatusController}.php
  Http/Controllers/{DashboardRedirectController,NotificationController,FileController,ProfileController,AvatarController}.php
  Http/Controllers/{Admin,Adviser,Company,Intern}/DashboardController.php
  Http/Middleware/{EnsureRole,EnsureAccountUsable}.php
  Http/Requests/Auth/{LoginRequest,RegisterInternRequest,RegisterCompanyRequest,ChangePasswordRequest}.php
  Http/Requests/{UpdateProfileRequest,StoreAvatarRequest}.php
  Models/{User,InternProfile,Company,Department,ClassSection,ClassAdviserLog,Announcement,AnnouncementComment,
          ClassFolder,ClassSubmission,ClassResource,InternshipPosting,Application,Interview,CosApplication,
          Placement,Dtr,DocumentRequest,Certificate}.php
  Notifications/{InternJoinedClass,CompanyRegistered}.php
  Policies/CompanyPolicy.php
  Services/{MemberNumberGenerator,JoinCodeGenerator,OjtHoursService}.php
  Support/{Navigation,PrivateFiles}.php
  View/Components/NotificationBell.php
config/wiis.php
database/migrations/0001_01_01_000000_create_users_table.php (rewritten)
database/migrations/2026_10_03_00000{1..5}_*.php + notifications table
database/factories/* (one per model)
database/seeders/{DatabaseSeeder,DepartmentSeeder,DemoSeeder}.php
resources/css/app.css, resources/js/app.js
resources/views/components/layouts/{app,auth}.blade.php + layouts/partials/{sidebar,topbar,nav-item}.blade.php
resources/views/components/{badge,card,stat-card,page-header,avatar,empty-state,flash,confirm-form,dropdown,
          modal,button,progress-ring,brand}.blade.php
resources/views/components/form/{label,error,input,select,textarea,file,checkbox}.blade.php
resources/views/components/notification-bell.blade.php (class-based)
resources/views/auth/{login,register-intern,register-company,forgot-password,reset-password}.blade.php
resources/views/account/pending.blade.php
resources/views/{admin,adviser,company,intern}/dashboard.blade.php
resources/views/notifications/index.blade.php
resources/views/profile/edit.blade.php
routes/web.php
tests/Pest.php, tests/Unit/**, tests/Feature/**
.github/workflows/ci.yml, pint.json, README.md
```

Task order matters: 1 toolchain → 2 config → 3 enums → 4–6 schema/models → 7 services → 8 seeders → 9 design system → 10 middleware/routes/dashboards → 11 login → 12 intern sign-up → 13 company sign-up → 14 passwords → 15 notifications → 16 files → 17 profile → 18 CI/README.

Public marketing layout is deferred to Phase 6 (nothing in Phase 1 uses it).

---

### Task 1: Replace the laravel/ui scaffold with Pest, Tailwind 4 and Alpine

**Files:**
- Delete: `app/Http/Controllers/Auth/*`, `app/Http/Controllers/HomeController.php`, `resources/sass/`, `resources/js/bootstrap.js`, `resources/views/auth/`, `resources/views/home.blade.php`, `resources/views/layouts/`
- Modify: `composer.json` (via composer), `package.json`, `vite.config.js`, `resources/css/app.css`, `resources/js/app.js`, `routes/web.php`, `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`
- Create: `tests/Pest.php`, `pint.json`, `resources/views/welcome.blade.php`

**Interfaces:**
- Produces: a Pest test runner (`php artisan test`), Vite entry points `resources/css/app.css` and `resources/js/app.js`, `window.Alpine`, Heroicons Blade components (`<x-heroicon-o-bell />`).

- [ ] **Step 1: Swap composer packages**

```bash
composer remove laravel/ui --no-interaction
composer require pestphp/pest:^3.0 pestphp/pest-plugin-laravel:^3.0 --dev --with-all-dependencies --no-interaction
composer require blade-ui-kit/blade-heroicons --no-interaction
```

Expected: `composer.json` no longer lists `laravel/ui`; `vendor/bin/pest` exists.

- [ ] **Step 2: Delete the scaffold files**

```bash
rm -rf app/Http/Controllers/Auth app/Http/Controllers/HomeController.php resources/sass resources/js/bootstrap.js resources/views/auth resources/views/home.blade.php resources/views/layouts
```

- [ ] **Step 3: Write the Pest bootstrap and the two example tests**

`tests/Pest.php`:
```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit');
```

`tests/Feature/ExampleTest.php`:
```php
<?php

it('serves the welcome page', function () {
    $this->get('/')->assertOk()->assertSee('WIIS');
});
```

`tests/Unit/ExampleTest.php`:
```php
<?php

it('boots the application', function () {
    expect(app()->environment())->toBe('testing');
});
```

- [ ] **Step 4: Run tests to verify they fail**

Run: `php artisan test`
Expected: FAIL — the welcome test fails because `/` still redirects to a missing `/login`.

- [ ] **Step 5: Replace the front-end toolchain**

`package.json`:
```json
{
    "$schema": "https://www.schemastore.org/package.json",
    "private": true,
    "type": "module",
    "scripts": {
        "build": "vite build",
        "dev": "vite"
    },
    "devDependencies": {
        "@tailwindcss/vite": "^4.0.0",
        "alpinejs": "^3.14.9",
        "axios": "^1.11.0",
        "concurrently": "^9.0.1",
        "laravel-vite-plugin": "^2.0.0",
        "tailwindcss": "^4.0.0",
        "vite": "^7.0.7"
    }
}
```

Run: `npm install`

`vite.config.js`:
```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
```

`resources/css/app.css` (tokens are completed in Task 9; this is the minimum):
```css
@import "tailwindcss";

@source "../views";
@source "../../app/View";
@source "../../app/Support";
@source "../../app/Enums";

@custom-variant dark (&:where(.dark, .dark *));
```

`resources/js/app.js`:
```js
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
```

- [ ] **Step 6: Minimal welcome route and view, Pint config**

`routes/web.php`:
```php
<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
```

`resources/views/welcome.blade.php`:
```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('wiis.name', 'WIIS') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen grid place-items-center bg-stone-50 text-stone-800">
    <h1 class="text-3xl font-semibold">WIIS</h1>
</body>
</html>
```

`pint.json`:
```json
{
    "preset": "laravel"
}
```

- [ ] **Step 7: Verify tests, build and style pass**

Run: `php artisan test && npm run build && vendor/bin/pint --test`
Expected: 2 passed; Vite writes `public/build/manifest.json`; Pint reports no issues.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "chore: replace laravel/ui scaffold with Pest, Tailwind 4 and Alpine"
```

---

### Task 2: WIIS configuration

**Files:**
- Create: `config/wiis.php`, `tests/Unit/WiisConfigTest.php`
- Modify: `.env.example`, `.env`

**Interfaces:**
- Produces: `config('wiis.name')`, `config('wiis.tagline')`, `config('wiis.institution.name|short|office|logo')`, `config('wiis.support.email|phone|address')`, `config('wiis.hours.required|certificate_min|buckets')`, `config('wiis.uploads.max_pdf_kb|max_avatar_kb')`, `config('wiis.demo_mode')`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/WiisConfigTest.php`:
```php
<?php

it('exposes OJT thresholds and branding defaults', function () {
    expect(config('wiis.hours.required'))->toBe(486)
        ->and(config('wiis.hours.certificate_min'))->toBe(250)
        ->and(config('wiis.hours.buckets'))->toHaveCount(4)
        ->and(config('wiis.uploads.max_pdf_kb'))->toBe(5120)
        ->and(config('wiis.uploads.max_avatar_kb'))->toBe(1024)
        ->and(config('wiis.institution.name'))->toBeString()->not->toBeEmpty()
        ->and(config('wiis.support.email'))->toContain('@')
        ->and(config('wiis.demo_mode'))->toBeFalse();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=WiisConfigTest`
Expected: FAIL — `config('wiis.hours.required')` is null.

- [ ] **Step 3: Create the config file**

`config/wiis.php`:
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product and institution branding
    |--------------------------------------------------------------------------
    */

    'name' => env('WIIS_NAME', 'WIIS'),
    'tagline' => env('WIIS_TAGLINE', 'Web Based Internship Information System'),

    'institution' => [
        'name' => env('WIIS_INSTITUTION_NAME', 'Quezon City University'),
        'short' => env('WIIS_INSTITUTION_SHORT', 'QCU'),
        'office' => env('WIIS_INSTITUTION_OFFICE', 'Scholarship, Placement and Alumni Relations Division'),
        // Path under public/ to a logo image, or null to render a text mark.
        'logo' => env('WIIS_INSTITUTION_LOGO'),
    ],

    'support' => [
        'email' => env('WIIS_SUPPORT_EMAIL', 'support@wiis.test'),
        'phone' => env('WIIS_SUPPORT_PHONE', '+63 900 000 0000'),
        'address' => env('WIIS_ADDRESS', 'Quezon City, Philippines'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OJT hour rules
    |--------------------------------------------------------------------------
    | required:        hours needed to complete the internship
    | certificate_min: hours at one company before it may issue a certificate
    | buckets:         [min, max|null, label] used by company dashboards
    */

    'hours' => [
        'required' => (int) env('WIIS_REQUIRED_HOURS', 486),
        'certificate_min' => (int) env('WIIS_CERTIFICATE_MIN_HOURS', 250),
        'buckets' => [
            [0, 250, '0–250'],
            [251, 300, '251–300'],
            [301, 400, '301–400'],
            [401, null, '401+'],
        ],
    ],

    'uploads' => [
        'max_pdf_kb' => 5120,
        'max_avatar_kb' => 1024,
    ],

    // Shows one-click demo sign-in buttons on the login page. Never enable in production.
    'demo_mode' => (bool) env('DEMO_MODE', false),

];
```

Append to `.env.example` and `.env` (after the `VITE_APP_NAME` line):
```
WIIS_NAME=WIIS
WIIS_INSTITUTION_NAME="Quezon City University"
WIIS_INSTITUTION_SHORT=QCU
WIIS_INSTITUTION_OFFICE="Scholarship, Placement and Alumni Relations Division"
WIIS_INSTITUTION_LOGO=
WIIS_SUPPORT_EMAIL=support@wiis.test
WIIS_SUPPORT_PHONE="+63 900 000 0000"
WIIS_ADDRESS="Quezon City, Philippines"
WIIS_REQUIRED_HOURS=486
WIIS_CERTIFICATE_MIN_HOURS=250
DEMO_MODE=false
```

Also change `APP_NAME=Laravel` to `APP_NAME=WIIS` in `.env.example`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=WiisConfigTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add config/wiis.php .env.example tests/Unit/WiisConfigTest.php
git commit -m "feat: add wiis config for branding and OJT thresholds"
```

---

### Task 3: Enums

**Files:**
- Create: `app/Enums/Concerns/HasLabel.php`, `app/Enums/Role.php`, `app/Enums/AccountStatus.php`, `app/Enums/ApprovalStatus.php`, `app/Enums/ApplicationStatus.php`, `app/Enums/DtrStatus.php`, `app/Enums/SubmissionStatus.php`, `app/Enums/CosApplicationStatus.php`, `app/Enums/DocumentRequestStatus.php`, `app/Enums/PostingStatus.php`, `app/Enums/ClassStatus.php`, `app/Enums/HoursTier.php`
- Test: `tests/Unit/EnumsTest.php`

**Interfaces:**
- Produces: every enum has `label(): string`, `badgeColor(): string` (one of `gray|amber|green|rose|sky|teal`), static `options(): array<string,string>` (value => label). `Role` additionally has `dashboardRoute(): string` (`admin.dashboard` …) and `memberPrefix(): string` (`ADM|ADV|CMP|INT`). `ApplicationStatus::isOpen()`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/EnumsTest.php`:
```php
<?php

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ClassStatus;
use App\Enums\CosApplicationStatus;
use App\Enums\DocumentRequestStatus;
use App\Enums\DtrStatus;
use App\Enums\HoursTier;
use App\Enums\PostingStatus;
use App\Enums\Role;
use App\Enums\SubmissionStatus;

it('labels roles and maps them to dashboards and member prefixes', function () {
    expect(Role::Intern->label())->toBe('Intern')
        ->and(Role::from('adviser')->dashboardRoute())->toBe('adviser.dashboard')
        ->and(Role::Company->memberPrefix())->toBe('CMP')
        ->and(Role::Admin->memberPrefix())->toBe('ADM')
        ->and(Role::options())->toBe([
            'admin' => 'Admin', 'adviser' => 'Adviser', 'company' => 'Company', 'intern' => 'Intern',
        ]);
});

it('turns snake_case values into human labels', function () {
    expect(ApplicationStatus::ForInterview->label())->toBe('For Interview')
        ->and(ApplicationStatus::ForInterview->value)->toBe('for_interview')
        ->and(ApplicationStatus::ForInterview->isOpen())->toBeTrue()
        ->and(ApplicationStatus::Declined->isOpen())->toBeFalse();
});

it('gives every status a known badge color', function (string $enum) {
    $allowed = ['gray', 'amber', 'green', 'rose', 'sky', 'teal'];
    foreach ($enum::cases() as $case) {
        expect($case->badgeColor())->toBeIn($allowed);
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
})->with([
    AccountStatus::class, ApprovalStatus::class, ApplicationStatus::class, DtrStatus::class,
    SubmissionStatus::class, CosApplicationStatus::class, DocumentRequestStatus::class,
    PostingStatus::class, ClassStatus::class, HoursTier::class,
]);

it('colors hours tiers red, amber, green', function () {
    expect(HoursTier::Low->badgeColor())->toBe('rose')
        ->and(HoursTier::Mid->badgeColor())->toBe('amber')
        ->and(HoursTier::Complete->badgeColor())->toBe('green');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=EnumsTest`
Expected: FAIL — class `App\Enums\Role` not found.

- [ ] **Step 3: Write the trait and enums**

`app/Enums/Concerns/HasLabel.php`:
```php
<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

trait HasLabel
{
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
```

`app/Enums/Role.php`:
```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum Role: string
{
    use HasLabel;

    case Admin = 'admin';
    case Adviser = 'adviser';
    case Company = 'company';
    case Intern = 'intern';

    public function dashboardRoute(): string
    {
        return "{$this->value}.dashboard";
    }

    public function memberPrefix(): string
    {
        return match ($this) {
            self::Admin => 'ADM',
            self::Adviser => 'ADV',
            self::Company => 'CMP',
            self::Intern => 'INT',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Admin => 'rose',
            self::Adviser => 'sky',
            self::Company => 'teal',
            self::Intern => 'amber',
        };
    }
}
```

`app/Enums/AccountStatus.php`:
```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum AccountStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Disabled = 'disabled';

    public function badgeColor(): string
    {
        return $this === self::Active ? 'green' : 'gray';
    }
}
```

`app/Enums/ApprovalStatus.php`:
```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ApprovalStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected => 'rose',
        };
    }
}
```

`app/Enums/ApplicationStatus.php`:
```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ApplicationStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case ForInterview = 'for_interview';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::ForInterview => 'sky',
            self::Accepted => 'green',
            self::Declined => 'rose',
            self::Cancelled => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::ForInterview], true);
    }
}
```

`app/Enums/DtrStatus.php`:
```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum DtrStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Approved = 'approved';
    case Disapproved = 'disapproved';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Disapproved => 'rose',
        };
    }
}
```

`app/Enums/SubmissionStatus.php`:
```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum SubmissionStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Declined => 'rose',
        };
    }
}
```

`app/Enums/CosApplicationStatus.php`:
```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum CosApplicationStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Declined => 'rose',
        };
    }
}
```

`app/Enums/DocumentRequestStatus.php`:
```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum DocumentRequestStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Fulfilled = 'fulfilled';
    case Declined = 'declined';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Fulfilled => 'green',
            self::Declined => 'rose',
        };
    }
}
```

`app/Enums/PostingStatus.php`:
```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PostingStatus: string
{
    use HasLabel;

    case Open = 'open';
    case Closed = 'closed';

    public function badgeColor(): string
    {
        return $this === self::Open ? 'green' : 'gray';
    }
}
```

`app/Enums/ClassStatus.php`:
```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ClassStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Archived = 'archived';

    public function badgeColor(): string
    {
        return $this === self::Active ? 'green' : 'gray';
    }
}
```

`app/Enums/HoursTier.php`:
```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum HoursTier: string
{
    use HasLabel;

    case Low = 'low';
    case Mid = 'mid';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Below certificate threshold',
            self::Mid => 'Certificate eligible',
            self::Complete => 'Complete',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Low => 'rose',
            self::Mid => 'amber',
            self::Complete => 'green',
        };
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=EnumsTest`
Expected: PASS (13 tests including the dataset cases).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Enums tests/Unit/EnumsTest.php
git commit -m "feat: add role and status enums"
```

---

### Task 4: Identity schema and models (users, intern_profiles, companies, departments)

**Files:**
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`, `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Providers/AppServiceProvider.php`
- Create: `database/migrations/2026_10_03_000001_create_departments_table.php`, `database/migrations/2026_10_03_000002_create_companies_table.php`, `database/migrations/2026_10_03_000003_create_intern_profiles_table.php`, `app/Models/InternProfile.php`, `app/Models/Company.php`, `app/Models/Department.php`, `database/factories/InternProfileFactory.php`, `database/factories/CompanyFactory.php`, `database/factories/DepartmentFactory.php`
- Test: `tests/Feature/Models/UserTest.php`

**Interfaces:**
- Consumes: enums from Task 3.
- Produces: `User` (`name`, `full_name`, `initials` accessors; `isAdmin|isAdviser|isCompany|isIntern|isDisabled()`; scopes `ofRole(Role)`, `active()`, `disabled()`; relations `internProfile()`, `company()`), `InternProfile` (`user()`), `Company` (`user()`, `approver()`, `isPartner()`, `isRegistered()`, `isApproved()`, scopes `approved()`, `pending()`, `partners()`, `registered()`), `Department`. Factories: `User::factory()->admin()|adviser()|company()|intern()|disabled()`, `Company::factory()->registered()|partner()|pending()`, `InternProfile::factory()`, `Department::factory()`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Models/UserTest.php`:
```php
<?php

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\User;

it('builds name, full name and initials', function () {
    $user = User::factory()->make(['first_name' => 'Juan', 'middle_name' => 'Santos', 'last_name' => 'Dela Cruz']);

    expect($user->name)->toBe('Juan Dela Cruz')
        ->and($user->full_name)->toBe('Juan Santos Dela Cruz')
        ->and($user->initials)->toBe('JD');
});

it('casts role and status to enums and hashes passwords', function () {
    $user = User::factory()->admin()->create(['password' => 'secret123']);

    expect($user->role)->toBe(Role::Admin)
        ->and($user->status)->toBe(AccountStatus::Active)
        ->and($user->isAdmin())->toBeTrue()
        ->and($user->isIntern())->toBeFalse()
        ->and(password_verify('secret123', $user->password))->toBeTrue()
        ->and($user->member_no)->toStartWith('ADM-');
});

it('creates an intern profile for intern users', function () {
    $user = User::factory()->intern()->create();

    expect($user->internProfile)->not->toBeNull()
        ->and($user->internProfile->student_number)->not->toBeEmpty()
        ->and($user->member_no)->toStartWith('INT-');
});

it('creates a registered, approved company for company users', function () {
    $user = User::factory()->company()->create();

    expect($user->company)->not->toBeNull()
        ->and($user->company->isRegistered())->toBeTrue()
        ->and($user->company->isApproved())->toBeTrue()
        ->and($user->company->company_code)->toHaveLength(8);
});

it('treats companies without a login as partners', function () {
    $partner = Company::factory()->partner()->create();

    expect($partner->isPartner())->toBeTrue()
        ->and($partner->user)->toBeNull()
        ->and(Company::partners()->count())->toBe(1)
        ->and(Company::registered()->count())->toBe(0);
});

it('scopes users by role and status', function () {
    User::factory()->intern()->count(2)->create();
    User::factory()->adviser()->disabled()->create();

    expect(User::ofRole(Role::Intern)->count())->toBe(2)
        ->and(User::active()->count())->toBe(2)
        ->and(User::disabled()->count())->toBe(1)
        ->and(User::disabled()->first()->isDisabled())->toBeTrue();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=UserTest`
Expected: FAIL — unknown column `first_name` / missing factory states.

- [ ] **Step 3: Rewrite the users migration**

`database/migrations/0001_01_01_000000_create_users_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 20)->index();
            $table->string('member_no', 30)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->string('phone', 30)->nullable();
            $table->string('avatar_path')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
```

- [ ] **Step 4: Create the departments, companies and intern_profiles migrations**

`database/migrations/2026_10_03_000001_create_departments_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
```

`database/migrations/2026_10_03_000002_create_companies_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            // Null for COS partner companies that have no portal login.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 100)->nullable();
            $table->string('company_code', 20)->unique();
            $table->string('logo_path')->nullable();
            $table->text('about')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('permit_path')->nullable();
            $table->string('moa_path')->nullable();
            $table->string('approval_status', 20)->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
```

`database/migrations/2026_10_03_000003_create_intern_profiles_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intern_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('student_number', 30)->unique();
            $table->string('gender', 20)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('present_address')->nullable();
            $table->string('permanent_address')->nullable();
            $table->text('about')->nullable();
            $table->string('school_year', 20)->nullable();
            $table->string('resume_path')->nullable();
            $table->unsignedInteger('total_hours')->default(0);
            $table->unsignedInteger('total_absences')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intern_profiles');
    }
};
```

- [ ] **Step 5: Write the models**

`app/Models/User.php`:
```php
<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'email', 'password',
        'role', 'member_no', 'status', 'phone', 'avatar_path', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'status' => AccountStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    /* Accessors */

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' '));
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper(
            mb_substr((string) $this->first_name, 0, 1).mb_substr((string) $this->last_name, 0, 1)
        ));
    }

    /* Role and status helpers */

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isAdviser(): bool
    {
        return $this->role === Role::Adviser;
    }

    public function isCompany(): bool
    {
        return $this->role === Role::Company;
    }

    public function isIntern(): bool
    {
        return $this->role === Role::Intern;
    }

    public function isDisabled(): bool
    {
        return $this->status === AccountStatus::Disabled;
    }

    /* Scopes */

    public function scopeOfRole(Builder $query, Role $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AccountStatus::Active);
    }

    public function scopeDisabled(Builder $query): Builder
    {
        return $query->where('status', AccountStatus::Disabled);
    }

    /* Relationships */

    public function internProfile(): HasOne
    {
        return $this->hasOne(InternProfile::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }
}
```

`app/Models/InternProfile.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternProfile extends Model
{
    /** @use HasFactory<\Database\Factories\InternProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'student_number', 'gender', 'birthdate', 'present_address', 'permanent_address',
        'about', 'school_year', 'resume_path', 'class_section_id', 'total_hours', 'total_absences',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'total_hours' => 'integer',
            'total_absences' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

`app/Models/Company.php`:
```php
<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'type', 'company_code', 'logo_path', 'about', 'website', 'address',
        'permit_path', 'moa_path', 'approval_status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approval_status' => ApprovalStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function isPartner(): bool
    {
        return $this->user_id === null;
    }

    public function isRegistered(): bool
    {
        return $this->user_id !== null;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === ApprovalStatus::Approved;
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', ApprovalStatus::Approved);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('approval_status', ApprovalStatus::Pending);
    }

    public function scopePartners(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    public function scopeRegistered(Builder $query): Builder
    {
        return $query->whereNotNull('user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
```

`app/Models/Department.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    /** @use HasFactory<\Database\Factories\DepartmentFactory> */
    use HasFactory;

    protected $fillable = ['name'];
}
```

- [ ] **Step 6: Write the factories**

`database/factories/UserFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\InternProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => null,
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Intern,
            // Closure runs after states are merged, so the prefix follows the final role.
            'member_no' => fn (array $attributes) => $this->memberNo($attributes['role']),
            'status' => AccountStatus::Active,
            'phone' => fake()->numerify('09#########'),
            'remember_token' => Str::random(10),
        ];
    }

    private function memberNo(Role|string $role): string
    {
        $role = $role instanceof Role ? $role : Role::from($role);

        return sprintf('%s-%d-%05d', $role->memberPrefix(), now()->year, fake()->unique()->numberBetween(1, 99999));
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }

    public function adviser(): static
    {
        return $this->state(['role' => Role::Adviser]);
    }

    public function intern(): static
    {
        return $this->state(['role' => Role::Intern])
            ->afterCreating(function (User $user) {
                if (! $user->internProfile()->exists()) {
                    InternProfile::factory()->for($user)->create();
                }
            });
    }

    public function company(): static
    {
        return $this->state(['role' => Role::Company])
            ->afterCreating(function (User $user) {
                if (! $user->company()->exists()) {
                    Company::factory()->registered()->for($user)->create();
                }
            });
    }

    public function disabled(): static
    {
        return $this->state(['status' => AccountStatus::Disabled]);
    }
}
```

`database/factories/InternProfileFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\InternProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternProfile>
 */
class InternProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => Role::Intern]),
            'student_number' => fake()->unique()->numerify('##-####'),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'birthdate' => fake()->dateTimeBetween('-25 years', '-19 years')->format('Y-m-d'),
            'present_address' => fake()->address(),
            'permanent_address' => fake()->address(),
            'about' => null,
            'school_year' => '2025-2026',
            'total_hours' => 0,
            'total_absences' => 0,
        ];
    }
}
```

`database/factories/CompanyFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->company(),
            'type' => fake()->randomElement(['IT Services', 'BPO', 'Government', 'Manufacturing', 'Education', 'Finance']),
            'company_code' => Str::upper(Str::random(8)),
            'about' => fake()->paragraph(),
            'website' => fake()->url(),
            'address' => fake()->address(),
            'approval_status' => ApprovalStatus::Approved,
            'approved_at' => now(),
        ];
    }

    /** A company with a portal login and uploaded documents. */
    public function registered(): static
    {
        return $this->state(fn () => [
            'user_id' => User::factory()->state(['role' => Role::Company]),
            'permit_path' => 'companies/demo/permit.pdf',
            'moa_path' => 'companies/demo/moa.pdf',
        ]);
    }

    /** A COS partner company without a login. */
    public function partner(): static
    {
        return $this->state(['user_id' => null, 'approval_status' => ApprovalStatus::Approved]);
    }

    public function pending(): static
    {
        return $this->state(['approval_status' => ApprovalStatus::Pending, 'approved_at' => null, 'approved_by' => null]);
    }
}
```

`database/factories/DepartmentFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Information Technology', 'Human Resources', 'Administration', 'Finance',
                'Marketing', 'Operations', 'Security', 'Engineering', 'Customer Support',
            ]),
        ];
    }
}
```

- [ ] **Step 7: Enable strict Eloquent in non-production**

In `app/Providers/AppServiceProvider.php`, `boot()`:
```php
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    Model::shouldBeStrict(! $this->app->isProduction());
}
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=UserTest`
Expected: PASS (6 tests). Then `php artisan test` — everything green.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add identity schema, models and factories"
```

---

### Task 5: Classroom schema and models

**Files:**
- Create: `database/migrations/2026_10_03_000004_create_classroom_tables.php`, `app/Models/ClassSection.php`, `app/Models/ClassAdviserLog.php`, `app/Models/Announcement.php`, `app/Models/AnnouncementComment.php`, `app/Models/ClassFolder.php`, `app/Models/ClassSubmission.php`, `app/Models/ClassResource.php`, factories for each (`database/factories/ClassSectionFactory.php`, `ClassAdviserLogFactory.php`, `AnnouncementFactory.php`, `AnnouncementCommentFactory.php`, `ClassFolderFactory.php`, `ClassSubmissionFactory.php`, `ClassResourceFactory.php`)
- Modify: `app/Models/User.php` (add `advisedClasses()`), `app/Models/InternProfile.php` (add `classSection()`)
- Test: `tests/Feature/Models/ClassroomModelsTest.php`

**Interfaces:**
- Produces: `ClassSection` (`adviser()`, `internProfiles()`, `interns()` (users through profiles), `folders()`, `announcements()`, `resources()`, `adviserLogs()`, `schedule_label` accessor like `Monday 08:00 AM – 12:00 PM`, scope `active()`), `ClassFolder` (`classSection()`, `submissions()`), `ClassSubmission` (`folder()`, `intern()`, `reviewer()`), `Announcement` (`classSection()`, `author()`, `comments()`), `AnnouncementComment`, `ClassResource`, `ClassAdviserLog`. `InternProfile::classSection()`, `User::advisedClasses()`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Models/ClassroomModelsTest.php`:
```php
<?php

use App\Enums\ClassStatus;
use App\Enums\SubmissionStatus;
use App\Models\Announcement;
use App\Models\ClassFolder;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\InternProfile;
use App\Models\User;

it('links a class to its adviser and interns', function () {
    $adviser = User::factory()->adviser()->create();
    $section = ClassSection::factory()->for($adviser, 'adviser')->create();
    InternProfile::factory()->count(3)->for($section)->create();

    expect($section->adviser->is($adviser))->toBeTrue()
        ->and($section->internProfiles)->toHaveCount(3)
        ->and($section->interns)->toHaveCount(3)
        ->and($adviser->advisedClasses)->toHaveCount(1)
        ->and($section->status)->toBe(ClassStatus::Active)
        ->and($section->join_code)->toHaveLength(8);
});

it('formats the schedule label', function () {
    $section = ClassSection::factory()->make(['day' => 'Monday', 'starts_at' => '08:00:00', 'ends_at' => '12:00:00']);

    expect($section->schedule_label)->toBe('Monday 8:00 AM – 12:00 PM');
});

it('tracks folder submissions with status and late flag', function () {
    $folder = ClassFolder::factory()->create(['is_locked' => true]);
    $submission = ClassSubmission::factory()->for($folder, 'folder')->create(['is_late' => true]);

    expect($submission->status)->toBe(SubmissionStatus::Pending)
        ->and($submission->is_late)->toBeTrue()
        ->and($folder->submissions)->toHaveCount(1)
        ->and($submission->intern->isIntern())->toBeTrue()
        ->and($folder->classSection)->not->toBeNull();
});

it('threads comments under announcements', function () {
    $announcement = Announcement::factory()->hasComments(2)->create();

    expect($announcement->comments)->toHaveCount(2)
        ->and($announcement->author)->not->toBeNull()
        ->and($announcement->classSection->announcements)->toHaveCount(1);
});

it('scopes active classes', function () {
    ClassSection::factory()->create();
    ClassSection::factory()->archived()->create();

    expect(ClassSection::active()->count())->toBe(1);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=ClassroomModelsTest`
Expected: FAIL — class `App\Models\ClassSection` not found.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_10_03_000004_create_classroom_tables.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adviser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('course_code', 30);
            $table->string('subject');
            $table->string('section', 50);
            $table->string('day', 20);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('school_year', 20);
            $table->string('join_code', 20)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::table('intern_profiles', function (Blueprint $table) {
            $table->foreignId('class_section_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::create('class_adviser_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adviser_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->longText('body');
            $table->timestamps();
        });

        Schema::create('announcement_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('class_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_section_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });

        Schema::create('class_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('is_late')->default(false);
            $table->text('reviewer_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('class_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_resources');
        Schema::dropIfExists('class_submissions');
        Schema::dropIfExists('class_folders');
        Schema::dropIfExists('announcement_comments');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('class_adviser_logs');
        Schema::table('intern_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_section_id');
        });
        Schema::dropIfExists('class_sections');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/ClassSection.php`:
```php
<?php

namespace App\Models;

use App\Enums\ClassStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

class ClassSection extends Model
{
    /** @use HasFactory<\Database\Factories\ClassSectionFactory> */
    use HasFactory;

    protected $fillable = [
        'adviser_id', 'course_code', 'subject', 'section', 'day', 'starts_at', 'ends_at',
        'school_year', 'join_code', 'status',
    ];

    protected function casts(): array
    {
        return ['status' => ClassStatus::class];
    }

    protected function scheduleLabel(): Attribute
    {
        return Attribute::get(function () {
            $from = Carbon::createFromFormat('H:i:s', $this->starts_at)->format('g:i A');
            $to = Carbon::createFromFormat('H:i:s', $this->ends_at)->format('g:i A');

            return "{$this->day} {$from} – {$to}";
        });
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => "{$this->course_code} · {$this->section}");
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ClassStatus::Active);
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_id');
    }

    public function internProfiles(): HasMany
    {
        return $this->hasMany(InternProfile::class);
    }

    public function interns(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, InternProfile::class, 'class_section_id', 'id', 'id', 'user_id');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(ClassFolder::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class)->latest();
    }

    public function resources(): HasMany
    {
        return $this->hasMany(ClassResource::class);
    }

    public function adviserLogs(): HasMany
    {
        return $this->hasMany(ClassAdviserLog::class);
    }
}
```

`app/Models/ClassAdviserLog.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassAdviserLog extends Model
{
    /** @use HasFactory<\Database\Factories\ClassAdviserLogFactory> */
    use HasFactory;

    protected $fillable = ['class_section_id', 'adviser_id', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime'];
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_id');
    }
}
```

`app/Models/Announcement.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    /** @use HasFactory<\Database\Factories\AnnouncementFactory> */
    use HasFactory;

    protected $fillable = ['class_section_id', 'author_id', 'body'];

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class)->oldest();
    }
}
```

`app/Models/AnnouncementComment.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementComment extends Model
{
    /** @use HasFactory<\Database\Factories\AnnouncementCommentFactory> */
    use HasFactory;

    protected $fillable = ['announcement_id', 'author_id', 'body'];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
```

`app/Models/ClassFolder.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassFolder extends Model
{
    /** @use HasFactory<\Database\Factories\ClassFolderFactory> */
    use HasFactory;

    protected $fillable = ['class_section_id', 'name', 'is_locked'];

    protected function casts(): array
    {
        return ['is_locked' => 'boolean'];
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ClassSubmission::class);
    }
}
```

`app/Models/ClassSubmission.php`:
```php
<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSubmission extends Model
{
    /** @use HasFactory<\Database\Factories\ClassSubmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'class_folder_id', 'intern_id', 'title', 'file_path', 'status', 'is_late',
        'reviewer_note', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'is_late' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ClassFolder::class, 'class_folder_id');
    }

    public function intern(): BelongsTo
    {
        return $this->belongsTo(User::class, 'intern_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
```

`app/Models/ClassResource.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassResource extends Model
{
    /** @use HasFactory<\Database\Factories\ClassResourceFactory> */
    use HasFactory;

    protected $fillable = ['class_section_id', 'uploader_id', 'title', 'file_path'];

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}
```

Add to `app/Models/User.php` (imports `HasMany`):
```php
    public function advisedClasses(): HasMany
    {
        return $this->hasMany(ClassSection::class, 'adviser_id');
    }
```

Add to `app/Models/InternProfile.php`:
```php
    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }
```

- [ ] **Step 5: Write the factories**

`database/factories/ClassSectionFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\ClassStatus;
use App\Enums\Role;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClassSection>
 */
class ClassSectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'adviser_id' => User::factory()->state(['role' => Role::Adviser]),
            'course_code' => fake()->randomElement(['CC101', 'IT401', 'CS402', 'IS403']),
            'subject' => fake()->randomElement(['Practicum', 'On-the-Job Training', 'Internship 1']),
            'section' => 'SBIT-4'.fake()->randomLetter(),
            'day' => fake()->randomElement(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']),
            'starts_at' => '08:00:00',
            'ends_at' => '12:00:00',
            'school_year' => '2025-2026',
            'join_code' => Str::upper(Str::random(8)),
            'status' => ClassStatus::Active,
        ];
    }

    public function unassigned(): static
    {
        return $this->state(['adviser_id' => null]);
    }

    public function archived(): static
    {
        return $this->state(['status' => ClassStatus::Archived]);
    }
}
```

`database/factories/ClassAdviserLogFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassAdviserLog>
 */
class ClassAdviserLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_section_id' => ClassSection::factory(),
            'adviser_id' => User::factory()->state(['role' => Role::Adviser]),
            'joined_at' => now()->subMonths(2),
            'left_at' => null,
        ];
    }
}
```

`database/factories/AnnouncementFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_section_id' => ClassSection::factory(),
            'author_id' => User::factory()->state(['role' => Role::Adviser]),
            'body' => '<p>'.fake()->paragraph().'</p>',
        ];
    }
}
```

`database/factories/AnnouncementCommentFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementComment>
 */
class AnnouncementCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'announcement_id' => Announcement::factory(),
            'author_id' => User::factory()->state(['role' => Role::Intern]),
            'body' => fake()->sentence(),
        ];
    }
}
```

`database/factories/ClassFolderFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\ClassFolder;
use App\Models\ClassSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassFolder>
 */
class ClassFolderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_section_id' => ClassSection::factory(),
            'name' => fake()->randomElement(['Resume', 'Endorsement Letter', 'Weekly Report 1', 'MOA Copy']),
            'is_locked' => false,
        ];
    }

    public function locked(): static
    {
        return $this->state(['is_locked' => true]);
    }
}
```

`database/factories/ClassSubmissionFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\ClassFolder;
use App\Models\ClassSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSubmission>
 */
class ClassSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_folder_id' => ClassFolder::factory(),
            'intern_id' => User::factory()->state(['role' => Role::Intern]),
            'title' => fake()->words(3, true),
            'file_path' => 'classroom/demo/submission.pdf',
            'status' => SubmissionStatus::Pending,
            'is_late' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => SubmissionStatus::Approved, 'reviewed_at' => now()]);
    }

    public function declined(): static
    {
        return $this->state(['status' => SubmissionStatus::Declined, 'reviewed_at' => now(), 'reviewer_note' => 'Please resubmit.']);
    }
}
```

`database/factories/ClassResourceFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassResource>
 */
class ClassResourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_section_id' => ClassSection::factory(),
            'uploader_id' => User::factory()->state(['role' => Role::Adviser]),
            'title' => fake()->words(3, true),
            'file_path' => 'classroom/demo/resource.pdf',
        ];
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=ClassroomModelsTest`
Expected: PASS (5 tests). Then `php artisan test` all green.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add classroom schema, models and factories"
```

---

### Task 6: Internship schema, models and the notifications table

**Files:**
- Create: `database/migrations/2026_10_03_000005_create_internship_tables.php`, notifications migration via artisan, `app/Models/InternshipPosting.php`, `app/Models/Application.php`, `app/Models/Interview.php`, `app/Models/CosApplication.php`, `app/Models/Placement.php`, `app/Models/Dtr.php`, `app/Models/DocumentRequest.php`, `app/Models/Certificate.php`, factories for each
- Modify: `app/Models/User.php` (`placements()`, `activePlacement()`, `applications()`), `app/Models/Company.php` (`postings()`, `placements()`, `activePlacements()`)
- Test: `tests/Feature/Models/InternshipModelsTest.php`

**Interfaces:**
- Produces: `Placement` (`intern()`, `company()`, `department()`, `dtrs()`, `documentRequests()`, `certificates()`, `isActive()`, scope `active()`), `Dtr` (`placement()`, `reviewer()`), `Application` (`posting()`, `intern()`, `interview()`), `InternshipPosting` (`company()`, `applications()`, scope `open()`), `CosApplication`, `DocumentRequest`, `Certificate`, `Interview`. `User::placements()`, `User::activePlacement()` (HasOne, `ended_at` null), `User::applications()`. `Company::postings()`, `Company::placements()`, `Company::activePlacements()`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Models/InternshipModelsTest.php`:
```php
<?php

use App\Enums\ApplicationStatus;
use App\Enums\DtrStatus;
use App\Enums\PostingStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('resolves an intern\'s active placement', function () {
    $intern = User::factory()->intern()->create();
    Placement::factory()->for($intern, 'intern')->ended()->create();
    $current = Placement::factory()->for($intern, 'intern')->create();

    expect($intern->placements)->toHaveCount(2)
        ->and($intern->activePlacement->is($current))->toBeTrue()
        ->and($current->isActive())->toBeTrue()
        ->and($current->company->activePlacements)->toHaveCount(1);
});

it('prevents an intern from applying twice to the same posting', function () {
    $application = Application::factory()->create();

    Application::factory()->create([
        'internship_posting_id' => $application->internship_posting_id,
        'intern_id' => $application->intern_id,
    ]);
})->throws(QueryException::class);

it('casts statuses and links DTRs to placements', function () {
    $dtr = Dtr::factory()->create(['hours' => 40]);

    expect($dtr->status)->toBe(DtrStatus::Pending)
        ->and($dtr->placement->dtrs)->toHaveCount(1)
        ->and($dtr->placement->intern->isIntern())->toBeTrue();
});

it('lists open postings per company with their applications', function () {
    $company = Company::factory()->registered()->create();
    $open = InternshipPosting::factory()->for($company)->create();
    InternshipPosting::factory()->for($company)->closed()->create();
    Application::factory()->for($open, 'posting')->count(2)->create();

    expect($company->postings)->toHaveCount(2)
        ->and(InternshipPosting::open()->count())->toBe(1)
        ->and($open->status)->toBe(PostingStatus::Open)
        ->and($open->applications)->toHaveCount(2)
        ->and($open->applications->first()->status)->toBe(ApplicationStatus::Pending);
});

it('stores notifications in the database', function () {
    expect(Schema::hasTable('notifications'))->toBeTrue();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=InternshipModelsTest`
Expected: FAIL — class `App\Models\Placement` not found.

- [ ] **Step 3: Create the notifications migration and the internship migration**

Run: `php artisan make:notifications-table` (creates `database/migrations/<timestamp>_create_notifications_table.php`; keep it).

`database/migrations/2026_10_03_000005_create_internship_tables.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internship_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('city', 100);
            $table->text('description');
            $table->text('responsibilities')->nullable();
            $table->date('closing_date')->nullable();
            $table->unsignedInteger('required_hours')->nullable();
            $table->unsignedInteger('vacancies')->default(1);
            $table->string('contact_name');
            $table->string('contact_position')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            $table->string('resume_path');
            $table->string('endorsement_path');
            $table->string('status', 20)->default('pending')->index();
            $table->text('decline_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['internship_posting_id', 'intern_id']);
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('venue');
            $table->string('link')->nullable();
            $table->date('scheduled_on');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cos_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            $table->string('acceptance_letter_path');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->unsignedInteger('hours_rendered')->default(0);
            $table->unsignedInteger('absences')->default(0);
            $table->timestamps();
            $table->index(['intern_id', 'ended_at']);
        });

        Schema::create('dtrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedInteger('hours');
            $table->unsignedInteger('absences')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reviewer_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained()->cascadeOnDelete();
            $table->string('control_no', 20)->unique();
            $table->string('document_name');
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('file_path')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->unsignedInteger('hours_at_issue');
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('document_requests');
        Schema::dropIfExists('dtrs');
        Schema::dropIfExists('placements');
        Schema::dropIfExists('cos_applications');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('internship_postings');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/InternshipPosting.php`:
```php
<?php

namespace App\Models;

use App\Enums\PostingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternshipPosting extends Model
{
    /** @use HasFactory<\Database\Factories\InternshipPostingFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id', 'title', 'city', 'description', 'responsibilities', 'closing_date',
        'required_hours', 'vacancies', 'contact_name', 'contact_position', 'contact_phone', 'status',
    ];

    protected function casts(): array
    {
        return ['status' => PostingStatus::class, 'closing_date' => 'date'];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', PostingStatus::Open);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
```

`app/Models/Application.php`:
```php
<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    /** @use HasFactory<\Database\Factories\ApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'internship_posting_id', 'intern_id', 'resume_path', 'endorsement_path',
        'status', 'decline_reason', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['status' => ApplicationStatus::class, 'decided_at' => 'datetime'];
    }

    public function posting(): BelongsTo
    {
        return $this->belongsTo(InternshipPosting::class, 'internship_posting_id');
    }

    public function intern(): BelongsTo
    {
        return $this->belongsTo(User::class, 'intern_id');
    }

    public function interview(): HasOne
    {
        return $this->hasOne(Interview::class);
    }
}
```

`app/Models/Interview.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    /** @use HasFactory<\Database\Factories\InterviewFactory> */
    use HasFactory;

    protected $fillable = ['application_id', 'title', 'venue', 'link', 'scheduled_on', 'starts_at', 'ends_at', 'notes'];

    protected function casts(): array
    {
        return ['scheduled_on' => 'date'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
```

`app/Models/CosApplication.php`:
```php
<?php

namespace App\Models;

use App\Enums\CosApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CosApplication extends Model
{
    /** @use HasFactory<\Database\Factories\CosApplicationFactory> */
    use HasFactory;

    protected $fillable = ['company_id', 'intern_id', 'acceptance_letter_path', 'status', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['status' => CosApplicationStatus::class, 'reviewed_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function intern(): BelongsTo
    {
        return $this->belongsTo(User::class, 'intern_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
```

`app/Models/Placement.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Placement extends Model
{
    /** @use HasFactory<\Database\Factories\PlacementFactory> */
    use HasFactory;

    protected $fillable = [
        'intern_id', 'company_id', 'department_id', 'started_at', 'ended_at', 'hours_rendered', 'absences',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
            'hours_rendered' => 'integer',
            'absences' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function intern(): BelongsTo
    {
        return $this->belongsTo(User::class, 'intern_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function dtrs(): HasMany
    {
        return $this->hasMany(Dtr::class);
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
```

`app/Models/Dtr.php`:
```php
<?php

namespace App\Models;

use App\Enums\DtrStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dtr extends Model
{
    /** @use HasFactory<\Database\Factories\DtrFactory> */
    use HasFactory;

    protected $fillable = [
        'placement_id', 'file_path', 'period_from', 'period_to', 'hours', 'absences',
        'status', 'reviewer_id', 'reviewer_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DtrStatus::class,
            'period_from' => 'date',
            'period_to' => 'date',
            'hours' => 'integer',
            'absences' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
```

`app/Models/DocumentRequest.php`:
```php
<?php

namespace App\Models;

use App\Enums\DocumentRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequest extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentRequestFactory> */
    use HasFactory;

    protected $fillable = ['placement_id', 'control_no', 'document_name', 'message', 'status', 'file_path', 'handled_at'];

    protected function casts(): array
    {
        return ['status' => DocumentRequestStatus::class, 'handled_at' => 'datetime'];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }
}
```

`app/Models/Certificate.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    /** @use HasFactory<\Database\Factories\CertificateFactory> */
    use HasFactory;

    protected $fillable = ['placement_id', 'file_path', 'hours_at_issue', 'issued_at'];

    protected function casts(): array
    {
        return ['hours_at_issue' => 'integer', 'issued_at' => 'datetime'];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }
}
```

Add to `app/Models/User.php`:
```php
    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class, 'intern_id')->latest('started_at');
    }

    public function activePlacement(): HasOne
    {
        return $this->hasOne(Placement::class, 'intern_id')->whereNull('ended_at');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'intern_id')->latest();
    }
```

Add to `app/Models/Company.php` (import `HasMany`):
```php
    public function postings(): HasMany
    {
        return $this->hasMany(InternshipPosting::class)->latest();
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function activePlacements(): HasMany
    {
        return $this->hasMany(Placement::class)->whereNull('ended_at');
    }
```

- [ ] **Step 5: Write the factories**

`database/factories/InternshipPostingFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\PostingStatus;
use App\Models\Company;
use App\Models\InternshipPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternshipPosting>
 */
class InternshipPostingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory()->registered(),
            'title' => fake()->randomElement(['Junior Web Developer Intern', 'IT Support Intern', 'QA Intern', 'Data Entry Intern']),
            'city' => fake()->randomElement(['Quezon City', 'Makati', 'Pasig', 'Taguig', 'Manila']),
            'description' => fake()->paragraphs(2, true),
            'responsibilities' => fake()->paragraph(),
            'closing_date' => now()->addMonth()->toDateString(),
            'required_hours' => 486,
            'vacancies' => fake()->numberBetween(1, 5),
            'contact_name' => fake()->name(),
            'contact_position' => 'HR Officer',
            'contact_phone' => fake()->numerify('09#########'),
            'status' => PostingStatus::Open,
        ];
    }

    public function closed(): static
    {
        return $this->state(['status' => PostingStatus::Closed]);
    }
}
```

`database/factories/ApplicationFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\InternshipPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'internship_posting_id' => InternshipPosting::factory(),
            'intern_id' => User::factory()->state(['role' => Role::Intern]),
            'resume_path' => 'applications/demo/resume.pdf',
            'endorsement_path' => 'applications/demo/endorsement.pdf',
            'status' => ApplicationStatus::Pending,
        ];
    }

    public function forInterview(): static
    {
        return $this->state(['status' => ApplicationStatus::ForInterview]);
    }

    public function accepted(): static
    {
        return $this->state(['status' => ApplicationStatus::Accepted, 'decided_at' => now()]);
    }

    public function declined(): static
    {
        return $this->state(['status' => ApplicationStatus::Declined, 'decided_at' => now(), 'decline_reason' => 'Position filled.']);
    }
}
```

`database/factories/InterviewFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Interview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'application_id' => Application::factory()->forInterview(),
            'title' => 'Initial Interview',
            'venue' => fake()->randomElement(['Google Meet', 'Zoom', 'On-site, 3F HR Office']),
            'link' => 'https://meet.google.com/abc-defg-hij',
            'scheduled_on' => now()->addDays(3)->toDateString(),
            'starts_at' => '10:00:00',
            'ends_at' => '10:30:00',
        ];
    }
}
```

`database/factories/CosApplicationFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\CosApplicationStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\CosApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CosApplication>
 */
class CosApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory()->partner(),
            'intern_id' => User::factory()->state(['role' => Role::Intern]),
            'acceptance_letter_path' => 'cos/demo/acceptance.pdf',
            'status' => CosApplicationStatus::Pending,
        ];
    }
}
```

`database/factories/PlacementFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Placement>
 */
class PlacementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'intern_id' => User::factory()->state(['role' => Role::Intern]),
            'company_id' => Company::factory()->registered(),
            'department_id' => null,
            'started_at' => now()->subMonths(2)->toDateString(),
            'ended_at' => null,
            'hours_rendered' => 0,
            'absences' => 0,
        ];
    }

    public function ended(): static
    {
        return $this->state([
            'started_at' => now()->subMonths(8)->toDateString(),
            'ended_at' => now()->subMonths(5)->toDateString(),
        ]);
    }
}
```

`database/factories/DtrFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\DtrStatus;
use App\Models\Dtr;
use App\Models\Placement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dtr>
 */
class DtrFactory extends Factory
{
    public function definition(): array
    {
        return [
            'placement_id' => Placement::factory(),
            'file_path' => 'dtrs/demo/dtr.pdf',
            'period_from' => now()->subWeek()->startOfWeek()->toDateString(),
            'period_to' => now()->subWeek()->endOfWeek()->toDateString(),
            'hours' => 40,
            'absences' => 0,
            'status' => DtrStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => DtrStatus::Approved, 'reviewed_at' => now()]);
    }

    public function disapproved(): static
    {
        return $this->state(['status' => DtrStatus::Disapproved, 'reviewed_at' => now(), 'reviewer_note' => 'Hours do not match the attached sheet.']);
    }
}
```

`database/factories/DocumentRequestFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\DocumentRequestStatus;
use App\Models\DocumentRequest;
use App\Models\Placement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRequest>
 */
class DocumentRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'placement_id' => Placement::factory(),
            'control_no' => 'CTRL-'.fake()->unique()->numerify('######'),
            'document_name' => fake()->randomElement(['Certificate of Completion', 'Acceptance Letter', 'Evaluation Form']),
            'message' => fake()->sentence(),
            'status' => DocumentRequestStatus::Pending,
        ];
    }
}
```

`database/factories/CertificateFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Placement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'placement_id' => Placement::factory(),
            'file_path' => 'certificates/demo/certificate.pdf',
            'hours_at_issue' => 486,
            'issued_at' => now(),
        ];
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=InternshipModelsTest`
Expected: PASS (5 tests). Then `php artisan test` all green.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add internship schema, models, factories and notifications table"
```

---

### Task 7: Domain services (member numbers, join codes, OJT hours)

**Files:**
- Create: `app/Services/MemberNumberGenerator.php`, `app/Services/JoinCodeGenerator.php`, `app/Services/OjtHoursService.php`
- Test: `tests/Unit/Services/MemberNumberGeneratorTest.php`, `tests/Unit/Services/JoinCodeGeneratorTest.php`, `tests/Unit/Services/OjtHoursServiceTest.php`

**Interfaces:**
- Produces: `MemberNumberGenerator::generate(Role $role): string` → `INT-2026-00001` style, unique in `users.member_no`. `JoinCodeGenerator::generate(string $table, string $column, int $length = 8): string` → uppercase unambiguous alphanumerics, unique in that column. `OjtHoursService`: `required(): int`, `certificateMinimum(): int`, `isComplete(int)`, `isCertificateEligible(int)`, `progressPercent(int): int` (0–100), `remaining(int): int`, `tier(int): HoursTier`, `bucketLabel(int): string`, `bucketLabels(): array<string>`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Services/MemberNumberGeneratorTest.php`:
```php
<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\MemberNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates sequential member numbers per role and year', function () {
    $generator = app(MemberNumberGenerator::class);
    $year = now()->year;

    expect($generator->generate(Role::Intern))->toBe("INT-{$year}-00001");

    User::factory()->intern()->create(['member_no' => "INT-{$year}-00001"]);

    expect($generator->generate(Role::Intern))->toBe("INT-{$year}-00002")
        ->and($generator->generate(Role::Adviser))->toBe("ADV-{$year}-00001");
});

it('skips numbers that are already taken', function () {
    $generator = app(MemberNumberGenerator::class);
    $year = now()->year;
    User::factory()->admin()->create(['member_no' => "ADM-{$year}-00002"]);

    // One admin exists, so the next candidate would be 00002, which is taken.
    expect($generator->generate(Role::Admin))->toBe("ADM-{$year}-00003");
});
```

`tests/Unit/Services/JoinCodeGeneratorTest.php`:
```php
<?php

use App\Models\ClassSection;
use App\Services\JoinCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates unambiguous uppercase codes of the requested length', function () {
    $generator = app(JoinCodeGenerator::class);
    $codes = collect(range(1, 50))->map(fn () => $generator->generate('class_sections', 'join_code'));

    expect($codes->unique())->toHaveCount(50);
    $codes->each(fn ($code) => expect($code)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8}$/'));
    expect($generator->generate('companies', 'company_code', 6))->toHaveLength(6);
});

it('never returns a code that already exists', function () {
    ClassSection::factory()->create(['join_code' => 'AAAAAAAA']);
    $generator = new JoinCodeGenerator(alphabet: 'A');

    expect(fn () => $generator->generate('class_sections', 'join_code', 8))
        ->toThrow(RuntimeException::class);
});
```

`tests/Unit/Services/OjtHoursServiceTest.php`:
```php
<?php

use App\Enums\HoursTier;
use App\Services\OjtHoursService;

beforeEach(function () {
    config()->set('wiis.hours.required', 486);
    config()->set('wiis.hours.certificate_min', 250);
    $this->hours = new OjtHoursService;
});

it('reads thresholds from config', function () {
    expect($this->hours->required())->toBe(486)
        ->and($this->hours->certificateMinimum())->toBe(250);
});

it('computes completion, eligibility, progress and remaining hours', function () {
    expect($this->hours->isComplete(485))->toBeFalse()
        ->and($this->hours->isComplete(486))->toBeTrue()
        ->and($this->hours->isCertificateEligible(249))->toBeFalse()
        ->and($this->hours->isCertificateEligible(250))->toBeTrue()
        ->and($this->hours->progressPercent(243))->toBe(50)
        ->and($this->hours->progressPercent(0))->toBe(0)
        ->and($this->hours->progressPercent(900))->toBe(100)
        ->and($this->hours->remaining(400))->toBe(86)
        ->and($this->hours->remaining(600))->toBe(0);
});

it('maps hours to tiers', function () {
    expect($this->hours->tier(0))->toBe(HoursTier::Low)
        ->and($this->hours->tier(249))->toBe(HoursTier::Low)
        ->and($this->hours->tier(250))->toBe(HoursTier::Mid)
        ->and($this->hours->tier(485))->toBe(HoursTier::Mid)
        ->and($this->hours->tier(486))->toBe(HoursTier::Complete);
});

it('maps hours to dashboard buckets', function () {
    expect($this->hours->bucketLabel(0))->toBe('0–250')
        ->and($this->hours->bucketLabel(250))->toBe('0–250')
        ->and($this->hours->bucketLabel(251))->toBe('251–300')
        ->and($this->hours->bucketLabel(350))->toBe('301–400')
        ->and($this->hours->bucketLabel(999))->toBe('401+')
        ->and($this->hours->bucketLabels())->toBe(['0–250', '251–300', '301–400', '401+']);
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter=Services`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write the services**

`app/Services/MemberNumberGenerator.php`:
```php
<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;

class MemberNumberGenerator
{
    /**
     * Display codes like INT-2026-00001: prefix by role, current year, zero-padded sequence.
     * Sequence starts from the number of existing users of that role and skips collisions.
     */
    public function generate(Role $role): string
    {
        $year = now()->year;
        $sequence = User::query()->where('role', $role)->count();

        do {
            $sequence++;
            $candidate = sprintf('%s-%d-%05d', $role->memberPrefix(), $year, $sequence);
        } while (User::query()->where('member_no', $candidate)->exists());

        return $candidate;
    }
}
```

`app/Services/JoinCodeGenerator.php`:
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class JoinCodeGenerator
{
    /** Uppercase letters and digits without 0/O/1/I to keep codes easy to read aloud. */
    public const DEFAULT_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly string $alphabet = self::DEFAULT_ALPHABET,
        private readonly int $maxAttempts = 25,
    ) {}

    public function generate(string $table, string $column, int $length = 8): string
    {
        for ($attempt = 0; $attempt < $this->maxAttempts; $attempt++) {
            $code = $this->random($length);

            if (! DB::table($table)->where($column, $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException("Could not generate a unique code for {$table}.{$column}.");
    }

    private function random(int $length): string
    {
        $max = strlen($this->alphabet) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $this->alphabet[random_int(0, $max)];
        }

        return $code;
    }
}
```

`app/Services/OjtHoursService.php`:
```php
<?php

namespace App\Services;

use App\Enums\HoursTier;

class OjtHoursService
{
    public function required(): int
    {
        return (int) config('wiis.hours.required');
    }

    public function certificateMinimum(): int
    {
        return (int) config('wiis.hours.certificate_min');
    }

    public function isComplete(int $hours): bool
    {
        return $hours >= $this->required();
    }

    public function isCertificateEligible(int $hours): bool
    {
        return $hours >= $this->certificateMinimum();
    }

    public function progressPercent(int $hours): int
    {
        $required = max(1, $this->required());

        return (int) min(100, floor($hours / $required * 100));
    }

    public function remaining(int $hours): int
    {
        return max(0, $this->required() - $hours);
    }

    public function tier(int $hours): HoursTier
    {
        return match (true) {
            $this->isComplete($hours) => HoursTier::Complete,
            $this->isCertificateEligible($hours) => HoursTier::Mid,
            default => HoursTier::Low,
        };
    }

    public function bucketLabel(int $hours): string
    {
        foreach ($this->buckets() as [$min, $max, $label]) {
            if ($hours >= $min && ($max === null || $hours <= $max)) {
                return $label;
            }
        }

        return $this->buckets()[array_key_last($this->buckets())][2];
    }

    /** @return array<int, string> */
    public function bucketLabels(): array
    {
        return array_map(fn (array $bucket) => $bucket[2], $this->buckets());
    }

    /** @return array<int, array{0:int,1:int|null,2:string}> */
    private function buckets(): array
    {
        return config('wiis.hours.buckets');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=Services`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Services tests/Unit/Services
git commit -m "feat: add member number, join code and OJT hours services"
```

---

### Task 8: Seeders with demo data

**Files:**
- Create: `database/seeders/DepartmentSeeder.php`, `database/seeders/DemoSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/SeederTest.php`

**Interfaces:**
- Produces demo accounts (password `password` for all): `admin@wiis.test`, `adviser@wiis.test`, `company@wiis.test` (approved, TechNova Solutions Inc., code `TECHNOVA`), `pending@wiis.test` (company awaiting approval), `intern@wiis.test` (placed at TechNova with 300 approved hours and one pending DTR), `intern2@wiis.test` (unplaced), class `CC101 · SBIT-4C` with join code `SBIT4C26`, one partner company, two open postings, two folders, one announcement.

- [ ] **Step 1: Write the failing test**

`tests/Feature/SeederTest.php`:
```php
<?php

use App\Models\ClassSection;
use App\Models\Company;
use App\Models\Department;
use App\Models\InternshipPosting;
use App\Models\User;

it('seeds a coherent demo dataset', function () {
    $this->seed();

    $intern = User::where('email', 'intern@wiis.test')->firstOrFail();
    $intern2 = User::where('email', 'intern2@wiis.test')->firstOrFail();
    $company = User::where('email', 'company@wiis.test')->firstOrFail()->company;

    expect(Department::count())->toBeGreaterThanOrEqual(5)
        ->and(User::where('email', 'admin@wiis.test')->exists())->toBeTrue()
        ->and(ClassSection::where('join_code', 'SBIT4C26')->exists())->toBeTrue()
        ->and($company->company_code)->toBe('TECHNOVA')
        ->and($company->isApproved())->toBeTrue()
        ->and(Company::pending()->count())->toBe(1)
        ->and(Company::partners()->count())->toBe(1)
        ->and($intern->internProfile->total_hours)->toBe(300)
        ->and($intern->activePlacement->hours_rendered)->toBe(300)
        ->and($intern->activePlacement->company_id)->toBe($company->id)
        ->and($intern->activePlacement->dtrs()->count())->toBe(4)
        ->and($intern2->activePlacement)->toBeNull()
        ->and($intern2->internProfile->classSection->join_code)->toBe('SBIT4C26')
        ->and(InternshipPosting::open()->count())->toBe(2);
});

it('can seed twice without unique violations', function () {
    $this->seed();
    $this->artisan('migrate:fresh', ['--seed' => true])->assertSuccessful();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=SeederTest`
Expected: FAIL — `intern@wiis.test` not found.

- [ ] **Step 3: Write the seeders**

`database/seeders/DepartmentSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Information Technology', 'Human Resources', 'Administration', 'Finance',
            'Marketing', 'Operations', 'Security', 'Engineering', 'Customer Support',
        ];

        foreach ($names as $name) {
            Department::firstOrCreate(['name' => $name]);
        }
    }
}
```

`database/seeders/DemoSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Models\Announcement;
use App\Models\ClassAdviserLog;
use App\Models\ClassFolder;
use App\Models\ClassSection;
use App\Models\Company;
use App\Models\Dtr;
use App\Models\InternshipPosting;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Database\Seeder;

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
            fn (User $user) => $user->internProfile->update(['class_section_id' => $section->id])
        );

        InternshipPosting::factory()->for($company)->create(['title' => 'Junior Web Developer Intern']);
        InternshipPosting::factory()->for($company)->create(['title' => 'IT Support Intern']);

        ClassFolder::factory()->for($section)->create(['name' => 'Endorsement Letter']);
        ClassFolder::factory()->for($section)->create(['name' => 'Weekly Report 1', 'is_locked' => true]);

        Announcement::factory()->for($section)->for($adviser, 'author')->create([
            'body' => '<p>Welcome to Practicum! Upload your endorsement letter to the Documents tab before Friday.</p>',
        ]);
    }
}
```

`database/seeders/DatabaseSeeder.php`:
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=SeederTest`
Expected: PASS (2 tests). Also run `php artisan migrate:fresh --seed` against the local SQLite file and confirm it finishes without errors.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add database/seeders tests/Feature/SeederTest.php
git commit -m "feat: add department and demo seeders"
```

---

### Task 9: Design system — tokens, layouts and Blade components

Invoke the `frontend-design` skill before this task for the aesthetic pass; the code below is the baseline that must exist and pass tests. Keep the token names and component props exactly as written, since later tasks rely on them.

**Files:**
- Modify: `resources/css/app.css`, `resources/js/app.js`, `tests/TestCase.php`
- Create: `app/Support/Navigation.php`, `resources/views/components/layouts/base.blade.php`, `resources/views/components/layouts/app.blade.php`, `resources/views/components/layouts/auth.blade.php`, `resources/views/components/layouts/partials/sidebar.blade.php`, `resources/views/components/layouts/partials/topbar.blade.php`, `resources/views/components/layouts/partials/nav-item.blade.php`, `resources/views/components/brand.blade.php`, `resources/views/components/badge.blade.php`, `resources/views/components/card.blade.php`, `resources/views/components/stat-card.blade.php`, `resources/views/components/page-header.blade.php`, `resources/views/components/avatar.blade.php`, `resources/views/components/empty-state.blade.php`, `resources/views/components/flash.blade.php`, `resources/views/components/confirm-form.blade.php`, `resources/views/components/dropdown.blade.php`, `resources/views/components/dropdown/item.blade.php`, `resources/views/components/modal.blade.php`, `resources/views/components/button.blade.php`, `resources/views/components/progress-ring.blade.php`, `resources/views/components/table.blade.php`, `resources/views/components/notification-bell.blade.php`, `resources/views/components/form/label.blade.php`, `resources/views/components/form/error.blade.php`, `resources/views/components/form/input.blade.php`, `resources/views/components/form/select.blade.php`, `resources/views/components/form/textarea.blade.php`, `resources/views/components/form/file.blade.php`, `resources/views/components/form/checkbox.blade.php`
- Test: `tests/Feature/DesignSystemTest.php`

**Interfaces:**
- Produces layouts `<x-layouts.app title="">` and `<x-layouts.auth title="">`; components with these props: `x-badge :status(enum)|color`, `x-card title subtitle :padding + slot actions`, `x-stat-card label value icon hint color`, `x-page-header title subtitle :breadcrumbs + slot actions`, `x-avatar :user size(xs|sm|md|lg|xl)`, `x-empty-state title description icon + slot action`, `x-flash` (reads session `success|error|warning|info`), `x-confirm-form action method confirm`, `x-dropdown align + slot trigger` with `x-dropdown.item href icon`, `x-modal name title maxWidth` opened by `$dispatch('open-modal', name)`, `x-button variant(primary|secondary|danger|ghost) type href icon`, `x-progress-ring :percent size label color`, `x-table + slot head`, `x-form.input name label type value required hint`, `x-form.select name label :options value placeholder required`, `x-form.textarea name label rows value required`, `x-form.file name label accept hint required`, `x-form.checkbox name label :checked`. CSS classes `card`, `btn-primary|secondary|danger|ghost`, `input`, `input-error`, `label`, `data-table`. `Navigation::for(User): array` of `['label','route','icon','active','badge'?]`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/DesignSystemTest.php`:
```php
<?php

use App\Enums\ApplicationStatus;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

it('renders a status badge from an enum', function () {
    $html = Blade::render('<x-badge :status="$status" />', ['status' => ApplicationStatus::ForInterview]);

    expect($html)->toContain('For Interview')->toContain('sky');
});

it('renders the auth layout with branding and title', function () {
    $html = Blade::render('<x-layouts.auth title="Sign in"><p>body-marker</p></x-layouts.auth>');

    expect($html)->toContain('<title>Sign in · WIIS</title>')
        ->toContain(config('wiis.institution.name'))
        ->toContain('body-marker')
        ->toContain("classList.add('dark')");
});

it('clamps the progress ring to 100 percent', function () {
    $html = Blade::render('<x-progress-ring :percent="140" label="rendered" />');

    expect($html)->toContain('100%')->toContain('rendered');
});

it('renders form inputs with validation errors', function () {
    $bag = new ViewErrorBag;
    $bag->put('default', new MessageBag(['email' => ['Email is required.']]));
    view()->share('errors', $bag);

    $html = Blade::render('<x-form.input name="email" label="Email" required />');

    expect($html)->toContain('name="email"')->toContain('Email is required.')->toContain('input-error');
});

it('renders an empty state with an action slot', function () {
    $html = Blade::render('<x-empty-state title="Nothing here" description="Try later"><x-slot:action><a href="#">Go</a></x-slot:action></x-empty-state>');

    expect($html)->toContain('Nothing here')->toContain('Try later')->toContain('Go');
});
```

- [ ] **Step 2: Disable Vite in tests, then run to verify it fails**

`tests/TestCase.php`:
```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
```

Run: `php artisan test --filter=DesignSystemTest`
Expected: FAIL — unable to locate component `badge`.

- [ ] **Step 3: Design tokens and base styles**

`resources/css/app.css`:
```css
@import "tailwindcss";

@source "../views";
@source "../../app/View";
@source "../../app/Support";
@source "../../app/Enums";

@custom-variant dark (&:where(.dark, .dark *));

@theme {
    --font-display: "Bricolage Grotesque", ui-sans-serif, system-ui, sans-serif;
    --font-sans: "Inter", ui-sans-serif, system-ui, sans-serif;

    /* Evergreen teal, the WIIS brand hue. */
    --color-brand-50: #eefaf6;
    --color-brand-100: #d5f2e8;
    --color-brand-200: #aee4d3;
    --color-brand-300: #79cfb8;
    --color-brand-400: #46b39a;
    --color-brand-500: #249880;
    --color-brand-600: #187a68;
    --color-brand-700: #166255;
    --color-brand-800: #154e45;
    --color-brand-900: #13413a;
    --color-brand-950: #082522;

    /* Warm surfaces instead of flat gray. */
    --color-surface: #faf9f6;
    --color-surface-raised: #ffffff;
    --color-surface-dark: #0e1514;
    --color-surface-dark-raised: #151f1d;

    --shadow-card: 0 1px 2px rgb(16 24 40 / 0.04), 0 1px 3px rgb(16 24 40 / 0.06);
}

@utility btn {
    @apply inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition
        focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500
        disabled:cursor-not-allowed disabled:opacity-60;
}

@utility input {
    @apply block w-full rounded-xl border border-stone-300 bg-white px-3.5 py-2.5 text-sm text-stone-900 shadow-sm
        placeholder:text-stone-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none
        dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100 dark:placeholder:text-stone-500;
}

@layer base {
    html {
        font-family: var(--font-sans);
    }

    body {
        @apply bg-surface text-stone-800 antialiased dark:bg-surface-dark dark:text-stone-100;
    }

    h1, h2, h3, .font-display {
        font-family: var(--font-display);
    }

    [x-cloak] {
        display: none !important;
    }
}

@layer components {
    .card {
        @apply rounded-2xl border border-stone-200/80 bg-white shadow-card dark:border-stone-800 dark:bg-surface-dark-raised;
    }

    .btn-primary { @apply btn bg-brand-600 text-white hover:bg-brand-700 active:bg-brand-800; }
    .btn-secondary { @apply btn border border-stone-300 bg-white text-stone-700 hover:bg-stone-50 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-200 dark:hover:bg-stone-800; }
    .btn-danger { @apply btn bg-rose-600 text-white hover:bg-rose-700; }
    .btn-ghost { @apply btn text-stone-600 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800; }

    .input-error { @apply border-rose-400 focus:border-rose-500 focus:ring-rose-500/20; }
    .label { @apply mb-1.5 block text-sm font-medium text-stone-700 dark:text-stone-300; }

    .data-table th { @apply px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-stone-500; }
    .data-table td { @apply px-5 py-3.5 align-middle; }
}
```

`resources/js/app.js`:
```js
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.store('theme', {
    dark: document.documentElement.classList.contains('dark'),
    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        try {
            localStorage.setItem('theme', this.dark ? 'dark' : 'light');
        } catch (e) {
            // Storage may be unavailable (private mode); the toggle still works for this page.
        }
    },
});

Alpine.start();
```

- [ ] **Step 4: Navigation support class**

`app/Support/Navigation.php`:
```php
<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class Navigation
{
    /**
     * Sidebar items for the user's portal. Later phases append to the per-role lists.
     *
     * @return array<int, array{label: string, route: string, icon: string, active: string, badge?: int|string|null}>
     */
    public static function for(User $user): array
    {
        $portal = $user->role->value;

        $items = [
            ['label' => 'Dashboard', 'route' => "{$portal}.dashboard", 'icon' => 'heroicon-o-home', 'active' => "{$portal}.dashboard"],
            ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'heroicon-o-bell', 'active' => 'notifications.*',
                'badge' => $user->unreadNotifications()->count() ?: null],
        ];

        return array_values(array_filter($items, fn (array $item) => Route::has($item['route'])));
    }
}
```

- [ ] **Step 5: Layouts**

`resources/views/components/layouts/base.blade.php`:
```blade
@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('wiis.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('theme');
                var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (dark) document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body {{ $attributes->merge(['class' => 'h-full']) }}>
    {{ $slot }}
</body>
</html>
```

`resources/views/components/layouts/app.blade.php`:
```blade
@props(['title' => null])
<x-layouts.base :title="$title">
    <div x-data="{ sidebarOpen: false }" class="min-h-full lg:flex">
        <x-layouts.partials.sidebar />
        <div class="flex min-w-0 flex-1 flex-col">
            <x-layouts.partials.topbar :title="$title" />
            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl space-y-6">
                    <x-flash />
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</x-layouts.base>
```

`resources/views/components/layouts/auth.blade.php`:
```blade
@props(['title' => null])
<x-layouts.base :title="$title">
    <div class="min-h-full lg:grid lg:grid-cols-[1.1fr_1fr]">
        <aside class="relative hidden overflow-hidden bg-brand-800 text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="absolute -right-24 -top-24 size-96 rounded-full bg-brand-600/50 blur-3xl"></div>
            <div class="absolute -bottom-32 -left-20 size-[28rem] rounded-full bg-brand-400/20 blur-3xl"></div>
            <x-brand class="relative" variant="light" />
            <div class="relative max-w-md space-y-6">
                <h1 class="font-display text-4xl font-semibold leading-tight">{{ config('wiis.tagline') }}</h1>
                <p class="text-brand-100">One place for interns, advisers and partner companies to manage applications, hours and requirements.</p>
            </div>
            <p class="relative text-sm text-brand-200">{{ config('wiis.institution.name') }} · {{ config('wiis.institution.office') }}</p>
        </aside>
        <div class="flex min-h-full flex-col px-4 py-10 sm:px-8 lg:px-16">
            <div class="mb-8 lg:hidden"><x-brand /></div>
            <div class="mx-auto w-full max-w-md flex-1">
                <x-flash />
                {{ $slot }}
            </div>
            <p class="mt-10 text-center text-xs text-stone-500">&copy; {{ date('Y') }} {{ config('wiis.name') }} · {{ config('wiis.institution.name') }}</p>
        </div>
    </div>
</x-layouts.base>
```

`resources/views/components/layouts/partials/sidebar.blade.php`:
```blade
@php $user = auth()->user(); @endphp
<div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-stone-900/50 lg:hidden" @click="sidebarOpen = false"></div>

<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-stone-200 bg-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 dark:border-stone-800 dark:bg-surface-dark-raised">
    <div class="flex h-16 items-center justify-between px-5">
        <x-brand />
        <button type="button" class="btn-ghost -mr-2 p-2 lg:hidden" @click="sidebarOpen = false" aria-label="Close menu">
            <x-heroicon-o-x-mark class="size-5" />
        </button>
    </div>

    <div class="mx-4 flex items-center gap-3 rounded-2xl bg-stone-50 p-3 dark:bg-stone-900">
        <x-avatar :user="$user" size="md" />
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold">{{ $user->name }}</p>
            <p class="truncate text-xs text-stone-500">{{ $user->member_no }} · {{ $user->role->label() }}</p>
        </div>
    </div>

    <nav class="mt-6 flex-1 space-y-1 overflow-y-auto px-3" aria-label="Main">
        @foreach (\App\Support\Navigation::for($user) as $item)
            <x-layouts.partials.nav-item :item="$item" />
        @endforeach
    </nav>

    <div class="space-y-1 border-t border-stone-200 p-3 dark:border-stone-800">
        <x-layouts.partials.nav-item :item="['label' => 'Profile', 'route' => 'profile.edit', 'icon' => 'heroicon-o-user-circle', 'active' => 'profile.*']" />
        <button type="button" @click="$store.theme.toggle()"
                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-stone-600 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800">
            <x-heroicon-o-moon class="size-5 dark:hidden" />
            <x-heroicon-o-sun class="hidden size-5 dark:block" />
            <span x-text="$store.theme.dark ? 'Light mode' : 'Dark mode'">Dark mode</span>
        </button>
        @if (Route::has('logout'))
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-stone-600 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800">
                    <x-heroicon-o-arrow-right-start-on-rectangle class="size-5" /> Sign out
                </button>
            </form>
        @endif
    </div>
</aside>
```

`resources/views/components/layouts/partials/topbar.blade.php`:
```blade
@props(['title' => null])
<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-stone-200/80 bg-surface/80 px-4 backdrop-blur sm:px-6 lg:px-8 dark:border-stone-800 dark:bg-surface-dark/80">
    <button type="button" class="btn-ghost -ml-2 p-2 lg:hidden" @click="sidebarOpen = true" aria-label="Open menu">
        <x-heroicon-o-bars-3 class="size-6" />
    </button>
    <p class="truncate text-sm font-medium text-stone-500">{{ $title }}</p>
    <div class="ml-auto flex items-center gap-1">
        <x-notification-bell />
        <x-dropdown align="right">
            <x-slot:trigger>
                <button type="button" class="flex items-center gap-2 rounded-full p-1 pr-2 hover:bg-stone-100 dark:hover:bg-stone-800" aria-label="Account menu">
                    <x-avatar :user="auth()->user()" size="sm" />
                    <x-heroicon-o-chevron-down class="size-4 text-stone-500" />
                </button>
            </x-slot:trigger>
            @if (Route::has('profile.edit'))
                <x-dropdown.item :href="route('profile.edit')" icon="heroicon-o-user-circle">Profile</x-dropdown.item>
            @endif
            @if (Route::has('logout'))
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-stone-700 hover:bg-stone-100 dark:text-stone-200 dark:hover:bg-stone-800">
                        <x-heroicon-o-arrow-right-start-on-rectangle class="size-4 text-stone-500" /> Sign out
                    </button>
                </form>
            @endif
        </x-dropdown>
    </div>
</header>
```

`resources/views/components/layouts/partials/nav-item.blade.php`:
```blade
@props(['item'])
@if (Route::has($item['route']))
    @php $active = request()->routeIs($item['active'] ?? $item['route']); @endphp
    <a href="{{ route($item['route']) }}"
       @class([
           'flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition',
           'bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-200' => $active,
           'text-stone-600 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800' => ! $active,
       ])
       @if ($active) aria-current="page" @endif>
        <x-dynamic-component :component="$item['icon']" class="size-5 shrink-0" />
        <span class="flex-1">{{ $item['label'] }}</span>
        @if (! empty($item['badge']))
            <span class="rounded-full bg-brand-600 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $item['badge'] }}</span>
        @endif
    </a>
@endif
```

- [ ] **Step 6: Components**

`resources/views/components/brand.blade.php`:
```blade
@props(['variant' => 'default'])
@php $light = $variant === 'light'; @endphp
<a href="{{ url('/') }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    @if (config('wiis.institution.logo'))
        <img src="{{ asset(config('wiis.institution.logo')) }}" alt="{{ config('wiis.institution.short') }}" class="size-9 rounded-lg object-contain">
    @else
        <span class="grid size-9 place-items-center rounded-lg font-display text-base font-bold {{ $light ? 'bg-white/15 text-white' : 'bg-brand-600 text-white' }}">{{ mb_substr(config('wiis.name'), 0, 1) }}</span>
    @endif
    <span class="leading-tight">
        <span class="block font-display text-lg font-semibold {{ $light ? 'text-white' : 'text-stone-900 dark:text-white' }}">{{ config('wiis.name') }}</span>
        <span class="block text-[11px] uppercase tracking-wider {{ $light ? 'text-brand-200' : 'text-stone-500' }}">{{ config('wiis.institution.short') }}</span>
    </span>
</a>
```

`resources/views/components/badge.blade.php`:
```blade
@props(['status' => null, 'color' => null])
@php
    $color = $color ?? ($status?->badgeColor() ?? 'gray');
    $label = $slot->isNotEmpty() ? $slot : ($status?->label() ?? '');
    $classes = match ($color) {
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300',
        'teal' => 'bg-brand-50 text-brand-700 ring-brand-600/20 dark:bg-brand-500/10 dark:text-brand-300',
        default => 'bg-stone-100 text-stone-700 ring-stone-500/20 dark:bg-stone-500/10 dark:text-stone-300',
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset $classes"]) }} data-color="{{ $color }}">{{ $label }}</span>
```

`resources/views/components/card.blade.php`:
```blade
@props(['title' => null, 'subtitle' => null, 'padding' => true])
<section {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($actions))
        <header class="flex items-start justify-between gap-4 border-b border-stone-200/80 px-5 py-4 dark:border-stone-800">
            <div>
                @if ($title)<h2 class="font-display text-base font-semibold">{{ $title }}</h2>@endif
                @if ($subtitle)<p class="mt-0.5 text-sm text-stone-500">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="shrink-0">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div @class(['p-5' => $padding])>{{ $slot }}</div>
</section>
```

`resources/views/components/stat-card.blade.php`:
```blade
@props(['label', 'value', 'icon' => null, 'hint' => null, 'color' => 'brand'])
@php
    $tone = match ($color) {
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10',
        'green' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10',
        'rose' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10',
        'sky' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10',
        default => 'bg-brand-50 text-brand-600 dark:bg-brand-500/10',
    };
@endphp
<div {{ $attributes->merge(['class' => 'card flex items-center gap-4 p-5']) }}>
    @if ($icon)
        <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $tone }}"><x-dynamic-component :component="$icon" class="size-5" /></span>
    @endif
    <div class="min-w-0">
        <p class="text-sm text-stone-500">{{ $label }}</p>
        <p class="font-display text-2xl font-semibold tabular-nums">{{ $value }}</p>
        @if ($hint)<p class="text-xs text-stone-500">{{ $hint }}</p>@endif
    </div>
</div>
```

`resources/views/components/page-header.blade.php`:
```blade
@props(['title', 'subtitle' => null, 'breadcrumbs' => []])
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        @if ($breadcrumbs)
            <nav class="mb-1 flex items-center gap-1.5 text-xs text-stone-500" aria-label="Breadcrumb">
                @foreach ($breadcrumbs as $label => $url)
                    @if (! $loop->first)<x-heroicon-o-chevron-right class="size-3" />@endif
                    @if ($url)<a href="{{ $url }}" class="hover:text-stone-800 dark:hover:text-stone-200">{{ $label }}</a>@else<span>{{ $label }}</span>@endif
                @endforeach
            </nav>
        @endif
        <h1 class="font-display text-2xl font-semibold tracking-tight sm:text-3xl">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-sm text-stone-500">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)<div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>@endisset
</div>
```

`resources/views/components/avatar.blade.php`:
```blade
@props(['user', 'size' => 'md'])
@php
    $dim = match ($size) {
        'xs' => 'size-6 text-[10px]', 'sm' => 'size-8 text-xs', 'lg' => 'size-16 text-xl', 'xl' => 'size-24 text-3xl',
        default => 'size-10 text-sm',
    };
@endphp
@if ($user->avatar_path)
    <img src="{{ Storage::disk('public')->url($user->avatar_path) }}" alt="{{ $user->name }}" {{ $attributes->merge(['class' => "$dim rounded-full object-cover"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$dim grid shrink-0 place-items-center rounded-full bg-brand-100 font-semibold text-brand-700 dark:bg-brand-900/50 dark:text-brand-200"]) }} aria-hidden="true">{{ $user->initials }}</span>
@endif
```

`resources/views/components/empty-state.blade.php`:
```blade
@props(['title', 'description' => null, 'icon' => 'heroicon-o-inbox'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="grid size-14 place-items-center rounded-2xl bg-stone-100 text-stone-400 dark:bg-stone-800"><x-dynamic-component :component="$icon" class="size-7" /></span>
    <h3 class="mt-4 font-display text-base font-semibold">{{ $title }}</h3>
    @if ($description)<p class="mt-1 max-w-sm text-sm text-stone-500">{{ $description }}</p>@endif
    @isset($action)<div class="mt-5">{{ $action }}</div>@endisset
</div>
```

`resources/views/components/flash.blade.php`:
```blade
@php
    $types = [
        'success' => ['border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200', 'heroicon-o-check-circle'],
        'error' => ['border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200', 'heroicon-o-exclamation-circle'],
        'warning' => ['border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200', 'heroicon-o-exclamation-triangle'],
        'info' => ['border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200', 'heroicon-o-information-circle'],
    ];
@endphp
@foreach ($types as $key => [$classes, $icon])
    @if (session($key))
        <div x-data="{ show: true }" x-show="show" x-transition role="status" class="flex items-start gap-3 rounded-2xl border p-4 text-sm {{ $classes }}">
            <x-dynamic-component :component="$icon" class="mt-0.5 size-5 shrink-0" />
            <p class="flex-1">{{ session($key) }}</p>
            <button type="button" @click="show = false" class="-m-1 rounded-lg p-1 opacity-70 hover:opacity-100" aria-label="Dismiss">
                <x-heroicon-o-x-mark class="size-4" />
            </button>
        </div>
    @endif
@endforeach
```

`resources/views/components/confirm-form.blade.php`:
```blade
@props(['action', 'method' => 'POST', 'confirm' => 'Are you sure?'])
<form method="POST" action="{{ $action }}" x-data @submit.prevent="if (window.confirm(@js($confirm))) $el.submit()" {{ $attributes }}>
    @csrf
    @if (! in_array(strtoupper($method), ['GET', 'POST'])) @method($method) @endif
    {{ $slot }}
</form>
```

`resources/views/components/dropdown.blade.php`:
```blade
@props(['align' => 'right', 'width' => 'w-56'])
<div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
    <div @click="open = ! open">{{ $trigger }}</div>
    <div x-cloak x-show="open" @click.outside="open = false" x-transition.origin.top
         class="absolute z-30 mt-2 {{ $width }} {{ $align === 'right' ? 'right-0' : 'left-0' }} overflow-hidden rounded-2xl border border-stone-200 bg-white p-1.5 shadow-lg dark:border-stone-700 dark:bg-stone-900">
        {{ $slot }}
    </div>
</div>
```

`resources/views/components/dropdown/item.blade.php`:
```blade
@props(['href' => '#', 'icon' => null])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-stone-700 hover:bg-stone-100 dark:text-stone-200 dark:hover:bg-stone-800']) }}>
    @if ($icon)<x-dynamic-component :component="$icon" class="size-4 text-stone-500" />@endif
    {{ $slot }}
</a>
```

`resources/views/components/modal.blade.php`:
```blade
@props(['name', 'title' => null, 'maxWidth' => 'max-w-lg'])
<div x-data="{ show: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') show = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') show = false"
     x-on:keydown.escape.window="show = false"
     x-cloak x-show="show"
     class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif>
    <div x-show="show" x-transition.opacity class="fixed inset-0 bg-stone-900/60" @click="show = false"></div>
    <div x-show="show" x-transition class="relative w-full {{ $maxWidth }} rounded-2xl bg-white p-6 shadow-xl dark:bg-stone-900">
        <div class="flex items-start justify-between gap-4">
            @if ($title)<h2 class="font-display text-lg font-semibold">{{ $title }}</h2>@endif
            <button type="button" class="btn-ghost -mr-2 -mt-1 p-1.5" @click="show = false" aria-label="Close"><x-heroicon-o-x-mark class="size-5" /></button>
        </div>
        <div class="mt-4">{{ $slot }}</div>
    </div>
</div>
```

`resources/views/components/button.blade.php`:
```blade
@props(['variant' => 'primary', 'type' => 'submit', 'href' => null, 'icon' => null])
@php
    $class = match ($variant) {
        'secondary' => 'btn-secondary', 'danger' => 'btn-danger', 'ghost' => 'btn-ghost', default => 'btn-primary',
    };
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>
        @if ($icon)<x-dynamic-component :component="$icon" class="size-4" />@endif{{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }}>
        @if ($icon)<x-dynamic-component :component="$icon" class="size-4" />@endif{{ $slot }}
    </button>
@endif
```

`resources/views/components/progress-ring.blade.php`:
```blade
@props(['percent' => 0, 'size' => 128, 'stroke' => 10, 'color' => 'brand', 'label' => null])
@php
    $percent = max(0, min(100, (int) $percent));
    $radius = ($size - $stroke) / 2;
    $circumference = 2 * M_PI * $radius;
    $offset = $circumference * (1 - $percent / 100);
    $strokeClass = match ($color) {
        'green' => 'stroke-emerald-500', 'amber' => 'stroke-amber-500', 'rose' => 'stroke-rose-500', default => 'stroke-brand-600',
    };
@endphp
<div {{ $attributes->merge(['class' => 'relative inline-grid place-items-center']) }} style="width: {{ $size }}px; height: {{ $size }}px" role="img" aria-label="{{ $percent }} percent">
    <svg width="{{ $size }}" height="{{ $size }}" class="-rotate-90">
        <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}" stroke-width="{{ $stroke }}" fill="none" class="stroke-stone-200 dark:stroke-stone-800" />
        <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}" stroke-width="{{ $stroke }}" fill="none" stroke-linecap="round"
                class="{{ $strokeClass }} transition-[stroke-dashoffset] duration-700"
                stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}" />
    </svg>
    <div class="absolute inset-0 grid place-items-center text-center">
        <div>
            <p class="font-display text-2xl font-semibold tabular-nums">{{ $percent }}%</p>
            @if ($label)<p class="text-xs text-stone-500">{{ $label }}</p>@endif
        </div>
    </div>
</div>
```

`resources/views/components/table.blade.php`:
```blade
<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <table class="data-table min-w-full divide-y divide-stone-200 text-sm dark:divide-stone-800">
        @isset($head)<thead><tr>{{ $head }}</tr></thead>@endisset
        <tbody class="divide-y divide-stone-100 dark:divide-stone-800">{{ $slot }}</tbody>
    </table>
</div>
```

`resources/views/components/notification-bell.blade.php` (baseline; Task 15 adds the dropdown preview):
```blade
@php $unread = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0; @endphp
@if (Route::has('notifications.index'))
    <a href="{{ route('notifications.index') }}" class="btn-ghost relative p-2" aria-label="Notifications{{ $unread ? ", $unread unread" : '' }}">
        <x-heroicon-o-bell class="size-5" />
        @if ($unread)
            <span class="absolute right-1.5 top-1.5 grid min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-4 text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </a>
@endif
```

`resources/views/components/form/label.blade.php`:
```blade
@props(['for', 'required' => false])
<label for="{{ $for }}" {{ $attributes->merge(['class' => 'label']) }}>{{ $slot }}@if ($required) <span class="text-rose-500">*</span>@endif</label>
```

`resources/views/components/form/error.blade.php`:
```blade
@props(['name'])
@error($name)
    <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
@enderror
```

`resources/views/components/form/input.blade.php`:
```blade
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])
<div class="{{ $attributes->get('class') }}">
    @if ($label)<x-form.label :for="$name" :required="$required">{{ $label }}</x-form.label>@endif
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password' && $type !== 'file') value="{{ old($name, $value) }}" @endif
           @required($required)
           {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($name) ? ' input-error' : '')]) }}>
    @if ($hint)<p class="mt-1.5 text-xs text-stone-500">{{ $hint }}</p>@endif
    <x-form.error :name="$name" />
</div>
```

`resources/views/components/form/select.blade.php`:
```blade
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false])
<div class="{{ $attributes->get('class') }}">
    @if ($label)<x-form.label :for="$name" :required="$required">{{ $label }}</x-form.label>@endif
    <select id="{{ $name }}" name="{{ $name }}" @required($required) {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($name) ? ' input-error' : '')]) }}>
        @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    <x-form.error :name="$name" />
</div>
```

`resources/views/components/form/textarea.blade.php`:
```blade
@props(['name', 'label' => null, 'rows' => 4, 'value' => null, 'required' => false, 'hint' => null])
<div class="{{ $attributes->get('class') }}">
    @if ($label)<x-form.label :for="$name" :required="$required">{{ $label }}</x-form.label>@endif
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
              {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($name) ? ' input-error' : '')]) }}>{{ old($name, $value) }}</textarea>
    @if ($hint)<p class="mt-1.5 text-xs text-stone-500">{{ $hint }}</p>@endif
    <x-form.error :name="$name" />
</div>
```

`resources/views/components/form/file.blade.php`:
```blade
@props(['name', 'label' => null, 'accept' => null, 'hint' => null, 'required' => false])
<div class="{{ $attributes->get('class') }}">
    @if ($label)<x-form.label :for="$name" :required="$required">{{ $label }}</x-form.label>@endif
    <input id="{{ $name }}" name="{{ $name }}" type="file" @if ($accept) accept="{{ $accept }}" @endif @required($required)
           class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-xl file:border-0 file:bg-brand-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:text-stone-300 dark:file:bg-brand-900/40 dark:file:text-brand-200 {{ $errors->has($name) ? 'rounded-xl ring-1 ring-rose-400' : '' }}">
    @if ($hint)<p class="mt-1.5 text-xs text-stone-500">{{ $hint }}</p>@endif
    <x-form.error :name="$name" />
</div>
```

`resources/views/components/form/checkbox.blade.php`:
```blade
@props(['name', 'label', 'checked' => false])
<div class="{{ $attributes->get('class') }}">
    <label class="flex items-start gap-3 text-sm">
        <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) class="mt-0.5 size-4 rounded border-stone-300 text-brand-600 focus:ring-brand-500">
        <span>{{ $label }}</span>
    </label>
    <x-form.error :name="$name" />
</div>
```

- [ ] **Step 7: Run tests and build**

Run: `php artisan test --filter=DesignSystemTest && npm run build`
Expected: PASS (5 tests); Vite build succeeds with no Tailwind errors (if `@apply btn` inside `.btn-primary` fails, the `@utility btn` block above is the fix and must be kept).

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add Tailwind design system, layouts and Blade components"
```

---

### Task 10: Role middleware, account-state middleware, route skeleton and dashboards

**Files:**
- Create: `app/Http/Middleware/EnsureRole.php`, `app/Http/Middleware/EnsureAccountUsable.php`, `app/Http/Controllers/DashboardRedirectController.php`, `app/Http/Controllers/Auth/LoginController.php` (only `create()` here; `store/destroy` in Task 11), `app/Http/Controllers/Auth/AccountStatusController.php`, `app/Http/Controllers/Admin/DashboardController.php`, `app/Http/Controllers/Adviser/DashboardController.php`, `app/Http/Controllers/Company/DashboardController.php`, `app/Http/Controllers/Intern/DashboardController.php`, `resources/views/auth/login.blade.php`, `resources/views/account/pending.blade.php`, `resources/views/admin/dashboard.blade.php`, `resources/views/adviser/dashboard.blade.php`, `resources/views/company/dashboard.blade.php`, `resources/views/intern/dashboard.blade.php`
- Modify: `bootstrap/app.php`, `routes/web.php`
- Delete: `resources/views/welcome.blade.php`
- Test: `tests/Feature/RoleAccessTest.php`

**Interfaces:**
- Produces: middleware aliases `role:<a>,<b>` and `account.usable`; routes `home`, `login` (GET), `account.pending`, `dashboard`, `admin.dashboard`, `adviser.dashboard`, `company.dashboard`, `intern.dashboard`. Guests redirect to `login`; authenticated users hitting guest routes redirect to `dashboard`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/RoleAccessTest.php`:
```php
<?php

use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use App\Models\User;

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/admin/dashboard')->assertRedirect('/login');
});

it('shows the login page to guests', function () {
    $this->get('/login')->assertOk()->assertSee('Sign in');
});

it('sends each role to its own dashboard', function (string $state, string $route) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route($route));
    $this->actingAs($user)->get(route($route))->assertOk()->assertSee($user->first_name);
    $this->actingAs($user)->get('/login')->assertRedirect(route('dashboard'));
})->with([
    ['admin', 'admin.dashboard'],
    ['adviser', 'adviser.dashboard'],
    ['company', 'company.dashboard'],
    ['intern', 'intern.dashboard'],
]);

it('forbids access to other portals', function () {
    $intern = User::factory()->intern()->create();

    $this->actingAs($intern)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($intern)->get(route('adviser.dashboard'))->assertForbidden();
    $this->actingAs($intern)->get(route('company.dashboard'))->assertForbidden();
});

it('holds unapproved companies on the pending page', function () {
    $user = User::factory()->company()->create();
    $user->company->update(['approval_status' => ApprovalStatus::Pending, 'approved_at' => null]);

    $this->actingAs($user)->get(route('company.dashboard'))->assertRedirect(route('account.pending'));
    $this->actingAs($user)->get(route('account.pending'))->assertOk()->assertSee('pending');
});

it('sends approved companies away from the pending page', function () {
    $user = User::factory()->company()->create();

    $this->actingAs($user)->get(route('account.pending'))->assertRedirect(route('dashboard'));
});

it('logs out a user who was disabled after signing in', function () {
    $user = User::factory()->intern()->create();
    $this->actingAs($user)->get(route('intern.dashboard'))->assertOk();

    $user->update(['status' => AccountStatus::Disabled]);

    $this->get(route('intern.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=RoleAccessTest`
Expected: FAIL — route `login` not defined.

- [ ] **Step 3: Middleware and bootstrap registration**

`app/Http/Middleware/EnsureRole.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /** Usage: ->middleware('role:admin') or 'role:adviser,admin'. */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user && in_array($user->role->value, $roles, true), 403);

        return $next($request);
    }
}
```

`app/Http/Middleware/EnsureAccountUsable.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountUsable
{
    /**
     * Signs out accounts that were disabled mid-session and keeps unapproved
     * companies on the pending page until an admin verifies them.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isDisabled()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been disabled. Please contact '.config('wiis.support.email').'.',
            ]);
        }

        if ($user && $user->isCompany() && ! $user->company?->isApproved() && ! $request->routeIs('account.pending', 'logout')) {
            return redirect()->route('account.pending');
        }

        return $next($request);
    }
}
```

`bootstrap/app.php`:
```php
<?php

use App\Http\Middleware\EnsureAccountUsable;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'account.usable' => EnsureAccountUsable::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

- [ ] **Step 4: Controllers**

`app/Http/Controllers/DashboardRedirectController.php`:
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->role->dashboardRoute());
    }
}
```

`app/Http/Controllers/Auth/LoginController.php` (Task 11 adds `store` and `destroy`):
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }
}
```

`app/Http/Controllers/Auth/AccountStatusController.php`:
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountStatusController extends Controller
{
    public function pending(Request $request): View|RedirectResponse
    {
        $company = $request->user()->company;

        if (! $request->user()->isCompany() || $company?->isApproved()) {
            return redirect()->route('dashboard');
        }

        return view('account.pending', ['company' => $company]);
    }
}
```

`app/Http/Controllers/Admin/DashboardController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'interns' => User::ofRole(Role::Intern)->active()->count(),
                'advisers' => User::ofRole(Role::Adviser)->active()->count(),
                'companies' => Company::registered()->approved()->count(),
                'pending_companies' => Company::registered()->pending()->count(),
            ],
            'recentUsers' => User::latest()->limit(6)->get(),
        ]);
    }
}
```

`app/Http/Controllers/Adviser/DashboardController.php`:
```php
<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\InternProfile;
use App\Services\OjtHoursService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OjtHoursService $hours): View
    {
        $classes = $request->user()->advisedClasses()->active()->withCount('internProfiles')->get();
        $interns = InternProfile::query()->whereIn('class_section_id', $classes->pluck('id'));

        return view('adviser.dashboard', [
            'classes' => $classes,
            'stats' => [
                'classes' => $classes->count(),
                'interns' => (clone $interns)->count(),
                'completed' => (clone $interns)->where('total_hours', '>=', $hours->required())->count(),
            ],
        ]);
    }
}
```

`app/Http/Controllers/Company/DashboardController.php`:
```php
<?php

namespace App\Http\Controllers\Company;

use App\Enums\DtrStatus;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Dtr;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $company = $request->user()->company;
        $placementIds = $company->placements()->select('id');

        return view('company.dashboard', [
            'company' => $company,
            'stats' => [
                'active_interns' => $company->activePlacements()->count(),
                'open_postings' => $company->postings()->open()->count(),
                'pending_dtrs' => Dtr::whereIn('placement_id', $placementIds)->where('status', DtrStatus::Pending)->count(),
                'certificates' => Certificate::whereIn('placement_id', $placementIds)->count(),
            ],
        ]);
    }
}
```

`app/Http/Controllers/Intern/DashboardController.php`:
```php
<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Services\OjtHoursService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OjtHoursService $hours): View
    {
        $user = $request->user()->load(['internProfile.classSection.adviser', 'activePlacement.company']);
        $total = $user->internProfile?->total_hours ?? 0;

        return view('intern.dashboard', [
            'profile' => $user->internProfile,
            'placement' => $user->activePlacement,
            'section' => $user->internProfile?->classSection,
            'progress' => [
                'hours' => $total,
                'required' => $hours->required(),
                'percent' => $hours->progressPercent($total),
                'remaining' => $hours->remaining($total),
                'tier' => $hours->tier($total),
            ],
        ]);
    }
}
```

- [ ] **Step 5: Routes**

`routes/web.php`:
```php
<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Adviser;
use App\Http\Controllers\Auth\AccountStatusController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Company;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Intern;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

/* Guests */
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
});

/* Any signed-in user */
Route::middleware(['auth', 'account.usable'])->group(function () {
    Route::get('account/pending', [AccountStatusController::class, 'pending'])->name('account.pending');
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');
    });

    Route::prefix('adviser')->name('adviser.')->middleware('role:adviser')->group(function () {
        Route::get('dashboard', Adviser\DashboardController::class)->name('dashboard');
    });

    Route::prefix('company')->name('company.')->middleware('role:company')->group(function () {
        Route::get('dashboard', Company\DashboardController::class)->name('dashboard');
    });

    Route::prefix('intern')->name('intern.')->middleware('role:intern')->group(function () {
        Route::get('dashboard', Intern\DashboardController::class)->name('dashboard');
    });
});
```

Delete `resources/views/welcome.blade.php` and update `tests/Feature/ExampleTest.php`:
```php
<?php

it('redirects the root to login', function () {
    $this->get('/')->assertRedirect('/login');
});
```

- [ ] **Step 6: Views**

`resources/views/auth/login.blade.php`:
```blade
<x-layouts.auth title="Sign in">
    <h2 class="font-display text-2xl font-semibold">Welcome back</h2>
    <p class="mt-1 text-sm text-stone-500">Sign in to your {{ config('wiis.name') }} account.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf
        <x-form.input name="email" label="Email address" type="email" required autofocus autocomplete="username" />
        <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />
        <div class="flex items-center justify-between">
            <x-form.checkbox name="remember" label="Remember me" />
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">Forgot password?</a>
            @endif
        </div>
        <x-button class="w-full">Sign in</x-button>
    </form>

    <div class="mt-8 space-y-2 text-sm text-stone-600 dark:text-stone-400">
        @if (Route::has('register.intern'))
            <p>Student? <a href="{{ route('register.intern') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Create an intern account</a></p>
        @endif
        @if (Route::has('register.company'))
            <p>Company? <a href="{{ route('register.company') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Register as a partner company</a></p>
        @endif
    </div>
</x-layouts.auth>
```

`resources/views/account/pending.blade.php`:
```blade
<x-layouts.auth title="Account verification">
    @php $rejected = $company->approval_status === \App\Enums\ApprovalStatus::Rejected; @endphp
    <div class="card p-6">
        <span class="grid size-12 place-items-center rounded-2xl {{ $rejected ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600' }}">
            @if ($rejected)<x-heroicon-o-x-circle class="size-6" />@else<x-heroicon-o-clock class="size-6" />@endif
        </span>
        <h2 class="mt-4 font-display text-2xl font-semibold">{{ $rejected ? 'Registration not approved' : 'Verification pending' }}</h2>
        <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">
            @if ($rejected)
                The {{ config('wiis.institution.office') }} could not verify <strong>{{ $company->name }}</strong>. Please contact {{ config('wiis.support.email') }} for details.
            @else
                Thanks for registering <strong>{{ $company->name }}</strong>. Your business permit and MOA are being reviewed by the {{ config('wiis.institution.office') }}. You will be able to post internships once your account is approved.
            @endif
        </p>
        <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
            <div><dt class="text-stone-500">Status</dt><dd class="mt-1"><x-badge :status="$company->approval_status" /></dd></div>
            <div><dt class="text-stone-500">Submitted</dt><dd class="mt-1">{{ $company->created_at->format('M j, Y') }}</dd></div>
        </dl>
        @if (Route::has('logout'))
            <form method="POST" action="{{ route('logout') }}" class="mt-6">
                @csrf
                <x-button variant="secondary" class="w-full">Sign out</x-button>
            </form>
        @endif
    </div>
</x-layouts.auth>
```

`resources/views/admin/dashboard.blade.php`:
```blade
<x-layouts.app title="Dashboard">
    <x-page-header title="Welcome back, {{ auth()->user()->first_name }}" subtitle="An overview of {{ config('wiis.institution.short') }} internship activity." />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Active interns" :value="$stats['interns']" icon="heroicon-o-academic-cap" />
        <x-stat-card label="Advisers" :value="$stats['advisers']" icon="heroicon-o-user-group" color="sky" />
        <x-stat-card label="Partner companies" :value="$stats['companies']" icon="heroicon-o-building-office-2" color="green" />
        <x-stat-card label="Awaiting approval" :value="$stats['pending_companies']" icon="heroicon-o-clock" color="amber" />
    </div>

    <x-card title="Recent accounts" subtitle="Newest registrations across all roles" :padding="false">
        <x-table>
            <x-slot:head><th>Name</th><th>Role</th><th>Member no.</th><th>Joined</th></x-slot:head>
            @forelse ($recentUsers as $user)
                <tr>
                    <td><div class="flex items-center gap-3"><x-avatar :user="$user" size="sm" /><span class="font-medium">{{ $user->name }}</span></div></td>
                    <td><x-badge :status="$user->role" /></td>
                    <td class="font-mono text-xs">{{ $user->member_no }}</td>
                    <td class="text-stone-500">{{ $user->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty-state title="No accounts yet" /></td></tr>
            @endforelse
        </x-table>
    </x-card>
</x-layouts.app>
```

`resources/views/adviser/dashboard.blade.php`:
```blade
<x-layouts.app title="Dashboard">
    <x-page-header title="Hello, {{ auth()->user()->first_name }}" subtitle="Your classes and the interns you advise." />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Classes" :value="$stats['classes']" icon="heroicon-o-rectangle-group" />
        <x-stat-card label="Interns" :value="$stats['interns']" icon="heroicon-o-academic-cap" color="sky" />
        <x-stat-card label="Completed OJT" :value="$stats['completed']" icon="heroicon-o-check-badge" color="green" />
    </div>

    <x-card title="My classes" :padding="false">
        @forelse ($classes as $class)
            <div class="flex items-center justify-between gap-4 border-b border-stone-100 px-5 py-4 last:border-0 dark:border-stone-800">
                <div>
                    <p class="font-semibold">{{ $class->display_name }}</p>
                    <p class="text-sm text-stone-500">{{ $class->subject }} · {{ $class->schedule_label }} · {{ $class->school_year }}</p>
                </div>
                <div class="text-right">
                    <p class="font-display text-xl font-semibold tabular-nums">{{ $class->intern_profiles_count }}</p>
                    <p class="text-xs text-stone-500">interns</p>
                </div>
            </div>
        @empty
            <x-empty-state title="No classes yet" description="Classes you advise will appear here." icon="heroicon-o-rectangle-group" />
        @endforelse
    </x-card>
</x-layouts.app>
```

`resources/views/company/dashboard.blade.php`:
```blade
<x-layouts.app title="Dashboard">
    <x-page-header :title="$company->name" subtitle="Hello, {{ auth()->user()->first_name }}. Here is your internship program at a glance.">
        <x-slot:actions><x-badge :status="$company->approval_status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Active interns" :value="$stats['active_interns']" icon="heroicon-o-users" />
        <x-stat-card label="Open postings" :value="$stats['open_postings']" icon="heroicon-o-megaphone" color="sky" />
        <x-stat-card label="DTRs to review" :value="$stats['pending_dtrs']" icon="heroicon-o-clipboard-document-check" color="amber" />
        <x-stat-card label="Certificates issued" :value="$stats['certificates']" icon="heroicon-o-trophy" color="green" />
    </div>

    <x-card title="Company code" subtitle="Accepted interns enter this code to join your company.">
        <div x-data="{ copied: false }" class="flex flex-wrap items-center gap-3">
            <code class="rounded-xl bg-stone-100 px-4 py-2 font-mono text-lg font-semibold tracking-widest dark:bg-stone-800">{{ $company->company_code }}</code>
            <x-button type="button" variant="secondary" icon="heroicon-o-clipboard"
                      @click="navigator.clipboard.writeText('{{ $company->company_code }}').then(() => { copied = true; setTimeout(() => copied = false, 1500) })">
                <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
            </x-button>
        </div>
    </x-card>
</x-layouts.app>
```

`resources/views/intern/dashboard.blade.php`:
```blade
<x-layouts.app title="Dashboard">
    <x-page-header title="Hi, {{ auth()->user()->first_name }}" subtitle="Track your hours, placement and class in one place." />

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card title="OJT progress" class="lg:row-span-2">
            <div class="flex flex-col items-center gap-4 text-center">
                <x-progress-ring :percent="$progress['percent']" :color="$progress['tier']->badgeColor()" label="of required hours" :size="160" />
                <div>
                    <p class="font-display text-3xl font-semibold tabular-nums">{{ $progress['hours'] }} <span class="text-base font-normal text-stone-500">/ {{ $progress['required'] }} h</span></p>
                    <p class="mt-1 text-sm text-stone-500">{{ $progress['remaining'] }} hours remaining</p>
                </div>
                <x-badge :status="$progress['tier']" />
            </div>
        </x-card>

        <x-card title="Current placement" class="lg:col-span-2">
            @if ($placement)
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-display text-lg font-semibold">{{ $placement->company->name }}</p>
                        <p class="text-sm text-stone-500">Since {{ $placement->started_at->format('M j, Y') }} · {{ $placement->hours_rendered }} h rendered here</p>
                    </div>
                    <x-badge color="green">Active</x-badge>
                </div>
            @else
                <x-empty-state title="Not placed yet" description="Apply to an internship posting and join a company with its code once you are accepted." icon="heroicon-o-briefcase" class="py-8" />
            @endif
        </x-card>

        <x-card title="My class" class="lg:col-span-2">
            @if ($section)
                <p class="font-display text-lg font-semibold">{{ $section->display_name }}</p>
                <p class="text-sm text-stone-500">{{ $section->subject }} · {{ $section->schedule_label }}</p>
                <p class="mt-3 text-sm">Adviser: <span class="font-medium">{{ $section->adviser?->name ?? 'Not assigned yet' }}</span></p>
            @else
                <x-empty-state title="No class joined" description="Ask your adviser for the class join code." icon="heroicon-o-rectangle-group" class="py-8" />
            @endif
        </x-card>
    </div>
</x-layouts.app>
```

- [ ] **Step 7: Run tests**

Run: `php artisan test`
Expected: all green, including the 10 RoleAccessTest cases.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add role middleware, portal routes and dashboards"
```

---

### Task 11: Login, logout and account-state handling

**Files:**
- Create: `app/Http/Requests/Auth/LoginRequest.php`
- Modify: `app/Http/Controllers/Auth/LoginController.php`, `routes/web.php`
- Test: `tests/Feature/Auth/LoginTest.php`

**Interfaces:**
- Produces: routes `login.store` (POST `/login`), `logout` (POST `/logout`). `LoginRequest::authenticate()` throws `ValidationException` on bad credentials, disabled accounts, or throttling (5 attempts per email+IP per minute).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Auth/LoginTest.php`:
```php
<?php

use App\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

it('signs in with valid credentials and records the login time', function () {
    $user = User::factory()->intern()->create(['email' => 'juan@example.com']);

    $this->post('/login', ['email' => 'juan@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('normalizes the email before authenticating', function () {
    $user = User::factory()->intern()->create(['email' => 'juan@example.com']);

    $this->post('/login', ['email' => '  Juan@Example.COM ', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    User::factory()->intern()->create(['email' => 'juan@example.com']);

    $this->from('/login')->post('/login', ['email' => 'juan@example.com', 'password' => 'wrong'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('refuses disabled accounts with a support hint', function () {
    User::factory()->intern()->disabled()->create(['email' => 'off@example.com']);

    $this->post('/login', ['email' => 'off@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => fn ($message) => str_contains($message, config('wiis.support.email'))]);

    $this->assertGuest();
});

it('sends pending companies to the verification page after login', function () {
    $user = User::factory()->company()->create(['email' => 'co@example.com']);
    $user->company->update(['approval_status' => ApprovalStatus::Pending]);

    $this->post('/login', ['email' => 'co@example.com', 'password' => 'password'])->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertRedirect(route('account.pending'));
});

it('throttles after five failed attempts', function () {
    RateLimiter::clear('juan@example.com|127.0.0.1');
    User::factory()->intern()->create(['email' => 'juan@example.com']);

    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => 'juan@example.com', 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => 'juan@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => fn ($message) => str_contains($message, 'Too many')]);

    $this->assertGuest();
});

it('signs out', function () {
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=LoginTest`
Expected: FAIL — POST `/login` returns 405.

- [ ] **Step 3: Login request and controller**

`app/Http/Requests/Auth/LoginRequest.php`:
```php
<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::query()->where('email', $this->input('email'))->first();

        if (! $user || ! Hash::check($this->input('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if ($user->isDisabled()) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'This account has been disabled. Please contact '.config('wiis.support.email').'.',
            ]);
        }

        Auth::login($user, $this->boolean('remember'));
        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate($this->input('email').'|'.$this->ip());
    }
}
```

`app/Http/Controllers/Auth/LoginController.php`:
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
```

Add to `routes/web.php` — inside the `guest` group:
```php
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
```
and a new `auth`-only group (before the `account.usable` group):
```php
Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
});
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=LoginTest`
Expected: PASS (7 tests). `auth.failed` / `auth.throttle` resolve from the framework's built-in English strings.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add login and logout with throttling and disabled-account handling"
```

---

### Task 12: Intern self-registration with class join code

**Files:**
- Create: `app/Http/Requests/Auth/RegisterInternRequest.php`, `app/Actions/RegisterIntern.php`, `app/Notifications/InternJoinedClass.php`, `app/Http/Controllers/Auth/RegisterInternController.php`, `resources/views/auth/register-intern.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/RegisterInternTest.php`, `tests/Unit/Actions/RegisterInternTest.php`

**Interfaces:**
- Consumes: `MemberNumberGenerator`, `ClassSection::active()`, `Role`, `AccountStatus`.
- Produces: routes `register.intern` (GET `/register/intern`), `register.intern.store` (POST). `RegisterIntern::__invoke(array $data): User` where `$data` has `first_name, middle_name?, last_name, email, student_number, join_code, password`. Notification payload convention for every `App\Notifications\*` class: `toArray()` returns `['title' => string, 'body' => string, 'url' => string|null, 'icon' => string]`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/RegisterInternTest.php`:
```php
<?php

use App\Actions\RegisterIntern;
use App\Enums\Role;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\InternJoinedClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('creates an intern with a profile in the class and notifies the adviser', function () {
    Notification::fake();
    $section = ClassSection::factory()->create(['join_code' => 'JOIN2026']);

    $user = app(RegisterIntern::class)([
        'first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Santos',
        'email' => 'maria@example.com', 'student_number' => '21-0001',
        'join_code' => 'JOIN2026', 'password' => 'Secret-Pass-123',
    ]);

    expect($user->role)->toBe(Role::Intern)
        ->and($user->member_no)->toStartWith('INT-')
        ->and($user->internProfile->class_section_id)->toBe($section->id)
        ->and($user->internProfile->school_year)->toBe($section->school_year)
        ->and(password_verify('Secret-Pass-123', $user->password))->toBeTrue();

    Notification::assertSentTo($section->adviser, InternJoinedClass::class);
});

it('registers into a class that has no adviser yet without failing', function () {
    Notification::fake();
    ClassSection::factory()->unassigned()->create(['join_code' => 'NOADV123']);

    $user = app(RegisterIntern::class)([
        'first_name' => 'Pedro', 'middle_name' => null, 'last_name' => 'Reyes',
        'email' => 'pedro@example.com', 'student_number' => '21-0002',
        'join_code' => 'NOADV123', 'password' => 'Secret-Pass-123',
    ]);

    expect($user->internProfile->classSection->join_code)->toBe('NOADV123');
    Notification::assertNothingSent();
});
```

`tests/Feature/Auth/RegisterInternTest.php`:
```php
<?php

use App\Models\ClassSection;
use App\Models\User;

beforeEach(function () {
    $this->section = ClassSection::factory()->create(['join_code' => 'JOIN2026']);
    $this->payload = [
        'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com',
        'student_number' => '21-0001', 'join_code' => 'JOIN2026',
        'password' => 'Secret-Pass-123', 'password_confirmation' => 'Secret-Pass-123', 'terms' => '1',
    ];
});

it('shows the registration form', function () {
    $this->get('/register/intern')->assertOk()->assertSee('join code', false);
});

it('registers, signs in and lands on the intern dashboard', function () {
    $this->post('/register/intern', $this->payload)->assertRedirect(route('intern.dashboard'));

    $this->assertAuthenticated();
    expect(User::where('email', 'maria@example.com')->first()->internProfile->class_section_id)->toBe($this->section->id);
});

it('normalizes email and join code casing', function () {
    $this->post('/register/intern', [...$this->payload, 'email' => ' Maria@Example.com ', 'join_code' => ' join2026 '])
        ->assertRedirect(route('intern.dashboard'));

    expect(User::where('email', 'maria@example.com')->exists())->toBeTrue();
});

it('rejects an unknown join code', function () {
    $this->post('/register/intern', [...$this->payload, 'join_code' => 'NOPE1234'])
        ->assertSessionHasErrors('join_code');

    $this->assertGuest();
    expect(User::count())->toBe(1); // only the adviser from the factory
});

it('rejects the join code of an archived class', function () {
    $this->section->update(['status' => \App\Enums\ClassStatus::Archived]);

    $this->post('/register/intern', $this->payload)->assertSessionHasErrors('join_code');
    $this->assertGuest();
});

it('rejects duplicate email and student number', function () {
    $existing = User::factory()->intern()->create(['email' => 'maria@example.com']);

    $this->post('/register/intern', $this->payload)->assertSessionHasErrors('email');

    $this->post('/register/intern', [...$this->payload, 'email' => 'other@example.com', 'student_number' => $existing->internProfile->student_number])
        ->assertSessionHasErrors('student_number');
});

it('requires accepting the terms and a confirmed password', function () {
    $this->post('/register/intern', [...$this->payload, 'terms' => null])->assertSessionHasErrors('terms');
    $this->post('/register/intern', [...$this->payload, 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter=RegisterIntern`
Expected: FAIL — class `App\Actions\RegisterIntern` not found / route missing.

- [ ] **Step 3: Notification, action, request, controller, view, routes**

`app/Notifications/InternJoinedClass.php`:
```php
<?php

namespace App\Notifications;

use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InternJoinedClass extends Notification
{
    use Queueable;

    public function __construct(public User $intern, public ClassSection $section) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "New intern in {$this->section->display_name}",
            'body' => "{$this->intern->name} ({$this->intern->internProfile?->student_number}) joined your class.",
            'url' => route('adviser.dashboard'),
            'icon' => 'heroicon-o-academic-cap',
        ];
    }
}
```

`app/Actions/RegisterIntern.php`:
```php
<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\InternJoinedClass;
use App\Services\MemberNumberGenerator;
use Illuminate\Support\Facades\DB;

class RegisterIntern
{
    public function __construct(private readonly MemberNumberGenerator $memberNumbers) {}

    /**
     * @param  array{first_name:string, middle_name?:?string, last_name:string, email:string, student_number:string, join_code:string, password:string}  $data
     */
    public function __invoke(array $data): User
    {
        $section = ClassSection::query()->active()->where('join_code', $data['join_code'])->firstOrFail();

        $user = DB::transaction(function () use ($data, $section) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => Role::Intern,
                'member_no' => $this->memberNumbers->generate(Role::Intern),
                'status' => AccountStatus::Active,
            ]);

            $user->internProfile()->create([
                'student_number' => $data['student_number'],
                'class_section_id' => $section->id,
                'school_year' => $section->school_year,
            ]);

            return $user;
        });

        $section->adviser?->notify(new InternJoinedClass($user->load('internProfile'), $section));

        return $user;
    }
}
```

`app/Http/Requests/Auth/RegisterInternRequest.php`:
```php
<?php

namespace App\Http\Requests\Auth;

use App\Enums\ClassStatus;
use App\Models\ClassSection;
use App\Models\InternProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterInternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'join_code' => Str::upper(trim((string) $this->input('join_code'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'student_number' => ['required', 'string', 'max:30', Rule::unique(InternProfile::class, 'student_number')],
            'join_code' => ['required', 'string', Rule::exists(ClassSection::class, 'join_code')->where('status', ClassStatus::Active->value)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'join_code.exists' => 'We could not find an active class with that join code.',
            'terms.accepted' => 'Please accept the terms and conditions.',
        ];
    }
}
```

`app/Http/Controllers/Auth/RegisterInternController.php`:
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterIntern;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterInternRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterInternController extends Controller
{
    public function create(): View
    {
        return view('auth.register-intern');
    }

    public function store(RegisterInternRequest $request, RegisterIntern $registerIntern): RedirectResponse
    {
        $user = $registerIntern($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('intern.dashboard')->with('success', 'Welcome to '.config('wiis.name').'! Your account is ready.');
    }
}
```

`resources/views/auth/register-intern.blade.php`:
```blade
<x-layouts.auth title="Create intern account">
    <h2 class="font-display text-2xl font-semibold">Create your intern account</h2>
    <p class="mt-1 text-sm text-stone-500">Use the class join code from your practicum adviser.</p>

    <form method="POST" action="{{ route('register.intern.store') }}" class="mt-8 space-y-5">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="first_name" label="First name" required autofocus />
            <x-form.input name="last_name" label="Last name" required />
        </div>
        <x-form.input name="middle_name" label="Middle name" hint="Optional" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="student_number" label="Student number" placeholder="19-0842" required />
            <x-form.input name="join_code" label="Class join code" placeholder="SBIT4C26" class="font-mono uppercase" required />
        </div>
        <x-form.input name="email" label="Email address" type="email" required autocomplete="email" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="password" label="Password" type="password" required autocomplete="new-password" />
            <x-form.input name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
        </div>
        <x-form.checkbox name="terms" label="I agree to the terms and conditions of the internship program." />
        <x-button class="w-full">Create account</x-button>
    </form>

    <p class="mt-8 text-sm text-stone-600 dark:text-stone-400">Already registered? <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Sign in</a></p>
</x-layouts.auth>
```

Add to `routes/web.php` inside the `guest` group:
```php
    Route::get('register/intern', [RegisterInternController::class, 'create'])->name('register.intern');
    Route::post('register/intern', [RegisterInternController::class, 'store'])->name('register.intern.store');
```
(and `use App\Http\Controllers\Auth\RegisterInternController;`).

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=RegisterIntern`
Expected: PASS (9 tests).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add intern registration with class join code"
```

---

### Task 13: Company self-registration with permit and MOA uploads

**Files:**
- Create: `app/Http/Requests/Auth/RegisterCompanyRequest.php`, `app/Actions/RegisterCompany.php`, `app/Notifications/CompanyRegistered.php`, `app/Http/Controllers/Auth/RegisterCompanyController.php`, `app/Policies/CompanyPolicy.php`, `resources/views/auth/register-company.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/RegisterCompanyTest.php`

**Interfaces:**
- Consumes: `MemberNumberGenerator`, `JoinCodeGenerator`, `Company`, `ApprovalStatus`.
- Produces: routes `register.company`, `register.company.store`. `RegisterCompany::__invoke(array $data, UploadedFile $permit, UploadedFile $moa): User`. Files stored on disk `local` at `companies/{company_id}/permit.pdf` and `companies/{company_id}/moa.pdf`. `CompanyPolicy::viewDocuments(User, Company): bool` (admins and the owning company user). Notification `CompanyRegistered` sent to all active admins.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Auth/RegisterCompanyTest.php`:
```php
<?php

use App\Enums\ApprovalStatus;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyRegistered;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->payload = fn (array $overrides = []) => array_merge([
        'company_name' => 'TechNova Solutions Inc.', 'company_type' => 'IT Services',
        'first_name' => 'Marco', 'last_name' => 'Villanueva', 'email' => 'hr@technova.example',
        'phone' => '09171234567', 'address' => 'Ortigas Center, Pasig City', 'website' => 'https://technova.example',
        'about' => 'Software consultancy.',
        'password' => 'Secret-Pass-123', 'password_confirmation' => 'Secret-Pass-123', 'terms' => '1',
        'permit' => UploadedFile::fake()->create('permit.pdf', 300, 'application/pdf'),
        'moa' => UploadedFile::fake()->create('moa.pdf', 300, 'application/pdf'),
    ], $overrides);
});

it('shows the company registration form', function () {
    $this->get('/register/company')->assertOk()->assertSee('business permit', false);
});

it('registers a pending company, stores the documents and notifies admins', function () {
    $this->post('/register/company', ($this->payload)())->assertRedirect(route('account.pending'));

    $user = User::where('email', 'hr@technova.example')->firstOrFail();
    $company = $user->company;

    $this->assertAuthenticatedAs($user);
    expect($user->member_no)->toStartWith('CMP-')
        ->and($company->approval_status)->toBe(ApprovalStatus::Pending)
        ->and($company->company_code)->toHaveLength(8)
        ->and($company->permit_path)->toBe("companies/{$company->id}/permit.pdf")
        ->and($company->moa_path)->toBe("companies/{$company->id}/moa.pdf");

    Storage::disk('local')->assertExists($company->permit_path);
    Storage::disk('local')->assertExists($company->moa_path);
    Notification::assertSentTo($this->admin, CompanyRegistered::class);
});

it('rejects a non-PDF renamed to .pdf', function () {
    $this->post('/register/company', ($this->payload)(['permit' => UploadedFile::fake()->image('permit.pdf')]))
        ->assertSessionHasErrors('permit');

    expect(Company::count())->toBe(0);
});

it('rejects documents over the size limit', function () {
    $tooBig = config('wiis.uploads.max_pdf_kb') + 1;

    $this->post('/register/company', ($this->payload)(['moa' => UploadedFile::fake()->create('moa.pdf', $tooBig, 'application/pdf')]))
        ->assertSessionHasErrors('moa');
});

it('requires both documents, the terms and a unique email', function () {
    User::factory()->company()->create(['email' => 'hr@technova.example']);

    $this->post('/register/company', ($this->payload)(['permit' => null, 'terms' => null]))
        ->assertSessionHasErrors(['permit', 'terms', 'email']);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=RegisterCompanyTest`
Expected: FAIL — route not found.

- [ ] **Step 3: Notification, policy, action, request, controller, view, routes**

`app/Notifications/CompanyRegistered.php`:
```php
<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanyRegistered extends Notification
{
    use Queueable;

    public function __construct(public Company $company) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New company registration',
            'body' => "{$this->company->name} submitted a business permit and MOA for verification.",
            'url' => route('admin.dashboard'),
            'icon' => 'heroicon-o-building-office-2',
        ];
    }
}
```

`app/Policies/CompanyPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /** Permit and MOA are visible to admins and to the company's own account. */
    public function viewDocuments(User $user, Company $company): bool
    {
        return $user->isAdmin() || $company->user_id === $user->id;
    }
}
```

`app/Actions/RegisterCompany.php`:
```php
<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyRegistered;
use App\Services\JoinCodeGenerator;
use App\Services\MemberNumberGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class RegisterCompany
{
    public function __construct(
        private readonly MemberNumberGenerator $memberNumbers,
        private readonly JoinCodeGenerator $codes,
    ) {}

    /**
     * @param  array{company_name:string, company_type:string, first_name:string, last_name:string, email:string, phone?:?string, address:string, website?:?string, about?:?string, password:string}  $data
     */
    public function __invoke(array $data, UploadedFile $permit, UploadedFile $moa): User
    {
        $user = DB::transaction(function () use ($data, $permit, $moa) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => Role::Company,
                'member_no' => $this->memberNumbers->generate(Role::Company),
                'status' => AccountStatus::Active,
            ]);

            $company = Company::create([
                'user_id' => $user->id,
                'name' => $data['company_name'],
                'type' => $data['company_type'],
                'company_code' => $this->codes->generate('companies', 'company_code'),
                'address' => $data['address'],
                'website' => $data['website'] ?? null,
                'about' => $data['about'] ?? null,
                'approval_status' => ApprovalStatus::Pending,
            ]);

            $company->update([
                'permit_path' => $permit->storeAs("companies/{$company->id}", 'permit.pdf', 'local'),
                'moa_path' => $moa->storeAs("companies/{$company->id}", 'moa.pdf', 'local'),
            ]);

            return $user;
        });

        Notification::send(User::ofRole(Role::Admin)->active()->get(), new CompanyRegistered($user->company));

        return $user;
    }
}
```

`app/Http/Requests/Auth/RegisterCompanyRequest.php`:
```php
<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        $pdf = ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('wiis.uploads.max_pdf_kb')];

        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_type' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'about' => ['nullable', 'string', 'max:2000'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'permit' => $pdf,
            'moa' => $pdf,
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'permit.mimetypes' => 'The business permit must be a PDF file.',
            'moa.mimetypes' => 'The MOA must be a PDF file.',
            'terms.accepted' => 'Please accept the terms and conditions.',
        ];
    }
}
```

`app/Http/Controllers/Auth/RegisterCompanyController.php`:
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterCompanyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterCompanyController extends Controller
{
    public function create(): View
    {
        return view('auth.register-company');
    }

    public function store(RegisterCompanyRequest $request, RegisterCompany $registerCompany): RedirectResponse
    {
        $user = $registerCompany(
            $request->safe()->except(['permit', 'moa', 'terms', 'password_confirmation']),
            $request->file('permit'),
            $request->file('moa'),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.pending')->with('success', 'Registration received. We will email you once your documents are verified.');
    }
}
```

`resources/views/auth/register-company.blade.php`:
```blade
<x-layouts.auth title="Register your company">
    <h2 class="font-display text-2xl font-semibold">Partner with {{ config('wiis.institution.short') }}</h2>
    <p class="mt-1 text-sm text-stone-500">Register your company to post internships. Upload your business permit and MOA for verification.</p>

    <form method="POST" action="{{ route('register.company.store') }}" enctype="multipart/form-data" class="mt-8 space-y-6">
        @csrf
        <fieldset class="space-y-5">
            <legend class="text-xs font-semibold uppercase tracking-wider text-stone-500">Company</legend>
            <x-form.input name="company_name" label="Company name" required autofocus />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="company_type" label="Industry / type" placeholder="IT Services" required />
                <x-form.input name="website" label="Website" type="url" placeholder="https://" />
            </div>
            <x-form.input name="address" label="Address" required />
            <x-form.textarea name="about" label="About the company" rows="3" hint="Optional. Shown to interns browsing your postings." />
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="text-xs font-semibold uppercase tracking-wider text-stone-500">Contact person</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="first_name" label="First name" required />
                <x-form.input name="last_name" label="Last name" required />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="email" label="Work email" type="email" required autocomplete="email" />
                <x-form.input name="phone" label="Phone" type="tel" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="password" label="Password" type="password" required autocomplete="new-password" />
                <x-form.input name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
            </div>
        </fieldset>

        <fieldset class="space-y-5">
            <legend class="text-xs font-semibold uppercase tracking-wider text-stone-500">Verification documents</legend>
            <x-form.file name="permit" label="Business permit (PDF)" accept="application/pdf" hint="PDF up to {{ config('wiis.uploads.max_pdf_kb') / 1024 }} MB." required />
            <x-form.file name="moa" label="Memorandum of Agreement (PDF)" accept="application/pdf" hint="PDF up to {{ config('wiis.uploads.max_pdf_kb') / 1024 }} MB." required />
        </fieldset>

        <x-form.checkbox name="terms" label="I agree to the terms and conditions of the internship program." />
        <x-button class="w-full">Submit for verification</x-button>
    </form>

    <p class="mt-8 text-sm text-stone-600 dark:text-stone-400">Already registered? <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Sign in</a></p>
</x-layouts.auth>
```

Add to `routes/web.php` inside the `guest` group (with the matching `use`):
```php
    Route::get('register/company', [RegisterCompanyController::class, 'create'])->name('register.company');
    Route::post('register/company', [RegisterCompanyController::class, 'store'])->name('register.company.store');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=RegisterCompanyTest`
Expected: PASS (5 tests). Then `php artisan test` all green.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add company registration with permit and MOA verification"
```

---

### Task 14: Password reset by email link and change password

**Files:**
- Create: `app/Http/Controllers/Auth/ForgotPasswordController.php`, `app/Http/Controllers/Auth/ResetPasswordController.php`, `app/Http/Controllers/Auth/ChangePasswordController.php`, `app/Http/Requests/Auth/ChangePasswordRequest.php`, `resources/views/auth/forgot-password.blade.php`, `resources/views/auth/reset-password.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/PasswordResetTest.php`, `tests/Feature/Auth/ChangePasswordTest.php`

**Interfaces:**
- Produces: guest routes `password.request` (GET `/password/forgot`), `password.email` (POST), `password.reset` (GET `/password/reset/{token}`), `password.store` (POST `/password/reset`); auth route `password.update` (PUT `/password`). `ChangePasswordRequest` uses error bag `updatePassword` so the profile page (Task 17) can render it separately.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Auth/PasswordResetTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('shows the forgot password form', function () {
    $this->get('/password/forgot')->assertOk()->assertSee('Reset');
});

it('emails a reset link to a known address', function () {
    Notification::fake();
    $user = User::factory()->intern()->create();

    $this->post('/password/forgot', ['email' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('does not reveal whether an email exists', function () {
    Notification::fake();

    $this->post('/password/forgot', ['email' => 'nobody@example.com'])
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    Notification::fake();
    $user = User::factory()->intern()->create();
    $this->post('/password/forgot', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->get('/password/reset/'.$notification->token.'?email='.$user->email)->assertOk();

        $this->post('/password/reset', [
            'token' => $notification->token, 'email' => $user->email,
            'password' => 'New-Secret-123', 'password_confirmation' => 'New-Secret-123',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        return true;
    });

    expect(password_verify('New-Secret-123', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid token', function () {
    $user = User::factory()->intern()->create();

    $this->post('/password/reset', [
        'token' => 'bogus', 'email' => $user->email,
        'password' => 'New-Secret-123', 'password_confirmation' => 'New-Secret-123',
    ])->assertSessionHasErrors('email');
});
```

`tests/Feature/Auth/ChangePasswordTest.php`:
```php
<?php

use App\Models\User;

it('changes the password when the current one is correct', function () {
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->from('/profile')->put('/password', [
        'current_password' => 'password',
        'password' => 'New-Secret-123',
        'password_confirmation' => 'New-Secret-123',
    ])->assertRedirect('/profile')->assertSessionHasNoErrors();

    expect(password_verify('New-Secret-123', $user->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function () {
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->put('/password', [
        'current_password' => 'nope',
        'password' => 'New-Secret-123',
        'password_confirmation' => 'New-Secret-123',
    ])->assertSessionHasErrorsIn('updatePassword', ['current_password']);

    expect(password_verify('password', $user->fresh()->password))->toBeTrue();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="PasswordResetTest|ChangePasswordTest"`
Expected: FAIL — routes missing.

- [ ] **Step 3: Controllers, request, views, routes**

`app/Http/Controllers/Auth/ForgotPasswordController.php`:
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate(['email' => ['required', 'email']]);

        // Always respond the same way so the form cannot be used to enumerate accounts.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If that email is registered, a password reset link is on its way.');
    }
}
```

`app/Http/Controllers/Auth/ResetPasswordController.php`:
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('success', 'Your password has been reset. You can sign in now.');
    }
}
```

`app/Http/Requests/Auth/ChangePasswordRequest.php`:
```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    protected $errorBag = 'updatePassword';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
```

`app/Http/Controllers/Auth/ChangePasswordController.php`:
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;

class ChangePasswordController extends Controller
{
    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return back()->with('success', 'Your password has been updated.');
    }
}
```

`resources/views/auth/forgot-password.blade.php`:
```blade
<x-layouts.auth title="Forgot password">
    <h2 class="font-display text-2xl font-semibold">Reset your password</h2>
    <p class="mt-1 text-sm text-stone-500">Enter your email and we will send you a link to choose a new password.</p>

    @if (session('status'))
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <x-form.input name="email" label="Email address" type="email" required autofocus autocomplete="email" />
        <x-button class="w-full">Email reset link</x-button>
    </form>

    <p class="mt-8 text-sm text-stone-600 dark:text-stone-400"><a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Back to sign in</a></p>
</x-layouts.auth>
```

`resources/views/auth/reset-password.blade.php`:
```blade
<x-layouts.auth title="Choose a new password">
    <h2 class="font-display text-2xl font-semibold">Choose a new password</h2>

    <form method="POST" action="{{ route('password.store') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.input name="email" label="Email address" type="email" :value="$email" required autocomplete="email" />
        <x-form.input name="password" label="New password" type="password" required autofocus autocomplete="new-password" />
        <x-form.input name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
        <x-button class="w-full">Reset password</x-button>
    </form>
</x-layouts.auth>
```

Add to `routes/web.php` in the `guest` group (with the matching `use` lines):
```php
    Route::get('password/forgot', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('password/forgot', [ForgotPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('password/reset/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('password/reset', [ResetPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
```
and in the `['auth', 'account.usable']` group:
```php
    Route::put('password', [ChangePasswordController::class, 'update'])->name('password.update');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter="PasswordResetTest|ChangePasswordTest"`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add password reset by email and change password"
```

---

### Task 15: Notifications center and topbar bell

**Files:**
- Create: `app/Http/Controllers/NotificationController.php`, `resources/views/notifications/index.blade.php`
- Modify: `resources/views/components/notification-bell.blade.php`, `routes/web.php`
- Test: `tests/Feature/NotificationTest.php`

**Interfaces:**
- Produces: routes `notifications.index` (GET `/notifications`), `notifications.open` (GET `/notifications/{id}` → marks read, redirects to `data.url` or index), `notifications.read-all` (POST `/notifications/read-all`), `notifications.destroy` (DELETE `/notifications/{id}`). All queries are scoped through `$request->user()->notifications()`, so foreign ids 404.

- [ ] **Step 1: Write the failing test**

`tests/Feature/NotificationTest.php`:
```php
<?php

use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyRegistered;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->other = User::factory()->admin()->create();
    $company = Company::factory()->registered()->pending()->create(['name' => 'BlueOrbit Analytics']);
    $this->admin->notify(new CompanyRegistered($company));
    $this->other->notify(new CompanyRegistered(Company::factory()->registered()->create(['name' => 'Elsewhere Corp'])));
});

it('lists only my notifications', function () {
    $this->actingAs($this->admin)->get('/notifications')
        ->assertOk()
        ->assertSee('BlueOrbit Analytics')
        ->assertDontSee('Elsewhere Corp');
});

it('shows the unread count in the bell', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('1 unread');
});

it('opens a notification, marks it read and follows its link', function () {
    $notification = $this->admin->notifications()->first();

    $this->actingAs($this->admin)->get("/notifications/{$notification->id}")
        ->assertRedirect(route('admin.dashboard'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all as read', function () {
    $this->actingAs($this->admin)->post('/notifications/read-all')->assertRedirect();

    expect($this->admin->unreadNotifications()->count())->toBe(0)
        ->and($this->other->unreadNotifications()->count())->toBe(1);
});

it('deletes a notification', function () {
    $notification = $this->admin->notifications()->first();

    $this->actingAs($this->admin)->delete("/notifications/{$notification->id}")->assertRedirect();

    expect($this->admin->notifications()->count())->toBe(0);
});

it('returns 404 for another user\'s notification', function () {
    $foreign = $this->other->notifications()->first();

    $this->actingAs($this->admin)->get("/notifications/{$foreign->id}")->assertNotFound();
    $this->actingAs($this->admin)->delete("/notifications/{$foreign->id}")->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=NotificationTest`
Expected: FAIL — route not found.

- [ ] **Step 3: Controller, routes, views**

`app/Http/Controllers/NotificationController.php`:
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return back()->with('success', 'Notification removed.');
    }
}
```

Add to `routes/web.php` in the `['auth', 'account.usable']` group (with `use App\Http\Controllers\NotificationController;`):
```php
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
```

`resources/views/notifications/index.blade.php`:
```blade
<x-layouts.app title="Notifications">
    <x-page-header title="Notifications" subtitle="Updates about your applications, classes and requests.">
        <x-slot:actions>
            @if (auth()->user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<x-button variant="secondary" icon="heroicon-o-check">Mark all as read</x-button></form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        @forelse ($notifications as $notification)
            @php $unread = $notification->read_at === null; @endphp
            <div class="flex items-start gap-4 border-b border-stone-100 px-5 py-4 last:border-0 dark:border-stone-800 {{ $unread ? 'bg-brand-50/40 dark:bg-brand-900/10' : '' }}">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-stone-100 text-stone-500 dark:bg-stone-800">
                    <x-dynamic-component :component="$notification->data['icon'] ?? 'heroicon-o-bell'" class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <a href="{{ route('notifications.open', $notification->id) }}" class="font-medium hover:underline">{{ $notification->data['title'] }}</a>
                    <p class="text-sm text-stone-600 dark:text-stone-400">{{ $notification->data['body'] }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
                <div class="flex items-center gap-1">
                    @if ($unread)<span class="size-2 rounded-full bg-brand-600" aria-label="Unread"></span>@endif
                    <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}">
                        @csrf @method('DELETE')
                        <button class="btn-ghost p-1.5" aria-label="Delete notification"><x-heroicon-o-trash class="size-4" /></button>
                    </form>
                </div>
            </div>
        @empty
            <x-empty-state title="You're all caught up" description="New activity on your account will show up here." icon="heroicon-o-bell-slash" />
        @endforelse
    </x-card>

    {{ $notifications->links() }}
</x-layouts.app>
```

`resources/views/components/notification-bell.blade.php` (replaces the Task 9 baseline):
```blade
@php
    $user = auth()->user();
    $unread = $user?->unreadNotifications()->count() ?? 0;
    $latest = $user?->unreadNotifications()->latest()->limit(5)->get() ?? collect();
@endphp
@if ($user && Route::has('notifications.index'))
    <x-dropdown align="right" width="w-80">
        <x-slot:trigger>
            <button type="button" class="btn-ghost relative p-2" aria-label="Notifications{{ $unread ? ", $unread unread" : '' }}">
                <x-heroicon-o-bell class="size-5" />
                @if ($unread)
                    <span class="absolute right-1.5 top-1.5 grid min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-4 text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                @endif
            </button>
        </x-slot:trigger>
        <div class="flex items-center justify-between px-3 py-2">
            <p class="text-sm font-semibold">Notifications</p>
            <span class="text-xs text-stone-500">{{ $unread }} unread</span>
        </div>
        @forelse ($latest as $notification)
            <a href="{{ route('notifications.open', $notification->id) }}" class="block rounded-xl px-3 py-2 hover:bg-stone-100 dark:hover:bg-stone-800">
                <p class="truncate text-sm font-medium">{{ $notification->data['title'] }}</p>
                <p class="line-clamp-2 text-xs text-stone-500">{{ $notification->data['body'] }}</p>
            </a>
        @empty
            <p class="px-3 py-4 text-center text-sm text-stone-500">No unread notifications</p>
        @endforelse
        <a href="{{ route('notifications.index') }}" class="mt-1 block rounded-xl border-t border-stone-100 px-3 py-2 text-center text-sm font-medium text-brand-700 hover:bg-stone-100 dark:border-stone-800 dark:text-brand-300 dark:hover:bg-stone-800">View all</a>
    </x-dropdown>
@endif
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=NotificationTest`
Expected: PASS (6 tests). Run `php artisan test` — the Navigation badge now also renders because `notifications.index` exists.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add notifications center and topbar bell"
```

---

### Task 16: Private file delivery

**Files:**
- Create: `app/Support/PrivateFiles.php`, `app/Http/Controllers/FileController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/FileAccessTest.php`

**Interfaces:**
- Produces: route `files.show` (GET `/files/{kind}/{id}`). `PrivateFiles::registry(): array<string, array{0: class-string, 1: string, 2: string}>` mapping kind → `[model, path attribute, policy ability]`; Phase 1 registers `company-permit` and `company-moa`. Later phases append kinds (resume, endorsement, dtr, certificate, submission, …) and the matching policy abilities.

- [ ] **Step 1: Write the failing test**

`tests/Feature/FileAccessTest.php`:
```php
<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->company = Company::factory()->registered()->create();
    Storage::disk('local')->put("companies/{$this->company->id}/permit.pdf", '%PDF-1.4 fake');
    $this->company->update(['permit_path' => "companies/{$this->company->id}/permit.pdf", 'moa_path' => null]);
});

it('lets admins download a company permit', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('files.show', ['company-permit', $this->company->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('lets the owning company download its own permit', function () {
    $this->actingAs($this->company->user)
        ->get(route('files.show', ['company-permit', $this->company->id]))
        ->assertOk();
});

it('forbids other users', function () {
    $this->actingAs(User::factory()->company()->create())
        ->get(route('files.show', ['company-permit', $this->company->id]))
        ->assertForbidden();

    $this->actingAs(User::factory()->intern()->create())
        ->get(route('files.show', ['company-permit', $this->company->id]))
        ->assertForbidden();
});

it('returns 404 for unknown kinds, missing records and missing files', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/files/not-a-kind/1')->assertNotFound();
    $this->actingAs($admin)->get(route('files.show', ['company-permit', 999]))->assertNotFound();
    $this->actingAs($admin)->get(route('files.show', ['company-moa', $this->company->id]))->assertNotFound();
});

it('requires authentication', function () {
    $this->get(route('files.show', ['company-permit', $this->company->id]))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=FileAccessTest`
Expected: FAIL — route `files.show` not defined.

- [ ] **Step 3: Registry, controller, route**

`app/Support/PrivateFiles.php`:
```php
<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

class PrivateFiles
{
    /**
     * kind => [model class, attribute holding the path, policy ability checked against the record].
     *
     * @return array<string, array{0: class-string<Model>, 1: string, 2: string}>
     */
    public static function registry(): array
    {
        return [
            'company-permit' => [Company::class, 'permit_path', 'viewDocuments'],
            'company-moa' => [Company::class, 'moa_path', 'viewDocuments'],
        ];
    }

    /** @return array{0: class-string<Model>, 1: string, 2: string} */
    public static function resolve(string $kind): array
    {
        return static::registry()[$kind] ?? abort(404);
    }
}
```

`app/Http/Controllers/FileController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Support\PrivateFiles;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    /** Streams a private upload inline after a per-record policy check. */
    public function show(string $kind, int $id): StreamedResponse
    {
        [$model, $attribute, $ability] = PrivateFiles::resolve($kind);

        $record = $model::query()->findOrFail($id);

        Gate::authorize($ability, $record);

        $path = $record->{$attribute};

        abort_if(blank($path) || ! Storage::disk('local')->exists($path), 404);

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';

        return Storage::disk('local')->response($path, "{$kind}-{$id}.{$extension}");
    }
}
```

Add to `routes/web.php` in the `['auth', 'account.usable']` group (with `use App\Http\Controllers\FileController;`):
```php
    Route::get('files/{kind}/{id}', [FileController::class, 'show'])->where('id', '[0-9]+')->name('files.show');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=FileAccessTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: serve private uploads through an authorized file controller"
```

---

### Task 17: Profile page with role-specific fields and avatar

**Files:**
- Create: `app/Http/Controllers/ProfileController.php`, `app/Http/Controllers/AvatarController.php`, `app/Http/Requests/UpdateProfileRequest.php`, `app/Http/Requests/StoreAvatarRequest.php`, `resources/views/profile/edit.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/ProfileTest.php`

**Interfaces:**
- Produces: routes `profile.edit` (GET `/profile`), `profile.update` (PUT `/profile`), `profile.avatar.store` (POST `/profile/avatar`), `profile.avatar.destroy` (DELETE `/profile/avatar`). Avatars are stored on the `public` disk under `avatars/`; `users.avatar_path` holds the relative path.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ProfileTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('shows the profile page with the change password form', function () {
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->get('/profile')->assertOk()->assertSee($user->member_no)->assertSee('Current password');
});

it('updates shared and intern fields', function () {
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->put('/profile', [
        'first_name' => 'Juan', 'middle_name' => 'S.', 'last_name' => 'Dela Cruz', 'phone' => '09170000000',
        'gender' => 'Male', 'birthdate' => '2003-05-10', 'present_address' => 'Novaliches, QC',
        'permanent_address' => 'Novaliches, QC', 'about' => 'Aspiring web developer.',
    ])->assertRedirect('/profile')->assertSessionHas('success');

    $user->refresh();
    expect($user->name)->toBe('Juan Dela Cruz')
        ->and($user->internProfile->gender)->toBe('Male')
        ->and($user->internProfile->birthdate->toDateString())->toBe('2003-05-10')
        ->and($user->internProfile->about)->toBe('Aspiring web developer.');
});

it('updates company fields for company users', function () {
    $user = User::factory()->company()->create();

    $this->actingAs($user)->put('/profile', [
        'first_name' => 'Marco', 'last_name' => 'Villanueva',
        'company_name' => 'TechNova Solutions Inc.', 'company_type' => 'IT Services',
        'website' => 'https://technova.example', 'address' => 'Pasig City', 'about' => 'Consultancy.',
    ])->assertRedirect('/profile');

    expect($user->company->fresh()->name)->toBe('TechNova Solutions Inc.')
        ->and($user->company->fresh()->website)->toBe('https://technova.example');
});

it('rejects a future birthdate and an invalid website', function () {
    $intern = User::factory()->intern()->create();
    $this->actingAs($intern)->put('/profile', ['first_name' => 'A', 'last_name' => 'B', 'birthdate' => now()->addDay()->toDateString()])
        ->assertSessionHasErrors('birthdate');

    $company = User::factory()->company()->create();
    $this->actingAs($company)->put('/profile', ['first_name' => 'A', 'last_name' => 'B', 'company_name' => 'X', 'company_type' => 'Y', 'address' => 'Z', 'website' => 'not a url'])
        ->assertSessionHasErrors('website');
});

it('uploads, replaces and removes an avatar', function () {
    Storage::fake('public');
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 300, 300)])->assertRedirect('/profile');
    $first = $user->fresh()->avatar_path;
    expect($first)->toStartWith('avatars/');
    Storage::disk('public')->assertExists($first);

    $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me2.jpg', 300, 300)]);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($user->fresh()->avatar_path);

    $this->actingAs($user)->delete('/profile/avatar')->assertRedirect('/profile');
    expect($user->fresh()->avatar_path)->toBeNull();
});

it('rejects oversized or non-image avatars', function () {
    Storage::fake('public');
    $user = User::factory()->intern()->create();

    $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('big.png', config('wiis.uploads.max_avatar_kb') + 1, 'image/png')])
        ->assertSessionHasErrors('avatar');
    $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('avatar');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=ProfileTest`
Expected: FAIL — routes missing.

- [ ] **Step 3: Requests, controllers, view, routes**

`app/Http/Requests/UpdateProfileRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];

        if ($this->user()->isIntern()) {
            $rules += [
                'gender' => ['nullable', 'string', 'max:20'],
                'birthdate' => ['nullable', 'date', 'before:today'],
                'present_address' => ['nullable', 'string', 'max:255'],
                'permanent_address' => ['nullable', 'string', 'max:255'],
                'about' => ['nullable', 'string', 'max:2000'],
            ];
        }

        if ($this->user()->isCompany()) {
            $rules += [
                'company_name' => ['required', 'string', 'max:255'],
                'company_type' => ['required', 'string', 'max:100'],
                'website' => ['nullable', 'url', 'max:255'],
                'address' => ['required', 'string', 'max:255'],
                'about' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return $rules;
    }
}
```

`app/Http/Requests/StoreAvatarRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('wiis.uploads.max_avatar_kb')],
        ];
    }
}
```

`app/Http/Controllers/ProfileController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->load(['internProfile.classSection', 'company']);

        return view('profile.edit', ['user' => $user]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            $user->update([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
            ]);

            if ($user->isIntern()) {
                $user->internProfile?->update([
                    'gender' => $data['gender'] ?? null,
                    'birthdate' => $data['birthdate'] ?? null,
                    'present_address' => $data['present_address'] ?? null,
                    'permanent_address' => $data['permanent_address'] ?? null,
                    'about' => $data['about'] ?? null,
                ]);
            }

            if ($user->isCompany()) {
                $user->company?->update([
                    'name' => $data['company_name'],
                    'type' => $data['company_type'],
                    'website' => $data['website'] ?? null,
                    'address' => $data['address'],
                    'about' => $data['about'] ?? null,
                ]);
            }
        });

        return redirect()->route('profile.edit')->with('success', 'Profile updated.');
    }
}
```

`app/Http/Controllers/AvatarController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAvatarRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AvatarController extends Controller
{
    public function store(StoreAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();

        $this->deleteExisting($user->avatar_path);

        $user->update(['avatar_path' => $request->file('avatar')->store('avatars', 'public')]);

        return redirect()->route('profile.edit')->with('success', 'Profile photo updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->deleteExisting($user->avatar_path);
        $user->update(['avatar_path' => null]);

        return redirect()->route('profile.edit')->with('success', 'Profile photo removed.');
    }

    private function deleteExisting(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
```

`resources/views/profile/edit.blade.php`:
```blade
<x-layouts.app title="Profile">
    <x-page-header title="My profile" subtitle="Keep your contact details current. Your email and member number are managed by the system." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-card title="Profile photo">
                <div class="flex flex-col items-center gap-4 text-center">
                    <x-avatar :user="$user" size="xl" />
                    <div>
                        <p class="font-semibold">{{ $user->full_name }}</p>
                        <p class="text-sm text-stone-500">{{ $user->member_no }} · {{ $user->role->label() }}</p>
                    </div>
                    <form method="POST" action="{{ route('profile.avatar.store') }}" enctype="multipart/form-data" class="w-full space-y-3">
                        @csrf
                        <x-form.file name="avatar" accept="image/png,image/jpeg,image/webp" hint="PNG, JPG or WebP up to {{ config('wiis.uploads.max_avatar_kb') / 1024 }} MB." />
                        <x-button variant="secondary" class="w-full">Upload photo</x-button>
                    </form>
                    @if ($user->avatar_path)
                        <form method="POST" action="{{ route('profile.avatar.destroy') }}">
                            @csrf @method('DELETE')
                            <button class="text-sm font-medium text-rose-600 hover:underline">Remove photo</button>
                        </form>
                    @endif
                </div>
            </x-card>

            <x-card title="Account">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-stone-500">Email</dt><dd class="font-medium">{{ $user->email }}</dd></div>
                    <div><dt class="text-stone-500">Member number</dt><dd class="font-mono">{{ $user->member_no }}</dd></div>
                    <div><dt class="text-stone-500">Status</dt><dd><x-badge :status="$user->status" /></dd></div>
                    @if ($user->isIntern() && $user->internProfile)
                        <div><dt class="text-stone-500">Student number</dt><dd class="font-mono">{{ $user->internProfile->student_number }}</dd></div>
                        <div><dt class="text-stone-500">Class</dt><dd>{{ $user->internProfile->classSection?->display_name ?? 'Not enrolled' }}</dd></div>
                    @endif
                </dl>
            </x-card>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf @method('PUT')
                <x-card title="Personal details">
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-form.input name="first_name" label="First name" :value="$user->first_name" required />
                        <x-form.input name="middle_name" label="Middle name" :value="$user->middle_name" />
                        <x-form.input name="last_name" label="Last name" :value="$user->last_name" required />
                    </div>
                    <x-form.input name="phone" label="Phone" type="tel" :value="$user->phone" class="mt-5 sm:max-w-xs" />

                    @if ($user->isIntern() && $user->internProfile)
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <x-form.select name="gender" label="Gender" :options="['Male' => 'Male', 'Female' => 'Female', 'Prefer not to say' => 'Prefer not to say']" :value="$user->internProfile->gender" placeholder="Select" />
                            <x-form.input name="birthdate" label="Birthdate" type="date" :value="$user->internProfile->birthdate?->toDateString()" />
                            <x-form.input name="present_address" label="Present address" :value="$user->internProfile->present_address" />
                            <x-form.input name="permanent_address" label="Permanent address" :value="$user->internProfile->permanent_address" />
                        </div>
                        <x-form.textarea name="about" label="About me" :value="$user->internProfile->about" rows="4" class="mt-5" hint="Shown to companies reviewing your applications." />
                    @endif

                    @if ($user->isCompany() && $user->company)
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <x-form.input name="company_name" label="Company name" :value="$user->company->name" required />
                            <x-form.input name="company_type" label="Industry / type" :value="$user->company->type" required />
                            <x-form.input name="website" label="Website" type="url" :value="$user->company->website" />
                            <x-form.input name="address" label="Address" :value="$user->company->address" required />
                        </div>
                        <x-form.textarea name="about" label="About the company" :value="$user->company->about" rows="4" class="mt-5" />
                    @endif

                    <div class="mt-6 flex justify-end"><x-button>Save changes</x-button></div>
                </x-card>
            </form>

            <form method="POST" action="{{ route('password.update') }}">
                @csrf @method('PUT')
                <x-card title="Change password">
                    @if ($errors->updatePassword->any())
                        <ul class="mb-4 list-inside list-disc rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
                            @foreach ($errors->updatePassword->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    @endif
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-form.input name="current_password" label="Current password" type="password" autocomplete="current-password" required />
                        <x-form.input name="password" label="New password" type="password" autocomplete="new-password" required />
                        <x-form.input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
                    </div>
                    <div class="mt-6 flex justify-end"><x-button variant="secondary">Update password</x-button></div>
                </x-card>
            </form>
        </div>
    </div>
</x-layouts.app>
```

Add to `routes/web.php` in the `['auth', 'account.usable']` group (with the `use` lines):
```php
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/avatar', [AvatarController::class, 'store'])->name('profile.avatar.store');
    Route::delete('profile/avatar', [AvatarController::class, 'destroy'])->name('profile.avatar.destroy');
```

Also run `php artisan storage:link` locally so avatars are served from `public/storage`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=ProfileTest`
Expected: PASS (6 tests). Then `php artisan test` all green.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add profile editing with role-specific fields and avatar upload"
```

---

### Task 18: CI workflow, README and phase wrap-up

**Files:**
- Create: `.github/workflows/ci.yml`
- Modify: `README.md`, `CLAUDE.md` (commands section if anything changed), `.gitignore` (ensure `public/build` and `public/storage` are ignored; Laravel's default already lists them)

- [ ] **Step 1: CI workflow**

`.github/workflows/ci.yml`:
```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, intl, sqlite3, pdo_sqlite, gd, zip, fileinfo
          coverage: none

      - uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: npm

      - name: Install PHP dependencies
        run: composer install --no-interaction --prefer-dist --no-progress

      - name: Prepare environment
        run: |
          cp .env.example .env
          php artisan key:generate

      - name: Build front-end assets
        run: |
          npm ci
          npm run build

      - name: Code style
        run: vendor/bin/pint --test

      - name: Tests
        run: php artisan test
```

- [ ] **Step 2: README**

Replace `README.md` with:
```markdown
# WIIS — Web Based Internship Information System

A Laravel 12 rebuild of my university capstone project: a platform where **interns**, **practicum advisers**,
**partner companies** and the placement office **admin** manage the whole on-the-job-training (OJT) lifecycle —
postings and applications, interviews, placement, daily time records and hour tracking, class requirements,
document requests and completion certificates.

The original plain-PHP version (2023) is preserved on the [`archive/v1`](../../tree/archive/v1) branch.
This branch is a from-scratch redesign focused on clean architecture, security and a modern UI.

> **Status:** Phase 1 (foundation) complete — schema, auth for all roles, dashboards, notifications, profiles.
> See the [roadmap](#roadmap).

## Features

| Portal | What it covers |
|---|---|
| **Admin** | Approve company registrations (permit + MOA review), manage interns/advisers/companies, partner (COS) companies, class sections, Excel imports, activity dashboards |
| **Adviser** | Google-Classroom-style classes: join codes, announcement stream with comments, document folders with lock/late flags, submission review, COS application and DTR approval |
| **Company** | Internship postings, applicant pipeline with interview scheduling, intern monitoring with hour thresholds, DTR approval, document requests, certificates, placement history |
| **Intern** | Browse and apply, track applications, join a company by code, submit DTRs, watch progress toward the required hours, request documents, receive certificates |

Business rules are configuration, not magic numbers: required OJT hours (486), certificate eligibility (250 h at one
company), institution branding and support contacts live in `config/wiis.php`.

## Tech stack

Laravel 12 · PHP 8.2 · Blade + Tailwind CSS 4 + Alpine.js (Vite) · Pest 3 · SQLite (dev/test) or MySQL · Laravel Pint · GitHub Actions

## Getting started

```bash
git clone https://github.com/wilfredo-domanico-jr/internship-information-system.git
cd internship-information-system
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # SQLite is the default; MySQL works by editing DB_* in .env
php artisan migrate:fresh --seed
php artisan storage:link
composer dev                           # serves the app, queue worker, logs and Vite together
```

Open http://127.0.0.1:8000.

### Demo accounts

All demo passwords are `password`.

| Role | Email | Notes |
|---|---|---|
| Admin | admin@wiis.test | Placement office |
| Adviser | adviser@wiis.test | Advises class `CC101 · SBIT-4C` (join code `SBIT4C26`) |
| Company | company@wiis.test | TechNova Solutions Inc., approved, company code `TECHNOVA` |
| Company | pending@wiis.test | Awaiting admin verification |
| Intern | intern@wiis.test | Placed at TechNova with 300 approved hours |
| Intern | intern2@wiis.test | Enrolled in the class, not yet placed |

## Architecture notes

- **Roles** — one `users` table with a `role` enum; role data lives in `intern_profiles` and `companies`.
  `companies.user_id` is nullable: partner (COS) companies exist without a login.
- **Layers** — thin controllers per portal (`App\Http\Controllers\{Admin,Adviser,Company,Intern}`) →
  Form Requests → single-purpose `App\Actions` → Eloquent. Statuses are PHP backed enums; authorization is Policies.
- **Hours ledger** — only the `ApproveDtr` action adds hours, inside a transaction, on the pending→approved transition.
- **Private files** — uploads live on the private disk and are streamed by `FileController` after a policy check.
- **Notifications** — Laravel database notifications surface in the topbar bell.
- **UI** — a small Blade component library (`resources/views/components`) on Tailwind 4 tokens, with dark mode.

## Testing

```bash
php artisan test          # Pest, in-memory SQLite
vendor/bin/pint --test    # code style
```

## Roadmap

- [x] Phase 1 — Foundation: schema, auth, design system, dashboards, notifications, files, profiles, CI
- [ ] Phase 2 — Admin portal: user management, company approvals, Excel imports, charts
- [ ] Phase 3 — Classroom: classes, stream, folders, submissions
- [ ] Phase 4 — Internship core: postings, applications, interviews, placements, DTRs, certificates
- [ ] Phase 5 — COS partner-company track
- [ ] Phase 6 — Public site, demo mode, polish

## What changed from the legacy app

The 2023 version stored plaintext passwords, interpolated request input into SQL, served every upload from the web
root, ran state changes over GET links without CSRF protection, and kept notifications inside the interview table.
The rebuild replaces all of that with hashed credentials, Eloquent and Form Requests, private authorized downloads,
POST/DELETE with CSRF, real foreign keys, and one normalized table per concept.
```

- [ ] **Step 3: Final verification**

Run:
```bash
php artisan test
vendor/bin/pint --test
npm run build
php artisan migrate:fresh --seed
php artisan route:list --except-vendor
```
Expected: all tests pass; Pint clean; build succeeds; seed succeeds; the route list shows exactly the routes named in Tasks 10–17.

Then start `php artisan serve` and walk through in a browser (desktop and ~390 px wide, light and dark): log in as each demo account, open the dashboard, notifications, profile; register a new intern with `SBIT4C26`; register a company with two small PDFs and land on the pending page; sign out.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore: add CI workflow and project README"
```

---

## Spec coverage check (Phase 1 scope)

| Spec item (Phase 1) | Task |
|---|---|
| Strip laravel/ui, Tailwind 4 + Alpine + Vite, Pest, Pint | 1 |
| `config/wiis.php` + `.env.example` keys | 2 |
| Enums with `label()`/`badgeColor()` | 3 |
| All migrations from the data-model table, models, factories | 4, 5, 6 |
| Notifications table | 6 |
| `MemberNumberGenerator`, `JoinCodeGenerator`, `OjtHoursService` | 7 |
| Demo seeders and accounts | 8 |
| Design tokens, layouts, component library, dark mode | 9 |
| `EnsureRole` middleware, four route groups, role dashboards, guest/user redirects | 10 |
| Login (disabled blocked, pending companies held), logout | 10, 11 |
| Intern sign-up with class join code | 12 |
| Company sign-up with permit + MOA, admin notification, pending page | 10, 13 |
| Forgot/reset password by email link, change password | 14 |
| Notifications bell + center | 9, 15 |
| `FileController` + `files.show` + policy-based access | 13 (policy), 16 |
| Profile page with avatar (public disk, 1 MB) | 17 |
| GitHub Actions CI, README first draft | 18 |

Deferred by design: `layouts/public` and `DEMO_MODE` login buttons (Phase 6), `ExcelImportService` and phpspreadsheet (Phase 2), `HtmlSanitizer`/Trix (Phase 3), Chart.js (Phase 2), `DomainRuleViolation` exception (first needed by Phase 3/4 actions), custom error pages (Phase 6).
