# WIIS Phase 2 — Admin Portal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the placement-office admin a complete portal: dashboard charts, searchable lists and detail pages for interns, advisers, companies and partner (COS) companies, company approvals with document review, disable/reactivate with an archive, class and department management, and Excel imports with downloadable templates and emailed credentials.

**Architecture:** Same layering as Phase 1: thin invokable/resource controllers under `App\Http\Controllers\Admin` → Form Requests → single-purpose `App\Actions` → models. Admin-wide access is enforced by the existing `role:admin` route group (no per-record policy is needed because the admin may see every record; the one exception, documents, keeps using `CompanyPolicy::viewDocuments`). Reporting math lives in `App\Services\AdminDashboardStats`; spreadsheet I/O lives in `App\Services\Excel\*`; each import is an Action returning an `ImportResult` and is all-or-nothing. Business-rule refusals throw `App\Exceptions\DomainRuleViolation`, rendered once as a flash error.

**Tech Stack:** Laravel 12.62, PHP 8.2, Pest 3, Blade + Tailwind 4 + Alpine (existing design system), Chart.js (npm), `phpoffice/phpspreadsheet` (composer), database queue + `MAIL_MAILER=log` for credential emails.

**Spec:** `docs/superpowers/specs/2026-10-03-wiis-laravel-rebuild-design.md` — section "Phase 2 — Admin portal" plus the business rules under "Legacy feature inventory" (imports, approvals, disable/reactivate).

## Global Constraints

- Phase 1 is the foundation (HEAD `57d41d5`, 140 tests green). Do not rename any existing route, component prop, enum value or model method. Existing route names: `admin.dashboard`, `files.show` (`kind` ∈ `company-permit`, `company-moa`), `notifications.*`, `profile.*`.
- Layers: controllers validate → call an Action → redirect/render. No model writes with raw request input; every `create`/`update` takes an explicit array. Every Action gets a unit test under `tests/Unit/Actions/`.
- Every status column is a `string(20)` cast to an `App\Enums\*` enum. Thresholds and branding come from `config/wiis.php` (`wiis.hours.required` 486, `wiis.hours.certificate_min` 250); never literal in `app/` or Blade.
- Uploaded documents stay on the private `local` disk, served only via `FileController`. Partner-company logos (public images) go to the `public` disk under `partner-logos/`, max `config('wiis.uploads.max_avatar_kb')` (1024 KB), `image|mimes:jpg,jpeg,png,webp`.
- Strict Eloquent mode is on outside production: eager-load every relation used inside a Blade loop; never lazy-load on a collection.
- Emails: `App\Notifications\AccountCredentials` is queued (`ShouldQueue`); tests run the queue synchronously (`QUEUE_CONNECTION=sync` in phpunit.xml) and fake notifications where they only assert dispatch.
- Imports are all-or-nothing: validate every row first; if any row fails, create nothing and return every row error.
- Route names follow `admin.<resource>.<action>`; nav items are added through `App\Support\Navigation::for()` and guarded by `Route::has()`.
- Commits: conventional prefixes (`feat:`, `fix:`, `refactor:`, `test:`, `chore:`), short descriptive messages, directly on `main`, **never any Claude attribution**. Run `vendor/bin/pint --dirty` before each commit.
- Shell: Windows Git Bash; long heredocs fail, so write files with the editor/Write tool. Work only inside `D:\Programming_Application\xampp-7.4.1\htdocs\internship-information-system`.

## Review Focus

1. **Spreadsheet headers with stray spaces or capitals** (`"Email "`, `"First Name"`) must still map to the expected columns. Pinned in Task 11 (`SpreadsheetReader` normalizes headers).
2. **Two rows in the same import sharing an email or student number** must fail the whole import and name both rows; nothing may be created. Pinned in Task 12.
3. **Double-submitting Approve on an already-approved company** must be a harmless no-op with a notice, not a second notification or a changed `approved_at`. Pinned in Task 6.
4. **An admin disabling themselves or another admin** must be refused with a message, never locking the office out. Pinned in Task 7.
5. **Deleting a partner company that already has placements or COS applications** must be refused with a friendly message, not a 500 from the restrict FK. Pinned in Task 8.

## File Structure (what Phase 2 creates)

```
app/
  Actions/{CreateAdviser,ApproveCompany,RejectCompany,DisableUser,ReactivateUser,
           CreatePartnerCompany,UpdatePartnerCompany,DeletePartnerCompany,
           ImportInterns,ImportAdvisers,ImportClasses}.php
  Exceptions/DomainRuleViolation.php
  Http/Controllers/Admin/{InternController,AdviserController,CompanyController,CompanyApprovalController,
           UserStatusController,ArchiveController,PartnerCompanyController,ClassSectionController,
           DepartmentController,ImportController}.php
  Http/Requests/Admin/{StoreAdviserRequest,RejectCompanyRequest,PartnerCompanyRequest,DepartmentRequest,ImportRequest}.php
  Notifications/{AccountCredentials,CompanyApproved,CompanyRejected}.php
  Services/AdminDashboardStats.php
  Services/Excel/{SpreadsheetReader,TemplateBuilder}.php
  Support/{ImportResult,ImportColumns}.php
  Support/Navigation.php (modified)
bootstrap/app.php (exception rendering)
resources/js/app.js (+ Chart.js Alpine component)
resources/views/components/{chart,search-form,pagination,tabs,detail-list}.blade.php
resources/views/vendor/pagination/wiis.blade.php
resources/views/admin/dashboard.blade.php (modified)
resources/views/admin/interns/{index,show}.blade.php
resources/views/admin/advisers/{index,create,show}.blade.php
resources/views/admin/companies/{index,pending,show}.blade.php
resources/views/admin/archive/index.blade.php
resources/views/admin/partners/{index,create,edit,_form}.blade.php
resources/views/admin/classes/{index,show}.blade.php
resources/views/admin/departments/index.blade.php
resources/views/admin/imports/index.blade.php
routes/web.php (admin group grows)
tests/Feature/Admin/*.php, tests/Unit/Actions/*.php, tests/Unit/Services/*.php
```

Task order: 1 shell/components → 2 dashboard charts → 3 interns → 4 advisers (+ credentials mail) → 5 companies list/show → 6 approvals → 7 disable/reactivate/archive → 8 partner companies → 9 classes → 10 departments → 11 Excel infra + templates → 12 import interns → 13 import advisers + classes → 14 docs/wrap-up.

---

### Task 1: Admin navigation, Chart.js, and list/detail components

**Files:**
- Modify: `app/Support/Navigation.php`, `resources/js/app.js`, `package.json` (add `chart.js`)
- Create: `resources/views/components/chart.blade.php`, `resources/views/components/search-form.blade.php`, `resources/views/components/pagination.blade.php`, `resources/views/vendor/pagination/wiis.blade.php`, `resources/views/components/tabs.blade.php`, `resources/views/components/detail-list.blade.php`
- Test: `tests/Feature/Admin/NavigationTest.php`, `tests/Feature/AdminComponentsTest.php`

**Interfaces:**
- Produces: `Navigation::for(User)` returns the admin items below (each guarded by `Route::has`), in this order after Dashboard: Interns (`admin.interns.index`, active `admin.interns.*`, icon `heroicon-o-academic-cap`), Advisers (`admin.advisers.*`, `heroicon-o-user-group`), Companies (`admin.companies.*`, `heroicon-o-building-office-2`, badge = pending registered companies count or null), Partner companies (`admin.partners.*`, `heroicon-o-building-storefront`), Classes (`admin.classes.*`, `heroicon-o-rectangle-group`), Departments (`admin.departments.*`, `heroicon-o-squares-2x2`), Imports (`admin.imports.*`, `heroicon-o-arrow-up-tray`), Archive (`admin.archive.*`, `heroicon-o-archive-box`), then Notifications.
- Components: `<x-chart type="doughnut|bar" :labels="[]" :datasets="[['label'=>..., 'data'=>[...]]]" :height="220" />` (renders a `<canvas>` with `data-chart` JSON and an Alpine `x-data="chart"`); `<x-search-form :action="route(...)" placeholder="...">[extra selects]</x-search-form>` (GET form, keeps `q`, has Search + Reset); `<x-pagination :paginator="$items" />`; `<x-tabs :tabs="[['label'=>'All','route'=>'admin.companies.index','active'=>'admin.companies.index','count'=>null], ...]" />`; `<x-detail-list :items="['Label' => 'value', ...]" />`.
- JS: `Alpine.data('chart', () => ({ init() { new Chart(this.$el, JSON.parse(this.$el.dataset.chart)) } }))`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Admin/NavigationTest.php`:
```php
<?php

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

it('lists the admin sections once their routes exist', function () {
    // Register throwaway routes so Route::has() passes without the later tasks.
    foreach (['interns', 'advisers', 'companies', 'partners', 'classes', 'departments', 'imports', 'archive'] as $r) {
        Route::get("/_t/{$r}", fn () => '')->name("admin.{$r}.index");
    }
    $admin = User::factory()->admin()->create();

    $labels = collect(Navigation::for($admin))->pluck('label')->all();

    expect($labels)->toBe(['Dashboard', 'Interns', 'Advisers', 'Companies', 'Partner companies', 'Classes', 'Departments', 'Imports', 'Archive', 'Notifications']);
});

it('does not show admin sections to other roles', function () {
    $intern = User::factory()->intern()->create();

    expect(collect(Navigation::for($intern))->pluck('label')->all())->toBe(['Dashboard', 'Notifications']);
});
```

`tests/Feature/AdminComponentsTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

it('renders a chart canvas with its config', function () {
    $html = Blade::render('<x-chart type="doughnut" :labels="[\'Placed\', \'Unplaced\']" :datasets="[[\'data\' => [3, 5]]]" />');

    expect($html)->toContain('<canvas')->toContain('x-data="chart"')->toContain('&quot;labels&quot;:[&quot;Placed&quot;,&quot;Unplaced&quot;]');
});

it('renders a search form that keeps the current query', function () {
    request()->merge(['q' => 'juan']);
    $html = Blade::render('<x-search-form action="/x" placeholder="Find"><select name="status"></select></x-search-form>');

    expect($html)->toContain('method="GET"')->toContain('value="juan"')->toContain('name="status"')->toContain('Reset');
});

it('renders tabs with counts and marks the active one', function () {
    Route::get('/_t/a', fn () => '')->name('t.a');
    Route::get('/_t/b', fn () => '')->name('t.b');
    $html = Blade::render('<x-tabs :tabs="[[\'label\' => \'All\', \'route\' => \'t.a\', \'active\' => \'t.a\'], [\'label\' => \'Pending\', \'route\' => \'t.b\', \'active\' => \'t.b\', \'count\' => 4]]" />');

    expect($html)->toContain('All')->toContain('Pending')->toContain('>4<');
});

it('renders a detail list and a styled paginator', function () {
    User::factory()->intern()->count(3)->create();
    $html = Blade::render('<x-detail-list :items="[\'Email\' => \'a@b.c\', \'Phone\' => null]" />');
    expect($html)->toContain('Email')->toContain('a@b.c')->toContain('—');

    $paginator = User::query()->paginate(2);
    $html = Blade::render('<x-pagination :paginator="$p" />', ['p' => $paginator]);
    expect($html)->toContain('Next')->toContain('page=2');
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="NavigationTest|AdminComponentsTest"`
Expected: FAIL — labels array mismatch / components not found.

- [ ] **Step 3: Install Chart.js and register the Alpine component**

Run: `npm install chart.js@^4`

`resources/js/app.js` (full file):
```js
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

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

Alpine.data('chart', () => ({
    instance: null,
    init() {
        const config = JSON.parse(this.$el.dataset.chart);
        config.options = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } } },
            ...(config.options ?? {}),
        };
        this.instance = new Chart(this.$el, config);
    },
    destroy() {
        this.instance?.destroy();
    },
}));

Alpine.start();
```

- [ ] **Step 4: Navigation**

`app/Support/Navigation.php`:
```php
<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class Navigation
{
    /**
     * Sidebar items for the user's portal.
     *
     * @return array<int, array{label: string, route: string, icon: string, active: string, badge?: int|string|null}>
     */
    public static function for(User $user): array
    {
        $portal = $user->role->value;

        $items = [
            ['label' => 'Dashboard', 'route' => "{$portal}.dashboard", 'icon' => 'heroicon-o-home', 'active' => "{$portal}.dashboard"],
            ...static::portalItems($user),
            ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'heroicon-o-bell', 'active' => 'notifications.*',
                'badge' => $user->unreadNotifications()->count() ?: null],
        ];

        return array_values(array_filter($items, fn (array $item) => Route::has($item['route'])));
    }

    /** @return array<int, array{label: string, route: string, icon: string, active: string, badge?: int|string|null}> */
    private static function portalItems(User $user): array
    {
        if ($user->isAdmin()) {
            return [
                ['label' => 'Interns', 'route' => 'admin.interns.index', 'icon' => 'heroicon-o-academic-cap', 'active' => 'admin.interns.*'],
                ['label' => 'Advisers', 'route' => 'admin.advisers.index', 'icon' => 'heroicon-o-user-group', 'active' => 'admin.advisers.*'],
                ['label' => 'Companies', 'route' => 'admin.companies.index', 'icon' => 'heroicon-o-building-office-2', 'active' => 'admin.companies.*',
                    'badge' => Company::registered()->pending()->count() ?: null],
                ['label' => 'Partner companies', 'route' => 'admin.partners.index', 'icon' => 'heroicon-o-building-storefront', 'active' => 'admin.partners.*'],
                ['label' => 'Classes', 'route' => 'admin.classes.index', 'icon' => 'heroicon-o-rectangle-group', 'active' => 'admin.classes.*'],
                ['label' => 'Departments', 'route' => 'admin.departments.index', 'icon' => 'heroicon-o-squares-2x2', 'active' => 'admin.departments.*'],
                ['label' => 'Imports', 'route' => 'admin.imports.index', 'icon' => 'heroicon-o-arrow-up-tray', 'active' => 'admin.imports.*'],
                ['label' => 'Archive', 'route' => 'admin.archive.index', 'icon' => 'heroicon-o-archive-box', 'active' => 'admin.archive.*'],
            ];
        }

        return [];
    }
}
```

- [ ] **Step 5: Components**

`resources/views/components/chart.blade.php`:
```blade
@props(['type' => 'doughnut', 'labels' => [], 'datasets' => [], 'height' => 220, 'options' => []])
@php
    $palette = ['#187a68', '#f59e0b', '#38bdf8', '#f43f5e', '#8b5cf6', '#84cc16'];
    $datasets = collect($datasets)->values()->map(function (array $set, int $i) use ($palette, $type) {
        $set['backgroundColor'] = $set['backgroundColor'] ?? ($type === 'bar' ? $palette[$i % count($palette)] : array_slice($palette, 0, max(1, count($set['data'] ?? []))));
        $set['borderWidth'] = $set['borderWidth'] ?? 0;
        $set['borderRadius'] = $set['borderRadius'] ?? ($type === 'bar' ? 6 : 0);
        return $set;
    })->all();
    $config = ['type' => $type, 'data' => ['labels' => array_values($labels), 'datasets' => $datasets], 'options' => (object) $options];
@endphp
<div {{ $attributes->merge(['class' => 'relative w-full']) }} style="height: {{ (int) $height }}px">
    <canvas x-data="chart" data-chart="{{ json_encode($config) }}" role="img" aria-label="{{ $type }} chart"></canvas>
</div>
```

`resources/views/components/search-form.blade.php`:
```blade
@props(['action', 'placeholder' => 'Search…'])
<form method="GET" action="{{ $action }}" {{ $attributes->merge(['class' => 'flex flex-wrap items-end gap-3']) }}>
    <label class="sr-only" for="q">Search</label>
    <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ $placeholder }}" class="input sm:max-w-xs">
    {{ $slot }}
    <x-button type="submit" variant="secondary" icon="heroicon-o-magnifying-glass">Search</x-button>
    @if (request()->query())
        <a href="{{ $action }}" class="text-sm font-medium text-stone-500 hover:text-stone-800 dark:hover:text-stone-200">Reset</a>
    @endif
</form>
```

`resources/views/components/pagination.blade.php`:
```blade
@props(['paginator'])
@if ($paginator->hasPages())
    <div {{ $attributes->merge(['class' => 'pt-2']) }}>{{ $paginator->withQueryString()->links('vendor.pagination.wiis') }}</div>
@endif
```

`resources/views/vendor/pagination/wiis.blade.php`:
```blade
<nav role="navigation" aria-label="Pagination" class="flex items-center justify-between gap-4 text-sm">
    <p class="text-stone-500">Showing <span class="font-medium">{{ $paginator->firstItem() }}</span>–<span class="font-medium">{{ $paginator->lastItem() }}</span> of <span class="font-medium">{{ $paginator->total() }}</span></p>
    <div class="flex items-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="btn-ghost cursor-not-allowed px-3 py-1.5 opacity-50">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-ghost px-3 py-1.5">Previous</a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))<span class="px-2 text-stone-400">{{ $element }}</span>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="rounded-lg bg-brand-600 px-3 py-1.5 font-semibold text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="btn-ghost px-3 py-1.5">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-ghost px-3 py-1.5">Next</a>
        @else
            <span class="btn-ghost cursor-not-allowed px-3 py-1.5 opacity-50">Next</span>
        @endif
    </div>
</nav>
```

`resources/views/components/tabs.blade.php`:
```blade
@props(['tabs' => []])
<nav {{ $attributes->merge(['class' => 'flex gap-1 overflow-x-auto border-b border-stone-200 dark:border-stone-800']) }} aria-label="Tabs">
    @foreach ($tabs as $tab)
        @php $active = request()->routeIs($tab['active'] ?? $tab['route']); @endphp
        <a href="{{ route($tab['route'], $tab['params'] ?? []) }}"
           @class(['-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition',
                   'border-brand-600 text-brand-700 dark:text-brand-300' => $active,
                   'border-transparent text-stone-500 hover:border-stone-300 hover:text-stone-800 dark:hover:text-stone-200' => ! $active])
           @if ($active) aria-current="page" @endif>
            {{ $tab['label'] }}
            @if (isset($tab['count']) && $tab['count'] !== null)
                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-700 dark:bg-stone-800 dark:text-stone-200">{{ $tab['count'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
```

`resources/views/components/detail-list.blade.php`:
```blade
@props(['items' => []])
<dl {{ $attributes->merge(['class' => 'grid gap-x-6 gap-y-4 sm:grid-cols-2']) }}>
    @foreach ($items as $label => $value)
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ $label }}</dt>
            <dd class="mt-1 text-sm">{{ filled($value) ? $value : '—' }}</dd>
        </div>
    @endforeach
</dl>
```

- [ ] **Step 6: Run tests and build**

Run: `php artisan test --filter="NavigationTest|AdminComponentsTest" && npm run build`
Expected: PASS (6 tests); build succeeds and the JS bundle grows (Chart.js).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add admin navigation, chart and list components"
```

---

### Task 2: Admin dashboard charts

**Files:**
- Create: `app/Services/AdminDashboardStats.php`, `tests/Unit/Services/AdminDashboardStatsTest.php`
- Modify: `app/Http/Controllers/Admin/DashboardController.php`, `resources/views/admin/dashboard.blade.php`
- Test: `tests/Feature/Admin/DashboardTest.php`

**Interfaces:**
- Produces: `AdminDashboardStats::counts(): array{interns:int, advisers:int, companies:int, pending_companies:int}`, `placementSplit(): array{placed:int, unplaced:int}` (active interns with/without an active placement), `sectioningSplit(): array{with_class:int, without_class:int}`, `accountStatusByRole(): array<string, array{active:int, disabled:int}>` keyed `Intern|Adviser|Company`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Services/AdminDashboardStatsTest.php`:
```php
<?php

use App\Models\ClassSection;
use App\Models\Company;
use App\Models\Placement;
use App\Models\User;
use App\Services\AdminDashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stats = new AdminDashboardStats;
    $section = ClassSection::factory()->create(); // creates one adviser
    $placed = User::factory()->intern()->create();
    // Partner company so no extra company *user* is created by the placement factory.
    Placement::factory()->for($placed, 'intern')->for(Company::factory()->partner()->create())->create();
    $placed->internProfile()->update(['class_section_id' => $section->id]);
    User::factory()->intern()->create();                 // unplaced, no class
    User::factory()->intern()->disabled()->create();     // excluded from active counts
    User::factory()->company()->create();                // approved registered
    $pending = User::factory()->company()->create();
    $pending->company->update(['approval_status' => \App\Enums\ApprovalStatus::Pending]);
});

it('counts the headline numbers', function () {
    // partner companies (no login) are not counted as verified companies
    expect($this->stats->counts())->toBe(['interns' => 2, 'advisers' => 1, 'companies' => 1, 'pending_companies' => 1]);
});

it('splits active interns by placement and by class', function () {
    expect($this->stats->placementSplit())->toBe(['placed' => 1, 'unplaced' => 1])
        ->and($this->stats->sectioningSplit())->toBe(['with_class' => 1, 'without_class' => 1]);
});

it('splits accounts by status per role', function () {
    expect($this->stats->accountStatusByRole())->toBe([
        'Intern' => ['active' => 2, 'disabled' => 1],
        'Adviser' => ['active' => 1, 'disabled' => 0],
        'Company' => ['active' => 2, 'disabled' => 0],
    ]);
});
```

`tests/Feature/Admin/DashboardTest.php`:
```php
<?php

use App\Models\User;

it('renders the three charts with live data', function () {
    User::factory()->intern()->count(2)->create();

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Placement')
        ->assertSee('Class enrollment')
        ->assertSee('Account status')
        ->assertSee('x-data="chart"', false)
        ->assertSee('&quot;Unplaced&quot;', false);
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="AdminDashboardStatsTest|Admin\\\\DashboardTest"`
Expected: FAIL — class not found / chart markup missing.

- [ ] **Step 3: Service**

`app/Services/AdminDashboardStats.php`:
```php
<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\User;

class AdminDashboardStats
{
    /** @return array{interns:int, advisers:int, companies:int, pending_companies:int} */
    public function counts(): array
    {
        return [
            'interns' => User::ofRole(Role::Intern)->active()->count(),
            'advisers' => User::ofRole(Role::Adviser)->active()->count(),
            'companies' => Company::registered()->approved()->count(),
            'pending_companies' => Company::registered()->pending()->count(),
        ];
    }

    /** @return array{placed:int, unplaced:int} */
    public function placementSplit(): array
    {
        $interns = User::ofRole(Role::Intern)->active();
        $placed = (clone $interns)->whereHas('activePlacement')->count();

        return ['placed' => $placed, 'unplaced' => (clone $interns)->count() - $placed];
    }

    /** @return array{with_class:int, without_class:int} */
    public function sectioningSplit(): array
    {
        $interns = User::ofRole(Role::Intern)->active();
        $withClass = (clone $interns)->whereHas('internProfile', fn ($q) => $q->whereNotNull('class_section_id'))->count();

        return ['with_class' => $withClass, 'without_class' => (clone $interns)->count() - $withClass];
    }

    /** @return array<string, array{active:int, disabled:int}> */
    public function accountStatusByRole(): array
    {
        $rows = User::query()
            ->selectRaw('role, status, count(*) as total')
            ->whereIn('role', [Role::Intern->value, Role::Adviser->value, Role::Company->value])
            ->groupBy('role', 'status')
            ->get();

        $result = [];
        foreach ([Role::Intern, Role::Adviser, Role::Company] as $role) {
            $result[$role->label()] = [
                'active' => (int) $rows->first(fn ($r) => $r->role === $role && $r->status === AccountStatus::Active)?->total,
                'disabled' => (int) $rows->first(fn ($r) => $r->role === $role && $r->status === AccountStatus::Disabled)?->total,
            ];
        }

        return $result;
    }
}
```

Note: `role`/`status` on the grouped rows are cast to enums because the query runs through the `User` model.

- [ ] **Step 4: Controller and view**

`app/Http/Controllers/Admin/DashboardController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminDashboardStats;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardStats $stats): View
    {
        $byRole = $stats->accountStatusByRole();

        return view('admin.dashboard', [
            'stats' => $stats->counts(),
            'placement' => $stats->placementSplit(),
            'sectioning' => $stats->sectioningSplit(),
            'statusLabels' => array_keys($byRole),
            'statusActive' => array_column($byRole, 'active'),
            'statusDisabled' => array_column($byRole, 'disabled'),
            'recentUsers' => User::latest()->limit(6)->get(),
        ]);
    }
}
```

`resources/views/admin/dashboard.blade.php` — insert this block between the stat cards grid and the "Recent accounts" card (keep everything else as is):
```blade
    <div class="grid gap-4 lg:grid-cols-3">
        <x-card title="Placement" subtitle="Active interns with and without a company">
            <x-chart type="doughnut" :labels="['Placed', 'Unplaced']" :datasets="[['data' => [$placement['placed'], $placement['unplaced']]]]" />
        </x-card>
        <x-card title="Class enrollment" subtitle="Active interns in a class section">
            <x-chart type="doughnut" :labels="['In a class', 'No class yet']" :datasets="[['data' => [$sectioning['with_class'], $sectioning['without_class']]]]" />
        </x-card>
        <x-card title="Account status" subtitle="Active vs disabled per role">
            <x-chart type="bar" :labels="$statusLabels" :datasets="[['label' => 'Active', 'data' => $statusActive], ['label' => 'Disabled', 'data' => $statusDisabled]]" :options="['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]]" />
        </x-card>
    </div>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="AdminDashboardStatsTest|DashboardTest|RoleAccessTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add placement, enrollment and account-status charts to the admin dashboard"
```

---

### Task 3: Interns list and detail page

**Files:**
- Create: `app/Http/Controllers/Admin/InternController.php`, `resources/views/admin/interns/index.blade.php`, `resources/views/admin/interns/show.blade.php`
- Modify: `routes/web.php` (admin group)
- Test: `tests/Feature/Admin/InternsTest.php`

**Interfaces:**
- Produces: routes `admin.interns.index` (GET `/admin/interns`, query `q`, `class` (class_section_id), `status` (`active|disabled`), `placement` (`placed|unplaced`)), `admin.interns.show` (GET `/admin/interns/{user}`; 404 unless the user is an intern). Later tasks add a "Danger zone" card to `show` (Task 7) — leave a clear spot at the bottom.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/InternsTest.php`:
```php
<?php

use App\Models\ClassSection;
use App\Models\Company;
use App\Models\Placement;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('lists interns with class, placement and hours', function () {
    $section = ClassSection::factory()->create(['course_code' => 'CC101', 'section' => 'SBIT-4C']);
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $intern->internProfile()->update(['class_section_id' => $section->id, 'student_number' => '21-0001', 'total_hours' => 120]);
    Placement::factory()->for($intern, 'intern')->for(Company::factory()->partner()->create(['name' => 'City Hall']))->create();
    User::factory()->adviser()->create(['first_name' => 'NotAnIntern']);

    $this->actingAs($this->admin)->get(route('admin.interns.index'))
        ->assertOk()
        ->assertSee('Maria Santos')->assertSee('21-0001')->assertSee('CC101')->assertSee('City Hall')->assertSee('120')
        ->assertDontSee('NotAnIntern');
});

it('searches by student number and filters by class and placement', function () {
    $section = ClassSection::factory()->create();
    $a = User::factory()->intern()->create(['first_name' => 'Alpha']);
    $a->internProfile()->update(['student_number' => '19-0842', 'class_section_id' => $section->id]);
    $b = User::factory()->intern()->create(['first_name' => 'Bravo']);
    Placement::factory()->for($b, 'intern')->for(Company::factory()->partner()->create())->create();

    $this->actingAs($this->admin)->get(route('admin.interns.index', ['q' => '0842']))->assertSee('Alpha')->assertDontSee('Bravo');
    $this->actingAs($this->admin)->get(route('admin.interns.index', ['class' => $section->id]))->assertSee('Alpha')->assertDontSee('Bravo');
    $this->actingAs($this->admin)->get(route('admin.interns.index', ['placement' => 'placed']))->assertSee('Bravo')->assertDontSee('Alpha');
    $this->actingAs($this->admin)->get(route('admin.interns.index', ['placement' => 'unplaced']))->assertSee('Alpha')->assertDontSee('Bravo');
});

it('shows an intern with progress and placement history', function () {
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $intern->internProfile()->update(['total_hours' => 243]);
    Placement::factory()->for($intern, 'intern')->for(Company::factory()->partner()->create(['name' => 'Old Place']))->ended()->create(['hours_rendered' => 100]);
    Placement::factory()->for($intern, 'intern')->for(Company::factory()->partner()->create(['name' => 'New Place']))->create(['hours_rendered' => 143]);

    $this->actingAs($this->admin)->get(route('admin.interns.show', $intern))
        ->assertOk()->assertSee('Maria Santos')->assertSee('50%')->assertSee('Old Place')->assertSee('New Place')->assertSee('243');
});

it('returns 404 for a non-intern user and 403 for non-admins', function () {
    $adviser = User::factory()->adviser()->create();
    $this->actingAs($this->admin)->get(route('admin.interns.show', $adviser))->assertNotFound();
    $this->actingAs($adviser)->get(route('admin.interns.index'))->assertForbidden();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=InternsTest`
Expected: FAIL — route `admin.interns.index` not defined.

- [ ] **Step 3: Controller and routes**

`app/Http/Controllers/Admin/InternController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\User;
use App\Services\OjtHoursService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $status = AccountStatus::tryFrom((string) $request->query('status'));
        $placement = $request->query('placement');

        $interns = User::ofRole(Role::Intern)
            ->with(['internProfile.classSection', 'activePlacement.company'])
            ->when($q !== '', fn (Builder $query) => $query->where(function (Builder $w) use ($q) {
                $w->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('member_no', 'like', "%{$q}%")
                    ->orWhereHas('internProfile', fn (Builder $p) => $p->where('student_number', 'like', "%{$q}%"));
            }))
            ->when($request->integer('class'), fn (Builder $query, int $class) => $query->whereHas('internProfile', fn (Builder $p) => $p->where('class_section_id', $class)))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($placement === 'placed', fn (Builder $query) => $query->whereHas('activePlacement'))
            ->when($placement === 'unplaced', fn (Builder $query) => $query->whereDoesntHave('activePlacement'))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(20);

        return view('admin.interns.index', [
            'interns' => $interns,
            'classes' => ClassSection::active()->orderBy('course_code')->orderBy('section')->get(['id', 'course_code', 'section']),
        ]);
    }

    public function show(User $user, OjtHoursService $hours): View
    {
        abort_unless($user->isIntern(), 404);

        $user->load(['internProfile.classSection.adviser', 'activePlacement.company', 'placements.company']);
        $total = $user->internProfile?->total_hours ?? 0;

        return view('admin.interns.show', [
            'intern' => $user,
            'profile' => $user->internProfile,
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

Add inside the `admin` route group in `routes/web.php`:
```php
        Route::get('interns', [Admin\InternController::class, 'index'])->name('interns.index');
        Route::get('interns/{user}', [Admin\InternController::class, 'show'])->name('interns.show');
```

- [ ] **Step 4: Views**

`resources/views/admin/interns/index.blade.php`:
```blade
<x-layouts.app title="Interns">
    <x-page-header title="Interns" subtitle="Every student account, with class, placement and rendered hours." />

    <x-card :padding="false">
        <div class="border-b border-stone-200/80 p-4 dark:border-stone-800">
            <x-search-form :action="route('admin.interns.index')" placeholder="Name, email, student or member no.">
                <select name="class" class="input sm:w-56" aria-label="Class">
                    <option value="">All classes</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(request('class') == $class->id)>{{ $class->course_code }} · {{ $class->section }}</option>
                    @endforeach
                </select>
                <select name="placement" class="input sm:w-40" aria-label="Placement">
                    <option value="">Any placement</option>
                    <option value="placed" @selected(request('placement') === 'placed')>Placed</option>
                    <option value="unplaced" @selected(request('placement') === 'unplaced')>Unplaced</option>
                </select>
                <select name="status" class="input sm:w-36" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach (\App\Enums\AccountStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-search-form>
        </div>
        <x-table>
            <x-slot:head><th>Intern</th><th>Class</th><th>Placement</th><th>Hours</th><th>Status</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($interns as $intern)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$intern" size="sm" />
                            <div>
                                <p class="font-medium">{{ $intern->name }}</p>
                                <p class="text-xs text-stone-500">{{ $intern->internProfile?->student_number }} · {{ $intern->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td>{{ $intern->internProfile?->classSection?->display_name ?? '—' }}</td>
                    <td>{{ $intern->activePlacement?->company?->name ?? 'Unplaced' }}</td>
                    <td class="tabular-nums">{{ $intern->internProfile?->total_hours ?? 0 }} h</td>
                    <td><x-badge :status="$intern->status" /></td>
                    <td class="text-right"><a href="{{ route('admin.interns.show', $intern) }}" class="btn-ghost px-3 py-1.5">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state title="No interns match" description="Try a different search or clear the filters." /></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$interns" /></div>
    </x-card>
</x-layouts.app>
```

`resources/views/admin/interns/show.blade.php`:
```blade
<x-layouts.app :title="$intern->name">
    <x-page-header :title="$intern->full_name" :subtitle="$intern->member_no.' · '.($profile?->student_number ?? 'No student number')" :breadcrumbs="['Interns' => route('admin.interns.index'), $intern->name => null]">
        <x-slot:actions><x-badge :status="$intern->status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="OJT progress">
            <div class="flex flex-col items-center gap-3 text-center">
                <x-progress-ring :percent="$progress['percent']" :color="$progress['tier']->badgeColor()" label="of required hours" :size="140" />
                <p class="font-display text-2xl font-semibold tabular-nums">{{ $progress['hours'] }} <span class="text-sm font-normal text-stone-500">/ {{ $progress['required'] }} h</span></p>
                <p class="text-sm text-stone-500">{{ $progress['remaining'] }} hours remaining</p>
                <x-badge :status="$progress['tier']" />
            </div>
        </x-card>

        <x-card title="Details" class="lg:col-span-2">
            <x-detail-list :items="[
                'Email' => $intern->email,
                'Phone' => $intern->phone,
                'Gender' => $profile?->gender,
                'Birthdate' => $profile?->birthdate?->format('M j, Y'),
                'Class' => $profile?->classSection?->display_name,
                'Adviser' => $profile?->classSection?->adviser?->name,
                'School year' => $profile?->school_year,
                'Present address' => $profile?->present_address,
                'Joined' => $intern->created_at->format('M j, Y'),
                'Last sign-in' => $intern->last_login_at?->diffForHumans(),
            ]" />
        </x-card>

        <x-card title="Placements" subtitle="Current and past companies" class="lg:col-span-3" :padding="false">
            <x-table>
                <x-slot:head><th>Company</th><th>From</th><th>To</th><th>Hours</th><th>Status</th></x-slot:head>
                @forelse ($intern->placements as $placement)
                    <tr>
                        <td class="font-medium">{{ $placement->company->name }}</td>
                        <td>{{ $placement->started_at->format('M j, Y') }}</td>
                        <td>{{ $placement->ended_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="tabular-nums">{{ $placement->hours_rendered }} h</td>
                        <td>@if ($placement->isActive())<x-badge color="green">Active</x-badge>@else<x-badge color="gray">Ended</x-badge>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state title="Not placed yet" icon="heroicon-o-briefcase" class="py-8" /></td></tr>
                @endforelse
            </x-table>
        </x-card>

        {{-- Task 7 adds the account-status "Danger zone" card here --}}
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=InternsTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add admin intern list and detail pages"
```

---

### Task 4: Advisers list, detail and manual creation with emailed credentials

**Files:**
- Create: `app/Notifications/AccountCredentials.php`, `app/Actions/CreateAdviser.php`, `app/Http/Requests/Admin/StoreAdviserRequest.php`, `app/Http/Controllers/Admin/AdviserController.php`, `resources/views/admin/advisers/index.blade.php`, `resources/views/admin/advisers/create.blade.php`, `resources/views/admin/advisers/show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Unit/Actions/CreateAdviserTest.php`, `tests/Feature/Admin/AdvisersTest.php`

**Interfaces:**
- Produces: `AccountCredentials extends Notification implements ShouldQueue` — `__construct(public string $plainPassword)`, `via` = `['mail']`, mail subject `Your {wiis.name} account` with the email, the temporary password and a Sign in button. Reused by Tasks 12–13 imports. `CreateAdviser::__invoke(array{first_name, middle_name?, last_name, email, phone?}): User` — generates `ADV-` member number and a 12-char password (`Str::password(12, symbols: false)`), creates an active adviser, sends `AccountCredentials`. Routes: `admin.advisers.index` (GET, `q`, `status`), `admin.advisers.create` (GET), `admin.advisers.store` (POST), `admin.advisers.show` (GET `/admin/advisers/{user}`, 404 unless adviser).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/CreateAdviserTest.php`:
```php
<?php

use App\Actions\CreateAdviser;
use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Notifications\AccountCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('creates an active adviser and emails a working temporary password', function () {
    Notification::fake();

    $user = app(CreateAdviser::class)(['first_name' => 'Elsie', 'last_name' => 'Isip', 'email' => 'elsie@example.com', 'phone' => '09170000000']);

    expect($user->role)->toBe(Role::Adviser)
        ->and($user->status)->toBe(AccountStatus::Active)
        ->and($user->member_no)->toStartWith('ADV-');

    Notification::assertSentTo($user, AccountCredentials::class, function (AccountCredentials $n) use ($user) {
        expect(strlen($n->plainPassword))->toBe(12)
            ->and(password_verify($n->plainPassword, $user->password))->toBeTrue();
        $mail = $n->toMail($user);
        expect($mail->subject)->toContain(config('wiis.name'))
            ->and(implode("\n", $mail->introLines))->toContain($user->email)->toContain($n->plainPassword);

        return true;
    });
});
```

`tests/Feature/Admin/AdvisersTest.php`:
```php
<?php

use App\Models\ClassSection;
use App\Models\InternProfile;
use App\Models\User;
use App\Notifications\AccountCredentials;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('lists advisers with their class count', function () {
    $adviser = User::factory()->adviser()->create(['first_name' => 'Elsie', 'last_name' => 'Isip']);
    ClassSection::factory()->count(2)->for($adviser, 'adviser')->create();
    User::factory()->intern()->create(['first_name' => 'NotAnAdviser']);

    $this->actingAs($this->admin)->get(route('admin.advisers.index'))
        ->assertOk()->assertSee('Elsie Isip')->assertSee('2')->assertDontSee('NotAnAdviser');
    $this->actingAs($this->admin)->get(route('admin.advisers.index', ['q' => 'isip']))->assertSee('Elsie Isip');
    $this->actingAs($this->admin)->get(route('admin.advisers.index', ['q' => 'zzz']))->assertDontSee('Elsie Isip');
});

it('creates an adviser from the form and emails credentials', function () {
    Notification::fake();

    $this->actingAs($this->admin)->get(route('admin.advisers.create'))->assertOk()->assertSee('Add adviser');

    $response = $this->actingAs($this->admin)->post(route('admin.advisers.store'), [
        'first_name' => 'Elsie', 'last_name' => 'Isip', 'email' => ' Elsie@Example.com ', 'phone' => '09170000000',
    ]);

    $adviser = User::where('email', 'elsie@example.com')->firstOrFail();
    $response->assertRedirect(route('admin.advisers.show', $adviser))->assertSessionHas('success');
    Notification::assertSentTo($adviser, AccountCredentials::class);
});

it('rejects duplicate emails', function () {
    User::factory()->intern()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->admin)->post(route('admin.advisers.store'), ['first_name' => 'A', 'last_name' => 'B', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');
});

it('shows an adviser with classes and their interns', function () {
    $adviser = User::factory()->adviser()->create(['first_name' => 'Elsie']);
    $section = ClassSection::factory()->for($adviser, 'adviser')->create(['course_code' => 'CC101', 'section' => 'SBIT-4C']);
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $intern->internProfile()->update(['class_section_id' => $section->id]);

    $this->actingAs($this->admin)->get(route('admin.advisers.show', $adviser))
        ->assertOk()->assertSee('Elsie')->assertSee('CC101')->assertSee('Maria Santos');
    $this->actingAs($this->admin)->get(route('admin.advisers.show', $intern))->assertNotFound();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="CreateAdviserTest|AdvisersTest"`
Expected: FAIL — classes/routes missing.

- [ ] **Step 3: Notification, action, request**

`app/Notifications/AccountCredentials.php`:
```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCredentials extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $plainPassword) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your '.config('wiis.name').' account')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('An account has been created for you on '.config('wiis.name').' by the '.config('wiis.institution.office').'.')
            ->line("Email: {$notifiable->email}")
            ->line("Temporary password: {$this->plainPassword}")
            ->action('Sign in', route('login'))
            ->line('Please change your password after your first sign-in.');
    }
}
```

`app/Actions/CreateAdviser.php`:
```php
<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\AccountCredentials;
use App\Services\MemberNumberGenerator;
use Illuminate\Support\Str;

class CreateAdviser
{
    public function __construct(private readonly MemberNumberGenerator $memberNumbers) {}

    /**
     * @param  array{first_name:string, middle_name?:?string, last_name:string, email:string, phone?:?string}  $data
     */
    public function __invoke(array $data): User
    {
        $password = Str::password(12, symbols: false);

        $user = User::create([
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $password,
            'role' => Role::Adviser,
            'member_no' => $this->memberNumbers->generate(Role::Adviser),
            'status' => AccountStatus::Active,
        ]);

        $user->notify(new AccountCredentials($password));

        return $user;
    }
}
```

`app/Http/Requests/Admin/StoreAdviserRequest.php`:
```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAdviserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, views**

`app/Http/Controllers/Admin/AdviserController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateAdviser;
use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdviserRequest;
use App\Models\InternProfile;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdviserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $status = AccountStatus::tryFrom((string) $request->query('status'));

        $advisers = User::ofRole(Role::Adviser)
            ->withCount(['advisedClasses' => fn (Builder $c) => $c->where('status', \App\Enums\ClassStatus::Active)])
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")->orWhere('member_no', 'like', "%{$q}%")))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(20);

        return view('admin.advisers.index', ['advisers' => $advisers]);
    }

    public function create(): View
    {
        return view('admin.advisers.create');
    }

    public function store(StoreAdviserRequest $request, CreateAdviser $createAdviser): RedirectResponse
    {
        $adviser = $createAdviser($request->validated());

        return redirect()->route('admin.advisers.show', $adviser)
            ->with('success', "{$adviser->name} was added. Sign-in details were emailed to {$adviser->email}.");
    }

    public function show(User $user): View
    {
        abort_unless($user->isAdviser(), 404);

        $classes = $user->advisedClasses()->withCount('internProfiles')->orderByDesc('school_year')->orderBy('section')->get();
        $interns = InternProfile::query()
            ->whereIn('class_section_id', $classes->pluck('id'))
            ->with(['user', 'classSection'])
            ->get()
            ->sortBy(fn (InternProfile $p) => $p->user->last_name);

        return view('admin.advisers.show', ['adviser' => $user, 'classes' => $classes, 'interns' => $interns]);
    }
}
```

Routes (admin group):
```php
        Route::get('advisers', [Admin\AdviserController::class, 'index'])->name('advisers.index');
        Route::get('advisers/create', [Admin\AdviserController::class, 'create'])->name('advisers.create');
        Route::post('advisers', [Admin\AdviserController::class, 'store'])->name('advisers.store');
        Route::get('advisers/{user}', [Admin\AdviserController::class, 'show'])->name('advisers.show');
```

`resources/views/admin/advisers/index.blade.php`:
```blade
<x-layouts.app title="Advisers">
    <x-page-header title="Advisers" subtitle="Practicum advisers and the classes they handle.">
        <x-slot:actions><x-button :href="route('admin.advisers.create')" icon="heroicon-o-plus">Add adviser</x-button></x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="border-b border-stone-200/80 p-4 dark:border-stone-800">
            <x-search-form :action="route('admin.advisers.index')" placeholder="Name, email or member no.">
                <select name="status" class="input sm:w-36" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach (\App\Enums\AccountStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-search-form>
        </div>
        <x-table>
            <x-slot:head><th>Adviser</th><th>Member no.</th><th>Active classes</th><th>Status</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($advisers as $adviser)
                <tr>
                    <td><div class="flex items-center gap-3"><x-avatar :user="$adviser" size="sm" /><div><p class="font-medium">{{ $adviser->name }}</p><p class="text-xs text-stone-500">{{ $adviser->email }}</p></div></div></td>
                    <td class="font-mono text-xs">{{ $adviser->member_no }}</td>
                    <td class="tabular-nums">{{ $adviser->advised_classes_count }}</td>
                    <td><x-badge :status="$adviser->status" /></td>
                    <td class="text-right"><a href="{{ route('admin.advisers.show', $adviser) }}" class="btn-ghost px-3 py-1.5">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state title="No advisers match" /></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$advisers" /></div>
    </x-card>
</x-layouts.app>
```

`resources/views/admin/advisers/create.blade.php`:
```blade
<x-layouts.app title="Add adviser">
    <x-page-header title="Add adviser" subtitle="A temporary password is emailed to the adviser." :breadcrumbs="['Advisers' => route('admin.advisers.index'), 'Add' => null]" />

    <form method="POST" action="{{ route('admin.advisers.store') }}" class="max-w-2xl">
        @csrf
        <x-card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="first_name" label="First name" required autofocus />
                <x-form.input name="last_name" label="Last name" required />
                <x-form.input name="middle_name" label="Middle name" />
                <x-form.input name="phone" label="Phone" type="tel" />
            </div>
            <x-form.input name="email" label="Work email" type="email" required class="mt-5" />
            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="secondary" :href="route('admin.advisers.index')">Cancel</x-button>
                <x-button>Create and email credentials</x-button>
            </div>
        </x-card>
    </form>
</x-layouts.app>
```

`resources/views/admin/advisers/show.blade.php`:
```blade
<x-layouts.app :title="$adviser->name">
    <x-page-header :title="$adviser->full_name" :subtitle="$adviser->member_no.' · '.$adviser->email" :breadcrumbs="['Advisers' => route('admin.advisers.index'), $adviser->name => null]">
        <x-slot:actions><x-badge :status="$adviser->status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Details">
            <x-detail-list class="sm:grid-cols-1" :items="['Email' => $adviser->email, 'Phone' => $adviser->phone, 'Joined' => $adviser->created_at->format('M j, Y'), 'Last sign-in' => $adviser->last_login_at?->diffForHumans()]" />
        </x-card>

        <x-card title="Classes" class="lg:col-span-2" :padding="false">
            <x-table>
                <x-slot:head><th>Class</th><th>Schedule</th><th>School year</th><th>Interns</th><th>Status</th></x-slot:head>
                @forelse ($classes as $class)
                    <tr>
                        <td class="font-medium">{{ $class->display_name }}<p class="text-xs font-normal text-stone-500">{{ $class->subject }}</p></td>
                        <td>{{ $class->schedule_label }}</td>
                        <td>{{ $class->school_year }}</td>
                        <td class="tabular-nums">{{ $class->intern_profiles_count }}</td>
                        <td><x-badge :status="$class->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state title="No classes yet" class="py-8" /></td></tr>
                @endforelse
            </x-table>
        </x-card>

        <x-card title="Interns across these classes" class="lg:col-span-3" :padding="false">
            <x-table>
                <x-slot:head><th>Intern</th><th>Student no.</th><th>Class</th><th>Hours</th></x-slot:head>
                @forelse ($interns as $profile)
                    <tr>
                        <td><a href="{{ route('admin.interns.show', $profile->user) }}" class="font-medium hover:underline">{{ $profile->user->name }}</a></td>
                        <td class="font-mono text-xs">{{ $profile->student_number }}</td>
                        <td>{{ $profile->classSection?->display_name }}</td>
                        <td class="tabular-nums">{{ $profile->total_hours }} h</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state title="No interns enrolled" class="py-8" /></td></tr>
                @endforelse
            </x-table>
        </x-card>

        {{-- Task 7 adds the account-status "Danger zone" card here --}}
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="CreateAdviserTest|AdvisersTest"`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add admin adviser pages and credential emails"
```

---

### Task 5: Companies list and detail page

**Files:**
- Create: `app/Http/Controllers/Admin/CompanyController.php`, `resources/views/admin/companies/index.blade.php`, `resources/views/admin/companies/show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/CompaniesTest.php`

**Interfaces:**
- Produces: `admin.companies.index` (GET, `q`, `approval` ∈ `pending|approved|rejected`), `admin.companies.show` (GET `/admin/companies/{company}`; 404 for partner companies — those have their own pages in Task 8). The show page links permit/MOA via `route('files.show', ['company-permit', $company])` / `company-moa`. Task 6 adds approve/reject controls and a Pending tab (guarded by `Route::has`); Task 7 adds the Danger zone.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/CompaniesTest.php`:
```php
<?php

use App\Enums\ApprovalStatus;
use App\Models\Company;
use App\Models\Placement;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('lists registered companies with contact, status and intern count', function () {
    $companyUser = User::factory()->company()->create(['first_name' => 'Marco', 'last_name' => 'Villanueva']);
    $companyUser->company->update(['name' => 'TechNova Solutions', 'company_code' => 'TECHNOVA']);
    Placement::factory()->for($companyUser->company)->count(2)->create();
    Company::factory()->partner()->create(['name' => 'Partner Only']);

    $this->actingAs($this->admin)->get(route('admin.companies.index'))
        ->assertOk()->assertSee('TechNova Solutions')->assertSee('Marco Villanueva')->assertSee('TECHNOVA')->assertSee('2')
        ->assertDontSee('Partner Only');
});

it('filters by approval status and searches by name or code', function () {
    $approved = User::factory()->company()->create();
    $approved->company->update(['name' => 'Approved Corp', 'company_code' => 'APPR0001']);
    $pending = User::factory()->company()->create();
    $pending->company->update(['name' => 'Pending Inc', 'approval_status' => ApprovalStatus::Pending]);

    $this->actingAs($this->admin)->get(route('admin.companies.index', ['approval' => 'pending']))->assertSee('Pending Inc')->assertDontSee('Approved Corp');
    $this->actingAs($this->admin)->get(route('admin.companies.index', ['q' => 'appr0001']))->assertSee('Approved Corp')->assertDontSee('Pending Inc');
});

it('shows a company with documents, contact and active interns', function () {
    $companyUser = User::factory()->company()->create(['first_name' => 'Marco']);
    $company = $companyUser->company;
    $company->update(['name' => 'TechNova Solutions']);
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    Placement::factory()->for($intern, 'intern')->for($company)->create(['hours_rendered' => 300]);

    $this->actingAs($this->admin)->get(route('admin.companies.show', $company))
        ->assertOk()->assertSee('TechNova Solutions')->assertSee('Marco')->assertSee('Maria Santos')->assertSee('300')
        ->assertSee(route('files.show', ['company-permit', $company]))
        ->assertSee(route('files.show', ['company-moa', $company]));
});

it('returns 404 for partner companies on the registered-company page', function () {
    $partner = Company::factory()->partner()->create();

    $this->actingAs($this->admin)->get(route('admin.companies.show', $partner))->assertNotFound();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=CompaniesTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Controller and routes**

`app/Http/Controllers/Admin/CompanyController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $approval = ApprovalStatus::tryFrom((string) $request->query('approval'));

        $companies = Company::registered()
            ->with('user')
            ->withCount(['activePlacements', 'postings'])
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('company_code', 'like', "%{$q}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('email', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%"))))
            ->when($approval, fn (Builder $query) => $query->where('approval_status', $approval))
            ->orderBy('name')
            ->paginate(20);

        return view('admin.companies.index', [
            'companies' => $companies,
            'pendingCount' => Company::registered()->pending()->count(),
        ]);
    }

    public function show(Company $company): View
    {
        abort_unless($company->isRegistered(), 404);

        $company->load(['user', 'approver', 'activePlacements.intern', 'activePlacements.department'])
            ->loadCount(['postings', 'placements']);

        return view('admin.companies.show', ['company' => $company]);
    }
}
```

Routes (admin group):
```php
        Route::get('companies', [Admin\CompanyController::class, 'index'])->name('companies.index');
        Route::get('companies/{company}', [Admin\CompanyController::class, 'show'])->name('companies.show');
```
(Task 6 inserts `companies/pending` BEFORE the `{company}` route.)

- [ ] **Step 4: Views**

`resources/views/admin/companies/index.blade.php`:
```blade
<x-layouts.app title="Companies">
    <x-page-header title="Companies" subtitle="Registered host companies with portal accounts." />

    @php
        $tabs = [['label' => 'All', 'route' => 'admin.companies.index', 'active' => 'admin.companies.index']];
        if (Route::has('admin.companies.pending')) {
            $tabs[] = ['label' => 'Pending approval', 'route' => 'admin.companies.pending', 'active' => 'admin.companies.pending', 'count' => $pendingCount];
        }
    @endphp
    <x-tabs :tabs="$tabs" />

    <x-card :padding="false">
        <div class="border-b border-stone-200/80 p-4 dark:border-stone-800">
            <x-search-form :action="route('admin.companies.index')" placeholder="Company, code, contact or email">
                <select name="approval" class="input sm:w-40" aria-label="Approval">
                    <option value="">Any approval</option>
                    @foreach (\App\Enums\ApprovalStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('approval') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-search-form>
        </div>
        <x-table>
            <x-slot:head><th>Company</th><th>Contact</th><th>Code</th><th>Active interns</th><th>Approval</th><th>Account</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($companies as $company)
                <tr>
                    <td class="font-medium">{{ $company->name }}<p class="text-xs font-normal text-stone-500">{{ $company->type }}</p></td>
                    <td>{{ $company->user?->name }}<p class="text-xs text-stone-500">{{ $company->user?->email }}</p></td>
                    <td class="font-mono text-xs">{{ $company->company_code }}</td>
                    <td class="tabular-nums">{{ $company->active_placements_count }}</td>
                    <td><x-badge :status="$company->approval_status" /></td>
                    <td>@if ($company->user)<x-badge :status="$company->user->status" />@endif</td>
                    <td class="text-right"><a href="{{ route('admin.companies.show', $company) }}" class="btn-ghost px-3 py-1.5">View</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty-state title="No companies match" /></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$companies" /></div>
    </x-card>
</x-layouts.app>
```

`resources/views/admin/companies/show.blade.php`:
```blade
<x-layouts.app :title="$company->name">
    <x-page-header :title="$company->name" :subtitle="($company->type ? $company->type.' · ' : '').'Code '.$company->company_code" :breadcrumbs="['Companies' => route('admin.companies.index'), $company->name => null]">
        <x-slot:actions>
            <x-badge :status="$company->approval_status" />
            {{-- Task 6 adds Approve / Reject buttons here when the company is pending --}}
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Verification documents">
            <ul class="space-y-2 text-sm">
                <li class="flex items-center justify-between"><span>Business permit</span>
                    @if ($company->permit_path)<a href="{{ route('files.show', ['company-permit', $company]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Open PDF</a>@else<span class="text-stone-500">Missing</span>@endif</li>
                <li class="flex items-center justify-between"><span>Memorandum of Agreement</span>
                    @if ($company->moa_path)<a href="{{ route('files.show', ['company-moa', $company]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Open PDF</a>@else<span class="text-stone-500">Missing</span>@endif</li>
            </ul>
            @if ($company->approved_at)
                <p class="mt-4 text-xs text-stone-500">Reviewed {{ $company->approved_at->format('M j, Y') }}@if ($company->approver) by {{ $company->approver->name }}@endif</p>
            @endif
        </x-card>

        <x-card title="Company and contact" class="lg:col-span-2">
            <x-detail-list :items="[
                'Contact person' => $company->user?->full_name,
                'Email' => $company->user?->email,
                'Phone' => $company->user?->phone,
                'Website' => $company->website,
                'Address' => $company->address,
                'Registered' => $company->created_at->format('M j, Y'),
                'Open postings' => $company->postings_count,
                'Total placements' => $company->placements_count,
            ]" />
            @if ($company->about)<p class="mt-5 text-sm text-stone-600 dark:text-stone-400">{{ $company->about }}</p>@endif
        </x-card>

        <x-card title="Active interns" class="lg:col-span-3" :padding="false">
            <x-table>
                <x-slot:head><th>Intern</th><th>Department</th><th>Since</th><th>Hours here</th></x-slot:head>
                @forelse ($company->activePlacements as $placement)
                    <tr>
                        <td><a href="{{ route('admin.interns.show', $placement->intern) }}" class="font-medium hover:underline">{{ $placement->intern->name }}</a></td>
                        <td>{{ $placement->department?->name ?? '—' }}</td>
                        <td>{{ $placement->started_at->format('M j, Y') }}</td>
                        <td class="tabular-nums">{{ $placement->hours_rendered }} h</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state title="No active interns" class="py-8" /></td></tr>
                @endforelse
            </x-table>
        </x-card>

        {{-- Task 7 adds the account-status "Danger zone" card here --}}
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=CompaniesTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add admin company list and detail pages"
```

---

### Task 6: Company approvals (approve / reject with document review)

**Files:**
- Create: `app/Actions/ApproveCompany.php`, `app/Actions/RejectCompany.php`, `app/Notifications/CompanyApproved.php`, `app/Notifications/CompanyRejected.php`, `app/Http/Requests/Admin/RejectCompanyRequest.php`, `app/Http/Controllers/Admin/CompanyApprovalController.php`, `resources/views/admin/companies/pending.blade.php`
- Modify: `routes/web.php`, `resources/views/admin/companies/show.blade.php` (actions slot)
- Test: `tests/Unit/Actions/CompanyApprovalActionsTest.php`, `tests/Feature/Admin/CompanyApprovalsTest.php`

**Interfaces:**
- Produces: `ApproveCompany::__invoke(Company $company, User $admin): bool` (false and no side effects when already approved), `RejectCompany::__invoke(Company $company, User $admin, ?string $reason = null): bool` (false when already rejected). Notifications `CompanyApproved(Company)` and `CompanyRejected(Company, ?string $reason)` use `['database', 'mail']` with the payload convention `['title','body','url','icon']`. Routes: `admin.companies.pending` (GET `/admin/companies/pending`, declared before `companies/{company}`), `admin.companies.approve` (POST `/admin/companies/{company}/approve`), `admin.companies.reject` (POST `/admin/companies/{company}/reject`, body `reason` nullable ≤500).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/CompanyApprovalActionsTest.php`:
```php
<?php

use App\Actions\ApproveCompany;
use App\Actions\RejectCompany;
use App\Enums\ApprovalStatus;
use App\Models\User;
use App\Notifications\CompanyApproved;
use App\Notifications\CompanyRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->companyUser = User::factory()->company()->create();
    $this->company = tap($this->companyUser->company)->update(['approval_status' => ApprovalStatus::Pending, 'approved_at' => null, 'approved_by' => null]);
});

it('approves a pending company, records who did it and notifies the company', function () {
    $changed = app(ApproveCompany::class)($this->company, $this->admin);

    $this->company->refresh();
    expect($changed)->toBeTrue()
        ->and($this->company->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($this->company->approved_by)->toBe($this->admin->id)
        ->and($this->company->approved_at)->not->toBeNull();
    Notification::assertSentTo($this->companyUser, CompanyApproved::class, function (CompanyApproved $n) {
        $data = $n->toArray($this->companyUser);
        expect($data)->toHaveKeys(['title', 'body', 'url', 'icon'])->and($data['url'])->toBe(route('company.dashboard'));
        expect($n->via($this->companyUser))->toBe(['database', 'mail']);

        return true;
    });
});

it('is a no-op when approving an already approved company', function () {
    app(ApproveCompany::class)($this->company, $this->admin);
    $firstApprovedAt = $this->company->refresh()->approved_at;
    Notification::fake();

    $changed = app(ApproveCompany::class)($this->company->refresh(), User::factory()->admin()->create());

    expect($changed)->toBeFalse()
        ->and($this->company->refresh()->approved_at->equalTo($firstApprovedAt))->toBeTrue()
        ->and($this->company->approved_by)->toBe($this->admin->id);
    Notification::assertNothingSent();
});

it('rejects with a reason and notifies the company', function () {
    $changed = app(RejectCompany::class)($this->company, $this->admin, 'Permit has expired.');

    expect($changed)->toBeTrue()
        ->and($this->company->refresh()->approval_status)->toBe(ApprovalStatus::Rejected)
        ->and($this->company->approved_at)->toBeNull();
    Notification::assertSentTo($this->companyUser, CompanyRejected::class, fn (CompanyRejected $n) => str_contains($n->toArray($this->companyUser)['body'], 'Permit has expired.'));
});

it('can re-approve a rejected company', function () {
    app(RejectCompany::class)($this->company, $this->admin, null);

    expect(app(ApproveCompany::class)($this->company->refresh(), $this->admin))->toBeTrue()
        ->and($this->company->refresh()->isApproved())->toBeTrue();
});
```

`tests/Feature/Admin/CompanyApprovalsTest.php`:
```php
<?php

use App\Enums\ApprovalStatus;
use App\Models\User;
use App\Notifications\CompanyApproved;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->companyUser = User::factory()->company()->create();
    $this->company = tap($this->companyUser->company)->update(['name' => 'BlueOrbit Analytics', 'approval_status' => ApprovalStatus::Pending, 'approved_at' => null]);
});

it('lists only pending companies with their documents', function () {
    $approved = User::factory()->company()->create();
    $approved->company->update(['name' => 'Already Verified']);

    $this->actingAs($this->admin)->get(route('admin.companies.pending'))
        ->assertOk()->assertSee('BlueOrbit Analytics')->assertDontSee('Already Verified')
        ->assertSee(route('files.show', ['company-permit', $this->company]))
        ->assertSee(route('admin.companies.approve', $this->company));
});

it('approves from the UI and the company can then use its portal', function () {
    $this->actingAs($this->companyUser)->get(route('company.dashboard'))->assertRedirect(route('account.pending'));

    $this->actingAs($this->admin)->post(route('admin.companies.approve', $this->company))
        ->assertRedirect()->assertSessionHas('success');

    expect($this->company->refresh()->isApproved())->toBeTrue();
    Notification::assertSentTo($this->companyUser, CompanyApproved::class);
    $this->actingAs($this->companyUser)->get(route('company.dashboard'))->assertOk();
});

it('double-submitting approve is harmless', function () {
    $this->actingAs($this->admin)->post(route('admin.companies.approve', $this->company));
    $at = $this->company->refresh()->approved_at;

    $this->actingAs($this->admin)->post(route('admin.companies.approve', $this->company))
        ->assertRedirect()->assertSessionHas('info');

    expect($this->company->refresh()->approved_at->equalTo($at))->toBeTrue();
});

it('rejects with a reason and validates its length', function () {
    $this->actingAs($this->admin)->post(route('admin.companies.reject', $this->company), ['reason' => str_repeat('x', 501)])
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->admin)->post(route('admin.companies.reject', $this->company), ['reason' => 'MOA unsigned'])
        ->assertRedirect()->assertSessionHas('success');

    expect($this->company->refresh()->approval_status)->toBe(ApprovalStatus::Rejected);
    $this->actingAs($this->companyUser)->get(route('account.pending'))->assertOk()->assertSee('not approved');
});

it('is admin-only', function () {
    $this->actingAs($this->companyUser)->post(route('admin.companies.approve', $this->company))->assertForbidden();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="CompanyApprovalActionsTest|CompanyApprovalsTest"`
Expected: FAIL — classes/routes missing.

- [ ] **Step 3: Notifications and actions**

`app/Notifications/CompanyApproved.php`:
```php
<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyApproved extends Notification
{
    use Queueable;

    public function __construct(public Company $company) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Your company is verified',
            'body' => "{$this->company->name} has been approved by the ".config('wiis.institution.office').'. You can now post internships and accept interns.',
            'url' => route('company.dashboard'),
            'icon' => 'heroicon-o-check-badge',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->company->name.' is verified on '.config('wiis.name'))
            ->greeting("Hello {$notifiable->first_name},")
            ->line($this->toArray($notifiable)['body'])
            ->action('Open your dashboard', route('company.dashboard'));
    }
}
```

`app/Notifications/CompanyRejected.php`:
```php
<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyRejected extends Notification
{
    use Queueable;

    public function __construct(public Company $company, public ?string $reason = null) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $reason = $this->reason ? " Reason: {$this->reason}" : ' Please contact '.config('wiis.support.email').' for details.';

        return [
            'title' => 'Company verification not approved',
            'body' => "The registration for {$this->company->name} was not approved.".$reason,
            'url' => route('account.pending'),
            'icon' => 'heroicon-o-x-circle',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Update on your '.config('wiis.name').' registration')
            ->greeting("Hello {$notifiable->first_name},")
            ->line($this->toArray($notifiable)['body'])
            ->line('You may reply to the placement office with corrected documents.');
    }
}
```

`app/Actions/ApproveCompany.php`:
```php
<?php

namespace App\Actions;

use App\Enums\ApprovalStatus;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyApproved;

class ApproveCompany
{
    /** @return bool true when the status actually changed */
    public function __invoke(Company $company, User $admin): bool
    {
        if ($company->isApproved()) {
            return false;
        }

        $company->update([
            'approval_status' => ApprovalStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $company->user?->notify(new CompanyApproved($company));

        return true;
    }
}
```

`app/Actions/RejectCompany.php`:
```php
<?php

namespace App\Actions;

use App\Enums\ApprovalStatus;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyRejected;

class RejectCompany
{
    /** @return bool true when the status actually changed */
    public function __invoke(Company $company, User $admin, ?string $reason = null): bool
    {
        if ($company->approval_status === ApprovalStatus::Rejected) {
            return false;
        }

        $company->update([
            'approval_status' => ApprovalStatus::Rejected,
            'approved_by' => $admin->id,
            'approved_at' => null,
        ]);

        $company->user?->notify(new CompanyRejected($company, $reason));

        return true;
    }
}
```

`app/Http/Requests/Admin/RejectCompanyRequest.php`:
```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }
}
```

- [ ] **Step 4: Controller, routes, views**

`app/Http/Controllers/Admin/CompanyApprovalController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveCompany;
use App\Actions\RejectCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectCompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyApprovalController extends Controller
{
    public function index(): View
    {
        return view('admin.companies.pending', [
            'companies' => Company::registered()->pending()->with('user')->oldest()->paginate(20),
            'pendingCount' => Company::registered()->pending()->count(),
        ]);
    }

    public function approve(Request $request, Company $company, ApproveCompany $approve): RedirectResponse
    {
        abort_unless($company->isRegistered(), 404);

        $changed = $approve($company, $request->user());

        return back()->with(
            $changed ? 'success' : 'info',
            $changed ? "{$company->name} is now verified. The company has been notified." : "{$company->name} was already approved."
        );
    }

    public function reject(RejectCompanyRequest $request, Company $company, RejectCompany $reject): RedirectResponse
    {
        abort_unless($company->isRegistered(), 404);

        $changed = $reject($company, $request->user(), $request->validated('reason'));

        return back()->with(
            $changed ? 'success' : 'info',
            $changed ? "{$company->name} was rejected. The company has been notified." : "{$company->name} was already rejected."
        );
    }
}
```

Routes (admin group; the `pending` line must come BEFORE `companies/{company}`):
```php
        Route::get('companies/pending', [Admin\CompanyApprovalController::class, 'index'])->name('companies.pending');
        Route::post('companies/{company}/approve', [Admin\CompanyApprovalController::class, 'approve'])->name('companies.approve');
        Route::post('companies/{company}/reject', [Admin\CompanyApprovalController::class, 'reject'])->name('companies.reject');
```

`resources/views/admin/companies/pending.blade.php`:
```blade
<x-layouts.app title="Pending approvals">
    <x-page-header title="Companies" subtitle="Review the business permit and MOA, then approve or reject." />
    <x-tabs :tabs="[
        ['label' => 'All', 'route' => 'admin.companies.index', 'active' => 'admin.companies.index'],
        ['label' => 'Pending approval', 'route' => 'admin.companies.pending', 'active' => 'admin.companies.pending', 'count' => $pendingCount],
    ]" />

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Company</th><th>Contact</th><th>Documents</th><th>Submitted</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($companies as $company)
                <tr>
                    <td><a href="{{ route('admin.companies.show', $company) }}" class="font-medium hover:underline">{{ $company->name }}</a><p class="text-xs text-stone-500">{{ $company->type }}</p></td>
                    <td>{{ $company->user?->name }}<p class="text-xs text-stone-500">{{ $company->user?->email }}</p></td>
                    <td class="space-x-3 text-sm">
                        <a href="{{ route('files.show', ['company-permit', $company]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Permit</a>
                        <a href="{{ route('files.show', ['company-moa', $company]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">MOA</a>
                    </td>
                    <td class="text-stone-500">{{ $company->created_at->diffForHumans() }}</td>
                    <td>
                        <div class="flex justify-end gap-2">
                            <x-confirm-form :action="route('admin.companies.approve', $company)" confirm="Approve {{ $company->name }}? The company will be notified.">
                                <x-button icon="heroicon-o-check">Approve</x-button>
                            </x-confirm-form>
                            <x-button type="button" variant="danger" icon="heroicon-o-x-mark" @click="$dispatch('open-modal', 'reject-{{ $company->id }}')">Reject</x-button>
                        </div>
                        <x-modal name="reject-{{ $company->id }}" title="Reject {{ $company->name }}">
                            <form method="POST" action="{{ route('admin.companies.reject', $company) }}" class="space-y-4">
                                @csrf
                                <x-form.textarea name="reason" label="Reason (sent to the company)" rows="3" hint="Optional, up to 500 characters." />
                                <div class="flex justify-end gap-2">
                                    <x-button type="button" variant="secondary" @click="$dispatch('close-modal', 'reject-{{ $company->id }}')">Cancel</x-button>
                                    <x-button variant="danger">Reject registration</x-button>
                                </div>
                            </form>
                        </x-modal>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state title="Nothing to review" description="New company registrations will appear here." icon="heroicon-o-check-badge" /></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$companies" /></div>
    </x-card>
</x-layouts.app>
```

In `resources/views/admin/companies/show.blade.php`, replace the actions slot with:
```blade
        <x-slot:actions>
            <x-badge :status="$company->approval_status" />
            @if (Route::has('admin.companies.approve') && ! $company->isApproved())
                <x-confirm-form :action="route('admin.companies.approve', $company)" confirm="Approve {{ $company->name }}? The company will be notified.">
                    <x-button icon="heroicon-o-check">Approve</x-button>
                </x-confirm-form>
            @endif
            @if (Route::has('admin.companies.reject') && $company->approval_status !== \App\Enums\ApprovalStatus::Rejected)
                <x-button type="button" variant="danger" icon="heroicon-o-x-mark" @click="$dispatch('open-modal', 'reject-company')">Reject</x-button>
                <x-modal name="reject-company" title="Reject {{ $company->name }}">
                    <form method="POST" action="{{ route('admin.companies.reject', $company) }}" class="space-y-4">
                        @csrf
                        <x-form.textarea name="reason" label="Reason (sent to the company)" rows="3" hint="Optional, up to 500 characters." />
                        <div class="flex justify-end gap-2">
                            <x-button type="button" variant="secondary" @click="$dispatch('close-modal', 'reject-company')">Cancel</x-button>
                            <x-button variant="danger">Reject registration</x-button>
                        </div>
                    </form>
                </x-modal>
            @endif
        </x-slot:actions>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="CompanyApprovalActionsTest|CompanyApprovalsTest|CompaniesTest"`
Expected: PASS (13 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add company approval review with approve and reject"
```

---

### Task 7: Disable / reactivate accounts and the archive

**Files:**
- Create: `app/Exceptions/DomainRuleViolation.php`, `app/Actions/DisableUser.php`, `app/Actions/ReactivateUser.php`, `app/Http/Controllers/Admin/UserStatusController.php`, `app/Http/Controllers/Admin/ArchiveController.php`, `resources/views/admin/archive/index.blade.php`, `resources/views/admin/partials/account-status.blade.php`
- Modify: `bootstrap/app.php` (render `DomainRuleViolation` as a flash error), `routes/web.php`, `resources/views/admin/interns/show.blade.php`, `resources/views/admin/advisers/show.blade.php`, `resources/views/admin/companies/show.blade.php` (include the partial)
- Test: `tests/Unit/Actions/UserStatusActionsTest.php`, `tests/Feature/Admin/AccountStatusTest.php`

**Interfaces:**
- Produces: `DomainRuleViolation extends \RuntimeException` (message is user-facing); rendered globally as `back()->with('error', message)` (JSON 422 for `expectsJson`). `DisableUser::__invoke(User $user, User $actor): void` — throws `DomainRuleViolation` when `$user->isAdmin()` or `$actor->is($user)`; sets status `disabled`. `ReactivateUser::__invoke(User $user): void`. Routes: `admin.users.disable` (POST `/admin/users/{user}/disable`), `admin.users.reactivate` (POST `/admin/users/{user}/reactivate`), `admin.archive.index` (GET `/admin/archive`, query `role`). Partial `admin.partials.account-status` expects `$user`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/UserStatusActionsTest.php`:
```php
<?php

use App\Actions\DisableUser;
use App\Actions\ReactivateUser;
use App\Enums\AccountStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('disables and reactivates a non-admin account', function () {
    $admin = User::factory()->admin()->create();
    $intern = User::factory()->intern()->create();

    app(DisableUser::class)($intern, $admin);
    expect($intern->refresh()->status)->toBe(AccountStatus::Disabled);

    app(ReactivateUser::class)($intern);
    expect($intern->refresh()->status)->toBe(AccountStatus::Active);
});

it('refuses to disable an admin or yourself', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    expect(fn () => app(DisableUser::class)($otherAdmin, $admin))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(DisableUser::class)($admin, $admin))->toThrow(DomainRuleViolation::class);
    expect($otherAdmin->refresh()->status)->toBe(AccountStatus::Active);
});
```

`tests/Feature/Admin/AccountStatusTest.php`:
```php
<?php

use App\Enums\AccountStatus;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('disables an intern, who is then signed out, and shows them in the archive', function () {
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->actingAs($intern)->get(route('intern.dashboard'))->assertOk();

    $this->actingAs($this->admin)->from(route('admin.interns.show', $intern))
        ->post(route('admin.users.disable', $intern))
        ->assertRedirect(route('admin.interns.show', $intern))->assertSessionHas('success');

    expect($intern->refresh()->status)->toBe(AccountStatus::Disabled);
    $this->actingAs($intern)->get(route('intern.dashboard'))->assertRedirect(route('login'));
    $this->actingAs($this->admin)->get(route('admin.archive.index'))->assertOk()->assertSee('Maria Santos');
});

it('reactivates from the archive', function () {
    $adviser = User::factory()->adviser()->disabled()->create(['first_name' => 'Elsie']);

    $this->actingAs($this->admin)->get(route('admin.archive.index', ['role' => 'adviser']))->assertSee('Elsie');
    $this->actingAs($this->admin)->get(route('admin.archive.index', ['role' => 'intern']))->assertDontSee('Elsie');

    $this->actingAs($this->admin)->post(route('admin.users.reactivate', $adviser))->assertRedirect()->assertSessionHas('success');
    expect($adviser->refresh()->status)->toBe(AccountStatus::Active);
    $this->actingAs($this->admin)->get(route('admin.archive.index'))->assertDontSee('Elsie');
});

it('refuses to disable an admin or yourself with a flash error', function () {
    $other = User::factory()->admin()->create();

    $this->actingAs($this->admin)->post(route('admin.users.disable', $other))->assertRedirect()->assertSessionHas('error');
    $this->actingAs($this->admin)->post(route('admin.users.disable', $this->admin))->assertRedirect()->assertSessionHas('error');
    expect($other->refresh()->status)->toBe(AccountStatus::Active)->and($this->admin->refresh()->status)->toBe(AccountStatus::Active);
});

it('shows the danger zone on intern, adviser and company pages', function () {
    $intern = User::factory()->intern()->create();
    $adviser = User::factory()->adviser()->create();
    $companyUser = User::factory()->company()->create();

    $this->actingAs($this->admin)->get(route('admin.interns.show', $intern))->assertSee(route('admin.users.disable', $intern));
    $this->actingAs($this->admin)->get(route('admin.advisers.show', $adviser))->assertSee(route('admin.users.disable', $adviser));
    $this->actingAs($this->admin)->get(route('admin.companies.show', $companyUser->company))->assertSee(route('admin.users.disable', $companyUser));
});

it('is admin-only', function () {
    $intern = User::factory()->intern()->create();
    $this->actingAs($intern)->post(route('admin.users.disable', $intern))->assertForbidden();
    $this->actingAs($intern)->get(route('admin.archive.index'))->assertForbidden();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="UserStatusActionsTest|AccountStatusTest"`
Expected: FAIL — classes/routes missing.

- [ ] **Step 3: Exception, actions, rendering**

`app/Exceptions/DomainRuleViolation.php`:
```php
<?php

namespace App\Exceptions;

use RuntimeException;

/** A business rule refused the operation. The message is safe to show to the user. */
class DomainRuleViolation extends RuntimeException {}
```

`app/Actions/DisableUser.php`:
```php
<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;

class DisableUser
{
    public function __invoke(User $user, User $actor): void
    {
        if ($actor->is($user)) {
            throw new DomainRuleViolation('You cannot disable your own account.');
        }

        if ($user->isAdmin()) {
            throw new DomainRuleViolation('Administrator accounts cannot be disabled from here.');
        }

        $user->update(['status' => AccountStatus::Disabled]);
    }
}
```

`app/Actions/ReactivateUser.php`:
```php
<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Models\User;

class ReactivateUser
{
    public function __invoke(User $user): void
    {
        $user->update(['status' => AccountStatus::Active]);
    }
}
```

`bootstrap/app.php` — replace the `withExceptions` block:
```php
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (DomainRuleViolation $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        });
    })
```
with imports `use App\Exceptions\DomainRuleViolation;` and `use Illuminate\Http\Request;`.

- [ ] **Step 4: Controllers, routes, views**

`app/Http/Controllers/Admin/UserStatusController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Actions\DisableUser;
use App\Actions\ReactivateUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserStatusController extends Controller
{
    public function disable(Request $request, User $user, DisableUser $disable): RedirectResponse
    {
        $disable($user, $request->user());

        return back()->with('success', "{$user->name} has been disabled and signed out.");
    }

    public function reactivate(User $user, ReactivateUser $reactivate): RedirectResponse
    {
        $reactivate($user);

        return back()->with('success', "{$user->name} has been reactivated.");
    }
}
```

`app/Http/Controllers/Admin/ArchiveController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    public function index(Request $request): View
    {
        $role = Role::tryFrom((string) $request->query('role'));
        $q = trim((string) $request->query('q'));

        $users = User::disabled()
            ->with('company')
            ->when($role, fn (Builder $query) => $query->where('role', $role))
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")->orWhere('member_no', 'like', "%{$q}%")))
            ->latest('updated_at')
            ->paginate(20);

        return view('admin.archive.index', ['users' => $users]);
    }
}
```

Routes (admin group):
```php
        Route::post('users/{user}/disable', [Admin\UserStatusController::class, 'disable'])->name('users.disable');
        Route::post('users/{user}/reactivate', [Admin\UserStatusController::class, 'reactivate'])->name('users.reactivate');
        Route::get('archive', [Admin\ArchiveController::class, 'index'])->name('archive.index');
```

`resources/views/admin/partials/account-status.blade.php` (an `@include`d partial; it expects a `$user` variable):
```blade
@if (Route::has('admin.users.disable') && ! $user->isAdmin())
    <x-card title="Account status" class="lg:col-span-3 border-rose-200 dark:border-rose-900">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm">This account is <x-badge :status="$user->status" />.</p>
                <p class="mt-1 text-sm text-stone-500">Disabled accounts cannot sign in and are signed out immediately. Nothing is deleted; you can reactivate at any time.</p>
            </div>
            @if ($user->isDisabled())
                <x-confirm-form :action="route('admin.users.reactivate', $user)" confirm="Reactivate {{ $user->name }}?">
                    <x-button icon="heroicon-o-arrow-path">Reactivate account</x-button>
                </x-confirm-form>
            @else
                <x-confirm-form :action="route('admin.users.disable', $user)" confirm="Disable {{ $user->name }}? They will be signed out immediately.">
                    <x-button variant="danger" icon="heroicon-o-no-symbol">Disable account</x-button>
                </x-confirm-form>
            @endif
        </div>
    </x-card>
@endif
```

Replace the `{{-- Task 7 adds ... --}}` placeholder comments with:
- interns/show: `@include('admin.partials.account-status', ['user' => $intern])`
- advisers/show: `@include('admin.partials.account-status', ['user' => $adviser])`
- companies/show: `@if ($company->user) @include('admin.partials.account-status', ['user' => $company->user]) @endif`

`resources/views/admin/archive/index.blade.php`:
```blade
<x-layouts.app title="Archive">
    <x-page-header title="Archive" subtitle="Disabled accounts. Reactivate to restore access; nothing here is deleted." />

    <x-card :padding="false">
        <div class="border-b border-stone-200/80 p-4 dark:border-stone-800">
            <x-search-form :action="route('admin.archive.index')" placeholder="Name, email or member no.">
                <select name="role" class="input sm:w-40" aria-label="Role">
                    <option value="">All roles</option>
                    @foreach (\App\Enums\Role::options() as $value => $label)
                        @continue($value === 'admin')
                        <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-search-form>
        </div>
        <x-table>
            <x-slot:head><th>Account</th><th>Role</th><th>Member no.</th><th>Disabled</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($users as $user)
                <tr>
                    <td><div class="flex items-center gap-3"><x-avatar :user="$user" size="sm" /><div><p class="font-medium">{{ $user->name }}@if ($user->company) <span class="text-stone-500">· {{ $user->company->name }}</span>@endif</p><p class="text-xs text-stone-500">{{ $user->email }}</p></div></div></td>
                    <td><x-badge :status="$user->role" /></td>
                    <td class="font-mono text-xs">{{ $user->member_no }}</td>
                    <td class="text-stone-500">{{ $user->updated_at->diffForHumans() }}</td>
                    <td class="text-right">
                        <x-confirm-form :action="route('admin.users.reactivate', $user)" confirm="Reactivate {{ $user->name }}?" class="inline">
                            <x-button variant="secondary" icon="heroicon-o-arrow-path">Reactivate</x-button>
                        </x-confirm-form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state title="No disabled accounts" icon="heroicon-o-archive-box" /></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$users" /></div>
    </x-card>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="UserStatusActionsTest|AccountStatusTest|InternsTest|AdvisersTest|CompaniesTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add account disable, reactivate and archive for admins"
```

---

### Task 8: Partner (COS) companies CRUD

**Files:**
- Create: `app/Actions/CreatePartnerCompany.php`, `app/Actions/UpdatePartnerCompany.php`, `app/Actions/DeletePartnerCompany.php`, `app/Http/Requests/Admin/PartnerCompanyRequest.php`, `app/Http/Controllers/Admin/PartnerCompanyController.php`, `resources/views/admin/partners/index.blade.php`, `resources/views/admin/partners/create.blade.php`, `resources/views/admin/partners/edit.blade.php`, `resources/views/admin/partners/_form.blade.php`
- Modify: `app/Models/Company.php` (add `cosApplications()` relation), `routes/web.php`
- Test: `tests/Unit/Actions/PartnerCompanyActionsTest.php`, `tests/Feature/Admin/PartnerCompaniesTest.php`

**Interfaces:**
- Produces: `Company::cosApplications(): HasMany`. `CreatePartnerCompany::__invoke(array $data, ?UploadedFile $logo = null): Company` (`$data`: name, type?, about?, website?, address?; sets `company_code` via `JoinCodeGenerator`, `approval_status` approved, `approved_at` now, logo on `public` disk under `partner-logos/`). `UpdatePartnerCompany::__invoke(Company $company, array $data, ?UploadedFile $logo = null): Company` (replaces the logo, deleting the old file). `DeletePartnerCompany::__invoke(Company $company): void` — throws `DomainRuleViolation` when the company has placements or COS applications; otherwise deletes the logo file and the row. Routes via `Route::resource('partners', ...)->except('show')->parameters(['partners' => 'company'])`: `admin.partners.index|create|store|edit|update|destroy` (edit/update/destroy 404 for non-partner companies).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/PartnerCompanyActionsTest.php`:
```php
<?php

use App\Actions\CreatePartnerCompany;
use App\Actions\DeletePartnerCompany;
use App\Actions\UpdatePartnerCompany;
use App\Enums\ApprovalStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Company;
use App\Models\CosApplication;
use App\Models\Placement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('public'));

it('creates an approved partner company with a code and logo', function () {
    $company = app(CreatePartnerCompany::class)(['name' => 'City Hall ICT', 'type' => 'Government'], UploadedFile::fake()->image('logo.png'));

    expect($company->isPartner())->toBeTrue()
        ->and($company->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($company->company_code)->toHaveLength(8)
        ->and($company->logo_path)->toStartWith('partner-logos/');
    Storage::disk('public')->assertExists($company->logo_path);
});

it('updates fields and replaces the logo', function () {
    $company = app(CreatePartnerCompany::class)(['name' => 'Old'], UploadedFile::fake()->image('a.png'));
    $old = $company->logo_path;

    app(UpdatePartnerCompany::class)($company, ['name' => 'New Name', 'about' => 'Updated'], UploadedFile::fake()->image('b.png'));

    expect($company->refresh()->name)->toBe('New Name')->and($company->logo_path)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($company->logo_path);
});

it('deletes an unused partner company and its logo', function () {
    $company = app(CreatePartnerCompany::class)(['name' => 'Temp'], UploadedFile::fake()->image('a.png'));
    $logo = $company->logo_path;

    app(DeletePartnerCompany::class)($company);

    expect(Company::find($company->id))->toBeNull();
    Storage::disk('public')->assertMissing($logo);
});

it('refuses to delete a partner company with placements or COS applications', function () {
    $withPlacement = Company::factory()->partner()->create();
    Placement::factory()->for($withPlacement)->create();
    $withApplication = Company::factory()->partner()->create();
    CosApplication::factory()->for($withApplication)->create();

    expect(fn () => app(DeletePartnerCompany::class)($withPlacement))->toThrow(DomainRuleViolation::class);
    expect(fn () => app(DeletePartnerCompany::class)($withApplication))->toThrow(DomainRuleViolation::class);
    expect(Company::count())->toBe(2);
});
```

`tests/Feature/Admin/PartnerCompaniesTest.php`:
```php
<?php

use App\Models\Company;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->admin()->create();
});

it('lists only partner companies with active intern counts', function () {
    $partner = Company::factory()->partner()->create(['name' => 'City Hall ICT']);
    Placement::factory()->for($partner)->create();
    User::factory()->company()->create()->company->update(['name' => 'Registered Corp']);

    $this->actingAs($this->admin)->get(route('admin.partners.index'))
        ->assertOk()->assertSee('City Hall ICT')->assertSee('1')->assertDontSee('Registered Corp');
});

it('creates, edits and deletes a partner company through the UI', function () {
    $this->actingAs($this->admin)->get(route('admin.partners.create'))->assertOk();

    $this->actingAs($this->admin)->post(route('admin.partners.store'), [
        'name' => 'City Hall ICT', 'type' => 'Government', 'website' => 'https://qc.example', 'about' => 'ICT office',
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
    ])->assertRedirect(route('admin.partners.index'))->assertSessionHas('success');

    $company = Company::partners()->where('name', 'City Hall ICT')->firstOrFail();
    Storage::disk('public')->assertExists($company->logo_path);

    $this->actingAs($this->admin)->get(route('admin.partners.edit', $company))->assertOk()->assertSee('City Hall ICT');
    $this->actingAs($this->admin)->put(route('admin.partners.update', $company), ['name' => 'QC Hall ICT', 'type' => 'Government'])
        ->assertRedirect(route('admin.partners.index'));
    expect($company->refresh()->name)->toBe('QC Hall ICT');

    $this->actingAs($this->admin)->delete(route('admin.partners.destroy', $company))->assertRedirect(route('admin.partners.index'))->assertSessionHas('success');
    expect(Company::find($company->id))->toBeNull();
});

it('refuses to delete a partner with placements and shows a friendly error', function () {
    $partner = Company::factory()->partner()->create();
    Placement::factory()->for($partner)->create();

    $this->actingAs($this->admin)->from(route('admin.partners.index'))->delete(route('admin.partners.destroy', $partner))
        ->assertRedirect(route('admin.partners.index'))->assertSessionHas('error');
    expect(Company::find($partner->id))->not->toBeNull();
});

it('validates the form and rejects oversized logos', function () {
    $this->actingAs($this->admin)->post(route('admin.partners.store'), ['name' => '', 'website' => 'nope'])
        ->assertSessionHasErrors(['name', 'website']);
    $this->actingAs($this->admin)->post(route('admin.partners.store'), ['name' => 'X', 'logo' => UploadedFile::fake()->create('big.png', config('wiis.uploads.max_avatar_kb') + 1, 'image/png')])
        ->assertSessionHasErrors('logo');
});

it('returns 404 when editing a registered company through the partner routes', function () {
    $registered = User::factory()->company()->create()->company;

    $this->actingAs($this->admin)->get(route('admin.partners.edit', $registered))->assertNotFound();
    $this->actingAs($this->admin)->delete(route('admin.partners.destroy', $registered))->assertNotFound();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="PartnerCompanyActionsTest|PartnerCompaniesTest"`
Expected: FAIL — classes/routes missing.

- [ ] **Step 3: Model relation, request, actions**

Add to `app/Models/Company.php`:
```php
    public function cosApplications(): HasMany
    {
        return $this->hasMany(CosApplication::class);
    }
```

`app/Http/Requests/Admin/PartnerCompanyRequest.php`:
```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PartnerCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('wiis.uploads.max_avatar_kb')],
        ];
    }
}
```

`app/Actions/CreatePartnerCompany.php`:
```php
<?php

namespace App\Actions;

use App\Enums\ApprovalStatus;
use App\Models\Company;
use App\Services\JoinCodeGenerator;
use Illuminate\Http\UploadedFile;

class CreatePartnerCompany
{
    public function __construct(private readonly JoinCodeGenerator $codes) {}

    /** @param  array{name:string, type?:?string, about?:?string, website?:?string, address?:?string}  $data */
    public function __invoke(array $data, ?UploadedFile $logo = null): Company
    {
        return Company::create([
            'user_id' => null,
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'about' => $data['about'] ?? null,
            'website' => $data['website'] ?? null,
            'address' => $data['address'] ?? null,
            'company_code' => $this->codes->generate('companies', 'company_code'),
            'logo_path' => $logo?->store('partner-logos', 'public'),
            'approval_status' => ApprovalStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
```

`app/Actions/UpdatePartnerCompany.php`:
```php
<?php

namespace App\Actions;

use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdatePartnerCompany
{
    /** @param  array{name:string, type?:?string, about?:?string, website?:?string, address?:?string}  $data */
    public function __invoke(Company $company, array $data, ?UploadedFile $logo = null): Company
    {
        $attributes = [
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'about' => $data['about'] ?? null,
            'website' => $data['website'] ?? null,
            'address' => $data['address'] ?? null,
        ];

        $previousLogo = $company->logo_path;

        if ($logo) {
            $attributes['logo_path'] = $logo->store('partner-logos', 'public');
        }

        $company->update($attributes);

        if ($logo && $previousLogo && Storage::disk('public')->exists($previousLogo)) {
            Storage::disk('public')->delete($previousLogo);
        }

        return $company;
    }
}
```

`app/Actions/DeletePartnerCompany.php`:
```php
<?php

namespace App\Actions;

use App\Exceptions\DomainRuleViolation;
use App\Models\Company;
use Illuminate\Support\Facades\Storage;

class DeletePartnerCompany
{
    public function __invoke(Company $company): void
    {
        if ($company->placements()->exists() || $company->cosApplications()->exists()) {
            throw new DomainRuleViolation("{$company->name} has intern placements or applications and cannot be deleted.");
        }

        $logo = $company->logo_path;
        $company->delete();

        if ($logo && Storage::disk('public')->exists($logo)) {
            Storage::disk('public')->delete($logo);
        }
    }
}
```

- [ ] **Step 4: Controller, routes, views**

`app/Http/Controllers/Admin/PartnerCompanyController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreatePartnerCompany;
use App\Actions\DeletePartnerCompany;
use App\Actions\UpdatePartnerCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PartnerCompanyRequest;
use App\Models\Company;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerCompanyController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('admin.partners.index', [
            'companies' => Company::partners()
                ->withCount(['activePlacements', 'cosApplications'])
                ->when($q !== '', fn (Builder $query) => $query->where('name', 'like', "%{$q}%")->orWhere('company_code', 'like', "%{$q}%"))
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.partners.create');
    }

    public function store(PartnerCompanyRequest $request, CreatePartnerCompany $create): RedirectResponse
    {
        $company = $create($request->safe()->except('logo'), $request->file('logo'));

        return redirect()->route('admin.partners.index')->with('success', "{$company->name} was added as a partner company.");
    }

    public function edit(Company $company): View
    {
        abort_unless($company->isPartner(), 404);

        return view('admin.partners.edit', ['company' => $company]);
    }

    public function update(PartnerCompanyRequest $request, Company $company, UpdatePartnerCompany $update): RedirectResponse
    {
        abort_unless($company->isPartner(), 404);

        $update($company, $request->safe()->except('logo'), $request->file('logo'));

        return redirect()->route('admin.partners.index')->with('success', "{$company->name} was updated.");
    }

    public function destroy(Company $company, DeletePartnerCompany $delete): RedirectResponse
    {
        abort_unless($company->isPartner(), 404);

        $delete($company);

        return redirect()->route('admin.partners.index')->with('success', "{$company->name} was removed.");
    }
}
```

Routes (admin group):
```php
        Route::resource('partners', Admin\PartnerCompanyController::class)->except('show')->parameters(['partners' => 'company']);
```

`resources/views/admin/partners/_form.blade.php`:
```blade
@props(['company' => null])
<div class="grid gap-5 sm:grid-cols-2">
    <x-form.input name="name" label="Company name" :value="$company?->name" required autofocus />
    <x-form.input name="type" label="Type / sector" :value="$company?->type" placeholder="Government" />
    <x-form.input name="website" label="Website" type="url" :value="$company?->website" placeholder="https://" />
    <x-form.input name="address" label="Address" :value="$company?->address" />
</div>
<x-form.textarea name="about" label="About" :value="$company?->about" rows="3" class="mt-5" hint="Shown to interns browsing partner companies." />
<div class="mt-5 flex items-end gap-4">
    @if ($company?->logo_path)
        <img src="{{ Storage::disk('public')->url($company->logo_path) }}" alt="" class="size-14 rounded-xl object-cover ring-1 ring-stone-200 dark:ring-stone-700">
    @endif
    <x-form.file name="logo" label="Logo" accept="image/png,image/jpeg,image/webp" hint="PNG, JPG or WebP up to {{ config('wiis.uploads.max_avatar_kb') / 1024 }} MB." class="flex-1" />
</div>
```

`resources/views/admin/partners/create.blade.php`:
```blade
<x-layouts.app title="Add partner company">
    <x-page-header title="Add partner company" subtitle="Partner (COS) companies host interns without a portal login; the class adviser reviews their DTRs." :breadcrumbs="['Partner companies' => route('admin.partners.index'), 'Add' => null]" />
    <form method="POST" action="{{ route('admin.partners.store') }}" enctype="multipart/form-data" class="max-w-3xl">
        @csrf
        <x-card>
            @include('admin.partners._form')
            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="secondary" :href="route('admin.partners.index')">Cancel</x-button>
                <x-button>Add partner</x-button>
            </div>
        </x-card>
    </form>
</x-layouts.app>
```

`resources/views/admin/partners/edit.blade.php`:
```blade
<x-layouts.app :title="'Edit '.$company->name">
    <x-page-header :title="$company->name" :subtitle="'Code '.$company->company_code" :breadcrumbs="['Partner companies' => route('admin.partners.index'), $company->name => null]" />
    <form method="POST" action="{{ route('admin.partners.update', $company) }}" enctype="multipart/form-data" class="max-w-3xl">
        @csrf @method('PUT')
        <x-card>
            @include('admin.partners._form', ['company' => $company])
            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="secondary" :href="route('admin.partners.index')">Cancel</x-button>
                <x-button>Save changes</x-button>
            </div>
        </x-card>
    </form>
</x-layouts.app>
```

`resources/views/admin/partners/index.blade.php`:
```blade
<x-layouts.app title="Partner companies">
    <x-page-header title="Partner companies" subtitle="Contract-of-service hosts without a portal login. Interns apply with an acceptance letter; advisers review their DTRs.">
        <x-slot:actions><x-button :href="route('admin.partners.create')" icon="heroicon-o-plus">Add partner</x-button></x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="border-b border-stone-200/80 p-4 dark:border-stone-800">
            <x-search-form :action="route('admin.partners.index')" placeholder="Name or code" />
        </div>
        <x-table>
            <x-slot:head><th>Company</th><th>Code</th><th>Active interns</th><th>Applications</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($companies as $company)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            @if ($company->logo_path)
                                <img src="{{ Storage::disk('public')->url($company->logo_path) }}" alt="" class="size-9 rounded-lg object-cover ring-1 ring-stone-200 dark:ring-stone-700">
                            @else
                                <span class="grid size-9 place-items-center rounded-lg bg-stone-100 text-stone-500 dark:bg-stone-800"><x-heroicon-o-building-storefront class="size-5" /></span>
                            @endif
                            <div><p class="font-medium">{{ $company->name }}</p><p class="text-xs text-stone-500">{{ $company->type }}</p></div>
                        </div>
                    </td>
                    <td class="font-mono text-xs">{{ $company->company_code }}</td>
                    <td class="tabular-nums">{{ $company->active_placements_count }}</td>
                    <td class="tabular-nums">{{ $company->cos_applications_count }}</td>
                    <td>
                        <div class="flex justify-end gap-1">
                            <a href="{{ route('admin.partners.edit', $company) }}" class="btn-ghost px-3 py-1.5">Edit</a>
                            <x-confirm-form :action="route('admin.partners.destroy', $company)" method="DELETE" confirm="Remove {{ $company->name }}? This cannot be undone.">
                                <button class="btn-ghost px-3 py-1.5 text-rose-600">Remove</button>
                            </x-confirm-form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state title="No partner companies yet" description="Add the government offices and partners that host interns under contract of service." icon="heroicon-o-building-storefront"><x-slot:action><x-button :href="route('admin.partners.create')" icon="heroicon-o-plus">Add partner</x-button></x-slot:action></x-empty-state></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$companies" /></div>
    </x-card>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="PartnerCompanyActionsTest|PartnerCompaniesTest"`
Expected: PASS (9 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add partner company management for admins"
```

---

### Task 9: Class sections list and roster

**Files:**
- Create: `app/Http/Controllers/Admin/ClassSectionController.php`, `resources/views/admin/classes/index.blade.php`, `resources/views/admin/classes/show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/ClassesTest.php`

**Interfaces:**
- Produces: `admin.classes.index` (GET `/admin/classes`, query `q` (course code, subject, section, join code), `year` (school_year), `status` (`active|archived`)), `admin.classes.show` (GET `/admin/classes/{classSection}`). The join code is shown to admins so they can hand it to advisers and interns.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/ClassesTest.php`:
```php
<?php

use App\Enums\ClassStatus;
use App\Models\ClassSection;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('lists classes with adviser, schedule, intern count and join code', function () {
    $adviser = User::factory()->adviser()->create(['first_name' => 'Elsie', 'last_name' => 'Isip']);
    $section = ClassSection::factory()->for($adviser, 'adviser')->create(['course_code' => 'CC101', 'section' => 'SBIT-4C', 'join_code' => 'SBIT4C26', 'school_year' => '2025-2026', 'day' => 'Monday']);
    User::factory()->intern()->count(2)->create()->each(fn ($u) => $u->internProfile()->update(['class_section_id' => $section->id]));
    ClassSection::factory()->unassigned()->create(['course_code' => 'IT401', 'school_year' => '2024-2025']);

    $this->actingAs($this->admin)->get(route('admin.classes.index'))
        ->assertOk()->assertSee('CC101')->assertSee('SBIT-4C')->assertSee('Elsie Isip')->assertSee('SBIT4C26')->assertSee('No adviser')->assertSee('Monday');
    $this->actingAs($this->admin)->get(route('admin.classes.index', ['q' => 'it401']))->assertSee('IT401')->assertDontSee('CC101');
    $this->actingAs($this->admin)->get(route('admin.classes.index', ['year' => '2025-2026']))->assertSee('CC101')->assertDontSee('IT401');
});

it('filters archived classes', function () {
    ClassSection::factory()->create(['course_code' => 'LIVE1']);
    ClassSection::factory()->archived()->create(['course_code' => 'OLD99']);

    $this->actingAs($this->admin)->get(route('admin.classes.index'))->assertSee('LIVE1')->assertSee('OLD99');
    $this->actingAs($this->admin)->get(route('admin.classes.index', ['status' => ClassStatus::Archived->value]))->assertSee('OLD99')->assertDontSee('LIVE1');
});

it('shows the roster with hours and links to interns', function () {
    $section = ClassSection::factory()->create(['course_code' => 'CC101', 'section' => 'SBIT-4C']);
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $intern->internProfile()->update(['class_section_id' => $section->id, 'student_number' => '21-0001', 'total_hours' => 88]);

    $this->actingAs($this->admin)->get(route('admin.classes.show', $section))
        ->assertOk()->assertSee('CC101 · SBIT-4C')->assertSee('Maria Santos')->assertSee('21-0001')->assertSee('88')
        ->assertSee(route('admin.interns.show', $intern));
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=ClassesTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Controller and routes**

`app/Http/Controllers/Admin/ClassSectionController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClassStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassSectionController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $status = ClassStatus::tryFrom((string) $request->query('status'));
        $year = (string) $request->query('year');

        $classes = ClassSection::query()
            ->with('adviser')
            ->withCount('internProfiles')
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->where('course_code', 'like', "%{$q}%")->orWhere('subject', 'like', "%{$q}%")
                ->orWhere('section', 'like', "%{$q}%")->orWhere('join_code', 'like', "%{$q}%")))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($year !== '', fn (Builder $query) => $query->where('school_year', $year))
            ->orderByDesc('school_year')->orderBy('course_code')->orderBy('section')
            ->paginate(20);

        return view('admin.classes.index', [
            'classes' => $classes,
            'years' => ClassSection::query()->distinct()->orderByDesc('school_year')->pluck('school_year'),
        ]);
    }

    public function show(ClassSection $classSection): View
    {
        $classSection->load(['adviser', 'internProfiles.user'])->loadCount(['folders', 'announcements']);

        return view('admin.classes.show', [
            'class' => $classSection,
            'profiles' => $classSection->internProfiles->sortBy(fn ($p) => $p->user->last_name)->values(),
        ]);
    }
}
```

Routes (admin group):
```php
        Route::get('classes', [Admin\ClassSectionController::class, 'index'])->name('classes.index');
        Route::get('classes/{classSection}', [Admin\ClassSectionController::class, 'show'])->name('classes.show');
```

- [ ] **Step 4: Views**

`resources/views/admin/classes/index.blade.php`:
```blade
<x-layouts.app title="Classes">
    <x-page-header title="Classes" subtitle="Practicum class sections. Advisers claim a class with its join code; interns join with the same code." />

    <x-card :padding="false">
        <div class="border-b border-stone-200/80 p-4 dark:border-stone-800">
            <x-search-form :action="route('admin.classes.index')" placeholder="Course code, subject, section or join code">
                <select name="year" class="input sm:w-40" aria-label="School year">
                    <option value="">All years</option>
                    @foreach ($years as $year)<option value="{{ $year }}" @selected(request('year') === $year)>{{ $year }}</option>@endforeach
                </select>
                <select name="status" class="input sm:w-36" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach (\App\Enums\ClassStatus::options() as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach
                </select>
            </x-search-form>
        </div>
        <x-table>
            <x-slot:head><th>Class</th><th>Adviser</th><th>Schedule</th><th>School year</th><th>Interns</th><th>Join code</th><th>Status</th><th class="sr-only">Actions</th></x-slot:head>
            @forelse ($classes as $class)
                <tr>
                    <td class="font-medium">{{ $class->display_name }}<p class="text-xs font-normal text-stone-500">{{ $class->subject }}</p></td>
                    <td>{{ $class->adviser?->name ?? 'No adviser' }}</td>
                    <td>{{ $class->schedule_label }}</td>
                    <td>{{ $class->school_year }}</td>
                    <td class="tabular-nums">{{ $class->intern_profiles_count }}</td>
                    <td><code class="rounded bg-stone-100 px-1.5 py-0.5 font-mono text-xs dark:bg-stone-800">{{ $class->join_code }}</code></td>
                    <td><x-badge :status="$class->status" /></td>
                    <td class="text-right"><a href="{{ route('admin.classes.show', $class) }}" class="btn-ghost px-3 py-1.5">View</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state title="No classes yet" description="Import classes from Excel or let advisers create them." icon="heroicon-o-rectangle-group" /></td></tr>
            @endforelse
        </x-table>
        <div class="p-4"><x-pagination :paginator="$classes" /></div>
    </x-card>
</x-layouts.app>
```

`resources/views/admin/classes/show.blade.php`:
```blade
<x-layouts.app :title="$class->display_name">
    <x-page-header :title="$class->display_name" :subtitle="$class->subject.' · '.$class->schedule_label.' · '.$class->school_year" :breadcrumbs="['Classes' => route('admin.classes.index'), $class->display_name => null]">
        <x-slot:actions><x-badge :status="$class->status" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Class">
            <x-detail-list class="sm:grid-cols-1" :items="[
                'Adviser' => $class->adviser?->name ?? 'No adviser',
                'Join code' => $class->join_code,
                'Folders' => $class->folders_count,
                'Announcements' => $class->announcements_count,
                'Created' => $class->created_at->format('M j, Y'),
            ]" />
        </x-card>

        <x-card title="Roster" :subtitle="$profiles->count().' interns'" class="lg:col-span-2" :padding="false">
            <x-table>
                <x-slot:head><th>Intern</th><th>Student no.</th><th>Hours</th><th>Status</th></x-slot:head>
                @forelse ($profiles as $profile)
                    <tr>
                        <td><a href="{{ route('admin.interns.show', $profile->user) }}" class="font-medium hover:underline">{{ $profile->user->name }}</a></td>
                        <td class="font-mono text-xs">{{ $profile->student_number }}</td>
                        <td class="tabular-nums">{{ $profile->total_hours }} h</td>
                        <td><x-badge :status="$profile->user->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state title="No interns enrolled" class="py-8" /></td></tr>
                @endforelse
            </x-table>
        </x-card>
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=ClassesTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add admin class section list and roster"
```

---

### Task 10: Departments CRUD

**Files:**
- Create: `app/Http/Requests/Admin/DepartmentRequest.php`, `app/Http/Controllers/Admin/DepartmentController.php`, `resources/views/admin/departments/index.blade.php`
- Modify: `app/Models/Department.php` (add `placements()` relation), `routes/web.php`
- Test: `tests/Feature/Admin/DepartmentsTest.php`

**Interfaces:**
- Produces: `admin.departments.index` (GET), `admin.departments.store` (POST), `admin.departments.update` (PUT `/admin/departments/{department}`), `admin.departments.destroy` (DELETE). `Department::placements(): HasMany`. Departments are a plain lookup written directly by the controller through validated data (no Action: a single-column lookup has no business rule). Deleting a department nulls `placements.department_id` (FK `nullOnDelete`), which is intended.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/DepartmentsTest.php`:
```php
<?php

use App\Models\Department;
use App\Models\Placement;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('lists departments with usage counts and manages them', function () {
    $it = Department::factory()->create(['name' => 'Information Technology']);
    Placement::factory()->count(2)->create(['department_id' => $it->id]);

    $this->actingAs($this->admin)->get(route('admin.departments.index'))->assertOk()->assertSee('Information Technology')->assertSee('2');

    $this->actingAs($this->admin)->post(route('admin.departments.store'), ['name' => 'Finance'])->assertRedirect()->assertSessionHas('success');
    expect(Department::where('name', 'Finance')->exists())->toBeTrue();

    $this->actingAs($this->admin)->put(route('admin.departments.update', $it), ['name' => 'IT & Systems'])->assertRedirect();
    expect($it->refresh()->name)->toBe('IT & Systems');

    $this->actingAs($this->admin)->delete(route('admin.departments.destroy', $it))->assertRedirect()->assertSessionHas('success');
    expect(Department::find($it->id))->toBeNull()
        ->and(Placement::whereNull('department_id')->count())->toBe(2);
});

it('requires unique names, ignoring the record being edited', function () {
    $a = Department::factory()->create(['name' => 'Finance']);
    Department::factory()->create(['name' => 'Marketing']);

    $this->actingAs($this->admin)->post(route('admin.departments.store'), ['name' => 'finance'])->assertSessionHasErrors('name');
    $this->actingAs($this->admin)->put(route('admin.departments.update', $a), ['name' => 'Marketing'])->assertSessionHasErrors('name');
    $this->actingAs($this->admin)->put(route('admin.departments.update', $a), ['name' => 'Finance'])->assertSessionHasNoErrors();
});

it('is admin-only', function () {
    $this->actingAs(User::factory()->adviser()->create())->get(route('admin.departments.index'))->assertForbidden();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=DepartmentsTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Model, request, controller, routes**

Add to `app/Models/Department.php` (import `HasMany`):
```php
    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }
```

`app/Http/Requests/Admin/DepartmentRequest.php`:
```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                // Case-insensitive uniqueness on both SQLite and MySQL, ignoring the record being renamed.
                function (string $attribute, mixed $value, Closure $fail) {
                    $exists = Department::query()
                        ->whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])
                        ->when($this->route('department'), fn ($q, $dept) => $q->whereKeyNot($dept->id))
                        ->exists();
                    if ($exists) {
                        $fail('A department with this name already exists.');
                    }
                },
            ],
        ];
    }
}
```
(Imports for this file: `App\Models\Department`, `Closure`, `Illuminate\Foundation\Http\FormRequest`; `Rule` is not needed.)

`app/Http/Controllers/Admin/DepartmentController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('admin.departments.index', [
            'departments' => Department::withCount('placements')->orderBy('name')->get(),
        ]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        $department = Department::create(['name' => $request->validated('name')]);

        return back()->with('success', "{$department->name} was added.");
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update(['name' => $request->validated('name')]);

        return back()->with('success', "{$department->name} was renamed.");
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return back()->with('success', "{$department->name} was removed. Interns assigned to it are now unassigned.");
    }
}
```

Routes (admin group):
```php
        Route::get('departments', [Admin\DepartmentController::class, 'index'])->name('departments.index');
        Route::post('departments', [Admin\DepartmentController::class, 'store'])->name('departments.store');
        Route::put('departments/{department}', [Admin\DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('departments/{department}', [Admin\DepartmentController::class, 'destroy'])->name('departments.destroy');
```

- [ ] **Step 4: View**

`resources/views/admin/departments/index.blade.php`:
```blade
<x-layouts.app title="Departments">
    <x-page-header title="Departments" subtitle="The units a company can assign an intern to. Shared across all companies." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Add department">
            <form method="POST" action="{{ route('admin.departments.store') }}" class="space-y-4">
                @csrf
                <x-form.input name="name" label="Name" placeholder="Information Technology" required />
                <x-button class="w-full" icon="heroicon-o-plus">Add</x-button>
            </form>
        </x-card>

        <x-card title="All departments" class="lg:col-span-2" :padding="false">
            <x-table>
                <x-slot:head><th>Name</th><th>Placements</th><th class="sr-only">Actions</th></x-slot:head>
                @forelse ($departments as $department)
                    <tr x-data="{ editing: false }">
                        <td>
                            <span x-show="!editing" class="font-medium">{{ $department->name }}</span>
                            <form x-cloak x-show="editing" method="POST" action="{{ route('admin.departments.update', $department) }}" class="flex items-center gap-2">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $department->name }}" class="input py-1.5" required aria-label="Department name">
                                <x-button class="py-1.5">Save</x-button>
                                <x-button type="button" variant="ghost" class="py-1.5" @click="editing = false">Cancel</x-button>
                            </form>
                        </td>
                        <td class="tabular-nums">{{ $department->placements_count }}</td>
                        <td>
                            <div class="flex justify-end gap-1" x-show="!editing">
                                <button type="button" class="btn-ghost px-3 py-1.5" @click="editing = true">Rename</button>
                                <x-confirm-form :action="route('admin.departments.destroy', $department)" method="DELETE" confirm="Remove {{ $department->name }}? Interns assigned to it will become unassigned.">
                                    <button class="btn-ghost px-3 py-1.5 text-rose-600">Remove</button>
                                </x-confirm-form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3"><x-empty-state title="No departments yet" /></td></tr>
                @endforelse
            </x-table>
        </x-card>
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=DepartmentsTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add department management for admins"
```

---

### Task 11: Excel infrastructure, import templates and the Imports page

**Files:**
- Create: `app/Services/Excel/SpreadsheetReader.php`, `app/Services/Excel/TemplateBuilder.php`, `app/Support/ImportColumns.php`, `app/Support/ImportResult.php`, `app/Http/Controllers/Admin/ImportController.php` (index + template; `store` is added in Task 12), `resources/views/admin/imports/index.blade.php`
- Modify: `composer.json` (via `composer require phpoffice/phpspreadsheet`), `routes/web.php`
- Test: `tests/Unit/Services/SpreadsheetReaderTest.php`, `tests/Unit/Services/TemplateBuilderTest.php`, `tests/Feature/Admin/ImportsPageTest.php`

**Interfaces:**
- Produces: `SpreadsheetReader::read(string $path): \Illuminate\Support\Collection` of rows; each row is an assoc array with normalized header keys (`trim`, lower-case, inner whitespace → `_`, e.g. `"First Name "` → `first_name`) plus `_row` (1-based spreadsheet row number); cell values are trimmed strings or `null`; rows where every value is empty are skipped; only the first worksheet is read. `TemplateBuilder::build(array $headers, array $example, string $sheetTitle): string` returns the path of a temp `.xlsx` with a bold header row and one example row. `ImportColumns` constants: `INTERNS`, `ADVISERS`, `CLASSES` (header lists) and `EXAMPLES` (one example row per type) and `TYPES = ['interns','advisers','classes']`. `ImportResult` (`created`, `errors` keyed by row number → list of messages, `addError(int $row, string $message)`, `failed(): bool`). Routes: `admin.imports.index` (GET `/admin/imports`), `admin.imports.template` (GET `/admin/imports/templates/{type}`, 404 for unknown type).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Services/SpreadsheetReaderTest.php`:
```php
<?php

use App\Services\Excel\SpreadsheetReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function writeSheet(array $rows): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
    $path = tempnam(sys_get_temp_dir(), 'wiis').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

it('normalizes headers, trims values, skips blank rows and numbers rows', function () {
    $path = writeSheet([
        ['First Name ', 'EMAIL', 'Student Number'],
        [' Maria ', 'maria@example.com', '21-0001'],
        ['', '', ''],
        ['Pedro', ' pedro@example.com', null],
    ]);

    $rows = (new SpreadsheetReader)->read($path);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['first_name' => 'Maria', 'email' => 'maria@example.com', 'student_number' => '21-0001', '_row' => 2])
        ->and($rows[1])->toBe(['first_name' => 'Pedro', 'email' => 'pedro@example.com', 'student_number' => null, '_row' => 4]);
});

it('returns an empty collection for a header-only sheet', function () {
    expect((new SpreadsheetReader)->read(writeSheet([['first_name', 'email']])))->toBeEmpty();
});
```

`tests/Unit/Services/TemplateBuilderTest.php`:
```php
<?php

use App\Services\Excel\SpreadsheetReader;
use App\Services\Excel\TemplateBuilder;
use App\Support\ImportColumns;

it('builds a template whose headers round-trip through the reader', function () {
    $path = (new TemplateBuilder)->build(ImportColumns::INTERNS, ImportColumns::EXAMPLES['interns'], 'Interns');

    expect($path)->toEndWith('.xlsx')->and(file_exists($path))->toBeTrue();

    $rows = (new SpreadsheetReader)->read($path);
    expect($rows)->toHaveCount(1)
        ->and(array_keys($rows[0]))->toBe([...ImportColumns::INTERNS, '_row'])
        ->and($rows[0]['email'])->toBe(ImportColumns::EXAMPLES['interns'][3]);
});
```

`tests/Feature/Admin/ImportsPageTest.php`:
```php
<?php

use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

it('shows the three import cards with template links', function () {
    $this->actingAs($this->admin)->get(route('admin.imports.index'))
        ->assertOk()->assertSee('Interns')->assertSee('Advisers')->assertSee('Classes')
        ->assertSee(route('admin.imports.template', 'interns'))
        ->assertSee(route('admin.imports.template', 'classes'));
});

it('downloads an xlsx template per type and 404s for unknown types', function (string $type) {
    $this->actingAs($this->admin)->get(route('admin.imports.template', $type))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertDownload("wiis-{$type}-template.xlsx");
})->with(['interns', 'advisers', 'classes']);

it('rejects unknown template types', function () {
    $this->actingAs($this->admin)->get(route('admin.imports.template', 'payroll'))->assertNotFound();
});
```

- [ ] **Step 2: Install the library, run tests to verify they fail**

Run: `composer require phpoffice/phpspreadsheet --no-interaction` (resolves to the latest version supporting PHP 8.2; needs the `gd`/`zip` extensions, which XAMPP 8.2 and the CI workflow already enable).

Run: `php artisan test --filter="SpreadsheetReaderTest|TemplateBuilderTest|ImportsPageTest"`
Expected: FAIL — classes/routes missing.

- [ ] **Step 3: Support classes and services**

`app/Support/ImportColumns.php`:
```php
<?php

namespace App\Support;

class ImportColumns
{
    public const TYPES = ['interns', 'advisers', 'classes'];

    public const INTERNS = ['first_name', 'middle_name', 'last_name', 'email', 'phone', 'student_number', 'section', 'school_year'];

    public const ADVISERS = ['first_name', 'middle_name', 'last_name', 'email', 'phone'];

    public const CLASSES = ['course_code', 'subject', 'section', 'day', 'starts_at', 'ends_at', 'school_year', 'adviser_member_no'];

    public const EXAMPLES = [
        'interns' => ['Maria', 'Reyes', 'Santos', 'maria.santos@example.com', '09171234567', '21-0001', 'SBIT-4C', '2025-2026'],
        'advisers' => ['Elsie', '', 'Isip', 'elsie.isip@example.com', '09181234567'],
        'classes' => ['CC101', 'Practicum', 'SBIT-4C', 'Monday', '08:00', '12:00', '2025-2026', 'ADV-2026-00001'],
    ];

    /** @return array<int, string> */
    public static function headersFor(string $type): array
    {
        return match ($type) {
            'interns' => self::INTERNS,
            'advisers' => self::ADVISERS,
            'classes' => self::CLASSES,
            default => abort(404),
        };
    }
}
```

`app/Support/ImportResult.php`:
```php
<?php

namespace App\Support;

class ImportResult
{
    /** @param  array<int, array<int, string>>  $errors  row number => messages */
    public function __construct(public int $created = 0, public array $errors = []) {}

    public function addError(int $row, string $message): void
    {
        $this->errors[$row][] = $message;
    }

    public function failed(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<int, string> flattened "Row N: message" lines, sorted by row */
    public function messages(): array
    {
        ksort($this->errors);
        $lines = [];
        foreach ($this->errors as $row => $messages) {
            foreach ($messages as $message) {
                $lines[] = "Row {$row}: {$message}";
            }
        }

        return $lines;
    }

    /** @return array{created:int, errors:array<int,string>} session-safe shape */
    public function toArray(): array
    {
        return ['created' => $this->created, 'errors' => $this->messages()];
    }
}
```

`app/Services/Excel/SpreadsheetReader.php`:
```php
<?php

namespace App\Services\Excel;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SpreadsheetReader
{
    /**
     * Read the first worksheet into rows keyed by normalized header.
     * Row 1 is the header. Blank rows are skipped. `_row` holds the 1-based sheet row.
     *
     * @return Collection<int, array<string, string|int|null>>
     */
    public function read(string $path): Collection
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getSheet(0);

        $raw = $sheet->toArray(null, true, true, false); // formatted values, 0-indexed
        $headers = array_map(fn ($h) => $this->normalizeHeader((string) $h), $raw[0] ?? []);

        return collect(array_slice($raw, 1, null, true))
            ->map(function (array $cells, int $index) use ($headers) {
                $row = [];
                foreach ($headers as $i => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $value = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
                    $row[$header] = $value === '' ? null : $value;
                }
                $row['_row'] = $index + 1;

                return $row;
            })
            ->reject(fn (array $row) => collect($row)->except('_row')->filter(fn ($v) => $v !== null)->isEmpty())
            ->values();
    }

    private function normalizeHeader(string $header): string
    {
        return (string) Str::of($header)->trim()->lower()->replaceMatches('/[\s\-]+/', '_');
    }
}
```

`app/Services/Excel/TemplateBuilder.php`:
```php
<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TemplateBuilder
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $example  one example row, same order as $headers
     * @return string absolute path of a temporary .xlsx file
     */
    public function build(array $headers, array $example, string $sheetTitle): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));
        $sheet->fromArray([$headers, $example], null, 'A1');
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'wiis-template').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
```

- [ ] **Step 4: Controller, routes, view**

`app/Http/Controllers/Admin/ImportController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Excel\TemplateBuilder;
use App\Support\ImportColumns;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    public function index(): View
    {
        return view('admin.imports.index', [
            'types' => [
                'interns' => ['title' => 'Interns', 'blurb' => 'Creates intern accounts, enrolls them in their class by section name, and emails each one a temporary password.', 'columns' => ImportColumns::INTERNS],
                'advisers' => ['title' => 'Advisers', 'blurb' => 'Creates adviser accounts and emails each one a temporary password.', 'columns' => ImportColumns::ADVISERS],
                'classes' => ['title' => 'Classes', 'blurb' => 'Creates class sections with a generated join code. Optionally assigns an adviser by member number.', 'columns' => ImportColumns::CLASSES],
            ],
            'result' => session('import_result'),
        ]);
    }

    public function template(string $type, TemplateBuilder $templates): BinaryFileResponse
    {
        abort_unless(in_array($type, ImportColumns::TYPES, true), 404);

        $path = $templates->build(ImportColumns::headersFor($type), ImportColumns::EXAMPLES[$type], ucfirst($type));

        return response()->download($path, "wiis-{$type}-template.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
```

Routes (admin group):
```php
        Route::get('imports', [Admin\ImportController::class, 'index'])->name('imports.index');
        Route::get('imports/templates/{type}', [Admin\ImportController::class, 'template'])->name('imports.template');
```

`resources/views/admin/imports/index.blade.php` (the upload form posts to `admin.imports.store`, added in Task 12; guard it with `Route::has`):
```blade
<x-layouts.app title="Imports">
    <x-page-header title="Excel imports" subtitle="Download a template, fill one row per record, then upload it. Imports are all-or-nothing: if any row has an error, nothing is created." />

    @if ($result)
        <x-card :title="$result['errors'] ? 'Import failed' : 'Import complete'" class="{{ $result['errors'] ? 'border-rose-200 dark:border-rose-900' : 'border-emerald-200 dark:border-emerald-900' }}">
            @if ($result['errors'])
                <p class="text-sm text-stone-600 dark:text-stone-400">Fix the rows below and upload the file again. No records were created.</p>
                <ul class="mt-3 max-h-72 list-inside list-disc space-y-1 overflow-y-auto text-sm text-rose-700 dark:text-rose-300">
                    @foreach ($result['errors'] as $line)<li>{{ $line }}</li>@endforeach
                </ul>
            @else
                <p class="text-sm">{{ $result['created'] }} record(s) created. Credential emails are being sent in the background.</p>
            @endif
        </x-card>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        @foreach ($types as $type => $meta)
            <x-card :title="$meta['title']" :subtitle="$meta['blurb']">
                <p class="text-xs font-medium uppercase tracking-wide text-stone-500">Columns</p>
                <p class="mt-1 font-mono text-xs text-stone-600 dark:text-stone-400">{{ implode(', ', $meta['columns']) }}</p>
                <a href="{{ route('admin.imports.template', $type) }}" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-brand-700 hover:underline dark:text-brand-300"><x-heroicon-o-arrow-down-tray class="size-4" /> Download template</a>
                @if (Route::has('admin.imports.store'))
                    <form method="POST" action="{{ route('admin.imports.store', $type) }}" enctype="multipart/form-data" class="mt-5 space-y-3 border-t border-stone-200/80 pt-5 dark:border-stone-800">
                        @csrf
                        <x-form.file name="file" label="Spreadsheet (.xlsx)" accept=".xlsx,.xls" required />
                        <x-button class="w-full" icon="heroicon-o-arrow-up-tray">Import {{ strtolower($meta['title']) }}</x-button>
                    </form>
                @endif
            </x-card>
        @endforeach
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="SpreadsheetReaderTest|TemplateBuilderTest|ImportsPageTest"`
Expected: PASS (7 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add Excel import templates and the admin imports page"
```

---

### Task 12: Import interns

**Files:**
- Create: `app/Actions/ImportInterns.php`, `app/Http/Requests/Admin/ImportRequest.php`
- Modify: `app/Http/Controllers/Admin/ImportController.php` (add `store`), `routes/web.php`
- Test: `tests/Unit/Actions/ImportInternsTest.php`, `tests/Feature/Admin/ImportInternsFeatureTest.php`

**Interfaces:**
- Produces: `ImportInterns::__invoke(\Illuminate\Support\Collection $rows): ImportResult` — rows from `SpreadsheetReader`; validates every row first (required `first_name`, `last_name`, `email` (valid, unique in DB and in the file, case-insensitive), `student_number` (unique in DB and file), `section` (must match exactly one ACTIVE class section by `section` name, case-insensitive)); on any error returns the result with no writes; otherwise, in one transaction, creates each intern (role intern, `INT-` member number, random 12-char password, active) with an `InternProfile` (`student_number`, `class_section_id`, `school_year` from the row or the class), then sends `AccountCredentials` to each new user after commit. `ImportRequest`: `file` required, `mimes:xlsx,xls`, `max:5120`. Route `admin.imports.store` (POST `/admin/imports/{type}`; 404 for unknown type; Task 13 adds the other two types to the controller's `match`). The controller flashes `import_result` (array from `ImportResult::toArray()`) plus `success` or `error`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/ImportInternsTest.php`:
```php
<?php

use App\Actions\ImportInterns;
use App\Enums\Role;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\AccountCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function internRow(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Santos', 'email' => 'maria@example.com',
        'phone' => '09171234567', 'student_number' => '21-0001', 'section' => 'SBIT-4C', 'school_year' => null, '_row' => 2,
    ], $overrides);
}

beforeEach(function () {
    Notification::fake();
    $this->section = ClassSection::factory()->create(['section' => 'SBIT-4C', 'school_year' => '2025-2026']);
});

it('creates interns in their class and emails credentials', function () {
    $result = app(ImportInterns::class)(collect([
        internRow(),
        internRow(['first_name' => 'Pedro', 'email' => 'PEDRO@Example.com', 'student_number' => '21-0002', 'section' => 'sbit-4c', 'school_year' => '2024-2025', '_row' => 3]),
    ]));

    expect($result->failed())->toBeFalse()->and($result->created)->toBe(2);
    $maria = User::where('email', 'maria@example.com')->firstOrFail();
    $pedro = User::where('email', 'pedro@example.com')->firstOrFail();
    expect($maria->role)->toBe(Role::Intern)
        ->and($maria->member_no)->toStartWith('INT-')
        ->and($maria->internProfile->class_section_id)->toBe($this->section->id)
        ->and($maria->internProfile->school_year)->toBe('2025-2026')
        ->and($pedro->internProfile->school_year)->toBe('2024-2025')
        ->and($pedro->internProfile->student_number)->toBe('21-0002');
    Notification::assertSentTo([$maria, $pedro], AccountCredentials::class);
});

it('fails the whole import when two rows share an email or student number', function () {
    $result = app(ImportInterns::class)(collect([
        internRow(['_row' => 2]),
        internRow(['email' => 'Maria@Example.com', 'student_number' => '21-0009', '_row' => 3]),
        internRow(['email' => 'other@example.com', 'student_number' => '21-0001', '_row' => 4]),
    ]));

    expect($result->failed())->toBeTrue()
        ->and(array_keys($result->errors))->toContain(3, 4)
        ->and(User::ofRole(Role::Intern)->count())->toBe(0);
    Notification::assertNothingSent();
});

it('reports unknown sections, taken emails and missing fields by row', function () {
    User::factory()->intern()->create(['email' => 'taken@example.com']);

    $result = app(ImportInterns::class)(collect([
        internRow(['section' => 'NOPE-1A', '_row' => 2]),
        internRow(['email' => 'taken@example.com', 'student_number' => '21-0003', '_row' => 3]),
        internRow(['last_name' => null, 'email' => 'x@example.com', 'student_number' => '21-0004', '_row' => 4]),
    ]));

    expect($result->failed())->toBeTrue()
        ->and(implode(' ', $result->errors[2]))->toContain('section')
        ->and(implode(' ', $result->errors[3]))->toContain('email')
        ->and(implode(' ', $result->errors[4]))->toContain('last name')
        ->and(User::ofRole(Role::Intern)->count())->toBe(1);
});

it('rejects a section name that matches more than one active class', function () {
    ClassSection::factory()->create(['section' => 'SBIT-4C', 'school_year' => '2024-2025']);

    $result = app(ImportInterns::class)(collect([internRow()]));

    expect($result->failed())->toBeTrue()->and(implode(' ', $result->errors[2]))->toContain('more than one');
});
```

`tests/Feature/Admin/ImportInternsFeatureTest.php`:
```php
<?php

use App\Models\ClassSection;
use App\Models\User;
use App\Support\ImportColumns;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function uploadFromRows(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'interns.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    ClassSection::factory()->create(['section' => 'SBIT-4C']);
});

it('imports a valid spreadsheet and reports the count', function () {
    $file = uploadFromRows([ImportColumns::INTERNS, ['Ana', '', 'Cruz', 'ana@example.com', '0917', '22-0001', 'SBIT-4C', '']]);

    $this->actingAs($this->admin)->post(route('admin.imports.store', 'interns'), ['file' => $file])
        ->assertRedirect(route('admin.imports.index'))->assertSessionHas('success')->assertSessionHas('import_result.created', 1);

    expect(User::where('email', 'ana@example.com')->exists())->toBeTrue();
    $this->actingAs($this->admin)->get(route('admin.imports.index'))->assertSee('1 record(s) created');
});

it('shows row errors and creates nothing on a bad file', function () {
    $file = uploadFromRows([ImportColumns::INTERNS, ['Ana', '', 'Cruz', 'not-an-email', '', '22-0001', 'SBIT-4C', '']]);

    $this->actingAs($this->admin)->post(route('admin.imports.store', 'interns'), ['file' => $file])
        ->assertRedirect(route('admin.imports.index'))->assertSessionHas('error');

    expect(User::where('first_name', 'Ana')->exists())->toBeFalse();
    $this->actingAs($this->admin)->get(route('admin.imports.index'))->assertSee('Row 2');
});

it('rejects non-spreadsheet uploads and unknown types', function () {
    $this->actingAs($this->admin)->post(route('admin.imports.store', 'interns'), ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('file');
    $this->actingAs($this->admin)->post(route('admin.imports.store', 'payroll'), ['file' => uploadFromRows([['a']])])->assertNotFound();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="ImportInternsTest|ImportInternsFeatureTest"`
Expected: FAIL — class/route missing.

- [ ] **Step 3: Request and action**

`app/Http/Requests/Admin/ImportRequest.php`:
```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120']];
    }
}
```

`app/Actions/ImportInterns.php`:
```php
<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\ClassSection;
use App\Models\InternProfile;
use App\Models\User;
use App\Notifications\AccountCredentials;
use App\Services\MemberNumberGenerator;
use App\Support\ImportResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportInterns
{
    public function __construct(private readonly MemberNumberGenerator $memberNumbers) {}

    /** @param  Collection<int, array<string, mixed>>  $rows */
    public function __invoke(Collection $rows): ImportResult
    {
        $result = new ImportResult;
        $sections = $this->activeSectionsByName();
        $seenEmails = [];
        $seenStudentNumbers = [];
        $prepared = [];

        foreach ($rows as $row) {
            $rowNo = (int) ($row['_row'] ?? 0);
            $email = Str::lower(trim((string) ($row['email'] ?? '')));
            $studentNumber = trim((string) ($row['student_number'] ?? ''));
            $sectionKey = Str::lower(trim((string) ($row['section'] ?? '')));

            $validator = Validator::make([
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'email' => $email ?: null,
                'student_number' => $studentNumber ?: null,
                'section' => $sectionKey ?: null,
                'school_year' => $row['school_year'] ?? null,
            ], [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'student_number' => ['required', 'string', 'max:30', 'unique:intern_profiles,student_number'],
                'section' => ['required', 'string'],
                'school_year' => ['nullable', 'string', 'max:20'],
            ], [], ['first_name' => 'first name', 'last_name' => 'last name', 'student_number' => 'student number']);

            foreach ($validator->errors()->all() as $message) {
                $result->addError($rowNo, $message);
            }

            if ($email !== '' && isset($seenEmails[$email])) {
                $result->addError($rowNo, "Duplicate email in this file (also on row {$seenEmails[$email]}).");
            }
            if ($studentNumber !== '' && isset($seenStudentNumbers[$studentNumber])) {
                $result->addError($rowNo, "Duplicate student number in this file (also on row {$seenStudentNumbers[$studentNumber]}).");
            }
            $seenEmails[$email] ??= $rowNo;
            $seenStudentNumbers[$studentNumber] ??= $rowNo;

            $section = null;
            if ($sectionKey !== '') {
                $matches = $sections->get($sectionKey, collect());
                if ($matches->isEmpty()) {
                    $result->addError($rowNo, "No active class has the section \"{$row['section']}\".");
                } elseif ($matches->count() > 1) {
                    $result->addError($rowNo, "The section \"{$row['section']}\" matches more than one active class; archive the old one first.");
                } else {
                    $section = $matches->first();
                }
            }

            $prepared[] = [
                'first_name' => trim((string) $row['first_name']),
                'middle_name' => filled($row['middle_name'] ?? null) ? trim((string) $row['middle_name']) : null,
                'last_name' => trim((string) $row['last_name']),
                'email' => $email,
                'phone' => filled($row['phone'] ?? null) ? trim((string) $row['phone']) : null,
                'student_number' => $studentNumber,
                'school_year' => filled($row['school_year'] ?? null) ? trim((string) $row['school_year']) : $section?->school_year,
                'section' => $section,
            ];
        }

        if ($result->failed()) {
            return $result;
        }

        $credentials = DB::transaction(function () use ($prepared) {
            $created = [];
            foreach ($prepared as $data) {
                $password = Str::password(12, symbols: false);
                $user = User::create([
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => $password,
                    'role' => Role::Intern,
                    'member_no' => $this->memberNumbers->generate(Role::Intern),
                    'status' => AccountStatus::Active,
                ]);
                InternProfile::create([
                    'user_id' => $user->id,
                    'student_number' => $data['student_number'],
                    'class_section_id' => $data['section']->id,
                    'school_year' => $data['school_year'],
                ]);
                $created[] = [$user, $password];
            }

            return $created;
        });

        foreach ($credentials as [$user, $password]) {
            $user->notify(new AccountCredentials($password));
        }

        $result->created = count($credentials);

        return $result;
    }

    /** @return Collection<string, Collection<int, ClassSection>> lower-cased section name => matching active classes */
    private function activeSectionsByName(): Collection
    {
        return ClassSection::query()->active()->get(['id', 'section', 'school_year'])
            ->groupBy(fn (ClassSection $s) => Str::lower(trim($s->section)));
    }
}
```

- [ ] **Step 4: Controller `store` and route**

Add to `app/Http/Controllers/Admin/ImportController.php` (imports: `ImportInterns`, `ImportRequest`, `SpreadsheetReader`, `ImportResult`, `RedirectResponse`):
```php
    public function store(ImportRequest $request, string $type, SpreadsheetReader $reader): RedirectResponse
    {
        abort_unless(in_array($type, ImportColumns::TYPES, true), 404);

        $rows = $reader->read($request->file('file')->getRealPath());

        /** @var ImportResult $result */
        $result = match ($type) {
            'interns' => app(ImportInterns::class)($rows),
            // Task 13 adds: 'advisers' => app(ImportAdvisers::class)($rows), 'classes' => app(ImportClasses::class)($rows),
            default => abort(404),
        };

        return redirect()->route('admin.imports.index')
            ->with('import_result', $result->toArray())
            ->with($result->failed() ? 'error' : 'success', $result->failed()
                ? 'The import was not applied because some rows have errors.'
                : "{$result->created} {$type} imported. Credential emails are queued.");
    }
```

Route (admin group):
```php
        Route::post('imports/{type}', [Admin\ImportController::class, 'store'])->name('imports.store');
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="ImportInternsTest|ImportInternsFeatureTest|ImportsPageTest"`
Expected: PASS (10 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: import interns from Excel with emailed credentials"
```

---

### Task 13: Import advisers and classes

**Files:**
- Create: `app/Actions/ImportAdvisers.php`, `app/Actions/ImportClasses.php`
- Modify: `app/Http/Controllers/Admin/ImportController.php` (`match` arms)
- Test: `tests/Unit/Actions/ImportAdvisersTest.php`, `tests/Unit/Actions/ImportClassesTest.php`, `tests/Feature/Admin/ImportOthersFeatureTest.php`

**Interfaces:**
- Produces: `ImportAdvisers::__invoke(Collection $rows): ImportResult` (required first/last name, email valid + unique in DB and file; creates advisers via the same recipe as `CreateAdviser` — `ADV-` member number, random password, `AccountCredentials` after commit). `ImportClasses::__invoke(Collection $rows): ImportResult` (required `course_code`, `subject`, `section`, `day` ∈ Monday…Sunday (case-insensitive), `starts_at`/`ends_at` parseable times (`08:00`, `8:00 AM`, `08:00:00`) with end after start, `school_year` matching `^\d{4}-\d{4}$`, optional `adviser_member_no` that must belong to an active adviser; a class with the same `course_code`+`section`+`school_year` (case-insensitive) must not already exist as ACTIVE nor repeat in the file; creates `ClassSection`s with a `JoinCodeGenerator` code, status active, times stored as `H:i:s`; when an adviser is assigned, also writes a `ClassAdviserLog` with `joined_at` now).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/ImportAdvisersTest.php`:
```php
<?php

use App\Actions\ImportAdvisers;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\AccountCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('creates advisers and emails credentials', function () {
    Notification::fake();

    $result = app(ImportAdvisers::class)(collect([
        ['first_name' => 'Elsie', 'middle_name' => null, 'last_name' => 'Isip', 'email' => 'Elsie@Example.com', 'phone' => '0918', '_row' => 2],
        ['first_name' => 'Ramon', 'middle_name' => 'D', 'last_name' => 'Cruz', 'email' => 'ramon@example.com', 'phone' => null, '_row' => 3],
    ]));

    expect($result->failed())->toBeFalse()->and($result->created)->toBe(2);
    $elsie = User::where('email', 'elsie@example.com')->firstOrFail();
    expect($elsie->role)->toBe(Role::Adviser)->and($elsie->member_no)->toStartWith('ADV-');
    Notification::assertSentTo($elsie, AccountCredentials::class);
});

it('fails on duplicate or taken emails and missing names without creating anything', function () {
    Notification::fake();
    User::factory()->intern()->create(['email' => 'taken@example.com']);

    $result = app(ImportAdvisers::class)(collect([
        ['first_name' => 'A', 'last_name' => 'B', 'email' => 'dup@example.com', '_row' => 2],
        ['first_name' => 'C', 'last_name' => 'D', 'email' => 'DUP@example.com', '_row' => 3],
        ['first_name' => 'E', 'last_name' => null, 'email' => 'taken@example.com', '_row' => 4],
    ]));

    expect($result->failed())->toBeTrue()
        ->and(array_keys($result->errors))->toBe([3, 4])
        ->and(User::ofRole(Role::Adviser)->count())->toBe(0);
    Notification::assertNothingSent();
});
```

`tests/Unit/Actions/ImportClassesTest.php`:
```php
<?php

use App\Actions\ImportClasses;
use App\Enums\ClassStatus;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function classRow(array $overrides = []): array
{
    return array_merge([
        'course_code' => 'CC101', 'subject' => 'Practicum', 'section' => 'SBIT-4C', 'day' => 'monday',
        'starts_at' => '8:00 AM', 'ends_at' => '12:00', 'school_year' => '2025-2026', 'adviser_member_no' => null, '_row' => 2,
    ], $overrides);
}

it('creates classes with join codes, normalized times and optional adviser', function () {
    $adviser = User::factory()->adviser()->create(['member_no' => 'ADV-2026-00001']);

    $result = app(ImportClasses::class)(collect([
        classRow(['adviser_member_no' => 'adv-2026-00001']),
        classRow(['course_code' => 'IT401', 'section' => 'SBIT-4A', 'day' => 'Friday', 'starts_at' => '13:00:00', 'ends_at' => '17:00', '_row' => 3]),
    ]));

    expect($result->failed())->toBeFalse()->and($result->created)->toBe(2);
    $cc = ClassSection::where('course_code', 'CC101')->firstOrFail();
    expect($cc->day)->toBe('Monday')
        ->and($cc->starts_at)->toBe('08:00:00')->and($cc->ends_at)->toBe('12:00:00')
        ->and($cc->join_code)->toHaveLength(8)
        ->and($cc->status)->toBe(ClassStatus::Active)
        ->and($cc->adviser_id)->toBe($adviser->id)
        ->and(ClassAdviserLog::where('class_section_id', $cc->id)->where('adviser_id', $adviser->id)->exists())->toBeTrue()
        ->and(ClassSection::where('course_code', 'IT401')->value('adviser_id'))->toBeNull();
});

it('rejects bad days, times, school years, unknown advisers and duplicates', function () {
    ClassSection::factory()->create(['course_code' => 'CC101', 'section' => 'SBIT-4C', 'school_year' => '2025-2026']);

    $result = app(ImportClasses::class)(collect([
        classRow(['_row' => 2]),                                                     // duplicates an existing active class
        classRow(['section' => 'SBIT-4B', 'day' => 'Funday', '_row' => 3]),
        classRow(['section' => 'SBIT-4D', 'starts_at' => '13:00', 'ends_at' => '09:00', '_row' => 4]),
        classRow(['section' => 'SBIT-4E', 'school_year' => '2025', '_row' => 5]),
        classRow(['section' => 'SBIT-4F', 'adviser_member_no' => 'ADV-0000-00000', '_row' => 6]),
        classRow(['section' => 'SBIT-4G', '_row' => 7]),
        classRow(['section' => 'sbit-4g', '_row' => 8]),                            // duplicates row 7 in the file
    ]));

    expect($result->failed())->toBeTrue()
        ->and(array_keys($result->errors))->toBe([2, 3, 4, 5, 6, 8])
        ->and(ClassSection::count())->toBe(1);
});
```

`tests/Feature/Admin/ImportOthersFeatureTest.php`:
```php
<?php

use App\Models\ClassSection;
use App\Models\User;
use App\Support\ImportColumns;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function uploadRows(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'upload.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
});

it('imports advisers through the UI', function () {
    $this->actingAs($this->admin)->post(route('admin.imports.store', 'advisers'), ['file' => uploadRows([ImportColumns::ADVISERS, ['Elsie', '', 'Isip', 'elsie@example.com', '0918']])])
        ->assertRedirect(route('admin.imports.index'))->assertSessionHas('success');

    expect(User::where('email', 'elsie@example.com')->value('role'))->toBe(\App\Enums\Role::Adviser);
});

it('imports classes through the UI', function () {
    $this->actingAs($this->admin)->post(route('admin.imports.store', 'classes'), ['file' => uploadRows([ImportColumns::CLASSES, ImportColumns::EXAMPLES['classes']])])
        ->assertRedirect(route('admin.imports.index'))->assertSessionHas('error'); // example adviser member no does not exist

    expect(ClassSection::count())->toBe(0);

    $row = ImportColumns::EXAMPLES['classes'];
    $row[7] = '';
    $this->actingAs($this->admin)->post(route('admin.imports.store', 'classes'), ['file' => uploadRows([ImportColumns::CLASSES, $row])])
        ->assertSessionHas('success');
    expect(ClassSection::where('course_code', 'CC101')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="ImportAdvisersTest|ImportClassesTest|ImportOthersFeatureTest"`
Expected: FAIL — classes missing / 404.

- [ ] **Step 3: Actions**

`app/Actions/ImportAdvisers.php`:
```php
<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\AccountCredentials;
use App\Services\MemberNumberGenerator;
use App\Support\ImportResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportAdvisers
{
    public function __construct(private readonly MemberNumberGenerator $memberNumbers) {}

    /** @param  Collection<int, array<string, mixed>>  $rows */
    public function __invoke(Collection $rows): ImportResult
    {
        $result = new ImportResult;
        $seen = [];
        $prepared = [];

        foreach ($rows as $row) {
            $rowNo = (int) ($row['_row'] ?? 0);
            $email = Str::lower(trim((string) ($row['email'] ?? '')));

            $validator = Validator::make([
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'email' => $email ?: null,
            ], [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            ], [], ['first_name' => 'first name', 'last_name' => 'last name']);

            foreach ($validator->errors()->all() as $message) {
                $result->addError($rowNo, $message);
            }
            if ($email !== '' && isset($seen[$email])) {
                $result->addError($rowNo, "Duplicate email in this file (also on row {$seen[$email]}).");
            }
            $seen[$email] ??= $rowNo;

            $prepared[] = [
                'first_name' => trim((string) $row['first_name']),
                'middle_name' => filled($row['middle_name'] ?? null) ? trim((string) $row['middle_name']) : null,
                'last_name' => trim((string) $row['last_name']),
                'email' => $email,
                'phone' => filled($row['phone'] ?? null) ? trim((string) $row['phone']) : null,
            ];
        }

        if ($result->failed()) {
            return $result;
        }

        $credentials = DB::transaction(function () use ($prepared) {
            $created = [];
            foreach ($prepared as $data) {
                $password = Str::password(12, symbols: false);
                $created[] = [User::create([
                    ...$data,
                    'password' => $password,
                    'role' => Role::Adviser,
                    'member_no' => $this->memberNumbers->generate(Role::Adviser),
                    'status' => AccountStatus::Active,
                ]), $password];
            }

            return $created;
        });

        foreach ($credentials as [$user, $password]) {
            $user->notify(new AccountCredentials($password));
        }

        $result->created = count($credentials);

        return $result;
    }
}
```

`app/Actions/ImportClasses.php`:
```php
<?php

namespace App\Actions;

use App\Enums\ClassStatus;
use App\Enums\Role;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use App\Services\JoinCodeGenerator;
use App\Support\ImportResult;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportClasses
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function __construct(private readonly JoinCodeGenerator $codes) {}

    /** @param  Collection<int, array<string, mixed>>  $rows */
    public function __invoke(Collection $rows): ImportResult
    {
        $result = new ImportResult;
        $existing = ClassSection::query()->active()->get(['course_code', 'section', 'school_year'])
            ->map(fn (ClassSection $c) => $this->identity($c->course_code, $c->section, $c->school_year))->flip();
        $advisers = User::ofRole(Role::Adviser)->active()->get(['id', 'member_no'])->keyBy(fn (User $u) => Str::upper($u->member_no));
        $seen = [];
        $prepared = [];

        foreach ($rows as $row) {
            $rowNo = (int) ($row['_row'] ?? 0);
            $data = [
                'course_code' => Str::upper(trim((string) ($row['course_code'] ?? ''))),
                'subject' => trim((string) ($row['subject'] ?? '')),
                'section' => Str::upper(trim((string) ($row['section'] ?? ''))),
                'school_year' => trim((string) ($row['school_year'] ?? '')),
            ];

            foreach (['course_code' => 'course code', 'subject' => 'subject', 'section' => 'section', 'school_year' => 'school year'] as $key => $label) {
                if ($data[$key] === '') {
                    $result->addError($rowNo, "The {$label} is required.");
                }
            }
            if ($data['school_year'] !== '' && ! preg_match('/^\d{4}-\d{4}$/', $data['school_year'])) {
                $result->addError($rowNo, 'The school year must look like 2025-2026.');
            }

            $day = Str::lower(trim((string) ($row['day'] ?? '')));
            if (! in_array($day, self::DAYS, true)) {
                $result->addError($rowNo, 'The day must be Monday to Sunday.');
            }
            $data['day'] = ucfirst($day);

            $starts = $this->parseTime($row['starts_at'] ?? null);
            $ends = $this->parseTime($row['ends_at'] ?? null);
            if (! $starts || ! $ends) {
                $result->addError($rowNo, 'Start and end times must be valid times such as 08:00 or 1:00 PM.');
            } elseif ($ends <= $starts) {
                $result->addError($rowNo, 'The end time must be after the start time.');
            }
            $data['starts_at'] = $starts?->format('H:i:s');
            $data['ends_at'] = $ends?->format('H:i:s');

            $memberNo = Str::upper(trim((string) ($row['adviser_member_no'] ?? '')));
            $data['adviser_id'] = null;
            if ($memberNo !== '') {
                $adviser = $advisers->get($memberNo);
                if (! $adviser) {
                    $result->addError($rowNo, "No active adviser has the member number {$memberNo}.");
                }
                $data['adviser_id'] = $adviser?->id;
            }

            $identity = $this->identity($data['course_code'], $data['section'], $data['school_year']);
            if ($existing->has($identity)) {
                $result->addError($rowNo, "An active class {$data['course_code']} {$data['section']} ({$data['school_year']}) already exists.");
            } elseif (isset($seen[$identity])) {
                $result->addError($rowNo, "Duplicate class in this file (also on row {$seen[$identity]}).");
            }
            $seen[$identity] ??= $rowNo;

            $prepared[] = $data;
        }

        if ($result->failed()) {
            return $result;
        }

        DB::transaction(function () use ($prepared) {
            foreach ($prepared as $data) {
                $section = ClassSection::create([
                    ...$data,
                    'join_code' => $this->codes->generate('class_sections', 'join_code'),
                    'status' => ClassStatus::Active,
                ]);
                if ($data['adviser_id']) {
                    ClassAdviserLog::create(['class_section_id' => $section->id, 'adviser_id' => $data['adviser_id'], 'joined_at' => now()]);
                }
            }
        });

        $result->created = count($prepared);

        return $result;
    }

    private function identity(string $courseCode, string $section, string $schoolYear): string
    {
        return Str::lower("{$courseCode}|{$section}|{$schoolYear}");
    }

    private function parseTime(mixed $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
```

Note on spreadsheet time cells: `SpreadsheetReader` reads formatted values, so a true Excel time cell arrives as text like `8:00` or `08:00:00`, which `Carbon::parse` accepts.

- [ ] **Step 4: Wire the controller**

In `ImportController::store`, replace the `match` with:
```php
        $result = match ($type) {
            'interns' => app(ImportInterns::class)($rows),
            'advisers' => app(ImportAdvisers::class)($rows),
            'classes' => app(ImportClasses::class)($rows),
            default => abort(404),
        };
```
(and import the two new actions). The flash message for classes should not mention credential emails: change the success message to `"{$result->created} {$type} imported."` followed by `' Credential emails are queued.'` only when `$type !== 'classes'`.

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="Import"`
Expected: PASS (all import tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: import advisers and classes from Excel"
```

---

### Task 14: Documentation, seed data and phase wrap-up

**Files:**
- Modify: `README.md`, `CLAUDE.md`
- Check only (no change expected): `.env.example` already has `QUEUE_CONNECTION=database` and `MAIL_MAILER=log`
- Verify: full suite, Pint, build, `migrate:fresh --seed`, route list.

- [ ] **Step 1: README**

In `README.md`:
- Roadmap: tick Phase 2 — "Admin portal: user management, company approvals, partner companies, classes, departments, Excel imports, charts".
- Add a short **Admin imports** subsection under Features or Getting started: download the template from Admin → Imports, one row per record, section names must match an active class, imports are all-or-nothing, credential emails are sent through the queue (`composer dev` runs a worker; otherwise `php artisan queue:work`), and with `MAIL_MAILER=log` the emails land in `storage/logs/laravel.log`.
- In "Architecture notes" add one bullet: business-rule refusals throw `DomainRuleViolation` and render as a flash error.

- [ ] **Step 2: CLAUDE.md**

Under Architecture add: "**Admin portal**: controllers in `App\Http\Controllers\Admin`, admin-wide access via the `role:admin` group (no per-record policy needed); imports are `App\Actions\Import*` returning `App\Support\ImportResult`, all-or-nothing; spreadsheet I/O in `App\Services\Excel`." Under Commands add: "Queue worker for credential emails: `php artisan queue:work` (or `composer dev`)."

- [ ] **Step 3: Final verification**

Run:
```bash
php artisan test
vendor/bin/pint --test
npm run build
php artisan migrate:fresh --seed
php artisan route:list --except-vendor --name=admin.
```
Expected: all green; the admin route list contains dashboard, interns.index/show, advisers.index/create/store/show, companies.index/pending/show/approve/reject, users.disable/reactivate, archive.index, partners.index/create/store/edit/update/destroy, classes.index/show, departments.index/store/update/destroy, imports.index/template/store.

Then start `php artisan serve` and walk the admin portal as `admin@wiis.test`: dashboard charts render; interns/advisers/companies/partners/classes/departments pages open; approve `pending@wiis.test` from the Pending tab; disable and reactivate `intern2@wiis.test`; download the interns template, add one row with section `SBIT-4C`, upload it and see the success card; log in as the imported intern using the password from `storage/logs/laravel.log`.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "docs: document the admin portal and Excel imports"
```

---

## Spec coverage check (Phase 2 scope)

| Spec item | Task |
|---|---|
| Dashboard counts + 3 charts (placed/unplaced, with/without class, active/disabled per role) | 2 |
| Interns index with search/filter/pagination + show | 3 |
| Advisers index/show (+ manual add with emailed credentials, as in the legacy app) | 4 |
| Companies index/show | 5 |
| Pending company approvals with permit/MOA viewer, approve/reject | 6 |
| Disable/reactivate + history (archive) list | 7 |
| Partner (COS) companies CRUD | 8 |
| Class sections list | 9 |
| Departments CRUD (simple) | 10 |
| Excel imports (3 types) with downloadable templates | 11, 12, 13 |
| Queued credential emails | 4 (notification), 12, 13 |
| Admin navigation, reusable list components (search, pagination, tabs, detail list, chart) | 1 |

Decisions recorded from the Phase 1 final review: deletion of companies is only offered for partner companies and only when unused (restrict FK + `DomainRuleViolation`); registered companies are never deleted, only disabled. An approved company may still rename itself (left as is; revisit if the office asks for re-verification).
