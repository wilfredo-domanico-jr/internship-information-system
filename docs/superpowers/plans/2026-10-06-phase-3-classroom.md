# WIIS Phase 3 — Classroom Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give advisers and interns a Google-Classroom-style class module: advisers manage their classes (create, edit, claim by join code, leave), post rich-text announcements, run a documents area with lockable folders and a submission review queue, share resources and see their interns' OJT hours with a printable roster; interns join a class by code, follow the stream, comment, upload documents into folders (flagged late when the folder is locked) and track their submissions.

**Architecture:** Same layering as Phases 1–2: thin controllers under `App\Http\Controllers\{Adviser,Intern}` → Form Requests (validation **and** per-record authorization via `authorize()`) → single-purpose `App\Actions` → models. Unlike the admin portal, every classroom record is scoped to a class, so authorization is a Policy on each model (`ClassSection`, `Announcement`, `AnnouncementComment`, `ClassFolder`, `ClassSubmission`, `ClassResource`), all built on three membership helpers on `ClassSection` (`isAdvisedBy`, `enrolls`, `hasMember`). Comments are identical for both roles, so one shared controller (`App\Http\Controllers\Classroom\CommentController`) is registered in both route groups. Rich text comes from Trix and is cleaned by `App\Services\HtmlSanitizer` (HTML Purifier) on save **and** on render. Uploaded PDFs stay on the private `local` disk and are served by the existing `FileController` through two new `PrivateFiles` kinds. Business-rule refusals throw `DomainRuleViolation` (already rendered as a flash error).

**Tech Stack:** Laravel 12, PHP 8.2, Pest 3, Blade + Tailwind 4 + Alpine (existing design system), Trix 2 (npm), `ezyang/htmlpurifier` (composer), database notifications.

**Spec:** `docs/superpowers/specs/2026-10-03-wiis-laravel-rebuild-design.md` — section "Phase 3 — Classroom", the Classroom bullet under "Business rules", the classroom tables under "Data model", and the Policy/Action lists under "Auth, roles, architecture".

## Global Constraints

- Phase 2 is complete (HEAD `b0268f0`, 251 tests green). Do not rename any existing route, component prop, enum value, model method or factory state. Existing routes this phase builds on: `adviser.dashboard`, `intern.dashboard`, `files.show` (`kind`, `id`), `notifications.*`, `admin.classes.*`.
- Layers: controllers validate → call an Action → redirect/render. No model writes with raw request input. Every Action gets a unit test under `tests/Unit/Actions/`; every new route gets a feature test including a role-isolation case.
- Authorization: every classroom route that touches a record checks a Policy — in the Form Request's `authorize()` when there is a request, otherwise `Gate::authorize()` in the controller. Never rely on the `role:` middleware alone for record access.
- Membership rule (verbatim from the spec): an adviser "claims a class via join code (blocked if it already has an adviser)"; an intern "joins a class via join code (one class per intern)"; "uploads into locked folders are accepted but flagged late"; "Submissions: pending → approved/declined with a note"; "leave logs history".
- Status columns stay `string(20)` cast to `App\Enums\SubmissionStatus` / `ClassStatus`. Thresholds come from `config('wiis.hours.*')` through `OjtHoursService`; never literal in `app/` or Blade.
- Uploads: PDF only, `mimetypes:application/pdf`, `max:`.`config('wiis.uploads.max_pdf_kb')` (5120 KB), stored on the `local` disk under `classroom/{class_section_id}/...`, served only via `files.show`. Delete the file from disk whenever its record is deleted.
- Rich text: only `App\Services\HtmlSanitizer` may turn user HTML into stored HTML; Blade renders stored HTML only through `<x-rich-text>` (which sanitizes again). `{!! !!}` appears nowhere else.
- Strict Eloquent mode is on outside production: eager-load every relation used inside a Blade loop. Single models (auth user, route-bound models) may lazy-load.
- Blade attributes: dynamic text inside component-tag attributes must be a bound expression (`:title="..."`), never `{{ }}` inside the attribute string.
- Route names follow `adviser.<resource>.<action>` / `intern.<resource>.<action>`; nav items are added through `App\Support\Navigation::for()` and guarded by `Route::has()`.
- Commits: conventional prefixes (`feat:`, `fix:`, `refactor:`, `test:`, `chore:`), short descriptive messages, directly on `main`, **never any Claude attribution**. Run `vendor/bin/pint --dirty` before each commit.
- Shell: Windows Git Bash; long heredocs fail, so write files with the editor/Write tool. Work only inside `D:\Programming_Application\xampp-7.4.1\htdocs\internship-information-system`.

## Review Focus

1. **An announcement body containing `<script>`, `onclick=` or `javascript:` links** must be stored without them and never rendered as code. Pinned in Task 1 (`HtmlSanitizer` unit test) and Task 7 (feature test posts a hostile body and asserts the stored and rendered HTML are clean).
2. **Uploading into a locked folder** must be accepted and flagged late, and unlocking the folder afterwards must not clear that flag. Pinned in Task 10.
3. **An adviser who left a class** must lose every write on it (403 on edit, post, folders) even for announcements they authored, and the class must be claimable by another adviser. Pinned in Task 4 and Task 2 (policy test).
4. **Deleting a folder or a pending submission** must remove the PDF from disk, and an intern must not be able to delete a submission once it is reviewed. Pinned in Task 9 and Task 10.
5. **An intern who already has an active class** entering another valid join code must be refused with a message, not silently moved; an archived class's code must not work for anyone. Pinned in Task 6 (intern) and Task 4 (adviser).

## File Structure (what Phase 3 creates)

```
app/
  Actions/{CreateClassSection,UpdateClassSection,ClaimClass,LeaveClass,JoinClass,
           PostAnnouncement,UpdateAnnouncement,AddComment,
           CreateFolder,ToggleFolderLock,DeleteFolder,
           SubmitClassDocument,DeleteSubmission,ReviewClassSubmission,
           AddClassResource,DeleteClassResource}.php
  Http/Controllers/Adviser/{ClassSectionController,AnnouncementController,FolderController,
           SubmissionReviewController,ResourceController}.php
  Http/Controllers/Intern/{ClassController,FolderController,SubmissionController}.php
  Http/Controllers/Classroom/CommentController.php
  Http/Requests/Adviser/{ClassSectionRequest,FolderRequest,DeclineSubmissionRequest,ResourceRequest}.php
  Http/Requests/Intern/SubmitDocumentRequest.php
  Http/Requests/Classroom/{JoinClassRequest,AnnouncementRequest,CommentRequest}.php
  Models/ClassSection.php (membership helpers + submissions relation)
  Notifications/{AnnouncementPosted,AnnouncementCommented,ClassDocumentSubmitted,ClassSubmissionReviewed}.php
  Notifications/InternJoinedClass.php (url → people tab)
  Policies/{ClassSectionPolicy,AnnouncementPolicy,AnnouncementCommentPolicy,ClassFolderPolicy,
            ClassSubmissionPolicy,ClassResourcePolicy}.php
  Providers/AppServiceProvider.php (HtmlSanitizer singleton)
  Services/HtmlSanitizer.php
  Support/Navigation.php (adviser + intern items), Support/PrivateFiles.php (2 kinds)
resources/css/app.css (Trix + rich-text styles)
resources/js/app.js (Trix import, attachments disabled)
resources/views/components/{rich-text,form/editor,layouts/print}.blade.php
resources/views/components/layouts/partials/nav-item.blade.php (array `active`)
resources/views/classroom/announcement.blade.php (shared stream card)
resources/views/adviser/classes/{index,create,edit,_form,show,people,print,documents}.blade.php
resources/views/adviser/classes/partials/header.blade.php
resources/views/adviser/announcements/edit.blade.php
resources/views/adviser/folders/show.blade.php
resources/views/adviser/dashboard.blade.php (links)
resources/views/intern/class/{join,show,people,documents}.blade.php
resources/views/intern/class/partials/header.blade.php
resources/views/intern/folders/show.blade.php
resources/views/intern/submissions/index.blade.php
resources/views/intern/dashboard.blade.php (links)
routes/web.php (adviser + intern groups grow)
database/seeders/DemoSeeder.php (comments, submissions, resource, placeholder PDF)
tests/Feature/Adviser/*.php, tests/Feature/Intern/*.php, tests/Feature/Classroom*.php,
tests/Unit/Actions/*.php, tests/Unit/Policies/ClassroomPoliciesTest.php, tests/Unit/Services/HtmlSanitizerTest.php
README.md, CLAUDE.md
```

Task order: 1 rich text + nav + print layout → 2 membership helpers, policies, file kinds → 3 adviser classes CRUD → 4 claim/leave → 5 class page shell, people, print → 6 intern join + class page → 7 announcements → 8 comments → 9 folders (adviser) → 10 intern submissions → 11 review queue → 12 resources → 13 isolation tests, seed, docs, wrap-up.

---

### Task 1: Rich text (Trix + sanitizer), print layout, and classroom navigation

**Files:**
- Create: `app/Services/HtmlSanitizer.php`
- Create: `resources/views/components/form/editor.blade.php`, `resources/views/components/rich-text.blade.php`, `resources/views/components/layouts/print.blade.php`
- Modify: `app/Providers/AppServiceProvider.php`, `app/Support/Navigation.php`, `resources/views/components/layouts/partials/nav-item.blade.php`, `resources/js/app.js`, `resources/css/app.css`, `composer.json`, `package.json`
- Test: `tests/Unit/Services/HtmlSanitizerTest.php`, `tests/Feature/ClassroomComponentsTest.php`, `tests/Feature/Admin/NavigationTest.php`

**Interfaces:**
- Consumes: `App\Support\Navigation::for()` (Phase 2), `x-form.label`, `x-form.error`, `x-layouts.base`.
- Produces: `HtmlSanitizer::clean(?string): string`, `HtmlSanitizer::isBlank(?string): bool`; `<x-form.editor name label value required hint placeholder>`; `<x-rich-text :html>`; `<x-layouts.print :title :back>`; nav `active` may be a string **or** an array of route patterns.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Services/HtmlSanitizerTest.php`:

```php
<?php

use App\Services\HtmlSanitizer;

it('keeps the formatting Trix produces', function () {
    $html = '<div><strong>Reminder</strong> — upload by <em>Friday</em>.<br>See <a href="https://example.com/guide">the guide</a>.</div><ul><li>One</li></ul>';

    $clean = app(HtmlSanitizer::class)->clean($html);

    expect($clean)->toContain('<strong>Reminder</strong>')->toContain('<em>Friday</em>')->toContain('<br')
        ->toContain('href="https://example.com/guide"')->toContain('<li>One</li>');
});

it('strips scripts, event handlers, images and javascript links', function () {
    $html = '<p onclick="steal()">Hi<script>alert(1)</script></p><a href="javascript:alert(1)">x</a><img src=x onerror=alert(1)>';

    $clean = app(HtmlSanitizer::class)->clean($html);

    expect($clean)->not->toContain('<script')->not->toContain('onclick')->not->toContain('javascript:')->not->toContain('<img')
        ->toContain('<p>Hi</p>');
});

it('opens links in a new tab without a referrer', function () {
    $clean = app(HtmlSanitizer::class)->clean('<a href="https://example.com">x</a>');

    expect($clean)->toContain('target="_blank"')->toContain('noopener')->toContain('noreferrer')->toContain('nofollow');
});

it('detects bodies that are only whitespace or empty tags', function () {
    $sanitizer = app(HtmlSanitizer::class);

    expect($sanitizer->isBlank('<div><br></div>'))->toBeTrue()
        ->and($sanitizer->isBlank('<p>&nbsp;</p>'))->toBeTrue()
        ->and($sanitizer->isBlank(null))->toBeTrue()
        ->and($sanitizer->isBlank('<p>Hello</p>'))->toBeFalse();
});

it('is registered as a singleton', function () {
    expect(app(HtmlSanitizer::class))->toBe(app(HtmlSanitizer::class));
});
```

`tests/Feature/ClassroomComponentsTest.php`:

```php
<?php

use Illuminate\Support\Facades\Blade;

it('renders a Trix editor bound to a hidden input', function () {
    $html = Blade::render('<x-form.editor name="body" label="Announcement" value="<p>Hi</p>" />');

    expect($html)->toContain('<trix-editor')->toContain('input="body"')->toContain('type="hidden"')->toContain('name="body"')
        ->toContain('value="&lt;p&gt;Hi&lt;/p&gt;"')->toContain('Announcement');
});

it('renders rich text after sanitizing it', function () {
    $html = Blade::render('<x-rich-text :html="$body" />', ['body' => '<p>Hi</p><script>alert(1)</script>']);

    expect($html)->toContain('class="rich-text"')->toContain('<p>Hi</p>')->not->toContain('<script');
});

it('renders the print layout with a print button', function () {
    $html = Blade::render('<x-layouts.print title="Roster" back="/x">Body</x-layouts.print>');

    expect($html)->toContain('window.print()')->toContain('Body')->toContain('href="/x"')->toContain('Roster');
});

it('marks a nav item active for any of several route patterns', function () {
    $html = Blade::render('<x-layouts.partials.nav-item :item="$item" />', [
        'item' => ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'heroicon-o-bell', 'active' => ['nope.*', 'notifications.*']],
    ]);

    expect($html)->toContain('Notifications');
});
```

Replace `tests/Feature/Admin/NavigationTest.php` with:

```php
<?php

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

it('lists the admin sections once their routes exist', function () {
    foreach (['interns', 'advisers', 'companies', 'partners', 'classes', 'departments', 'imports', 'archive'] as $r) {
        Route::get("/_t/{$r}", fn () => '')->name("admin.{$r}.index");
    }
    Route::getRoutes()->refreshNameLookups();
    $admin = User::factory()->admin()->create();

    $labels = collect(Navigation::for($admin))->pluck('label')->all();

    expect($labels)->toBe(['Dashboard', 'Interns', 'Advisers', 'Companies', 'Partner companies', 'Classes', 'Departments', 'Imports', 'Archive', 'Notifications']);
});

it('lists the adviser classroom section once its route exists', function () {
    Route::get('/_t/adviser-classes', fn () => '')->name('adviser.classes.index');
    Route::getRoutes()->refreshNameLookups();

    expect(collect(Navigation::for(User::factory()->adviser()->create()))->pluck('label')->all())
        ->toBe(['Dashboard', 'My classes', 'Notifications']);
});

it('lists the intern classroom sections once their routes exist', function () {
    Route::get('/_t/intern-class', fn () => '')->name('intern.class.show');
    Route::get('/_t/intern-submissions', fn () => '')->name('intern.submissions.index');
    Route::getRoutes()->refreshNameLookups();

    expect(collect(Navigation::for(User::factory()->intern()->create()))->pluck('label')->all())
        ->toBe(['Dashboard', 'My class', 'My submissions', 'Notifications']);
});

it('does not show portal sections to roles that have none', function () {
    $company = User::factory()->company()->create();

    expect(collect(Navigation::for($company))->pluck('label')->all())->toBe(['Dashboard', 'Notifications']);
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="HtmlSanitizer|ClassroomComponents|Navigation"`
Expected: FAIL — `HtmlSanitizer` class not found, `form.editor` / `rich-text` / `layouts.print` components not found, adviser/intern nav labels missing.

- [ ] **Step 3: Install Trix and HTML Purifier**

```bash
composer require ezyang/htmlpurifier
npm install trix
```

- [ ] **Step 4: Sanitizer service and singleton**

`app/Services/HtmlSanitizer.php`:

```php
<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/** Turns user-submitted rich text (Trix output) into safe HTML. The only way HTML reaches the database. */
class HtmlSanitizer
{
    private ?HTMLPurifier $purifier = null;

    public function clean(?string $html): string
    {
        return trim($this->purifier()->purify((string) $html));
    }

    /** True when the body has no visible text after cleaning (e.g. Trix's empty "<div><br></div>"). */
    public function isBlank(?string $html): bool
    {
        $text = html_entity_decode(strip_tags($this->clean($html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{A0}", ' ', $text)) === '';
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier) {
            return $this->purifier;
        }

        $cachePath = storage_path('framework/cache/htmlpurifier');

        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('HTML.Allowed', 'p,div,br,strong,b,em,i,u,s,del,a[href],ul,ol,li,h1,h2,h3,blockquote,pre,code');
        $config->set('HTML.Nofollow', true);
        $config->set('HTML.TargetBlank', true);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);

        return $this->purifier = new HTMLPurifier($config);
    }
}
```

In `app/Providers/AppServiceProvider.php` add the import `use App\Services\HtmlSanitizer;` and in `register()`:

```php
        $this->app->singleton(HtmlSanitizer::class);
```

- [ ] **Step 5: Trix wiring and styles**

`resources/js/app.js` — add after the Chart.js import, and the attachment guard before `Alpine.start()`:

```js
import 'trix';
import 'trix/dist/trix.css';
```

```js
// Attachments are not supported: documents go through the class folders instead.
document.addEventListener('trix-file-accept', (event) => event.preventDefault());
```

`resources/css/app.css` — append inside `@layer components` (after the `.data-table` rules):

```css
    /* Trix editor: match the input styling and hide the attachment tools. */
    trix-toolbar { @apply mb-2; }
    trix-toolbar .trix-button-group { @apply overflow-hidden rounded-xl border-stone-300 dark:border-stone-700; }
    trix-toolbar .trix-button { @apply border-stone-300 dark:border-stone-700 dark:bg-stone-800; }
    trix-toolbar .trix-button-group--file-tools { display: none; }
    .dark trix-toolbar .trix-button--icon::before { filter: invert(1); }
    trix-editor:empty:not(:focus)::before { @apply text-stone-400; }

    /* Rendered rich text (announcements). */
    .rich-text { @apply text-sm leading-6 text-stone-700 dark:text-stone-200; }
    .rich-text p, .rich-text div { @apply mb-2 last:mb-0; }
    .rich-text a { @apply font-medium text-brand-700 underline dark:text-brand-300; }
    .rich-text ul { @apply mb-2 list-disc pl-5; }
    .rich-text ol { @apply mb-2 list-decimal pl-5; }
    .rich-text h1, .rich-text h2, .rich-text h3 { @apply mb-2 font-display text-base font-semibold; }
    .rich-text blockquote { @apply mb-2 border-l-2 border-stone-300 pl-3 italic dark:border-stone-700; }
    .rich-text pre { @apply mb-2 overflow-x-auto rounded-lg bg-stone-100 p-3 font-mono text-xs dark:bg-stone-800; }
    .rich-text strong { @apply font-semibold; }
```

- [ ] **Step 6: Components**

`resources/views/components/form/editor.blade.php`:

```blade
@props(['name', 'label' => null, 'value' => null, 'required' => false, 'hint' => null, 'placeholder' => 'Write something…'])
<div class="{{ $attributes->get('class') }}">
    @if ($label)<x-form.label :for="$name.'-editor'" :required="$required">{{ $label }}</x-form.label>@endif
    <input id="{{ $name }}" name="{{ $name }}" type="hidden" value="{{ old($name, $value) }}">
    <trix-editor id="{{ $name }}-editor" input="{{ $name }}" placeholder="{{ $placeholder }}"
                 {{ $attributes->except('class')->merge(['class' => 'input trix-content min-h-40'.($errors->has($name) ? ' input-error' : '')]) }}></trix-editor>
    @if ($hint)<p class="mt-1.5 text-xs text-stone-500">{{ $hint }}</p>@endif
    <x-form.error :name="$name" />
</div>
```

`resources/views/components/rich-text.blade.php`:

```blade
@props(['html'])
<div {{ $attributes->merge(['class' => 'rich-text']) }}>{!! app(\App\Services\HtmlSanitizer::class)->clean($html) !!}</div>
```

`resources/views/components/layouts/print.blade.php`:

```blade
@props(['title' => null, 'back' => null])
<x-layouts.base :title="$title" class="bg-white text-stone-900">
    <div class="mx-auto max-w-5xl p-8 print:p-0">
        <div class="mb-6 flex items-center justify-between gap-3 print:hidden">
            @if ($back)<a href="{{ $back }}" class="btn-secondary">Back</a>@else<span></span>@endif
            <button type="button" class="btn-primary" onclick="window.print()"><x-heroicon-o-printer class="size-4" /> Print</button>
        </div>
        {{ $slot }}
    </div>
</x-layouts.base>
```

`resources/views/components/layouts/partials/nav-item.blade.php` — change the `$active` line to accept an array:

```blade
    @php $active = request()->routeIs(...(array) ($item['active'] ?? $item['route'])); @endphp
```

- [ ] **Step 7: Navigation**

In `app/Support/Navigation.php` change the docblocks' `active: string` to `active: string|array<int, string>` (both methods) and replace the `return [];` at the end of `portalItems()` with:

```php
        if ($user->isAdviser()) {
            return [
                ['label' => 'My classes', 'route' => 'adviser.classes.index', 'icon' => 'heroicon-o-rectangle-group',
                    'active' => ['adviser.classes.*', 'adviser.announcements.*', 'adviser.comments.*', 'adviser.folders.*', 'adviser.submissions.*', 'adviser.resources.*']],
            ];
        }

        if ($user->isIntern()) {
            return [
                ['label' => 'My class', 'route' => 'intern.class.show', 'icon' => 'heroicon-o-rectangle-group',
                    'active' => ['intern.class.*', 'intern.comments.*', 'intern.folders.*']],
                ['label' => 'My submissions', 'route' => 'intern.submissions.index', 'icon' => 'heroicon-o-document-check', 'active' => 'intern.submissions.*'],
            ];
        }

        return [];
```

- [ ] **Step 8: Run tests and build**

Run: `php artisan test --filter="HtmlSanitizer|ClassroomComponents|Navigation"` then `npm run build`
Expected: all PASS; build succeeds and the CSS bundle contains `trix-editor`.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add rich-text editor, HTML sanitizer, print layout and classroom navigation"
```

---

### Task 2: Class membership helpers, classroom policies and private file kinds

**Files:**
- Modify: `app/Models/ClassSection.php`, `app/Support/PrivateFiles.php`
- Create: `app/Policies/{ClassSectionPolicy,AnnouncementPolicy,AnnouncementCommentPolicy,ClassFolderPolicy,ClassSubmissionPolicy,ClassResourcePolicy}.php`
- Test: `tests/Unit/Policies/ClassroomPoliciesTest.php`, `tests/Feature/ClassroomFileAccessTest.php`

**Interfaces:**
- Consumes: `FileController` + `PrivateFiles::registry()` (Phase 1), model factories.
- Produces: `ClassSection::isAdvisedBy(User): bool`, `enrolls(User): bool`, `hasMember(User): bool`; policy abilities — `ClassSection`: `view`, `manage`; `Announcement`: `view`, `comment`, `update`, `delete`; `AnnouncementComment`: `delete`; `ClassFolder`: `view`, `manage`, `submit`; `ClassSubmission`: `view`, `review`, `delete`; `ClassResource`: `view`, `delete`. File kinds `class-submission`, `class-resource` on `files.show`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Policies/ClassroomPoliciesTest.php`:

```php
<?php

use App\Enums\SubmissionStatus;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\ClassFolder;
use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
    $this->intern = User::factory()->intern()->create();
    $this->intern->internProfile->update(['class_section_id' => $this->section->id]);
    $this->otherAdviser = User::factory()->adviser()->create();
    $this->otherIntern = User::factory()->intern()->create();
    $this->admin = User::factory()->admin()->create();
});

it('knows its members', function () {
    expect($this->section->isAdvisedBy($this->adviser))->toBeTrue()
        ->and($this->section->isAdvisedBy($this->otherAdviser))->toBeFalse()
        ->and($this->section->enrolls($this->intern))->toBeTrue()
        ->and($this->section->enrolls($this->otherIntern))->toBeFalse()
        ->and($this->section->enrolls($this->adviser))->toBeFalse()
        ->and($this->section->hasMember($this->intern))->toBeTrue()
        ->and($this->section->hasMember($this->adviser))->toBeTrue()
        ->and($this->section->hasMember($this->admin))->toBeFalse();
});

it('lets members and admins view a class but only its adviser manage it', function () {
    expect($this->adviser->can('view', $this->section))->toBeTrue()
        ->and($this->intern->can('view', $this->section))->toBeTrue()
        ->and($this->admin->can('view', $this->section))->toBeTrue()
        ->and($this->otherAdviser->can('view', $this->section))->toBeFalse()
        ->and($this->otherIntern->can('view', $this->section))->toBeFalse()
        ->and($this->adviser->can('manage', $this->section))->toBeTrue()
        ->and($this->admin->can('manage', $this->section))->toBeFalse()
        ->and($this->intern->can('manage', $this->section))->toBeFalse();
});

it('limits announcement edits to the author while they still advise the class', function () {
    $announcement = Announcement::factory()->for($this->section)->for($this->adviser, 'author')->create();

    expect($this->adviser->can('update', $announcement))->toBeTrue()
        ->and($this->adviser->can('delete', $announcement))->toBeTrue()
        ->and($this->intern->can('update', $announcement))->toBeFalse()
        ->and($this->intern->can('view', $announcement))->toBeTrue()
        ->and($this->intern->can('comment', $announcement))->toBeTrue()
        ->and($this->adviser->can('comment', $announcement))->toBeTrue()
        ->and($this->otherIntern->can('comment', $announcement))->toBeFalse()
        ->and($this->admin->can('comment', $announcement))->toBeFalse();

    $this->section->update(['adviser_id' => $this->otherAdviser->id]);

    expect($this->adviser->can('update', $announcement->fresh()))->toBeFalse()
        ->and($this->otherAdviser->can('update', $announcement->fresh()))->toBeFalse();
});

it('lets comment authors and the class adviser delete comments', function () {
    $announcement = Announcement::factory()->for($this->section)->for($this->adviser, 'author')->create();
    $comment = AnnouncementComment::factory()->for($announcement)->for($this->intern, 'author')->create();

    expect($this->intern->can('delete', $comment))->toBeTrue()
        ->and($this->adviser->can('delete', $comment))->toBeTrue()
        ->and($this->otherIntern->can('delete', $comment))->toBeFalse()
        ->and($this->otherAdviser->can('delete', $comment))->toBeFalse();
});

it('guards folders, submissions and resources by class membership', function () {
    $folder = ClassFolder::factory()->for($this->section)->create();
    $submission = ClassSubmission::factory()->for($folder, 'folder')->for($this->intern, 'intern')->create();
    $resource = ClassResource::factory()->for($this->section)->for($this->adviser, 'uploader')->create();

    expect($this->intern->can('view', $folder))->toBeTrue()
        ->and($this->intern->can('submit', $folder))->toBeTrue()
        ->and($this->adviser->can('submit', $folder))->toBeFalse()
        ->and($this->adviser->can('manage', $folder))->toBeTrue()
        ->and($this->intern->can('manage', $folder))->toBeFalse()
        ->and($this->otherIntern->can('view', $folder))->toBeFalse()
        ->and($this->intern->can('view', $submission))->toBeTrue()
        ->and($this->adviser->can('view', $submission))->toBeTrue()
        ->and($this->admin->can('view', $submission))->toBeTrue()
        ->and($this->otherIntern->can('view', $submission))->toBeFalse()
        ->and($this->adviser->can('review', $submission))->toBeTrue()
        ->and($this->intern->can('review', $submission))->toBeFalse()
        ->and($this->intern->can('delete', $submission))->toBeTrue()
        ->and($this->adviser->can('delete', $submission))->toBeFalse()
        ->and($this->intern->can('view', $resource))->toBeTrue()
        ->and($this->admin->can('view', $resource))->toBeTrue()
        ->and($this->otherIntern->can('view', $resource))->toBeFalse()
        ->and($this->adviser->can('delete', $resource))->toBeTrue()
        ->and($this->intern->can('delete', $resource))->toBeFalse();

    $submission->update(['status' => SubmissionStatus::Approved]);

    expect($this->intern->can('delete', $submission->fresh()))->toBeFalse();
});
```

`tests/Feature/ClassroomFileAccessTest.php`:

```php
<?php

use App\Models\ClassFolder;
use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
    $this->intern = User::factory()->intern()->create();
    $this->intern->internProfile->update(['class_section_id' => $this->section->id]);
    $folder = ClassFolder::factory()->for($this->section)->create();
    Storage::disk('local')->put('classroom/t/sub.pdf', '%PDF-1.4 fake');
    Storage::disk('local')->put('classroom/t/res.pdf', '%PDF-1.4 fake');
    $this->submission = ClassSubmission::factory()->for($folder, 'folder')->for($this->intern, 'intern')->create(['file_path' => 'classroom/t/sub.pdf']);
    $this->resource = ClassResource::factory()->for($this->section)->for($this->adviser, 'uploader')->create(['file_path' => 'classroom/t/res.pdf']);
});

it('serves a submission to its intern, the class adviser and admins', function () {
    foreach ([$this->intern, $this->adviser, User::factory()->admin()->create()] as $user) {
        $this->actingAs($user)->get(route('files.show', ['class-submission', $this->submission->id]))
            ->assertOk()->assertHeader('content-type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    }
});

it('serves a resource to class members and admins only', function () {
    $this->actingAs($this->intern)->get(route('files.show', ['class-resource', $this->resource->id]))->assertOk();
    $this->actingAs($this->adviser)->get(route('files.show', ['class-resource', $this->resource->id]))->assertOk();
    $this->actingAs(User::factory()->intern()->create())->get(route('files.show', ['class-resource', $this->resource->id]))->assertForbidden();
});

it('forbids submissions to outsiders', function () {
    $this->actingAs(User::factory()->intern()->create())->get(route('files.show', ['class-submission', $this->submission->id]))->assertForbidden();
    $this->actingAs(User::factory()->adviser()->create())->get(route('files.show', ['class-submission', $this->submission->id]))->assertForbidden();
    $this->actingAs(User::factory()->company()->create())->get(route('files.show', ['class-submission', $this->submission->id]))->assertForbidden();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="ClassroomPolicies|ClassroomFileAccess"`
Expected: FAIL — `isAdvisedBy` undefined; `can()` returns false (no policies); file routes 404 (unknown kinds).

- [ ] **Step 3: Membership helpers**

Add to `app/Models/ClassSection.php` (after `scopeActive`):

```php
    /* Membership */

    public function isAdvisedBy(User $user): bool
    {
        return $this->adviser_id !== null && $this->adviser_id === $user->id;
    }

    public function enrolls(User $user): bool
    {
        return $user->isIntern() && $user->internProfile?->class_section_id === $this->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->isAdvisedBy($user) || $this->enrolls($user);
    }
```

- [ ] **Step 4: Policies**

`app/Policies/ClassSectionPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\ClassSection;
use App\Models\User;

class ClassSectionPolicy
{
    /** Members (adviser, enrolled interns) and admins may open the class. */
    public function view(User $user, ClassSection $section): bool
    {
        return $user->isAdmin() || $section->hasMember($user);
    }

    /** Only the current adviser may change the class or anything inside it. */
    public function manage(User $user, ClassSection $section): bool
    {
        return $section->isAdvisedBy($user);
    }
}
```

`app/Policies/AnnouncementPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function view(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin() || $announcement->classSection->hasMember($user);
    }

    public function comment(User $user, Announcement $announcement): bool
    {
        return $announcement->classSection->hasMember($user);
    }

    /** The author, and only while they still advise the class. */
    public function update(User $user, Announcement $announcement): bool
    {
        return $announcement->author_id === $user->id && $announcement->classSection->isAdvisedBy($user);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement);
    }
}
```

`app/Policies/AnnouncementCommentPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\AnnouncementComment;
use App\Models\User;

class AnnouncementCommentPolicy
{
    /** The comment's author, or the adviser moderating their class. */
    public function delete(User $user, AnnouncementComment $comment): bool
    {
        return $comment->author_id === $user->id || $comment->announcement->classSection->isAdvisedBy($user);
    }
}
```

`app/Policies/ClassFolderPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\ClassFolder;
use App\Models\User;

class ClassFolderPolicy
{
    public function view(User $user, ClassFolder $folder): bool
    {
        return $user->isAdmin() || $folder->classSection->hasMember($user);
    }

    public function manage(User $user, ClassFolder $folder): bool
    {
        return $folder->classSection->isAdvisedBy($user);
    }

    /** Enrolled interns upload; locked folders still accept uploads (flagged late by the action). */
    public function submit(User $user, ClassFolder $folder): bool
    {
        return $folder->classSection->enrolls($user);
    }
}
```

`app/Policies/ClassSubmissionPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Models\ClassSubmission;
use App\Models\User;

class ClassSubmissionPolicy
{
    public function view(User $user, ClassSubmission $submission): bool
    {
        return $user->isAdmin()
            || $submission->intern_id === $user->id
            || $submission->folder->classSection->isAdvisedBy($user);
    }

    public function review(User $user, ClassSubmission $submission): bool
    {
        return $submission->folder->classSection->isAdvisedBy($user);
    }

    /** Interns may withdraw a submission only before it is reviewed. */
    public function delete(User $user, ClassSubmission $submission): bool
    {
        return $submission->intern_id === $user->id && $submission->status === SubmissionStatus::Pending;
    }
}
```

`app/Policies/ClassResourcePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\ClassResource;
use App\Models\User;

class ClassResourcePolicy
{
    public function view(User $user, ClassResource $resource): bool
    {
        return $user->isAdmin() || $resource->classSection->hasMember($user);
    }

    public function delete(User $user, ClassResource $resource): bool
    {
        return $resource->classSection->isAdvisedBy($user);
    }
}
```

- [ ] **Step 5: Private file kinds**

In `app/Support/PrivateFiles.php` add imports `use App\Models\ClassResource; use App\Models\ClassSubmission;` and extend the registry:

```php
        return [
            'company-permit' => [Company::class, 'permit_path', 'viewDocuments'],
            'company-moa' => [Company::class, 'moa_path', 'viewDocuments'],
            'class-submission' => [ClassSubmission::class, 'file_path', 'view'],
            'class-resource' => [ClassResource::class, 'file_path', 'view'],
        ];
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter="ClassroomPolicies|ClassroomFileAccess|FileAccess|ClassroomModels"`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add classroom policies, class membership helpers and private file kinds"
```

---

### Task 3: Adviser "My classes": list, create and edit

**Files:**
- Create: `app/Actions/CreateClassSection.php`, `app/Actions/UpdateClassSection.php`, `app/Http/Requests/Adviser/ClassSectionRequest.php`, `app/Http/Controllers/Adviser/ClassSectionController.php`
- Create: `resources/views/adviser/classes/{index,create,edit,_form}.blade.php`
- Modify: `routes/web.php` (adviser group)
- Test: `tests/Feature/Adviser/ClassesTest.php`, `tests/Unit/Actions/ClassSectionActionsTest.php`

**Interfaces:**
- Consumes: `ClassSectionPolicy::manage`, `JoinCodeGenerator::generate('class_sections', 'join_code')`, `ClassAdviserLog`.
- Produces: `CreateClassSection(array $data, User $adviser): ClassSection`, `UpdateClassSection(ClassSection, array $data): ClassSection` where `$data` has keys `course_code, subject, section, day, starts_at (H:i), ends_at (H:i), school_year`; `ClassSectionRequest::DAYS`; routes `adviser.classes.index|create|store|edit|update`. Task 4 adds the join form and leave buttons to `index`; Task 5 adds `show|people|print` to the same controller (register `classes/create` **before** `classes/{classSection}`).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/ClassSectionActionsTest.php`:

```php
<?php

use App\Actions\CreateClassSection;
use App\Actions\UpdateClassSection;
use App\Enums\ClassStatus;
use App\Models\ClassAdviserLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an active class owned by the adviser with a join code and a log row', function () {
    $adviser = User::factory()->adviser()->create();

    $section = app(CreateClassSection::class)([
        'course_code' => 'CC101', 'subject' => 'Practicum', 'section' => 'SBIT-4C', 'day' => 'Monday',
        'starts_at' => '08:00', 'ends_at' => '12:00', 'school_year' => '2025-2026',
    ], $adviser);

    expect($section->adviser_id)->toBe($adviser->id)
        ->and($section->status)->toBe(ClassStatus::Active)
        ->and($section->join_code)->toHaveLength(8)
        ->and($section->starts_at)->toBe('08:00:00')
        ->and($section->ends_at)->toBe('12:00:00')
        ->and(ClassAdviserLog::where('class_section_id', $section->id)->where('adviser_id', $adviser->id)->whereNull('left_at')->count())->toBe(1);
});

it('updates the schedule without touching the join code or adviser', function () {
    $adviser = User::factory()->adviser()->create();
    $section = app(CreateClassSection::class)([
        'course_code' => 'CC101', 'subject' => 'Practicum', 'section' => 'SBIT-4C', 'day' => 'Monday',
        'starts_at' => '08:00', 'ends_at' => '12:00', 'school_year' => '2025-2026',
    ], $adviser);
    $code = $section->join_code;

    app(UpdateClassSection::class)($section, [
        'course_code' => 'IT401', 'subject' => 'Internship 1', 'section' => 'SBIT-4D', 'day' => 'Friday',
        'starts_at' => '09:30', 'ends_at' => '11:30', 'school_year' => '2026-2027',
    ]);

    $section->refresh();
    expect($section->course_code)->toBe('IT401')->and($section->day)->toBe('Friday')->and($section->starts_at)->toBe('09:30:00')
        ->and($section->join_code)->toBe($code)->and($section->adviser_id)->toBe($adviser->id);
});
```

`tests/Feature/Adviser/ClassesTest.php`:

```php
<?php

use App\Enums\ClassStatus;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;

beforeEach(fn () => $this->adviser = User::factory()->adviser()->create());

it('lists only the active classes the adviser advises', function () {
    $mine = ClassSection::factory()->for($this->adviser, 'adviser')->create(['course_code' => 'CC101', 'section' => 'SBIT-4C', 'join_code' => 'MINE0001']);
    ClassSection::factory()->create(['course_code' => 'XX999']);
    ClassSection::factory()->for($this->adviser, 'adviser')->archived()->create(['course_code' => 'OLD111']);
    User::factory()->intern()->count(2)->create()->each(fn ($u) => $u->internProfile()->update(['class_section_id' => $mine->id]));

    $this->actingAs($this->adviser)->get(route('adviser.classes.index'))
        ->assertOk()->assertSee('CC101 · SBIT-4C')->assertSee('MINE0001')->assertSee('2 interns')
        ->assertDontSee('XX999')->assertDontSee('OLD111');
});

it('creates a class with a join code and an adviser log', function () {
    $this->actingAs($this->adviser)->get(route('adviser.classes.create'))->assertOk()->assertSee('Course code');

    $this->actingAs($this->adviser)->post(route('adviser.classes.store'), [
        'course_code' => ' cc101 ', 'subject' => 'Practicum', 'section' => 'sbit-4c', 'day' => 'Monday',
        'starts_at' => '08:00', 'ends_at' => '12:00', 'school_year' => '2025-2026',
    ])->assertRedirect(route('adviser.classes.index'))->assertSessionHas('success');

    $section = ClassSection::firstOrFail();
    expect($section->course_code)->toBe('CC101')->and($section->section)->toBe('SBIT-4C')
        ->and($section->adviser_id)->toBe($this->adviser->id)->and($section->join_code)->toHaveLength(8)
        ->and($section->status)->toBe(ClassStatus::Active)
        ->and(ClassAdviserLog::where('class_section_id', $section->id)->where('adviser_id', $this->adviser->id)->whereNull('left_at')->exists())->toBeTrue();
});

it('validates the schedule and refuses duplicate active classes', function () {
    ClassSection::factory()->create(['course_code' => 'CC101', 'section' => 'SBIT-4C', 'school_year' => '2025-2026']);
    $payload = ['course_code' => 'CC101', 'subject' => 'Practicum', 'section' => 'SBIT-4C', 'day' => 'Funday', 'starts_at' => '13:00', 'ends_at' => '12:00', 'school_year' => '2025'];

    $this->actingAs($this->adviser)->post(route('adviser.classes.store'), $payload)
        ->assertSessionHasErrors(['day', 'ends_at', 'school_year']);
    $this->actingAs($this->adviser)->post(route('adviser.classes.store'), [...$payload, 'day' => 'Monday', 'ends_at' => '15:00', 'school_year' => '2025-2026'])
        ->assertSessionHasErrors('section');

    expect(ClassSection::count())->toBe(1);
});

it('edits only its own classes', function () {
    $mine = ClassSection::factory()->for($this->adviser, 'adviser')->create(['subject' => 'Practicum']);
    $other = ClassSection::factory()->create();

    $this->actingAs($this->adviser)->get(route('adviser.classes.edit', $mine))->assertOk()->assertSee('Practicum');
    $this->actingAs($this->adviser)->get(route('adviser.classes.edit', $other))->assertForbidden();
    $this->actingAs($this->adviser)->put(route('adviser.classes.update', $other), ['subject' => 'Hacked'])->assertForbidden();

    $this->actingAs($this->adviser)->put(route('adviser.classes.update', $mine), [
        'course_code' => $mine->course_code, 'subject' => 'Internship 1', 'section' => $mine->section, 'day' => 'Friday',
        'starts_at' => '09:30', 'ends_at' => '11:30', 'school_year' => $mine->school_year,
    ])->assertRedirect(route('adviser.classes.index'));

    expect($mine->refresh()->subject)->toBe('Internship 1')->and($mine->day)->toBe('Friday')->and($mine->starts_at)->toBe('09:30:00');
});

it('lets a class keep its own identity when edited', function () {
    $mine = ClassSection::factory()->for($this->adviser, 'adviser')->create(['course_code' => 'CC101', 'section' => 'SBIT-4C', 'school_year' => '2025-2026']);

    $this->actingAs($this->adviser)->put(route('adviser.classes.update', $mine), [
        'course_code' => 'CC101', 'subject' => 'Practicum', 'section' => 'SBIT-4C', 'day' => 'Monday',
        'starts_at' => '08:00', 'ends_at' => '12:00', 'school_year' => '2025-2026',
    ])->assertSessionHasNoErrors();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="ClassSectionActions|Adviser.*ClassesTest"`
Expected: FAIL — classes/actions/routes not defined.

- [ ] **Step 3: Request and actions**

`app/Http/Requests/Adviser/ClassSectionRequest.php`:

```php
<?php

namespace App\Http\Requests\Adviser;

use App\Models\ClassSection;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClassSectionRequest extends FormRequest
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function authorize(): bool
    {
        $section = $this->route('classSection');

        return $section instanceof ClassSection
            ? $this->user()->can('manage', $section)
            : ($this->user()?->isAdviser() ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'course_code' => Str::upper(trim((string) $this->input('course_code'))),
            'section' => Str::upper(trim((string) $this->input('section'))),
            'subject' => trim((string) $this->input('subject')),
            'school_year' => trim((string) $this->input('school_year')),
        ]);
    }

    public function rules(): array
    {
        return [
            'course_code' => ['required', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:255'],
            'section' => ['required', 'string', 'max:50'],
            'day' => ['required', Rule::in(self::DAYS)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'school_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $current = $this->route('classSection');

            $duplicate = ClassSection::query()->active()
                ->where('course_code', $this->input('course_code'))
                ->where('section', $this->input('section'))
                ->where('school_year', $this->input('school_year'))
                ->when($current instanceof ClassSection, fn (Builder $q) => $q->whereKeyNot($current->id))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('section', 'An active class with this course code, section and school year already exists.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'school_year.regex' => 'The school year must look like 2025-2026.',
            'ends_at.after' => 'The end time must be after the start time.',
        ];
    }
}
```

`app/Actions/CreateClassSection.php`:

```php
<?php

namespace App\Actions;

use App\Enums\ClassStatus;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use App\Services\JoinCodeGenerator;
use Illuminate\Support\Facades\DB;

class CreateClassSection
{
    public function __construct(private readonly JoinCodeGenerator $codes) {}

    /** @param  array{course_code:string, subject:string, section:string, day:string, starts_at:string, ends_at:string, school_year:string}  $data */
    public function __invoke(array $data, User $adviser): ClassSection
    {
        return DB::transaction(function () use ($data, $adviser) {
            $section = ClassSection::create([
                'adviser_id' => $adviser->id,
                'course_code' => $data['course_code'],
                'subject' => $data['subject'],
                'section' => $data['section'],
                'day' => $data['day'],
                'starts_at' => self::dbTime($data['starts_at']),
                'ends_at' => self::dbTime($data['ends_at']),
                'school_year' => $data['school_year'],
                'join_code' => $this->codes->generate('class_sections', 'join_code'),
                'status' => ClassStatus::Active,
            ]);

            ClassAdviserLog::create(['class_section_id' => $section->id, 'adviser_id' => $adviser->id, 'joined_at' => now()]);

            return $section;
        });
    }

    /** "08:00" → "08:00:00" so stored times match the Excel import format. */
    public static function dbTime(string $time): string
    {
        return strlen($time) === 5 ? "{$time}:00" : $time;
    }
}
```

`app/Actions/UpdateClassSection.php`:

```php
<?php

namespace App\Actions;

use App\Models\ClassSection;

class UpdateClassSection
{
    /** @param  array{course_code:string, subject:string, section:string, day:string, starts_at:string, ends_at:string, school_year:string}  $data */
    public function __invoke(ClassSection $section, array $data): ClassSection
    {
        $section->update([
            'course_code' => $data['course_code'],
            'subject' => $data['subject'],
            'section' => $data['section'],
            'day' => $data['day'],
            'starts_at' => CreateClassSection::dbTime($data['starts_at']),
            'ends_at' => CreateClassSection::dbTime($data['ends_at']),
            'school_year' => $data['school_year'],
        ]);

        return $section;
    }
}
```

- [ ] **Step 4: Controller, routes, views**

`app/Http/Controllers/Adviser/ClassSectionController.php`:

```php
<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\CreateClassSection;
use App\Actions\UpdateClassSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\ClassSectionRequest;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClassSectionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('adviser.classes.index', [
            'classes' => $user->advisedClasses()->active()
                ->withCount(['internProfiles', 'folders', 'announcements'])
                ->orderByDesc('school_year')->orderBy('course_code')->orderBy('section')
                ->get(),
            'past' => ClassAdviserLog::query()->with('classSection')
                ->where('adviser_id', $user->id)->whereNotNull('left_at')
                ->latest('left_at')->get(),
        ]);
    }

    public function create(): View
    {
        return view('adviser.classes.create', ['days' => ClassSectionRequest::DAYS]);
    }

    public function store(ClassSectionRequest $request, CreateClassSection $create): RedirectResponse
    {
        $section = $create($request->validated(), $request->user());

        return redirect()->route('adviser.classes.index')
            ->with('success', "{$section->display_name} was created. Share the join code {$section->join_code} with your interns.");
    }

    public function edit(ClassSection $classSection): View
    {
        Gate::authorize('manage', $classSection);

        return view('adviser.classes.edit', ['class' => $classSection, 'days' => ClassSectionRequest::DAYS]);
    }

    public function update(ClassSectionRequest $request, ClassSection $classSection, UpdateClassSection $update): RedirectResponse
    {
        $update($classSection, $request->validated());

        return redirect()->route('adviser.classes.index')->with('success', "{$classSection->display_name} was updated.");
    }
}
```

`routes/web.php` — replace the adviser group with:

```php
    Route::prefix('adviser')->name('adviser.')->middleware('role:adviser')->group(function () {
        Route::get('dashboard', Adviser\DashboardController::class)->name('dashboard');
        Route::get('classes', [Adviser\ClassSectionController::class, 'index'])->name('classes.index');
        Route::get('classes/create', [Adviser\ClassSectionController::class, 'create'])->name('classes.create');
        Route::post('classes', [Adviser\ClassSectionController::class, 'store'])->name('classes.store');
        Route::get('classes/{classSection}/edit', [Adviser\ClassSectionController::class, 'edit'])->name('classes.edit');
        Route::put('classes/{classSection}', [Adviser\ClassSectionController::class, 'update'])->name('classes.update');
    });
```

`resources/views/adviser/classes/_form.blade.php` (included with `$class` (nullable) and `$days`):

```blade
<div class="grid gap-5 sm:grid-cols-2">
    <x-form.input name="course_code" label="Course code" :value="$class?->course_code" required placeholder="CC101" />
    <x-form.input name="section" label="Section" :value="$class?->section" required placeholder="SBIT-4C" />
    <x-form.input name="subject" label="Subject" :value="$class?->subject" required placeholder="Practicum" class="sm:col-span-2" />
    <x-form.select name="day" label="Day" :options="array_combine($days, $days)" :value="$class?->day" required placeholder="Choose a day" />
    <x-form.input name="school_year" label="School year" :value="$class?->school_year" required placeholder="2025-2026" hint="Format: 2025-2026" />
    <x-form.input name="starts_at" label="Starts at" type="time" :value="$class ? substr($class->starts_at, 0, 5) : null" required />
    <x-form.input name="ends_at" label="Ends at" type="time" :value="$class ? substr($class->ends_at, 0, 5) : null" required />
</div>
```

`resources/views/adviser/classes/create.blade.php`:

```blade
<x-layouts.app title="New class">
    <x-page-header title="New class" subtitle="Create a class and share its join code with your interns." :breadcrumbs="['My classes' => route('adviser.classes.index'), 'New class' => null]" />

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('adviser.classes.store') }}" class="space-y-6">
            @csrf
            @include('adviser.classes._form', ['class' => null, 'days' => $days])
            <div class="flex items-center justify-end gap-3">
                <x-button variant="secondary" :href="route('adviser.classes.index')">Cancel</x-button>
                <x-button icon="heroicon-o-plus">Create class</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
```

`resources/views/adviser/classes/edit.blade.php`:

```blade
<x-layouts.app :title="'Edit · '.$class->display_name">
    <x-page-header :title="'Edit '.$class->display_name" :subtitle="'Join code '.$class->join_code" :breadcrumbs="['My classes' => route('adviser.classes.index'), $class->display_name => null]" />

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('adviser.classes.update', $class) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('adviser.classes._form', ['class' => $class, 'days' => $days])
            <div class="flex items-center justify-end gap-3">
                <x-button variant="secondary" :href="route('adviser.classes.index')">Cancel</x-button>
                <x-button icon="heroicon-o-check">Save changes</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
```

`resources/views/adviser/classes/index.blade.php` (Task 4 rewrites this file to add the join form, leave buttons and past classes):

```blade
<x-layouts.app title="My classes">
    <x-page-header title="My classes" subtitle="Classes you advise this school year.">
        <x-slot:actions>
            <x-button :href="route('adviser.classes.create')" icon="heroicon-o-plus">New class</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($classes->isEmpty())
        <x-card>
            <x-empty-state title="You have no classes yet" description="Create a class, or claim one the office imported by entering its join code." icon="heroicon-o-rectangle-group">
                <x-slot:action><x-button :href="route('adviser.classes.create')" icon="heroicon-o-plus">New class</x-button></x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($classes as $class)
                <x-card class="flex flex-col">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-display text-lg font-semibold">{{ $class->display_name }}</p>
                            <p class="text-sm text-stone-500">{{ $class->subject }} · {{ $class->school_year }}</p>
                            <p class="mt-1 text-sm text-stone-500">{{ $class->schedule_label }}</p>
                        </div>
                        <x-badge :status="$class->status" />
                    </div>
                    <dl class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
                        <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-900"><dt class="text-xs text-stone-500">Interns</dt><dd class="font-semibold tabular-nums">{{ $class->intern_profiles_count }}</dd></div>
                        <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-900"><dt class="text-xs text-stone-500">Folders</dt><dd class="font-semibold tabular-nums">{{ $class->folders_count }}</dd></div>
                        <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-900"><dt class="text-xs text-stone-500">Posts</dt><dd class="font-semibold tabular-nums">{{ $class->announcements_count }}</dd></div>
                    </dl>
                    <p class="mt-4 text-xs text-stone-500">{{ $class->intern_profiles_count }} interns · Join code <span class="font-mono font-semibold text-stone-800 dark:text-stone-100">{{ $class->join_code }}</span></p>
                    <div class="mt-4 flex items-center gap-2">
                        <x-button variant="secondary" :href="route('adviser.classes.edit', $class)" icon="heroicon-o-pencil-square">Edit</x-button>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="ClassSectionActions|Adviser.*ClassesTest|Navigation"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add adviser class list, create and edit"
```

---

### Task 4: Claim a class by join code and leave a class (with history)

**Files:**
- Create: `app/Actions/ClaimClass.php`, `app/Actions/LeaveClass.php`, `app/Http/Requests/Classroom/JoinClassRequest.php`
- Modify: `app/Http/Controllers/Adviser/ClassSectionController.php`, `routes/web.php`, `resources/views/adviser/classes/index.blade.php`
- Test: `tests/Unit/Actions/ClassMembershipActionsTest.php` (claim/leave parts; Task 6 appends the intern case), `tests/Feature/Adviser/ClassMembershipTest.php`

**Interfaces:**
- Consumes: `ClassSection::isAdvisedBy`, `ClassAdviserLog`, `DomainRuleViolation`.
- Produces: `ClaimClass(string $joinCode, User $adviser): ClassSection`, `LeaveClass(ClassSection, User $adviser): void`, `JoinClassRequest` (`join_code` upper-cased + trimmed; reused by the intern in Task 6), routes `adviser.classes.join` (POST `/adviser/classes/join`), `adviser.classes.leave` (POST `/adviser/classes/{classSection}/leave`).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/ClassMembershipActionsTest.php`:

```php
<?php

use App\Actions\ClaimClass;
use App\Actions\LeaveClass;
use App\Exceptions\DomainRuleViolation;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets an adviser claim an unassigned active class by code, case-insensitively', function () {
    $adviser = User::factory()->adviser()->create();
    $section = ClassSection::factory()->unassigned()->create(['join_code' => 'SBIT4C26']);

    $claimed = app(ClaimClass::class)(' sbit4c26 ', $adviser);

    expect($claimed->is($section))->toBeTrue()
        ->and($section->refresh()->adviser_id)->toBe($adviser->id)
        ->and(ClassAdviserLog::where('class_section_id', $section->id)->where('adviser_id', $adviser->id)->whereNull('left_at')->count())->toBe(1);
});

it('refuses codes that are unknown, archived or already taken', function () {
    $adviser = User::factory()->adviser()->create();
    ClassSection::factory()->create(['join_code' => 'TAKEN001']);
    ClassSection::factory()->unassigned()->archived()->create(['join_code' => 'ARCHIVED']);
    $mine = ClassSection::factory()->for($adviser, 'adviser')->create(['join_code' => 'MINE0001']);

    expect(fn () => app(ClaimClass::class)('NOPE0000', $adviser))->toThrow(DomainRuleViolation::class, 'No active class');
    expect(fn () => app(ClaimClass::class)('ARCHIVED', $adviser))->toThrow(DomainRuleViolation::class, 'No active class');
    expect(fn () => app(ClaimClass::class)('TAKEN001', $adviser))->toThrow(DomainRuleViolation::class, 'already has an adviser');
    expect(fn () => app(ClaimClass::class)('MINE0001', $adviser))->toThrow(DomainRuleViolation::class, 'already advise');
    expect(ClassAdviserLog::count())->toBe(0);
});

it('leaving unassigns the adviser and closes the log, and the class can be claimed again', function () {
    $adviser = User::factory()->adviser()->create();
    $section = ClassSection::factory()->unassigned()->create(['join_code' => 'SBIT4C26']);
    app(ClaimClass::class)('SBIT4C26', $adviser);

    app(LeaveClass::class)($section->refresh(), $adviser);

    $log = ClassAdviserLog::where('class_section_id', $section->id)->where('adviser_id', $adviser->id)->firstOrFail();
    expect($section->refresh()->adviser_id)->toBeNull()->and($log->left_at)->not->toBeNull();

    $next = User::factory()->adviser()->create();
    app(ClaimClass::class)('SBIT4C26', $next);
    expect($section->refresh()->adviser_id)->toBe($next->id)->and(ClassAdviserLog::where('class_section_id', $section->id)->count())->toBe(2);
});

it('records history even when a class had no open log row', function () {
    $adviser = User::factory()->adviser()->create();
    $section = ClassSection::factory()->for($adviser, 'adviser')->create();

    app(LeaveClass::class)($section, $adviser);

    $log = ClassAdviserLog::where('class_section_id', $section->id)->where('adviser_id', $adviser->id)->firstOrFail();
    expect($log->joined_at->equalTo($section->created_at))->toBeTrue()->and($log->left_at)->not->toBeNull();
});

it('refuses to leave a class you do not advise', function () {
    $section = ClassSection::factory()->create();

    expect(fn () => app(LeaveClass::class)($section, User::factory()->adviser()->create()))->toThrow(DomainRuleViolation::class);
    expect($section->refresh()->adviser_id)->not->toBeNull();
});
```

`tests/Feature/Adviser/ClassMembershipTest.php`:

```php
<?php

use App\Models\ClassSection;
use App\Models\User;

beforeEach(fn () => $this->adviser = User::factory()->adviser()->create());

it('claims a class from the join form and shows it in the list', function () {
    ClassSection::factory()->unassigned()->create(['join_code' => 'SBIT4C26', 'course_code' => 'CC101', 'section' => 'SBIT-4C']);

    $this->actingAs($this->adviser)->get(route('adviser.classes.index'))->assertOk()->assertSee('name="join_code"', false);

    $this->actingAs($this->adviser)->post(route('adviser.classes.join'), ['join_code' => 'sbit4c26'])
        ->assertRedirect(route('adviser.classes.index'))->assertSessionHas('success');

    $this->actingAs($this->adviser)->get(route('adviser.classes.index'))->assertSee('CC101 · SBIT-4C');
});

it('flashes an error for a taken code and validates the input', function () {
    ClassSection::factory()->create(['join_code' => 'TAKEN001']);

    $this->actingAs($this->adviser)->from(route('adviser.classes.index'))->post(route('adviser.classes.join'), ['join_code' => 'TAKEN001'])
        ->assertRedirect(route('adviser.classes.index'))->assertSessionHas('error');
    $this->actingAs($this->adviser)->post(route('adviser.classes.join'), ['join_code' => ''])->assertSessionHasErrors('join_code');
});

it('leaves a class, lists it under past classes and loses access to it', function () {
    $section = ClassSection::factory()->for($this->adviser, 'adviser')->create(['course_code' => 'CC101', 'section' => 'SBIT-4C']);

    $this->actingAs($this->adviser)->post(route('adviser.classes.leave', $section))
        ->assertRedirect(route('adviser.classes.index'))->assertSessionHas('success');

    expect($section->refresh()->adviser_id)->toBeNull();
    $this->actingAs($this->adviser)->get(route('adviser.classes.index'))->assertSee('Past classes')->assertSee('CC101 · SBIT-4C');
    $this->actingAs($this->adviser)->get(route('adviser.classes.edit', $section))->assertForbidden();
});

it('cannot leave someone else’s class', function () {
    $section = ClassSection::factory()->create();

    $this->actingAs($this->adviser)->post(route('adviser.classes.leave', $section))->assertForbidden();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="ClassMembership"`
Expected: FAIL — actions and routes missing.

- [ ] **Step 3: Request and actions**

`app/Http/Requests/Classroom/JoinClassRequest.php`:

```php
<?php

namespace App\Http\Requests\Classroom;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class JoinClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the portal's role middleware already limits who can reach it
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['join_code' => Str::upper(trim((string) $this->input('join_code')))]);
    }

    public function rules(): array
    {
        return ['join_code' => ['required', 'string', 'max:20']];
    }
}
```

`app/Actions/ClaimClass.php`:

```php
<?php

namespace App\Actions;

use App\Exceptions\DomainRuleViolation;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** An adviser takes over an imported or vacated class by entering its join code. */
class ClaimClass
{
    public function __invoke(string $joinCode, User $adviser): ClassSection
    {
        $section = ClassSection::query()->active()->where('join_code', Str::upper(trim($joinCode)))->first();

        if (! $section) {
            throw new DomainRuleViolation('No active class has that join code.');
        }

        if ($section->isAdvisedBy($adviser)) {
            throw new DomainRuleViolation("You already advise {$section->display_name}.");
        }

        if ($section->adviser_id !== null) {
            throw new DomainRuleViolation("{$section->display_name} already has an adviser.");
        }

        return DB::transaction(function () use ($section, $adviser) {
            $section->update(['adviser_id' => $adviser->id]);
            ClassAdviserLog::create(['class_section_id' => $section->id, 'adviser_id' => $adviser->id, 'joined_at' => now()]);

            return $section;
        });
    }
}
```

`app/Actions/LeaveClass.php`:

```php
<?php

namespace App\Actions;

use App\Exceptions\DomainRuleViolation;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** The adviser steps down; interns stay enrolled and the class becomes claimable again. */
class LeaveClass
{
    public function __invoke(ClassSection $section, User $adviser): void
    {
        if (! $section->isAdvisedBy($adviser)) {
            throw new DomainRuleViolation('You do not advise this class.');
        }

        DB::transaction(function () use ($section, $adviser) {
            $section->update(['adviser_id' => null]);

            $open = ClassAdviserLog::query()
                ->where('class_section_id', $section->id)->where('adviser_id', $adviser->id)->whereNull('left_at')
                ->latest('joined_at')->first();

            $open
                ? $open->update(['left_at' => now()])
                : ClassAdviserLog::create(['class_section_id' => $section->id, 'adviser_id' => $adviser->id, 'joined_at' => $section->created_at, 'left_at' => now()]);
        });
    }
}
```

- [ ] **Step 4: Controller, routes, view**

Add to `ClassSectionController` (imports: `App\Actions\ClaimClass`, `App\Actions\LeaveClass`, `App\Http\Requests\Classroom\JoinClassRequest`):

```php
    public function join(JoinClassRequest $request, ClaimClass $claim): RedirectResponse
    {
        $section = $claim($request->validated('join_code'), $request->user());

        return redirect()->route('adviser.classes.index')->with('success', "You are now the adviser of {$section->display_name}.");
    }

    public function leave(ClassSection $classSection, Request $request, LeaveClass $leave): RedirectResponse
    {
        Gate::authorize('manage', $classSection);

        $leave($classSection, $request->user());

        return redirect()->route('adviser.classes.index')->with('success', "You left {$classSection->display_name}. Another adviser can claim it with its join code.");
    }
```

Routes — add inside the adviser group, after `classes/create`:

```php
        Route::post('classes/join', [Adviser\ClassSectionController::class, 'join'])->name('classes.join');
        Route::post('classes/{classSection}/leave', [Adviser\ClassSectionController::class, 'leave'])->name('classes.leave');
```

Replace `resources/views/adviser/classes/index.blade.php`:

```blade
<x-layouts.app title="My classes">
    <x-page-header title="My classes" subtitle="Classes you advise this school year.">
        <x-slot:actions>
            <x-button :href="route('adviser.classes.create')" icon="heroicon-o-plus">New class</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($classes->isEmpty())
                <x-card>
                    <x-empty-state title="You have no classes yet" description="Create a class, or claim one the office imported by entering its join code." icon="heroicon-o-rectangle-group">
                        <x-slot:action><x-button :href="route('adviser.classes.create')" icon="heroicon-o-plus">New class</x-button></x-slot:action>
                    </x-empty-state>
                </x-card>
            @else
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($classes as $class)
                        <x-card class="flex flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-display text-lg font-semibold">
                                        @if (Route::has('adviser.classes.show'))<a href="{{ route('adviser.classes.show', $class) }}" class="hover:underline">{{ $class->display_name }}</a>@else{{ $class->display_name }}@endif
                                    </p>
                                    <p class="text-sm text-stone-500">{{ $class->subject }} · {{ $class->school_year }}</p>
                                    <p class="mt-1 text-sm text-stone-500">{{ $class->schedule_label }}</p>
                                </div>
                                <x-badge :status="$class->status" />
                            </div>
                            <dl class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
                                <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-900"><dt class="text-xs text-stone-500">Interns</dt><dd class="font-semibold tabular-nums">{{ $class->intern_profiles_count }}</dd></div>
                                <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-900"><dt class="text-xs text-stone-500">Folders</dt><dd class="font-semibold tabular-nums">{{ $class->folders_count }}</dd></div>
                                <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-900"><dt class="text-xs text-stone-500">Posts</dt><dd class="font-semibold tabular-nums">{{ $class->announcements_count }}</dd></div>
                            </dl>
                            <p class="mt-4 text-xs text-stone-500">{{ $class->intern_profiles_count }} interns · Join code <span class="font-mono font-semibold text-stone-800 dark:text-stone-100">{{ $class->join_code }}</span></p>
                            <div class="mt-4 flex items-center gap-2">
                                <x-button variant="secondary" :href="route('adviser.classes.edit', $class)" icon="heroicon-o-pencil-square">Edit</x-button>
                                <x-confirm-form :action="route('adviser.classes.leave', $class)" :confirm="'Leave '.$class->display_name.'? Interns stay enrolled and another adviser can claim the class with its join code.'">
                                    <x-button variant="ghost" icon="heroicon-o-arrow-right-start-on-rectangle">Leave</x-button>
                                </x-confirm-form>
                            </div>
                        </x-card>
                    @endforeach
                </div>
            @endif

            @if ($past->isNotEmpty())
                <x-card title="Past classes" subtitle="Classes you advised before. Enter the join code again to claim one back." :padding="false">
                    <x-table>
                        <x-slot:head><th>Class</th><th>Subject</th><th>Advised</th></x-slot:head>
                        @foreach ($past as $log)
                            <tr>
                                <td class="font-medium">{{ $log->classSection->display_name }}</td>
                                <td>{{ $log->classSection->subject }} · {{ $log->classSection->school_year }}</td>
                                <td class="text-stone-500">{{ $log->joined_at->format('M j, Y') }} – {{ $log->left_at->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                </x-card>
            @endif
        </div>

        <x-card title="Claim a class" subtitle="Imported classes have no adviser until someone enters their join code.">
            <form method="POST" action="{{ route('adviser.classes.join') }}" class="space-y-4">
                @csrf
                <x-form.input name="join_code" label="Join code" placeholder="SBIT4C26" required class="font-mono uppercase" />
                <x-button class="w-full" icon="heroicon-o-key">Claim class</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="ClassMembership|Adviser.*ClassesTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: let advisers claim a class by join code and leave it with history"
```

---

### Task 5: Class page shell — stream (read-only), people tab and printable roster

**Files:**
- Modify: `app/Http/Controllers/Adviser/ClassSectionController.php`, `routes/web.php`
- Create: `resources/views/adviser/classes/partials/header.blade.php`, `resources/views/classroom/announcement.blade.php`, `resources/views/adviser/classes/{show,people,print}.blade.php`
- Test: `tests/Feature/Adviser/ClassPagesTest.php`

**Interfaces:**
- Consumes: `ClassSectionPolicy::view`, `OjtHoursService::required|tier`, `x-tabs`, `x-layouts.print`, `x-rich-text`.
- Produces: routes `adviser.classes.show` (GET `/adviser/classes/{classSection}`), `adviser.classes.people`, `adviser.classes.print`; partial `adviser.classes.partials.header` (expects `$class`), shared partial `classroom.announcement` (expects `$announcement` with `author` and `comments.author` loaded). Task 7 adds the composer and edit menu; Task 8 adds comment forms; Task 9 adds the Documents tab (the header already shows it when `adviser.classes.documents` exists).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Adviser/ClassPagesTest.php`:

```php
<?php

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\ClassSection;
use App\Models\Placement;
use App\Models\User;

beforeEach(function () {
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create(['course_code' => 'CC101', 'section' => 'SBIT-4C', 'join_code' => 'SBIT4C26']);
});

it('shows the stream with announcements and comments', function () {
    $announcement = Announcement::factory()->for($this->section)->for($this->adviser, 'author')->create(['body' => '<p>Upload your <strong>endorsement</strong> letter.</p><script>alert(1)</script>']);
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    AnnouncementComment::factory()->for($announcement)->for($intern, 'author')->create(['body' => 'Noted, thank you!']);

    $this->actingAs($this->adviser)->get(route('adviser.classes.show', $this->section))
        ->assertOk()->assertSee('CC101 · SBIT-4C')->assertSee('SBIT4C26')->assertSee('Stream')->assertSee('People')
        ->assertSee('<strong>endorsement</strong>', false)->assertDontSee('<script', false)
        ->assertSee('Maria Santos')->assertSee('Noted, thank you!');
});

it('lists interns with hours, company and progress tier, and prints the roster', function () {
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $intern->internProfile()->update(['class_section_id' => $this->section->id, 'student_number' => '21-0001', 'total_hours' => 300]);
    Placement::factory()->for($intern, 'intern')->create(['hours_rendered' => 300]);
    $unplaced = User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Cruz']);
    $unplaced->internProfile()->update(['class_section_id' => $this->section->id, 'student_number' => '21-0002']);

    $this->actingAs($this->adviser)->get(route('adviser.classes.people', $this->section))
        ->assertOk()->assertSee('Maria Santos')->assertSee('21-0001')->assertSee('300 / '.config('wiis.hours.required'))
        ->assertSee($intern->activePlacement->company->name)->assertSee('Certificate eligible')->assertSee('Juan Cruz')->assertSee('Below certificate threshold');

    $this->actingAs($this->adviser)->get(route('adviser.classes.print', $this->section))
        ->assertOk()->assertSee('window.print()')->assertSee(config('wiis.institution.name'))->assertSee('Maria Santos')->assertSee('Juan Cruz')->assertSee('21-0002');
});

it('forbids advisers who do not advise the class', function () {
    $other = User::factory()->adviser()->create();

    $this->actingAs($other)->get(route('adviser.classes.show', $this->section))->assertForbidden();
    $this->actingAs($other)->get(route('adviser.classes.people', $this->section))->assertForbidden();
    $this->actingAs($other)->get(route('adviser.classes.print', $this->section))->assertForbidden();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter="ClassPages"`
Expected: FAIL — routes not defined.

- [ ] **Step 3: Controller and routes**

Add to `ClassSectionController` (imports: `App\Models\InternProfile`, `App\Services\OjtHoursService`, `Illuminate\Support\Collection`):

```php
    public function show(ClassSection $classSection): View
    {
        Gate::authorize('view', $classSection);

        return view('adviser.classes.show', [
            'class' => $classSection->loadCount('internProfiles'),
            'announcements' => $classSection->announcements()->with(['author', 'comments.author'])->paginate(10),
        ]);
    }

    public function people(ClassSection $classSection, OjtHoursService $hours): View
    {
        Gate::authorize('view', $classSection);

        return view('adviser.classes.people', [
            'class' => $classSection->loadCount('internProfiles'),
            'profiles' => $this->roster($classSection),
            'hours' => $hours,
        ]);
    }

    public function print(ClassSection $classSection, OjtHoursService $hours): View
    {
        Gate::authorize('view', $classSection);

        return view('adviser.classes.print', [
            'class' => $classSection,
            'profiles' => $this->roster($classSection),
            'hours' => $hours,
        ]);
    }

    /** @return Collection<int, InternProfile> sorted by surname, with user, active placement and company loaded */
    private function roster(ClassSection $section): Collection
    {
        return $section->internProfiles()->with(['user.activePlacement.company'])->get()
            ->sortBy(fn (InternProfile $profile) => mb_strtolower($profile->user->last_name.' '.$profile->user->first_name))
            ->values();
    }
```

Routes — add inside the adviser group **after** `classes/{classSection}/leave` (and after `classes/create` / `classes/join`):

```php
        Route::get('classes/{classSection}', [Adviser\ClassSectionController::class, 'show'])->name('classes.show');
        Route::get('classes/{classSection}/people', [Adviser\ClassSectionController::class, 'people'])->name('classes.people');
        Route::get('classes/{classSection}/print', [Adviser\ClassSectionController::class, 'print'])->name('classes.print');
```

- [ ] **Step 4: Views**

`resources/views/adviser/classes/partials/header.blade.php`:

```blade
<x-page-header :title="$class->display_name" :subtitle="$class->subject.' · '.$class->schedule_label.' · '.$class->school_year" :breadcrumbs="['My classes' => route('adviser.classes.index'), $class->display_name => null]">
    <x-slot:actions>
        <span class="inline-flex items-center gap-2 rounded-xl border border-dashed border-stone-300 px-3 py-1.5 text-sm dark:border-stone-700">Join code <span class="font-mono font-semibold">{{ $class->join_code }}</span></span>
        @can('manage', $class)
            <x-button variant="secondary" :href="route('adviser.classes.edit', $class)" icon="heroicon-o-pencil-square">Edit</x-button>
        @endcan
    </x-slot:actions>
</x-page-header>

@php
    $tabs = [['label' => 'Stream', 'route' => 'adviser.classes.show', 'params' => $class, 'active' => 'adviser.classes.show']];
    if (Route::has('adviser.classes.documents')) {
        $tabs[] = ['label' => 'Documents', 'route' => 'adviser.classes.documents', 'params' => $class, 'active' => 'adviser.classes.documents'];
    }
    $tabs[] = ['label' => 'People', 'route' => 'adviser.classes.people', 'params' => $class, 'active' => 'adviser.classes.people', 'count' => $class->intern_profiles_count ?? null];
@endphp
<x-tabs :tabs="$tabs" />
```

`resources/views/classroom/announcement.blade.php` (shared by both portals; this is the Task 5 version — Task 7 and Task 8 extend it):

```blade
<x-card>
    <div class="flex items-start gap-3">
        <x-avatar :user="$announcement->author" size="sm" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                <p class="font-semibold">{{ $announcement->author->name }}</p>
                <time class="text-xs text-stone-500" datetime="{{ $announcement->created_at->toIso8601String() }}" title="{{ $announcement->created_at->format('M j, Y g:i A') }}">{{ $announcement->created_at->diffForHumans() }}</time>
            </div>
            <x-rich-text :html="$announcement->body" class="mt-2" />
        </div>
        {{-- Task 7 adds the edit/delete menu here --}}
    </div>

    @if ($announcement->comments->isNotEmpty())
        <div class="mt-4 space-y-3 border-t border-stone-100 pt-4 dark:border-stone-800">
            @foreach ($announcement->comments as $comment)
                <div class="flex items-start gap-3">
                    <x-avatar :user="$comment->author" size="xs" class="mt-0.5" />
                    <div class="min-w-0 flex-1 text-sm">
                        <span class="font-medium">{{ $comment->author->name }}</span>
                        <span class="ml-2 text-xs text-stone-500">{{ $comment->created_at->diffForHumans() }}</span>
                        <p class="mt-0.5 whitespace-pre-line text-stone-700 dark:text-stone-200">{{ $comment->body }}</p>
                    </div>
                    {{-- Task 8 adds the delete button here --}}
                </div>
            @endforeach
        </div>
    @endif
    {{-- Task 8 adds the comment form here --}}
</x-card>
```

`resources/views/adviser/classes/show.blade.php`:

```blade
<x-layouts.app :title="$class->display_name">
    @include('adviser.classes.partials.header')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Task 7 adds the announcement composer here --}}
            @forelse ($announcements as $announcement)
                @include('classroom.announcement', ['announcement' => $announcement])
            @empty
                <x-card><x-empty-state title="No announcements yet" description="Posts to the class stream will appear here." icon="heroicon-o-megaphone" /></x-card>
            @endforelse
            <x-pagination :paginator="$announcements" />
        </div>

        <div class="space-y-4">
            <x-card title="About this class">
                <x-detail-list class="sm:grid-cols-1" :items="[
                    'Subject' => $class->subject,
                    'Schedule' => $class->schedule_label,
                    'School year' => $class->school_year,
                    'Adviser' => $class->adviser?->name ?? 'No adviser',
                    'Interns' => $class->intern_profiles_count,
                ]" />
            </x-card>
        </div>
    </div>
</x-layouts.app>
```

`resources/views/adviser/classes/people.blade.php`:

```blade
<x-layouts.app :title="'People · '.$class->display_name">
    @include('adviser.classes.partials.header')

    <x-card title="Interns" :subtitle="$profiles->count().' enrolled'" :padding="false">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('adviser.classes.print', $class)" icon="heroicon-o-printer" target="_blank">Print list</x-button>
        </x-slot:actions>
        <x-table>
            <x-slot:head><th>Intern</th><th>Student no.</th><th>Company</th><th>Hours</th><th>Progress</th><th>Account</th></x-slot:head>
            @forelse ($profiles as $profile)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$profile->user" size="sm" />
                            <div class="min-w-0"><p class="font-medium">{{ $profile->user->name }}</p><p class="truncate text-xs text-stone-500">{{ $profile->user->email }}</p></div>
                        </div>
                    </td>
                    <td class="font-mono text-xs">{{ $profile->student_number }}</td>
                    <td>{{ $profile->user->activePlacement?->company?->name ?? '—' }}</td>
                    <td class="tabular-nums">{{ $profile->total_hours }} / {{ $hours->required() }} h</td>
                    <td><x-badge :status="$hours->tier($profile->total_hours)" /></td>
                    <td><x-badge :status="$profile->user->status" /></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state title="No interns yet" :description="'Share the join code '.$class->join_code.' so interns can enrol.'" class="py-8" /></td></tr>
            @endforelse
        </x-table>
    </x-card>
</x-layouts.app>
```

`resources/views/adviser/classes/print.blade.php`:

```blade
<x-layouts.print :title="'Interns · '.$class->display_name" :back="route('adviser.classes.people', $class)">
    <header class="border-b border-stone-300 pb-4">
        <p class="text-sm uppercase tracking-wide text-stone-500">{{ config('wiis.institution.name') }} · {{ config('wiis.institution.office') }}</p>
        <h1 class="mt-1 font-display text-2xl font-semibold">{{ $class->display_name }} — {{ $class->subject }}</h1>
        <p class="mt-1 text-sm text-stone-600">{{ $class->schedule_label }} · SY {{ $class->school_year }} · Adviser: {{ $class->adviser?->name ?? 'None' }}</p>
    </header>

    <table class="mt-6 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-stone-300 text-left text-xs uppercase tracking-wide text-stone-500">
                <th class="py-2 pr-3">#</th><th class="py-2 pr-3">Intern</th><th class="py-2 pr-3">Student no.</th><th class="py-2 pr-3">Company</th><th class="py-2 pr-3 text-right">Hours</th><th class="py-2">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($profiles as $profile)
                <tr class="border-b border-stone-200">
                    <td class="py-2 pr-3 tabular-nums">{{ $loop->iteration }}</td>
                    <td class="py-2 pr-3 font-medium">{{ $profile->user->last_name }}, {{ $profile->user->first_name }}</td>
                    <td class="py-2 pr-3 font-mono text-xs">{{ $profile->student_number }}</td>
                    <td class="py-2 pr-3">{{ $profile->user->activePlacement?->company?->name ?? '—' }}</td>
                    <td class="py-2 pr-3 text-right tabular-nums">{{ $profile->total_hours }} / {{ $hours->required() }}</td>
                    <td class="py-2">{{ $hours->tier($profile->total_hours)->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="mt-6 text-xs text-stone-500">{{ $profiles->count() }} interns · Printed {{ now()->format('M j, Y g:i A') }}</p>
</x-layouts.print>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="ClassPages|ClassMembership|Adviser.*ClassesTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add adviser class page with stream, people tab and printable roster"
```

---

### Task 6: Intern joins a class and sees the stream and people tabs

**Files:**
- Create: `app/Actions/JoinClass.php`, `app/Http/Controllers/Intern/ClassController.php`
- Create: `resources/views/intern/class/{join,show,people}.blade.php`, `resources/views/intern/class/partials/header.blade.php`
- Modify: `app/Notifications/InternJoinedClass.php` (url), `routes/web.php` (intern group), `resources/views/adviser/dashboard.blade.php`, `resources/views/intern/dashboard.blade.php`
- Test: `tests/Feature/Intern/ClassTest.php`, append to `tests/Unit/Actions/ClassMembershipActionsTest.php`

**Interfaces:**
- Consumes: `JoinClassRequest` (Task 4), `classroom.announcement` partial (Task 5), `InternJoinedClass`, `ClassSection::active()`.
- Produces: `JoinClass(User $intern, string $joinCode): ClassSection`; routes `intern.class.show` (GET `/intern/class`), `intern.class.join` (POST `/intern/class/join`), `intern.class.people`; partial `intern.class.partials.header` (expects `$class`; shows a Documents tab once `intern.class.documents` exists). `Intern\ClassController::currentSection(User): ?ClassSection` is private; Task 10 adds `documents()` to this controller.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/Actions/ClassMembershipActionsTest.php` (add `use App\Actions\JoinClass;` and `use App\Notifications\InternJoinedClass;` and `use Illuminate\Support\Facades\Notification;` to the imports):

```php
it('enrols an intern by join code and tells the adviser', function () {
    Notification::fake();
    $section = ClassSection::factory()->create(['join_code' => 'SBIT4C26', 'school_year' => '2026-2027']);
    $intern = User::factory()->intern()->create();

    $joined = app(JoinClass::class)($intern, 'sbit4c26');

    expect($joined->is($section))->toBeTrue()
        ->and($intern->internProfile->refresh()->class_section_id)->toBe($section->id)
        ->and($intern->internProfile->school_year)->toBe('2026-2027');
    Notification::assertSentTo($section->adviser, InternJoinedClass::class, fn (InternJoinedClass $n) => $n->toArray($section->adviser)['url'] === route('adviser.classes.people', $section));
});

it('refuses a second active class, unknown codes and archived classes', function () {
    $current = ClassSection::factory()->create(['join_code' => 'FIRST001']);
    $other = ClassSection::factory()->create(['join_code' => 'OTHER001']);
    ClassSection::factory()->archived()->create(['join_code' => 'ARCHIVED']);
    $intern = User::factory()->intern()->create();
    $intern->internProfile->update(['class_section_id' => $current->id]);

    expect(fn () => app(JoinClass::class)($intern, 'OTHER001'))->toThrow(DomainRuleViolation::class, 'already enrolled');
    expect($intern->internProfile->refresh()->class_section_id)->toBe($current->id);

    $free = User::factory()->intern()->create();
    expect(fn () => app(JoinClass::class)($free, 'NOPE0000'))->toThrow(DomainRuleViolation::class, 'No active class');
    expect(fn () => app(JoinClass::class)($free, 'ARCHIVED'))->toThrow(DomainRuleViolation::class, 'No active class');
});

it('lets an intern whose class was archived join a new one', function () {
    $old = ClassSection::factory()->archived()->create();
    $new = ClassSection::factory()->create(['join_code' => 'NEWCLASS']);
    $intern = User::factory()->intern()->create();
    $intern->internProfile->update(['class_section_id' => $old->id]);

    app(JoinClass::class)($intern, 'NEWCLASS');

    expect($intern->internProfile->refresh()->class_section_id)->toBe($new->id);
});
```

`tests/Feature/Intern/ClassTest.php`:

```php
<?php

use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\User;

beforeEach(function () {
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
});

it('shows the join form when the intern has no active class', function () {
    $this->actingAs($this->intern)->get(route('intern.class.show'))
        ->assertOk()->assertSee('Join a class')->assertSee('name="join_code"', false);
    $this->actingAs($this->intern)->get(route('intern.class.people'))->assertRedirect(route('intern.class.show'));
});

it('joins a class by code and then sees its stream', function () {
    $section = ClassSection::factory()->create(['join_code' => 'SBIT4C26', 'course_code' => 'CC101', 'section' => 'SBIT-4C']);
    Announcement::factory()->for($section)->for($section->adviser, 'author')->create(['body' => '<p>Welcome to Practicum!</p>']);

    $this->actingAs($this->intern)->post(route('intern.class.join'), ['join_code' => 'sbit4c26'])
        ->assertRedirect(route('intern.class.show'))->assertSessionHas('success');

    $this->actingAs($this->intern)->get(route('intern.class.show'))
        ->assertOk()->assertSee('CC101 · SBIT-4C')->assertSee('Welcome to Practicum!')->assertSee($section->adviser->name)->assertDontSee('name="join_code"', false);
});

it('flashes an error when already enrolled or the code is unknown', function () {
    $current = ClassSection::factory()->create(['join_code' => 'FIRST001']);
    ClassSection::factory()->create(['join_code' => 'OTHER001']);
    $this->intern->internProfile->update(['class_section_id' => $current->id]);

    $this->actingAs($this->intern)->from(route('intern.class.show'))->post(route('intern.class.join'), ['join_code' => 'OTHER001'])
        ->assertRedirect(route('intern.class.show'))->assertSessionHas('error');
    expect($this->intern->internProfile->refresh()->class_section_id)->toBe($current->id);

    $free = User::factory()->intern()->create();
    $this->actingAs($free)->from(route('intern.class.show'))->post(route('intern.class.join'), ['join_code' => 'NOPE0000'])
        ->assertRedirect(route('intern.class.show'))->assertSessionHas('error');
});

it('lists the adviser and classmates on the people tab', function () {
    $section = ClassSection::factory()->create();
    $this->intern->internProfile->update(['class_section_id' => $section->id, 'student_number' => '21-0001']);
    $mate = User::factory()->intern()->create(['first_name' => 'Juan', 'last_name' => 'Cruz']);
    $mate->internProfile()->update(['class_section_id' => $section->id, 'student_number' => '21-0002']);
    User::factory()->intern()->create(['first_name' => 'Nobody', 'last_name' => 'Else']);

    $this->actingAs($this->intern)->get(route('intern.class.people'))
        ->assertOk()->assertSee($section->adviser->name)->assertSee('Juan Cruz')->assertSee('21-0002')->assertSee('Maria Santos')->assertDontSee('Nobody Else');
});

it('links the dashboards to the class pages', function () {
    $section = ClassSection::factory()->create();
    $this->intern->internProfile->update(['class_section_id' => $section->id]);

    $this->actingAs($this->intern)->get(route('intern.dashboard'))->assertSee(route('intern.class.show'));
    $this->actingAs($section->adviser)->get(route('adviser.dashboard'))->assertSee(route('adviser.classes.show', $section));
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="ClassMembershipActions|Intern.*ClassTest"`
Expected: FAIL — `JoinClass` missing, intern routes missing, notification url still the dashboard.

- [ ] **Step 3: Action and notification url**

`app/Actions/JoinClass.php`:

```php
<?php

namespace App\Actions;

use App\Enums\ClassStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\InternJoinedClass;
use Illuminate\Support\Str;

/** An intern enrols in a class by join code. One active class per intern. */
class JoinClass
{
    public function __invoke(User $intern, string $joinCode): ClassSection
    {
        $profile = $intern->internProfile;

        if (! $profile) {
            throw new DomainRuleViolation('Only interns can join a class.');
        }

        $current = $profile->classSection;

        if ($current && $current->status === ClassStatus::Active) {
            throw new DomainRuleViolation("You are already enrolled in {$current->display_name}.");
        }

        $section = ClassSection::query()->active()->where('join_code', Str::upper(trim($joinCode)))->first();

        if (! $section) {
            throw new DomainRuleViolation('No active class has that join code.');
        }

        $profile->update(['class_section_id' => $section->id, 'school_year' => $section->school_year]);

        $section->adviser?->notify(new InternJoinedClass($intern->load('internProfile'), $section));

        return $section;
    }
}
```

In `app/Notifications/InternJoinedClass.php` change the url line to:

```php
            'url' => route('adviser.classes.people', $this->section),
```

- [ ] **Step 4: Controller, routes, views, dashboard links**

`app/Http/Controllers/Intern/ClassController.php`:

```php
<?php

namespace App\Http\Controllers\Intern;

use App\Actions\JoinClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\Classroom\JoinClassRequest;
use App\Models\ClassSection;
use App\Models\InternProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function show(Request $request): View
    {
        $section = $this->currentSection($request->user());

        if (! $section) {
            return view('intern.class.join');
        }

        return view('intern.class.show', [
            'class' => $section,
            'announcements' => $section->announcements()->with(['author', 'comments.author'])->paginate(10),
        ]);
    }

    public function join(JoinClassRequest $request, JoinClass $join): RedirectResponse
    {
        $section = $join($request->user(), $request->validated('join_code'));

        return redirect()->route('intern.class.show')->with('success', "Welcome to {$section->display_name}!");
    }

    public function people(Request $request): View|RedirectResponse
    {
        $section = $this->currentSection($request->user());

        if (! $section) {
            return redirect()->route('intern.class.show');
        }

        return view('intern.class.people', [
            'class' => $section,
            'profiles' => $section->internProfiles()->with('user')->get()
                ->sortBy(fn (InternProfile $profile) => mb_strtolower($profile->user->last_name.' '.$profile->user->first_name))
                ->values(),
        ]);
    }

    /** The intern's active class with adviser and intern count loaded, or null. */
    private function currentSection(User $user): ?ClassSection
    {
        return $user->internProfile?->classSection()->active()->with('adviser')->withCount('internProfiles')->first();
    }
}
```

Routes — replace the intern group with:

```php
    Route::prefix('intern')->name('intern.')->middleware('role:intern')->group(function () {
        Route::get('dashboard', Intern\DashboardController::class)->name('dashboard');
        Route::get('class', [Intern\ClassController::class, 'show'])->name('class.show');
        Route::post('class/join', [Intern\ClassController::class, 'join'])->name('class.join');
        Route::get('class/people', [Intern\ClassController::class, 'people'])->name('class.people');
    });
```

`resources/views/intern/class/partials/header.blade.php`:

```blade
<x-page-header :title="$class->display_name" :subtitle="$class->subject.' · '.$class->schedule_label.' · '.$class->school_year">
    <x-slot:actions>
        <span class="text-sm text-stone-500">Adviser: <span class="font-medium text-stone-800 dark:text-stone-100">{{ $class->adviser?->name ?? 'Not assigned yet' }}</span></span>
    </x-slot:actions>
</x-page-header>

@php
    $tabs = [['label' => 'Stream', 'route' => 'intern.class.show', 'active' => 'intern.class.show']];
    if (Route::has('intern.class.documents')) {
        $tabs[] = ['label' => 'Documents', 'route' => 'intern.class.documents', 'active' => ['intern.class.documents', 'intern.folders.*']];
    }
    $tabs[] = ['label' => 'People', 'route' => 'intern.class.people', 'active' => 'intern.class.people', 'count' => $class->intern_profiles_count];
@endphp
<x-tabs :tabs="$tabs" />
```

The `x-tabs` component calls `request()->routeIs($tab['active'] ?? $tab['route'])`; update `resources/views/components/tabs.blade.php` so an array works there too:

```blade
        @php $active = request()->routeIs(...(array) ($tab['active'] ?? $tab['route'])); @endphp
```

`resources/views/intern/class/join.blade.php`:

```blade
<x-layouts.app title="My class">
    <x-page-header title="My class" subtitle="You are not enrolled in a class yet." />

    <x-card title="Join a class" subtitle="Enter the join code your adviser gave you." class="max-w-lg">
        <form method="POST" action="{{ route('intern.class.join') }}" class="space-y-4">
            @csrf
            <x-form.input name="join_code" label="Join code" placeholder="SBIT4C26" required class="font-mono uppercase" />
            <x-button class="w-full" icon="heroicon-o-key">Join class</x-button>
        </form>
    </x-card>
</x-layouts.app>
```

`resources/views/intern/class/show.blade.php`:

```blade
<x-layouts.app :title="$class->display_name">
    @include('intern.class.partials.header')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @forelse ($announcements as $announcement)
                @include('classroom.announcement', ['announcement' => $announcement])
            @empty
                <x-card><x-empty-state title="Nothing posted yet" description="Announcements from your adviser will appear here." icon="heroicon-o-megaphone" /></x-card>
            @endforelse
            <x-pagination :paginator="$announcements" />
        </div>

        <div class="space-y-4">
            <x-card title="About this class">
                <x-detail-list class="sm:grid-cols-1" :items="[
                    'Subject' => $class->subject,
                    'Schedule' => $class->schedule_label,
                    'School year' => $class->school_year,
                    'Adviser' => $class->adviser?->name ?? 'Not assigned yet',
                    'Classmates' => $class->intern_profiles_count,
                ]" />
            </x-card>
        </div>
    </div>
</x-layouts.app>
```

`resources/views/intern/class/people.blade.php`:

```blade
<x-layouts.app :title="'People · '.$class->display_name">
    @include('intern.class.partials.header')

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Adviser">
            @if ($class->adviser)
                <div class="flex items-center gap-3">
                    <x-avatar :user="$class->adviser" size="lg" />
                    <div><p class="font-semibold">{{ $class->adviser->name }}</p><p class="text-sm text-stone-500">{{ $class->adviser->email }}</p></div>
                </div>
            @else
                <p class="text-sm text-stone-500">No adviser has claimed this class yet.</p>
            @endif
        </x-card>

        <x-card title="Classmates" :subtitle="$profiles->count().' enrolled'" class="lg:col-span-2" :padding="false">
            <x-table>
                <x-slot:head><th>Name</th><th>Student no.</th></x-slot:head>
                @foreach ($profiles as $profile)
                    <tr>
                        <td><div class="flex items-center gap-3"><x-avatar :user="$profile->user" size="sm" /><span class="font-medium">{{ $profile->user->name }}</span></div></td>
                        <td class="font-mono text-xs">{{ $profile->student_number }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    </div>
</x-layouts.app>
```

Dashboard links — in `resources/views/adviser/dashboard.blade.php` replace the class name line with:

```blade
                    <p class="font-semibold"><a href="{{ route('adviser.classes.show', $class) }}" class="hover:underline">{{ $class->display_name }}</a></p>
```

In `resources/views/intern/dashboard.blade.php` replace the "My class" card body with:

```blade
            @if ($section)
                <p class="font-display text-lg font-semibold"><a href="{{ route('intern.class.show') }}" class="hover:underline">{{ $section->display_name }}</a></p>
                <p class="text-sm text-stone-500">{{ $section->subject }} · {{ $section->schedule_label }}</p>
                <p class="mt-3 text-sm">Adviser: <span class="font-medium">{{ $section->adviser?->name ?? 'Not assigned yet' }}</span></p>
                <x-button variant="secondary" :href="route('intern.class.show')" icon="heroicon-o-arrow-right" class="mt-4">Open class</x-button>
            @else
                <x-empty-state title="No class joined" description="Ask your adviser for the class join code." icon="heroicon-o-rectangle-group" class="py-8">
                    <x-slot:action><x-button :href="route('intern.class.show')" icon="heroicon-o-key">Join a class</x-button></x-slot:action>
                </x-empty-state>
            @endif
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="ClassMembershipActions|Intern.*ClassTest|RegisterIntern|AdminComponents|RoleAccess"`
Expected: PASS (the registration tests still pass because the notification is only built when an adviser exists and the route now exists).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: let interns join a class by code and see its stream and people"
```

---

### Task 7: Announcements — post, edit and delete with notifications

**Files:**
- Create: `app/Actions/PostAnnouncement.php`, `app/Actions/UpdateAnnouncement.php`, `app/Http/Requests/Classroom/AnnouncementRequest.php`, `app/Http/Controllers/Adviser/AnnouncementController.php`, `app/Notifications/AnnouncementPosted.php`, `resources/views/adviser/announcements/edit.blade.php`
- Modify: `routes/web.php`, `resources/views/adviser/classes/show.blade.php` (composer), `resources/views/classroom/announcement.blade.php` (edit/delete menu)
- Test: `tests/Unit/Actions/AnnouncementActionsTest.php`, `tests/Feature/Adviser/AnnouncementsTest.php`

**Interfaces:**
- Consumes: `HtmlSanitizer`, `AnnouncementPolicy`, `x-form.editor`, `ClassSection::interns()` (HasManyThrough `User`).
- Produces: `PostAnnouncement(ClassSection, User $author, string $body): Announcement`, `UpdateAnnouncement(Announcement, string $body): Announcement`; routes `adviser.announcements.store` (POST `/adviser/classes/{classSection}/announcements`), `adviser.announcements.edit|update|destroy` (`/adviser/announcements/{announcement}`); `AnnouncementPosted` notification (database) to every active intern of the class.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/AnnouncementActionsTest.php`:

```php
<?php

use App\Actions\PostAnnouncement;
use App\Actions\UpdateAnnouncement;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('stores a sanitized body and notifies the active interns of the class', function () {
    Notification::fake();
    $section = ClassSection::factory()->create();
    $interns = User::factory()->intern()->count(2)->create();
    $interns->each(fn (User $u) => $u->internProfile()->update(['class_section_id' => $section->id]));
    $disabled = User::factory()->intern()->disabled()->create();
    $disabled->internProfile()->update(['class_section_id' => $section->id]);
    $outsider = User::factory()->intern()->create();

    $announcement = app(PostAnnouncement::class)($section, $section->adviser, '<div>Hello <b>class</b><script>alert(1)</script></div>');

    expect($announcement->body)->toContain('<b>class</b>')->not->toContain('script')
        ->and($announcement->author_id)->toBe($section->adviser_id);
    Notification::assertSentTo($interns, AnnouncementPosted::class);
    Notification::assertNotSentTo([$disabled, $outsider, $section->adviser], AnnouncementPosted::class);
    expect((new AnnouncementPosted($announcement))->toArray($interns->first())['url'])->toBe(route('intern.class.show'));
});

it('updates the body through the sanitizer', function () {
    $section = ClassSection::factory()->create();
    $announcement = app(PostAnnouncement::class)($section, $section->adviser, '<p>Old</p>');

    app(UpdateAnnouncement::class)($announcement, '<p onclick="x()">New</p>');

    expect($announcement->refresh()->body)->toBe('<p>New</p>');
});
```

`tests/Feature/Adviser/AnnouncementsTest.php`:

```php
<?php

use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
    $this->intern = User::factory()->intern()->create();
    $this->intern->internProfile->update(['class_section_id' => $this->section->id]);
});

it('posts a sanitized announcement from the stream and notifies interns', function () {
    Notification::fake();

    $this->actingAs($this->adviser)->get(route('adviser.classes.show', $this->section))->assertSee('<trix-editor', false);

    $this->actingAs($this->adviser)->post(route('adviser.announcements.store', $this->section), [
        'body' => '<div>Upload by <strong>Friday</strong>.<script>alert(1)</script><a href="javascript:evil()">x</a></div>',
    ])->assertRedirect(route('adviser.classes.show', $this->section))->assertSessionHas('success');

    $announcement = Announcement::firstOrFail();
    expect($announcement->body)->toContain('<strong>Friday</strong>')->not->toContain('script')->not->toContain('javascript:');
    Notification::assertSentTo($this->intern, AnnouncementPosted::class);

    $this->actingAs($this->intern)->get(route('intern.class.show'))->assertSee('<strong>Friday</strong>', false)->assertDontSee('<script', false);
});

it('rejects empty bodies', function () {
    $this->actingAs($this->adviser)->post(route('adviser.announcements.store', $this->section), ['body' => '<div><br></div>'])
        ->assertSessionHasErrors('body');
    $this->actingAs($this->adviser)->post(route('adviser.announcements.store', $this->section), ['body' => ''])
        ->assertSessionHasErrors('body');
    expect(Announcement::count())->toBe(0);
});

it('edits and deletes its own announcements only', function () {
    $mine = Announcement::factory()->for($this->section)->for($this->adviser, 'author')->create(['body' => '<p>Old</p>']);
    $otherAdviser = User::factory()->adviser()->create();
    $theirs = Announcement::factory()->for(ClassSection::factory()->for($otherAdviser, 'adviser'))->for($otherAdviser, 'author')->create();

    $this->actingAs($this->adviser)->get(route('adviser.announcements.edit', $mine))->assertOk()->assertSee('&lt;p&gt;Old&lt;/p&gt;', false);
    $this->actingAs($this->adviser)->put(route('adviser.announcements.update', $mine), ['body' => '<p>New</p>'])
        ->assertRedirect(route('adviser.classes.show', $this->section));
    expect($mine->refresh()->body)->toBe('<p>New</p>');

    $this->actingAs($this->adviser)->get(route('adviser.announcements.edit', $theirs))->assertForbidden();
    $this->actingAs($this->adviser)->put(route('adviser.announcements.update', $theirs), ['body' => '<p>Hacked</p>'])->assertForbidden();
    $this->actingAs($this->adviser)->delete(route('adviser.announcements.destroy', $theirs))->assertForbidden();

    $this->actingAs($this->adviser)->delete(route('adviser.announcements.destroy', $mine))->assertRedirect(route('adviser.classes.show', $this->section));
    expect(Announcement::whereKey($mine->id)->exists())->toBeFalse()->and(Announcement::whereKey($theirs->id)->exists())->toBeTrue();
});

it('cannot post to a class it does not advise', function () {
    $other = ClassSection::factory()->create();

    $this->actingAs($this->adviser)->post(route('adviser.announcements.store', $other), ['body' => '<p>Hi</p>'])->assertForbidden();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="AnnouncementActions|Adviser.*AnnouncementsTest"`
Expected: FAIL — actions/routes missing.

- [ ] **Step 3: Request, actions, notification**

`app/Http/Requests/Classroom/AnnouncementRequest.php`:

```php
<?php

namespace App\Http\Requests\Classroom;

use App\Models\Announcement;
use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $announcement = $this->route('announcement');

        return $announcement instanceof Announcement
            ? $this->user()->can('update', $announcement)
            : $this->user()->can('manage', $this->route('classSection'));
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:20000']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $validator->errors()->has('body') && app(HtmlSanitizer::class)->isBlank($this->input('body'))) {
                $validator->errors()->add('body', 'Write something before posting.');
            }
        });
    }

    public function messages(): array
    {
        return ['body.required' => 'Write something before posting.'];
    }
}
```

`app/Actions/PostAnnouncement.php`:

```php
<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Facades\Notification;

class PostAnnouncement
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function __invoke(ClassSection $section, User $author, string $body): Announcement
    {
        $announcement = $section->announcements()->create([
            'author_id' => $author->id,
            'body' => $this->sanitizer->clean($body),
        ]);

        $interns = $section->interns()->where('users.status', AccountStatus::Active)->get();

        Notification::send($interns, new AnnouncementPosted($announcement));

        return $announcement;
    }
}
```

`app/Actions/UpdateAnnouncement.php`:

```php
<?php

namespace App\Actions;

use App\Models\Announcement;
use App\Services\HtmlSanitizer;

class UpdateAnnouncement
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function __invoke(Announcement $announcement, string $body): Announcement
    {
        $announcement->update(['body' => $this->sanitizer->clean($body)]);

        return $announcement;
    }
}
```

`app/Notifications/AnnouncementPosted.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementPosted extends Notification
{
    use Queueable;

    public function __construct(public Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $section = $this->announcement->classSection;

        return [
            'title' => "New announcement in {$section->display_name}",
            'body' => Str::limit(trim(strip_tags($this->announcement->body)), 120),
            'url' => route('intern.class.show'),
            'icon' => 'heroicon-o-megaphone',
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, views**

`app/Http/Controllers/Adviser/AnnouncementController.php`:

```php
<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\PostAnnouncement;
use App\Actions\UpdateAnnouncement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Classroom\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\ClassSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function store(AnnouncementRequest $request, ClassSection $classSection, PostAnnouncement $post): RedirectResponse
    {
        $post($classSection, $request->user(), $request->validated('body'));

        return redirect()->route('adviser.classes.show', $classSection)->with('success', 'Announcement posted.');
    }

    public function edit(Announcement $announcement): View
    {
        Gate::authorize('update', $announcement);

        return view('adviser.announcements.edit', ['announcement' => $announcement, 'class' => $announcement->classSection]);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement, UpdateAnnouncement $update): RedirectResponse
    {
        $update($announcement, $request->validated('body'));

        return redirect()->route('adviser.classes.show', $announcement->classSection)->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('delete', $announcement);

        $section = $announcement->classSection;
        $announcement->delete();

        return redirect()->route('adviser.classes.show', $section)->with('success', 'Announcement deleted.');
    }
}
```

Routes — add inside the adviser group:

```php
        Route::post('classes/{classSection}/announcements', [Adviser\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('announcements/{announcement}/edit', [Adviser\AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('announcements/{announcement}', [Adviser\AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [Adviser\AnnouncementController::class, 'destroy'])->name('announcements.destroy');
```

`resources/views/adviser/announcements/edit.blade.php`:

```blade
<x-layouts.app title="Edit announcement">
    <x-page-header title="Edit announcement" :breadcrumbs="['My classes' => route('adviser.classes.index'), $class->display_name => route('adviser.classes.show', $class), 'Edit announcement' => null]" />

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('adviser.announcements.update', $announcement) }}" class="space-y-6">
            @csrf
            @method('PUT')
            <x-form.editor name="body" label="Announcement" :value="$announcement->body" required />
            <div class="flex items-center justify-end gap-3">
                <x-button variant="secondary" :href="route('adviser.classes.show', $class)">Cancel</x-button>
                <x-button icon="heroicon-o-check">Save changes</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
```

In `resources/views/adviser/classes/show.blade.php` replace `{{-- Task 7 adds the announcement composer here --}}` with:

```blade
            @can('manage', $class)
                <x-card x-data="{ open: {{ $errors->has('body') ? 'true' : 'false' }} }">
                    <button type="button" x-show="!open" @click="open = true; $nextTick(() => $el.parentElement.querySelector('trix-editor')?.focus())"
                            class="flex w-full items-center gap-3 text-left text-sm text-stone-500 hover:text-stone-800 dark:hover:text-stone-200">
                        <x-avatar :user="auth()->user()" size="sm" /> Announce something to your class…
                    </button>
                    <form x-show="open" x-cloak method="POST" action="{{ route('adviser.announcements.store', $class) }}" class="space-y-4">
                        @csrf
                        <x-form.editor name="body" placeholder="Announce something to your class…" />
                        <div class="flex items-center justify-end gap-2">
                            <x-button type="button" variant="ghost" @click="open = false">Cancel</x-button>
                            <x-button icon="heroicon-o-paper-airplane">Post</x-button>
                        </div>
                    </form>
                </x-card>
            @endcan
```

In `resources/views/classroom/announcement.blade.php` replace `{{-- Task 7 adds the edit/delete menu here --}}` with:

```blade
        @can('update', $announcement)
            <x-dropdown width="w-44">
                <x-slot:trigger><button type="button" class="btn-ghost -mr-2 p-1.5" aria-label="Announcement actions"><x-heroicon-o-ellipsis-vertical class="size-5" /></button></x-slot:trigger>
                <x-dropdown.item :href="route('adviser.announcements.edit', $announcement)" icon="heroicon-o-pencil-square">Edit</x-dropdown.item>
                <x-confirm-form :action="route('adviser.announcements.destroy', $announcement)" method="DELETE" confirm="Delete this announcement and its comments?">
                    <button class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"><x-heroicon-o-trash class="size-4" /> Delete</button>
                </x-confirm-form>
            </x-dropdown>
        @endcan
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="AnnouncementActions|Adviser.*AnnouncementsTest|ClassPages|Intern.*ClassTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: post, edit and delete class announcements with intern notifications"
```

---

### Task 8: Comments on announcements (both portals)

**Files:**
- Create: `app/Actions/AddComment.php`, `app/Http/Requests/Classroom/CommentRequest.php`, `app/Http/Controllers/Classroom/CommentController.php`, `app/Notifications/AnnouncementCommented.php`
- Modify: `routes/web.php` (both groups), `resources/views/classroom/announcement.blade.php`
- Test: `tests/Unit/Actions/AddCommentTest.php`, `tests/Feature/ClassroomCommentsTest.php`

**Interfaces:**
- Consumes: `AnnouncementPolicy::comment`, `AnnouncementCommentPolicy::delete`.
- Produces: `AddComment(Announcement, User $author, string $body): AnnouncementComment`; routes `adviser.comments.store` / `intern.comments.store` (POST `/{portal}/announcements/{announcement}/comments`), `adviser.comments.destroy` / `intern.comments.destroy` (DELETE `/{portal}/comments/{comment}`); `AnnouncementCommented` notification to the announcement's author.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/AddCommentTest.php`:

```php
<?php

use App\Actions\AddComment;
use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\AnnouncementCommented;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('adds a comment and notifies the announcement author', function () {
    Notification::fake();
    $section = ClassSection::factory()->create();
    $announcement = Announcement::factory()->for($section)->for($section->adviser, 'author')->create();
    $intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);

    $comment = app(AddComment::class)($announcement, $intern, '  Noted, thank you!  ');

    expect($comment->body)->toBe('Noted, thank you!')->and($comment->author_id)->toBe($intern->id)->and($comment->announcement_id)->toBe($announcement->id);
    Notification::assertSentTo($section->adviser, AnnouncementCommented::class, function (AnnouncementCommented $n) use ($section) {
        $data = $n->toArray($section->adviser);

        return str_contains($data['body'], 'Maria Santos') && $data['url'] === route('adviser.classes.show', $section);
    });
});

it('does not notify authors about their own comments', function () {
    Notification::fake();
    $section = ClassSection::factory()->create();
    $announcement = Announcement::factory()->for($section)->for($section->adviser, 'author')->create();

    app(AddComment::class)($announcement, $section->adviser, 'Reminder!');

    Notification::assertNothingSent();
});
```

`tests/Feature/ClassroomCommentsTest.php`:

```php
<?php

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\ClassSection;
use App\Models\User;

beforeEach(function () {
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->intern->internProfile->update(['class_section_id' => $this->section->id]);
    $this->announcement = Announcement::factory()->for($this->section)->for($this->adviser, 'author')->create();
});

it('lets an enrolled intern comment from the stream', function () {
    $this->actingAs($this->intern)->get(route('intern.class.show'))->assertSee(route('intern.comments.store', $this->announcement));

    $this->actingAs($this->intern)->from(route('intern.class.show'))
        ->post(route('intern.comments.store', $this->announcement), ['body' => 'Noted, thank you!'])
        ->assertRedirect(route('intern.class.show'))->assertSessionHas('success');

    $this->actingAs($this->intern)->get(route('intern.class.show'))->assertSee('Noted, thank you!')->assertSee('Maria Santos');
    $this->actingAs($this->adviser)->get(route('adviser.classes.show', $this->section))->assertSee('Noted, thank you!');
});

it('lets the adviser comment and rejects blank comments', function () {
    $this->actingAs($this->adviser)->post(route('adviser.comments.store', $this->announcement), ['body' => 'Reminder: Friday!'])->assertRedirect();
    expect(AnnouncementComment::count())->toBe(1);

    $this->actingAs($this->adviser)->post(route('adviser.comments.store', $this->announcement), ['body' => '   '])->assertSessionHasErrors('body');
    $this->actingAs($this->intern)->post(route('intern.comments.store', $this->announcement), ['body' => str_repeat('x', 1001)])->assertSessionHasErrors('body');
});

it('forbids outsiders', function () {
    $outsider = User::factory()->intern()->create();
    $otherAdviser = User::factory()->adviser()->create();

    $this->actingAs($outsider)->post(route('intern.comments.store', $this->announcement), ['body' => 'Hi'])->assertForbidden();
    $this->actingAs($otherAdviser)->post(route('adviser.comments.store', $this->announcement), ['body' => 'Hi'])->assertForbidden();
});

it('lets authors and the class adviser delete comments', function () {
    $mine = AnnouncementComment::factory()->for($this->announcement)->for($this->intern, 'author')->create();
    $advisers = AnnouncementComment::factory()->for($this->announcement)->for($this->adviser, 'author')->create();

    $this->actingAs($this->intern)->delete(route('intern.comments.destroy', $advisers))->assertForbidden();
    $this->actingAs($this->intern)->delete(route('intern.comments.destroy', $mine))->assertRedirect();
    expect(AnnouncementComment::whereKey($mine->id)->exists())->toBeFalse();

    $another = AnnouncementComment::factory()->for($this->announcement)->for($this->intern, 'author')->create();
    $this->actingAs($this->adviser)->delete(route('adviser.comments.destroy', $another))->assertRedirect();
    expect(AnnouncementComment::whereKey($another->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="AddComment|ClassroomComments"`
Expected: FAIL.

- [ ] **Step 3: Request, action, notification, controller, routes**

`app/Http/Requests/Classroom/CommentRequest.php`:

```php
<?php

namespace App\Http\Requests\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class CommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('comment', $this->route('announcement'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['body' => trim((string) $this->input('body'))]);
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:1000']];
    }
}
```

`app/Actions/AddComment.php`:

```php
<?php

namespace App\Actions;

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\User;
use App\Notifications\AnnouncementCommented;

class AddComment
{
    public function __invoke(Announcement $announcement, User $author, string $body): AnnouncementComment
    {
        $comment = $announcement->comments()->create(['author_id' => $author->id, 'body' => trim($body)]);

        if (! $author->is($announcement->author)) {
            $announcement->author->notify(new AnnouncementCommented($comment->load('author')));
        }

        return $comment;
    }
}
```

`app/Notifications/AnnouncementCommented.php`:

```php
<?php

namespace App\Notifications;

use App\Models\AnnouncementComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementCommented extends Notification
{
    use Queueable;

    public function __construct(public AnnouncementComment $comment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $section = $this->comment->announcement->classSection;

        return [
            'title' => "New comment in {$section->display_name}",
            'body' => "{$this->comment->author->name}: ".Str::limit($this->comment->body, 100),
            'url' => $notifiable instanceof User && $notifiable->isAdviser()
                ? route('adviser.classes.show', $section)
                : route('intern.class.show'),
            'icon' => 'heroicon-o-chat-bubble-left',
        ];
    }
}
```

`app/Http/Controllers/Classroom/CommentController.php` (one controller for both portals — the policy decides who may comment):

```php
<?php

namespace App\Http\Controllers\Classroom;

use App\Actions\AddComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Classroom\CommentRequest;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(CommentRequest $request, Announcement $announcement, AddComment $add): RedirectResponse
    {
        $add($announcement, $request->user(), $request->validated('body'));

        return back()->with('success', 'Comment posted.');
    }

    public function destroy(AnnouncementComment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return back()->with('success', 'Comment removed.');
    }
}
```

Routes — add `use App\Http\Controllers\Classroom;` next to the other controller imports at the top of `routes/web.php`, then inside the **adviser** group:

```php
        Route::post('announcements/{announcement}/comments', [Classroom\CommentController::class, 'store'])->name('comments.store');
        Route::delete('comments/{comment}', [Classroom\CommentController::class, 'destroy'])->name('comments.destroy');
```

and the same two lines inside the **intern** group (they get the `intern.` prefix).

- [ ] **Step 4: Comment form and delete button in the shared partial**

Replace `resources/views/classroom/announcement.blade.php` with the final version:

```blade
@php
    $portal = auth()->user()->isAdviser() ? 'adviser' : 'intern';
    $mine = (string) old('announcement_id') === (string) $announcement->id;
@endphp
<x-card>
    <div class="flex items-start gap-3">
        <x-avatar :user="$announcement->author" size="sm" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                <p class="font-semibold">{{ $announcement->author->name }}</p>
                <time class="text-xs text-stone-500" datetime="{{ $announcement->created_at->toIso8601String() }}" title="{{ $announcement->created_at->format('M j, Y g:i A') }}">{{ $announcement->created_at->diffForHumans() }}</time>
            </div>
            <x-rich-text :html="$announcement->body" class="mt-2" />
        </div>
        @can('update', $announcement)
            <x-dropdown width="w-44">
                <x-slot:trigger><button type="button" class="btn-ghost -mr-2 p-1.5" aria-label="Announcement actions"><x-heroicon-o-ellipsis-vertical class="size-5" /></button></x-slot:trigger>
                <x-dropdown.item :href="route('adviser.announcements.edit', $announcement)" icon="heroicon-o-pencil-square">Edit</x-dropdown.item>
                <x-confirm-form :action="route('adviser.announcements.destroy', $announcement)" method="DELETE" confirm="Delete this announcement and its comments?">
                    <button class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"><x-heroicon-o-trash class="size-4" /> Delete</button>
                </x-confirm-form>
            </x-dropdown>
        @endcan
    </div>

    <div class="mt-4 space-y-3 border-t border-stone-100 pt-4 dark:border-stone-800">
        @foreach ($announcement->comments as $comment)
            <div class="flex items-start gap-3">
                <x-avatar :user="$comment->author" size="xs" class="mt-0.5" />
                <div class="min-w-0 flex-1 text-sm">
                    <span class="font-medium">{{ $comment->author->name }}</span>
                    <span class="ml-2 text-xs text-stone-500">{{ $comment->created_at->diffForHumans() }}</span>
                    <p class="mt-0.5 whitespace-pre-line text-stone-700 dark:text-stone-200">{{ $comment->body }}</p>
                </div>
                @can('delete', $comment)
                    <x-confirm-form :action="route($portal.'.comments.destroy', $comment)" method="DELETE" confirm="Remove this comment?">
                        <button class="btn-ghost p-1 text-stone-400 hover:text-rose-600" aria-label="Remove comment"><x-heroicon-o-x-mark class="size-4" /></button>
                    </x-confirm-form>
                @endcan
            </div>
        @endforeach

        @can('comment', $announcement)
            <form method="POST" action="{{ route($portal.'.comments.store', $announcement) }}" class="flex items-start gap-3">
                @csrf
                <input type="hidden" name="announcement_id" value="{{ $announcement->id }}">
                <x-avatar :user="auth()->user()" size="xs" class="mt-2" />
                <div class="flex-1">
                    <label for="comment-{{ $announcement->id }}" class="sr-only">Add a comment</label>
                    <div class="flex items-start gap-2">
                        <textarea id="comment-{{ $announcement->id }}" name="body" rows="1" placeholder="Add a class comment…" required maxlength="1000"
                                  class="input py-2 {{ $mine && $errors->has('body') ? 'input-error' : '' }}">{{ $mine ? old('body') : '' }}</textarea>
                        <button class="btn-primary px-3" aria-label="Post comment"><x-heroicon-o-paper-airplane class="size-4" /></button>
                    </div>
                    @if ($mine)<x-form.error name="body" />@endif
                </div>
            </form>
        @endcan
    </div>
</x-card>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="AddComment|ClassroomComments|ClassPages|Intern.*ClassTest|Adviser.*AnnouncementsTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add announcement comments for advisers and interns"
```

---

### Task 9: Documents tab and folders (adviser) with the review queue page

**Files:**
- Create: `app/Actions/CreateFolder.php`, `app/Actions/ToggleFolderLock.php`, `app/Actions/DeleteFolder.php`, `app/Http/Requests/Adviser/FolderRequest.php`, `app/Http/Controllers/Adviser/FolderController.php`
- Create: `resources/views/adviser/classes/documents.blade.php`, `resources/views/adviser/folders/show.blade.php`
- Modify: `app/Models/ClassSection.php` (`submissions()`), `app/Http/Controllers/Adviser/ClassSectionController.php` (`documents()`), `routes/web.php`
- Test: `tests/Unit/Actions/FolderActionsTest.php`, `tests/Feature/Adviser/FoldersTest.php`

**Interfaces:**
- Consumes: `ClassFolderPolicy::manage`, `ClassSectionPolicy::view|manage`, `SubmissionStatus`.
- Produces: `ClassSection::submissions()` (HasManyThrough `ClassSubmission`), `CreateFolder(ClassSection, string $name): ClassFolder`, `ToggleFolderLock(ClassFolder): ClassFolder`, `DeleteFolder(ClassFolder): void` (removes files); routes `adviser.classes.documents` (GET `/adviser/classes/{classSection}/documents`), `adviser.folders.store` (POST `/adviser/classes/{classSection}/folders`), `adviser.folders.show` (GET `/adviser/folders/{folder}?status=pending|approved|declined|late`), `adviser.folders.lock` (POST toggle), `adviser.folders.destroy` (DELETE). Task 11 adds approve/decline controls to `adviser/folders/show.blade.php` at the marked spot; Task 12 adds the resources card to `documents.blade.php` at the marked spot.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/FolderActionsTest.php`:

```php
<?php

use App\Actions\CreateFolder;
use App\Actions\DeleteFolder;
use App\Actions\ToggleFolderLock;
use App\Models\ClassFolder;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('creates an unlocked folder and toggles its lock', function () {
    $section = ClassSection::factory()->create();

    $folder = app(CreateFolder::class)($section, '  Weekly Report 1 ');
    expect($folder->name)->toBe('Weekly Report 1')->and($folder->is_locked)->toBeFalse()->and($folder->class_section_id)->toBe($section->id);

    app(ToggleFolderLock::class)($folder);
    expect($folder->refresh()->is_locked)->toBeTrue();
    app(ToggleFolderLock::class)($folder);
    expect($folder->refresh()->is_locked)->toBeFalse();
});

it('deletes a folder with its submissions and their files', function () {
    Storage::fake('local');
    $folder = ClassFolder::factory()->create();
    Storage::disk('local')->put('classroom/x/a.pdf', 'a');
    Storage::disk('local')->put('classroom/x/b.pdf', 'b');
    Storage::disk('local')->put('classroom/x/keep.pdf', 'k');
    ClassSubmission::factory()->for($folder, 'folder')->create(['file_path' => 'classroom/x/a.pdf']);
    ClassSubmission::factory()->for($folder, 'folder')->create(['file_path' => 'classroom/x/b.pdf']);
    $other = ClassSubmission::factory()->create(['file_path' => 'classroom/x/keep.pdf']);

    app(DeleteFolder::class)($folder);

    expect(ClassFolder::whereKey($folder->id)->exists())->toBeFalse()
        ->and(ClassSubmission::where('class_folder_id', $folder->id)->count())->toBe(0)
        ->and(ClassSubmission::whereKey($other->id)->exists())->toBeTrue();
    Storage::disk('local')->assertMissing('classroom/x/a.pdf');
    Storage::disk('local')->assertMissing('classroom/x/b.pdf');
    Storage::disk('local')->assertExists('classroom/x/keep.pdf');
});
```

`tests/Feature/Adviser/FoldersTest.php`:

```php
<?php

use App\Enums\SubmissionStatus;
use App\Models\ClassFolder;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
});

it('lists folders with lock state and pending counts on the documents tab', function () {
    $open = ClassFolder::factory()->for($this->section)->create(['name' => 'Endorsement Letter']);
    ClassFolder::factory()->for($this->section)->locked()->create(['name' => 'Weekly Report 1']);
    ClassSubmission::factory()->for($open, 'folder')->count(2)->create();
    ClassSubmission::factory()->for($open, 'folder')->approved()->create();

    $this->actingAs($this->adviser)->get(route('adviser.classes.documents', $this->section))
        ->assertOk()->assertSee('Endorsement Letter')->assertSee('Weekly Report 1')->assertSee('Locked')->assertSee('2 pending')->assertSee('Documents');
    $this->actingAs($this->adviser)->get(route('adviser.classes.show', $this->section))->assertSee(route('adviser.classes.documents', $this->section));
});

it('creates folders with unique names per class', function () {
    $this->actingAs($this->adviser)->post(route('adviser.folders.store', $this->section), ['name' => 'Resume'])
        ->assertRedirect(route('adviser.classes.documents', $this->section))->assertSessionHas('success');
    expect($this->section->folders()->where('name', 'Resume')->exists())->toBeTrue();

    $this->actingAs($this->adviser)->post(route('adviser.folders.store', $this->section), ['name' => 'Resume'])->assertSessionHasErrors('name');
    $this->actingAs($this->adviser)->post(route('adviser.folders.store', ClassSection::factory()->create()), ['name' => 'Resume'])->assertForbidden();
});

it('locks, unlocks and deletes folders it manages', function () {
    Storage::fake('local');
    $folder = ClassFolder::factory()->for($this->section)->create();
    Storage::disk('local')->put('classroom/x/a.pdf', 'a');
    ClassSubmission::factory()->for($folder, 'folder')->create(['file_path' => 'classroom/x/a.pdf']);
    $foreign = ClassFolder::factory()->create();

    $this->actingAs($this->adviser)->post(route('adviser.folders.lock', $folder))->assertRedirect();
    expect($folder->refresh()->is_locked)->toBeTrue();
    $this->actingAs($this->adviser)->post(route('adviser.folders.lock', $folder))->assertRedirect();
    expect($folder->refresh()->is_locked)->toBeFalse();

    $this->actingAs($this->adviser)->post(route('adviser.folders.lock', $foreign))->assertForbidden();
    $this->actingAs($this->adviser)->delete(route('adviser.folders.destroy', $foreign))->assertForbidden();

    $this->actingAs($this->adviser)->delete(route('adviser.folders.destroy', $folder))->assertRedirect(route('adviser.classes.documents', $this->section));
    expect(ClassFolder::whereKey($folder->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('classroom/x/a.pdf');
});

it('groups submissions by status and late on the folder page', function () {
    $folder = ClassFolder::factory()->for($this->section)->create(['name' => 'Endorsement Letter']);
    $pending = ClassSubmission::factory()->for($folder, 'folder')->create(['title' => 'Pending doc']);
    $late = ClassSubmission::factory()->for($folder, 'folder')->create(['title' => 'Late doc', 'is_late' => true]);
    $approved = ClassSubmission::factory()->for($folder, 'folder')->approved()->create(['title' => 'Approved doc']);
    $declined = ClassSubmission::factory()->for($folder, 'folder')->declined()->create(['title' => 'Declined doc', 'reviewer_note' => 'Wrong file']);

    $page = fn (string $status) => $this->actingAs($this->adviser)->get(route('adviser.folders.show', [$folder, 'status' => $status]))->assertOk();

    $page('pending')->assertSee('Pending doc')->assertSee('Late doc')->assertDontSee('Approved doc')->assertSee($pending->intern->name);
    $page('approved')->assertSee('Approved doc')->assertDontSee('Pending doc');
    $page('declined')->assertSee('Declined doc')->assertSee('Wrong file');
    $page('late')->assertSee('Late doc')->assertDontSee('Pending doc')->assertSee('Late');
    $this->actingAs($this->adviser)->get(route('adviser.folders.show', $folder))->assertSee('Pending doc')->assertSee(route('files.show', ['class-submission', $pending->id]));
    $this->actingAs($this->adviser)->get(route('adviser.folders.show', [$folder, 'status' => 'bogus']))->assertNotFound();
    $this->actingAs(User::factory()->adviser()->create())->get(route('adviser.folders.show', $folder))->assertForbidden();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="FolderActions|Adviser.*FoldersTest"`
Expected: FAIL.

- [ ] **Step 3: Model relation, request, actions**

Add to `app/Models/ClassSection.php` (import `Illuminate\Database\Eloquent\Relations\HasManyThrough` is already there):

```php
    public function submissions(): HasManyThrough
    {
        return $this->hasManyThrough(ClassSubmission::class, ClassFolder::class);
    }
```

`app/Http/Requests/Adviser/FolderRequest.php`:

```php
<?php

namespace App\Http\Requests\Adviser;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('classSection'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100',
                Rule::unique('class_folders', 'name')->where('class_section_id', $this->route('classSection')->id)],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'This class already has a folder with that name.'];
    }
}
```

`app/Actions/CreateFolder.php`:

```php
<?php

namespace App\Actions;

use App\Models\ClassFolder;
use App\Models\ClassSection;

class CreateFolder
{
    public function __invoke(ClassSection $section, string $name): ClassFolder
    {
        return $section->folders()->create(['name' => trim($name), 'is_locked' => false]);
    }
}
```

`app/Actions/ToggleFolderLock.php`:

```php
<?php

namespace App\Actions;

use App\Models\ClassFolder;

/** Locking does not block uploads; it marks later uploads as late. */
class ToggleFolderLock
{
    public function __invoke(ClassFolder $folder): ClassFolder
    {
        $folder->update(['is_locked' => ! $folder->is_locked]);

        return $folder;
    }
}
```

`app/Actions/DeleteFolder.php`:

```php
<?php

namespace App\Actions;

use App\Models\ClassFolder;
use Illuminate\Support\Facades\Storage;

class DeleteFolder
{
    public function __invoke(ClassFolder $folder): void
    {
        $paths = $folder->submissions()->pluck('file_path')->filter()->values()->all();

        $folder->delete(); // submissions cascade at the database level

        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }
}
```

- [ ] **Step 4: Controllers, routes, views**

Add to `ClassSectionController` (import `App\Enums\SubmissionStatus`):

```php
    public function documents(ClassSection $classSection): View
    {
        Gate::authorize('view', $classSection);

        return view('adviser.classes.documents', [
            'class' => $classSection->loadCount('internProfiles'),
            'folders' => $classSection->folders()
                ->withCount(['submissions', 'submissions as pending_submissions_count' => fn ($q) => $q->where('status', SubmissionStatus::Pending)])
                ->orderBy('name')->get(),
        ]);
    }
```

`app/Http/Controllers/Adviser/FolderController.php`:

```php
<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\CreateFolder;
use App\Actions\DeleteFolder;
use App\Actions\ToggleFolderLock;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\FolderRequest;
use App\Models\ClassFolder;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FolderController extends Controller
{
    public const GROUPS = ['pending', 'approved', 'declined', 'late'];

    public function store(FolderRequest $request, ClassSection $classSection, CreateFolder $create): RedirectResponse
    {
        $folder = $create($classSection, $request->validated('name'));

        return redirect()->route('adviser.classes.documents', $classSection)->with('success', "Folder “{$folder->name}” created.");
    }

    public function show(Request $request, ClassFolder $folder): View
    {
        Gate::authorize('manage', $folder);

        $group = (string) $request->query('status', 'pending');
        abort_unless(in_array($group, self::GROUPS, true), 404);

        $base = ClassSubmission::query()->where('class_folder_id', $folder->id);

        $counts = [
            'pending' => (clone $base)->where('status', SubmissionStatus::Pending)->count(),
            'approved' => (clone $base)->where('status', SubmissionStatus::Approved)->count(),
            'declined' => (clone $base)->where('status', SubmissionStatus::Declined)->count(),
            'late' => (clone $base)->where('is_late', true)->count(),
        ];

        $submissions = $group === 'late'
            ? (clone $base)->where('is_late', true)
            : (clone $base)->where('status', SubmissionStatus::from($group));

        return view('adviser.folders.show', [
            'folder' => $folder->load('classSection'),
            'class' => $folder->classSection->loadCount('internProfiles'),
            'group' => $group,
            'counts' => $counts,
            'submissions' => $submissions->with(['intern.internProfile', 'reviewer'])->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function toggleLock(ClassFolder $folder, ToggleFolderLock $toggle): RedirectResponse
    {
        Gate::authorize('manage', $folder);

        $toggle($folder);

        return back()->with('success', $folder->is_locked
            ? "“{$folder->name}” is locked. New uploads will be marked late."
            : "“{$folder->name}” is open again.");
    }

    public function destroy(ClassFolder $folder, DeleteFolder $delete): RedirectResponse
    {
        Gate::authorize('manage', $folder);

        $section = $folder->classSection;
        $delete($folder);

        return redirect()->route('adviser.classes.documents', $section)->with('success', "Folder “{$folder->name}” and its submissions were deleted.");
    }
}
```

Routes — add inside the adviser group:

```php
        Route::get('classes/{classSection}/documents', [Adviser\ClassSectionController::class, 'documents'])->name('classes.documents');
        Route::post('classes/{classSection}/folders', [Adviser\FolderController::class, 'store'])->name('folders.store');
        Route::get('folders/{folder}', [Adviser\FolderController::class, 'show'])->name('folders.show');
        Route::post('folders/{folder}/lock', [Adviser\FolderController::class, 'toggleLock'])->name('folders.lock');
        Route::delete('folders/{folder}', [Adviser\FolderController::class, 'destroy'])->name('folders.destroy');
```

`resources/views/adviser/classes/documents.blade.php`:

```blade
<x-layouts.app :title="'Documents · '.$class->display_name">
    @include('adviser.classes.partials.header')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card title="Folders" subtitle="Interns upload PDFs into folders. Lock a folder once the deadline passes; later uploads are flagged late." :padding="false">
                @forelse ($folders as $folder)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 px-5 py-4 last:border-0 dark:border-stone-800">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10">
                                @if ($folder->is_locked)<x-heroicon-o-lock-closed class="size-5" />@else<x-heroicon-o-folder class="size-5" />@endif
                            </span>
                            <div class="min-w-0">
                                <a href="{{ route('adviser.folders.show', $folder) }}" class="font-medium hover:underline">{{ $folder->name }}</a>
                                <p class="text-xs text-stone-500">{{ $folder->pending_submissions_count }} pending · {{ $folder->submissions_count }} total</p>
                            </div>
                            @if ($folder->is_locked)<x-badge color="amber">Locked</x-badge>@endif
                        </div>
                        @can('manage', $class)
                            <div class="flex items-center gap-1">
                                <form method="POST" action="{{ route('adviser.folders.lock', $folder) }}">
                                    @csrf
                                    <x-button variant="ghost" :icon="$folder->is_locked ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed'">{{ $folder->is_locked ? 'Unlock' : 'Lock' }}</x-button>
                                </form>
                                <x-confirm-form :action="route('adviser.folders.destroy', $folder)" method="DELETE" :confirm="'Delete “'.$folder->name.'” and all '.$folder->submissions_count.' submissions in it? Files are removed permanently.'">
                                    <x-button variant="ghost" icon="heroicon-o-trash" class="text-rose-600">Delete</x-button>
                                </x-confirm-form>
                            </div>
                        @endcan
                    </div>
                @empty
                    <x-empty-state title="No folders yet" description="Create a folder such as “Endorsement Letter” or “Weekly Report 1”." icon="heroicon-o-folder" />
                @endforelse
            </x-card>

            {{-- Task 12 adds the shared resources card here --}}
        </div>

        <div class="space-y-4">
            @can('manage', $class)
                <x-card title="New folder">
                    <form method="POST" action="{{ route('adviser.folders.store', $class) }}" class="space-y-4">
                        @csrf
                        <x-form.input name="name" label="Folder name" placeholder="Weekly Report 2" required maxlength="100" />
                        <x-button class="w-full" icon="heroicon-o-folder-plus">Create folder</x-button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
</x-layouts.app>
```

`resources/views/adviser/folders/show.blade.php`:

```blade
<x-layouts.app :title="$folder->name.' · '.$class->display_name">
    <x-page-header :title="$folder->name" :subtitle="$class->display_name.' · '.$class->subject" :breadcrumbs="['My classes' => route('adviser.classes.index'), $class->display_name => route('adviser.classes.show', $class), 'Documents' => route('adviser.classes.documents', $class), $folder->name => null]">
        <x-slot:actions>
            @if ($folder->is_locked)<x-badge color="amber">Locked · new uploads are marked late</x-badge>@endif
            <form method="POST" action="{{ route('adviser.folders.lock', $folder) }}">
                @csrf
                <x-button variant="secondary" :icon="$folder->is_locked ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed'">{{ $folder->is_locked ? 'Unlock folder' : 'Lock folder' }}</x-button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <nav class="flex flex-wrap gap-2" aria-label="Submission groups">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'declined' => 'Declined', 'late' => 'Late'] as $key => $label)
            <a href="{{ route('adviser.folders.show', [$folder, 'status' => $key]) }}"
               @class(['inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                       'bg-brand-600 text-white ring-brand-600' => $group === $key,
                       'bg-white text-stone-600 ring-stone-300 hover:bg-stone-50 dark:bg-stone-900 dark:text-stone-300 dark:ring-stone-700' => $group !== $key])
               @if ($group === $key) aria-current="page" @endif>
                {{ $label }} <span class="rounded-full bg-black/10 px-1.5 text-xs tabular-nums dark:bg-white/10">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Intern</th><th>Document</th><th>Submitted</th><th>Status</th><th>Note</th><th class="text-right">Actions</th></x-slot:head>
            @forelse ($submissions as $submission)
                <tr>
                    <td>
                        <p class="font-medium">{{ $submission->intern->name }}</p>
                        <p class="font-mono text-xs text-stone-500">{{ $submission->intern->internProfile?->student_number }}</p>
                    </td>
                    <td><a href="{{ route('files.show', ['class-submission', $submission]) }}" target="_blank" class="inline-flex items-center gap-1.5 font-medium text-brand-700 hover:underline dark:text-brand-300"><x-heroicon-o-document-text class="size-4" /> {{ $submission->title }}</a></td>
                    <td class="text-stone-500">
                        {{ $submission->created_at->format('M j, Y g:i A') }}
                        @if ($submission->is_late)<x-badge color="rose" class="ml-1">Late</x-badge>@endif
                    </td>
                    <td>
                        <x-badge :status="$submission->status" />
                        @if ($submission->reviewed_at)<p class="mt-1 text-xs text-stone-500">{{ $submission->reviewer?->name }} · {{ $submission->reviewed_at->format('M j') }}</p>@endif
                    </td>
                    <td class="max-w-xs text-sm text-stone-600 dark:text-stone-300">{{ $submission->reviewer_note ?? '—' }}</td>
                    <td class="text-right">
                        {{-- Task 11 adds the approve and decline controls here --}}
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state :title="'No '.$group.' submissions'" class="py-8" icon="heroicon-o-document-check" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$submissions" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="FolderActions|Adviser.*FoldersTest|ClassPages"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: add class folders with lock, delete and the submission queue page"
```

---

### Task 10: Intern documents tab, folder uploads (late flag), delete and "My submissions"

**Files:**
- Create: `app/Actions/SubmitClassDocument.php`, `app/Actions/DeleteSubmission.php`, `app/Http/Requests/Intern/SubmitDocumentRequest.php`, `app/Http/Controllers/Intern/FolderController.php`, `app/Http/Controllers/Intern/SubmissionController.php`, `app/Notifications/ClassDocumentSubmitted.php`
- Create: `resources/views/intern/class/documents.blade.php`, `resources/views/intern/folders/show.blade.php`, `resources/views/intern/submissions/index.blade.php`
- Modify: `app/Http/Controllers/Intern/ClassController.php` (`documents()`), `routes/web.php`
- Test: `tests/Unit/Actions/SubmissionActionsTest.php`, `tests/Feature/Intern/SubmissionsTest.php`

**Interfaces:**
- Consumes: `ClassFolderPolicy::view|submit`, `ClassSubmissionPolicy::delete`, `files.show` kind `class-submission`, `adviser.folders.show` (for the notification url).
- Produces: `SubmitClassDocument(ClassFolder, User $intern, string $title, UploadedFile $file): ClassSubmission` (stores `classroom/{class}/folders/{folder}/{intern}/{uuid}.pdf`, `is_late = folder.is_locked`), `DeleteSubmission(ClassSubmission): void`; routes `intern.class.documents` (GET `/intern/class/documents`), `intern.folders.show` (GET `/intern/folders/{folder}`), `intern.submissions.store` (POST `/intern/folders/{folder}/submissions`), `intern.submissions.index` (GET `/intern/submissions`), `intern.submissions.destroy` (DELETE `/intern/submissions/{submission}`); `ClassDocumentSubmitted` notification to the class adviser. Task 12 adds the resources list to `intern/class/documents.blade.php` at the marked spot.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/SubmissionActionsTest.php`:

```php
<?php

use App\Actions\DeleteSubmission;
use App\Actions\SubmitClassDocument;
use App\Actions\ToggleFolderLock;
use App\Enums\SubmissionStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\ClassFolder;
use App\Models\ClassSubmission;
use App\Models\User;
use App\Notifications\ClassDocumentSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->folder = ClassFolder::factory()->create();
    $this->intern = User::factory()->intern()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->intern->internProfile->update(['class_section_id' => $this->folder->class_section_id]);
});

it('stores the PDF privately, creates a pending submission and notifies the adviser', function () {
    $submission = app(SubmitClassDocument::class)($this->folder, $this->intern, ' Endorsement letter ', UploadedFile::fake()->create('letter.pdf', 120, 'application/pdf'));

    expect($submission->status)->toBe(SubmissionStatus::Pending)->and($submission->is_late)->toBeFalse()
        ->and($submission->title)->toBe('Endorsement letter')->and($submission->intern_id)->toBe($this->intern->id)
        ->and($submission->file_path)->toStartWith("classroom/{$this->folder->class_section_id}/folders/{$this->folder->id}/{$this->intern->id}/")->toEndWith('.pdf');
    Storage::disk('local')->assertExists($submission->file_path);
    Notification::assertSentTo($this->folder->classSection->adviser, ClassDocumentSubmitted::class, function (ClassDocumentSubmitted $n) {
        $data = $n->toArray($this->folder->classSection->adviser);

        return str_contains($data['body'], 'Maria Santos') && $data['url'] === route('adviser.folders.show', $this->folder);
    });
});

it('flags uploads into a locked folder as late, and unlocking later keeps the flag', function () {
    app(ToggleFolderLock::class)($this->folder);

    $submission = app(SubmitClassDocument::class)($this->folder->refresh(), $this->intern, 'Weekly report', UploadedFile::fake()->create('w.pdf', 10, 'application/pdf'));
    expect($submission->is_late)->toBeTrue();

    app(ToggleFolderLock::class)($this->folder);
    expect($submission->refresh()->is_late)->toBeTrue();
    expect(app(SubmitClassDocument::class)($this->folder->refresh(), $this->intern, 'Again', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))->is_late)->toBeFalse();
});

it('deletes a pending submission with its file but refuses reviewed ones', function () {
    $submission = app(SubmitClassDocument::class)($this->folder, $this->intern, 'Doc', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'));
    $path = $submission->file_path;

    app(DeleteSubmission::class)($submission);
    expect(ClassSubmission::whereKey($submission->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);

    $approved = ClassSubmission::factory()->for($this->folder, 'folder')->approved()->create();
    expect(fn () => app(DeleteSubmission::class)($approved))->toThrow(DomainRuleViolation::class);
    expect(ClassSubmission::whereKey($approved->id)->exists())->toBeTrue();
});
```

`tests/Feature/Intern/SubmissionsTest.php`:

```php
<?php

use App\Models\ClassFolder;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\User;
use App\Notifications\ClassDocumentSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->section = ClassSection::factory()->create();
    $this->folder = ClassFolder::factory()->for($this->section)->create(['name' => 'Endorsement Letter']);
    $this->intern = User::factory()->intern()->create();
    $this->intern->internProfile->update(['class_section_id' => $this->section->id]);
});

it('lists folders on the documents tab and shows the upload form in a folder', function () {
    ClassFolder::factory()->for($this->section)->locked()->create(['name' => 'Weekly Report 1']);

    $this->actingAs($this->intern)->get(route('intern.class.documents'))
        ->assertOk()->assertSee('Endorsement Letter')->assertSee('Weekly Report 1')->assertSee('Locked')->assertSee(route('intern.folders.show', $this->folder));
    $this->actingAs($this->intern)->get(route('intern.folders.show', $this->folder))
        ->assertOk()->assertSee('Endorsement Letter')->assertSee('name="file"', false)->assertSee('name="title"', false);
    $this->actingAs($this->intern)->get(route('intern.class.show'))->assertSee(route('intern.class.documents'));
});

it('uploads a PDF, notifies the adviser and marks late uploads', function () {
    Notification::fake();

    $this->actingAs($this->intern)->post(route('intern.submissions.store', $this->folder), [
        'title' => 'My endorsement letter', 'file' => UploadedFile::fake()->create('letter.pdf', 200, 'application/pdf'),
    ])->assertRedirect(route('intern.folders.show', $this->folder))->assertSessionHas('success');

    $submission = ClassSubmission::firstOrFail();
    expect($submission->is_late)->toBeFalse()->and($submission->intern_id)->toBe($this->intern->id);
    Storage::disk('local')->assertExists($submission->file_path);
    Notification::assertSentTo($this->section->adviser, ClassDocumentSubmitted::class);

    $this->folder->update(['is_locked' => true]);
    $this->actingAs($this->intern)->post(route('intern.submissions.store', $this->folder), [
        'title' => 'Late one', 'file' => UploadedFile::fake()->create('late.pdf', 200, 'application/pdf'),
    ])->assertRedirect();
    expect(ClassSubmission::where('title', 'Late one')->firstOrFail()->is_late)->toBeTrue();

    $this->actingAs($this->intern)->get(route('intern.folders.show', $this->folder))
        ->assertSee('My endorsement letter')->assertSee('Late one')->assertSee('Late')->assertSee(route('files.show', ['class-submission', $submission->id]));
});

it('rejects non-PDF, oversized and untitled uploads', function () {
    $this->actingAs($this->intern)->post(route('intern.submissions.store', $this->folder), [
        'title' => 'Doc', 'file' => UploadedFile::fake()->create('doc.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ])->assertSessionHasErrors('file');
    $this->actingAs($this->intern)->post(route('intern.submissions.store', $this->folder), [
        'title' => 'Doc', 'file' => UploadedFile::fake()->create('big.pdf', config('wiis.uploads.max_pdf_kb') + 1, 'application/pdf'),
    ])->assertSessionHasErrors('file');
    $this->actingAs($this->intern)->post(route('intern.submissions.store', $this->folder), [
        'title' => '', 'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('title');
    expect(ClassSubmission::count())->toBe(0);
});

it('keeps outsiders out of folders', function () {
    $outsider = User::factory()->intern()->create();

    $this->actingAs($outsider)->get(route('intern.folders.show', $this->folder))->assertForbidden();
    $this->actingAs($outsider)->post(route('intern.submissions.store', $this->folder), [
        'title' => 'Doc', 'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
    ])->assertForbidden();
    $this->actingAs($outsider)->get(route('intern.class.documents'))->assertRedirect(route('intern.class.show'));
});

it('lets the intern withdraw a pending submission but not a reviewed one', function () {
    Storage::disk('local')->put('classroom/x/mine.pdf', 'x');
    $pending = ClassSubmission::factory()->for($this->folder, 'folder')->for($this->intern, 'intern')->create(['file_path' => 'classroom/x/mine.pdf']);
    $approved = ClassSubmission::factory()->for($this->folder, 'folder')->for($this->intern, 'intern')->approved()->create();
    $someoneElses = ClassSubmission::factory()->for($this->folder, 'folder')->create();

    $this->actingAs($this->intern)->delete(route('intern.submissions.destroy', $approved))->assertForbidden();
    $this->actingAs($this->intern)->delete(route('intern.submissions.destroy', $someoneElses))->assertForbidden();
    $this->actingAs($this->intern)->delete(route('intern.submissions.destroy', $pending))->assertRedirect();

    expect(ClassSubmission::whereKey($pending->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('classroom/x/mine.pdf');
});

it('lists all of the intern’s submissions with folder, status and notes', function () {
    ClassSubmission::factory()->for($this->folder, 'folder')->for($this->intern, 'intern')->declined()->create(['title' => 'Old resume', 'reviewer_note' => 'Please use the template.']);
    ClassSubmission::factory()->for($this->folder, 'folder')->create(['title' => 'Not mine']);

    $this->actingAs($this->intern)->get(route('intern.submissions.index'))
        ->assertOk()->assertSee('Old resume')->assertSee('Endorsement Letter')->assertSee('Declined')->assertSee('Please use the template.')->assertDontSee('Not mine');
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="SubmissionActions|Intern.*SubmissionsTest"`
Expected: FAIL.

- [ ] **Step 3: Request, actions, notification**

`app/Http/Requests/Intern/SubmitDocumentRequest.php`:

```php
<?php

namespace App\Http\Requests\Intern;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->route('folder'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['title' => trim((string) $this->input('title'))]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('wiis.uploads.max_pdf_kb')],
        ];
    }

    public function messages(): array
    {
        return ['file.mimetypes' => 'The document must be a PDF file.'];
    }
}
```

`app/Actions/SubmitClassDocument.php`:

```php
<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Models\ClassFolder;
use App\Models\ClassSubmission;
use App\Models\User;
use App\Notifications\ClassDocumentSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Uploads are always accepted; a locked folder only marks the submission late. */
class SubmitClassDocument
{
    public function __invoke(ClassFolder $folder, User $intern, string $title, UploadedFile $file): ClassSubmission
    {
        $folder->loadMissing('classSection.adviser');

        $path = $file->storeAs(
            "classroom/{$folder->class_section_id}/folders/{$folder->id}/{$intern->id}",
            Str::uuid().'.pdf',
            'local',
        );

        $submission = $folder->submissions()->create([
            'intern_id' => $intern->id,
            'title' => trim($title),
            'file_path' => $path,
            'status' => SubmissionStatus::Pending,
            'is_late' => $folder->is_locked,
        ]);

        $folder->classSection->adviser?->notify(new ClassDocumentSubmitted($submission->load('intern')));

        return $submission;
    }
}
```

`app/Actions/DeleteSubmission.php`:

```php
<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\ClassSubmission;
use Illuminate\Support\Facades\Storage;

class DeleteSubmission
{
    public function __invoke(ClassSubmission $submission): void
    {
        if ($submission->status !== SubmissionStatus::Pending) {
            throw new DomainRuleViolation('Only pending submissions can be withdrawn.');
        }

        $path = $submission->file_path;

        $submission->delete();

        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
```

`app/Notifications/ClassDocumentSubmitted.php`:

```php
<?php

namespace App\Notifications;

use App\Models\ClassSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClassDocumentSubmitted extends Notification
{
    use Queueable;

    public function __construct(public ClassSubmission $submission) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $folder = $this->submission->folder;

        return [
            'title' => "New submission in {$folder->name}",
            'body' => "{$this->submission->intern->name} uploaded “{$this->submission->title}”".($this->submission->is_late ? ' (late).' : '.'),
            'url' => route('adviser.folders.show', $folder),
            'icon' => 'heroicon-o-document-arrow-up',
        ];
    }
}
```

- [ ] **Step 4: Controllers, routes, views**

Add to `Intern\ClassController` (imports: `App\Enums\SubmissionStatus` is not needed here):

```php
    public function documents(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $section = $this->currentSection($user);

        if (! $section) {
            return redirect()->route('intern.class.show');
        }

        return view('intern.class.documents', [
            'class' => $section,
            'folders' => $section->folders()
                ->with(['submissions' => fn ($q) => $q->where('intern_id', $user->id)->latest()])
                ->orderBy('name')->get(),
        ]);
    }
```

`app/Http/Controllers/Intern/FolderController.php`:

```php
<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\ClassFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FolderController extends Controller
{
    public function show(Request $request, ClassFolder $folder): View
    {
        Gate::authorize('view', $folder);

        return view('intern.folders.show', [
            'folder' => $folder->load('classSection'),
            'class' => $folder->classSection->loadCount('internProfiles'),
            'submissions' => $folder->submissions()->where('intern_id', $request->user()->id)->with('reviewer')->latest()->get(),
        ]);
    }
}
```

`app/Http/Controllers/Intern/SubmissionController.php`:

```php
<?php

namespace App\Http\Controllers\Intern;

use App\Actions\DeleteSubmission;
use App\Actions\SubmitClassDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intern\SubmitDocumentRequest;
use App\Models\ClassFolder;
use App\Models\ClassSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SubmissionController extends Controller
{
    public function index(Request $request): View
    {
        return view('intern.submissions.index', [
            'submissions' => ClassSubmission::query()
                ->where('intern_id', $request->user()->id)
                ->with(['folder.classSection', 'reviewer'])
                ->latest()->paginate(20),
        ]);
    }

    public function store(SubmitDocumentRequest $request, ClassFolder $folder, SubmitClassDocument $submit): RedirectResponse
    {
        $submission = $submit($folder, $request->user(), $request->validated('title'), $request->file('file'));

        return redirect()->route('intern.folders.show', $folder)->with('success', $submission->is_late
            ? 'Uploaded. This folder is locked, so the submission is marked late.'
            : 'Uploaded. Your adviser will review it.');
    }

    public function destroy(ClassSubmission $submission, DeleteSubmission $delete): RedirectResponse
    {
        Gate::authorize('delete', $submission);

        $delete($submission);

        return back()->with('success', 'Submission withdrawn.');
    }
}
```

Routes — add inside the intern group:

```php
        Route::get('class/documents', [Intern\ClassController::class, 'documents'])->name('class.documents');
        Route::get('folders/{folder}', [Intern\FolderController::class, 'show'])->name('folders.show');
        Route::post('folders/{folder}/submissions', [Intern\SubmissionController::class, 'store'])->name('submissions.store');
        Route::get('submissions', [Intern\SubmissionController::class, 'index'])->name('submissions.index');
        Route::delete('submissions/{submission}', [Intern\SubmissionController::class, 'destroy'])->name('submissions.destroy');
```

`resources/views/intern/class/documents.blade.php`:

```blade
<x-layouts.app :title="'Documents · '.$class->display_name">
    @include('intern.class.partials.header')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card title="Folders" subtitle="Upload the PDF your adviser asked for into its folder." :padding="false">
                @forelse ($folders as $folder)
                    @php $latest = $folder->submissions->first(); @endphp
                    <a href="{{ route('intern.folders.show', $folder) }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 px-5 py-4 last:border-0 hover:bg-stone-50 dark:border-stone-800 dark:hover:bg-stone-900">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10">
                                @if ($folder->is_locked)<x-heroicon-o-lock-closed class="size-5" />@else<x-heroicon-o-folder class="size-5" />@endif
                            </span>
                            <div class="min-w-0">
                                <p class="font-medium">{{ $folder->name }}</p>
                                <p class="text-xs text-stone-500">{{ $folder->submissions->count() }} of yours{{ $folder->is_locked ? ' · new uploads are marked late' : '' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($folder->is_locked)<x-badge color="amber">Locked</x-badge>@endif
                            @if ($latest)<x-badge :status="$latest->status" />@else<span class="text-xs text-stone-500">Nothing uploaded</span>@endif
                        </div>
                    </a>
                @empty
                    <x-empty-state title="No folders yet" description="Your adviser has not created any folders." icon="heroicon-o-folder" />
                @endforelse
            </x-card>

            {{-- Task 12 adds the shared resources card here --}}
        </div>

        <x-card title="How it works">
            <ul class="list-disc space-y-2 pl-5 text-sm text-stone-600 dark:text-stone-300">
                <li>Only PDF files up to {{ (int) (config('wiis.uploads.max_pdf_kb') / 1024) }} MB are accepted.</li>
                <li>Your adviser approves or declines each upload and may leave a note.</li>
                <li>You can withdraw an upload until it is reviewed.</li>
                <li>Locked folders still accept uploads, but they are marked late.</li>
            </ul>
        </x-card>
    </div>
</x-layouts.app>
```

`resources/views/intern/folders/show.blade.php`:

```blade
<x-layouts.app :title="$folder->name.' · '.$class->display_name">
    @include('intern.class.partials.header')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="$folder->name" :subtitle="$submissions->count().' uploaded by you'" :padding="false">
                <x-slot:actions>
                    <x-button variant="ghost" :href="route('intern.class.documents')" icon="heroicon-o-arrow-left">All folders</x-button>
                </x-slot:actions>
                <x-table>
                    <x-slot:head><th>Document</th><th>Uploaded</th><th>Status</th><th>Note</th><th></th></x-slot:head>
                    @forelse ($submissions as $submission)
                        <tr>
                            <td><a href="{{ route('files.show', ['class-submission', $submission]) }}" target="_blank" class="inline-flex items-center gap-1.5 font-medium text-brand-700 hover:underline dark:text-brand-300"><x-heroicon-o-document-text class="size-4" /> {{ $submission->title }}</a></td>
                            <td class="text-stone-500">{{ $submission->created_at->format('M j, Y g:i A') }} @if ($submission->is_late)<x-badge color="rose" class="ml-1">Late</x-badge>@endif</td>
                            <td><x-badge :status="$submission->status" /></td>
                            <td class="max-w-xs text-sm text-stone-600 dark:text-stone-300">{{ $submission->reviewer_note ?? '—' }}</td>
                            <td class="text-right">
                                @can('delete', $submission)
                                    <x-confirm-form :action="route('intern.submissions.destroy', $submission)" method="DELETE" confirm="Withdraw this upload? The file will be deleted.">
                                        <x-button variant="ghost" icon="heroicon-o-trash" class="text-rose-600">Withdraw</x-button>
                                    </x-confirm-form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state title="Nothing uploaded yet" description="Use the form to upload your PDF." class="py-8" icon="heroicon-o-document-arrow-up" /></td></tr>
                    @endforelse
                </x-table>
            </x-card>
        </div>

        <x-card title="Upload a document">
            @if ($folder->is_locked)
                <div class="mb-4 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    <x-heroicon-o-lock-closed class="mt-0.5 size-4 shrink-0" />
                    <p>This folder is locked. You can still upload, but the submission will be marked <strong>late</strong>.</p>
                </div>
            @endif
            <form method="POST" action="{{ route('intern.submissions.store', $folder) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <x-form.input name="title" label="Title" placeholder="Endorsement letter" required maxlength="150" />
                <x-form.file name="file" label="PDF file" accept="application/pdf" required :hint="'PDF only, up to '.(int) (config('wiis.uploads.max_pdf_kb') / 1024).' MB.'" />
                <x-button class="w-full" icon="heroicon-o-arrow-up-tray">Upload</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.app>
```

`resources/views/intern/submissions/index.blade.php`:

```blade
<x-layouts.app title="My submissions">
    <x-page-header title="My submissions" subtitle="Everything you have uploaded to your class folders." />

    <x-card :padding="false">
        <x-table>
            <x-slot:head><th>Document</th><th>Folder</th><th>Uploaded</th><th>Status</th><th>Note</th></x-slot:head>
            @forelse ($submissions as $submission)
                <tr>
                    <td><a href="{{ route('files.show', ['class-submission', $submission]) }}" target="_blank" class="font-medium text-brand-700 hover:underline dark:text-brand-300">{{ $submission->title }}</a></td>
                    <td><a href="{{ route('intern.folders.show', $submission->folder) }}" class="hover:underline">{{ $submission->folder->name }}</a><p class="text-xs text-stone-500">{{ $submission->folder->classSection->display_name }}</p></td>
                    <td class="text-stone-500">{{ $submission->created_at->format('M j, Y') }} @if ($submission->is_late)<x-badge color="rose" class="ml-1">Late</x-badge>@endif</td>
                    <td><x-badge :status="$submission->status" />@if ($submission->reviewed_at)<p class="mt-1 text-xs text-stone-500">{{ $submission->reviewer?->name }} · {{ $submission->reviewed_at->format('M j') }}</p>@endif</td>
                    <td class="max-w-xs text-sm text-stone-600 dark:text-stone-300">{{ $submission->reviewer_note ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state title="No submissions yet" description="Open your class's Documents tab to upload." icon="heroicon-o-document-check" /></td></tr>
            @endforelse
        </x-table>
        <x-pagination :paginator="$submissions" class="px-5 pb-4" />
    </x-card>
</x-layouts.app>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="SubmissionActions|Intern.*SubmissionsTest|Intern.*ClassTest|Navigation"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: let interns upload class documents with late flagging and track submissions"
```

---

### Task 11: Submission review — approve / decline with a note

**Files:**
- Create: `app/Actions/ReviewClassSubmission.php`, `app/Http/Requests/Adviser/DeclineSubmissionRequest.php`, `app/Http/Controllers/Adviser/SubmissionReviewController.php`, `app/Notifications/ClassSubmissionReviewed.php`
- Modify: `routes/web.php`, `resources/views/adviser/folders/show.blade.php`
- Test: `tests/Unit/Actions/ReviewClassSubmissionTest.php`, `tests/Feature/Adviser/SubmissionReviewTest.php`

**Interfaces:**
- Consumes: `ClassSubmissionPolicy::review`, `intern.folders.show` (notification url), `x-modal`.
- Produces: `ReviewClassSubmission(ClassSubmission, User $reviewer, SubmissionStatus $decision, ?string $note = null): ClassSubmission`; routes `adviser.submissions.approve` (POST `/adviser/submissions/{submission}/approve`), `adviser.submissions.decline` (POST `/adviser/submissions/{submission}/decline`, `note` required); `ClassSubmissionReviewed` notification to the intern.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/ReviewClassSubmissionTest.php`:

```php
<?php

use App\Actions\ReviewClassSubmission;
use App\Enums\SubmissionStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\ClassSubmission;
use App\Notifications\ClassSubmissionReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('approves a submission, records the reviewer and notifies the intern', function () {
    Notification::fake();
    $submission = ClassSubmission::factory()->create();
    $adviser = $submission->folder->classSection->adviser;

    app(ReviewClassSubmission::class)($submission, $adviser, SubmissionStatus::Approved);

    $submission->refresh();
    expect($submission->status)->toBe(SubmissionStatus::Approved)->and($submission->reviewed_by)->toBe($adviser->id)
        ->and($submission->reviewed_at)->not->toBeNull()->and($submission->reviewer_note)->toBeNull();
    Notification::assertSentTo($submission->intern, ClassSubmissionReviewed::class, function (ClassSubmissionReviewed $n) use ($submission) {
        $data = $n->toArray($submission->intern);

        return str_contains($data['title'], 'approved') && $data['url'] === route('intern.folders.show', $submission->folder);
    });
});

it('declining requires a note and stores it', function () {
    Notification::fake();
    $submission = ClassSubmission::factory()->create();
    $adviser = $submission->folder->classSection->adviser;

    expect(fn () => app(ReviewClassSubmission::class)($submission, $adviser, SubmissionStatus::Declined, '  '))->toThrow(DomainRuleViolation::class);
    expect($submission->refresh()->status)->toBe(SubmissionStatus::Pending);

    app(ReviewClassSubmission::class)($submission, $adviser, SubmissionStatus::Declined, ' Wrong template. ');
    expect($submission->refresh()->status)->toBe(SubmissionStatus::Declined)->and($submission->reviewer_note)->toBe('Wrong template.');
    Notification::assertSentTo($submission->intern, ClassSubmissionReviewed::class, fn (ClassSubmissionReviewed $n) => str_contains($n->toArray($submission->intern)['body'], 'Wrong template.'));
});

it('allows a decision to be changed and refuses pending as a decision', function () {
    Notification::fake();
    $submission = ClassSubmission::factory()->declined()->create(['reviewer_note' => 'Old note']);
    $adviser = $submission->folder->classSection->adviser;

    app(ReviewClassSubmission::class)($submission, $adviser, SubmissionStatus::Approved);
    expect($submission->refresh()->status)->toBe(SubmissionStatus::Approved)->and($submission->reviewer_note)->toBeNull();

    expect(fn () => app(ReviewClassSubmission::class)($submission, $adviser, SubmissionStatus::Pending))->toThrow(InvalidArgumentException::class);
});
```

`tests/Feature/Adviser/SubmissionReviewTest.php`:

```php
<?php

use App\Enums\SubmissionStatus;
use App\Models\ClassFolder;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
use App\Models\User;
use App\Notifications\ClassSubmissionReviewed;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
    $this->folder = ClassFolder::factory()->for($this->section)->create();
    $this->submission = ClassSubmission::factory()->for($this->folder, 'folder')->create(['title' => 'Endorsement']);
});

it('approves from the folder page and notifies the intern', function () {
    Notification::fake();

    $this->actingAs($this->adviser)->get(route('adviser.folders.show', $this->folder))
        ->assertSee(route('adviser.submissions.approve', $this->submission))->assertSee(route('adviser.submissions.decline', $this->submission));

    $this->actingAs($this->adviser)->from(route('adviser.folders.show', $this->folder))
        ->post(route('adviser.submissions.approve', $this->submission))
        ->assertRedirect(route('adviser.folders.show', $this->folder))->assertSessionHas('success');

    expect($this->submission->refresh()->status)->toBe(SubmissionStatus::Approved)->and($this->submission->reviewed_by)->toBe($this->adviser->id);
    Notification::assertSentTo($this->submission->intern, ClassSubmissionReviewed::class);
});

it('declines with a required note', function () {
    $this->actingAs($this->adviser)->post(route('adviser.submissions.decline', $this->submission), ['note' => ''])->assertSessionHasErrors('note');
    expect($this->submission->refresh()->status)->toBe(SubmissionStatus::Pending);

    $this->actingAs($this->adviser)->post(route('adviser.submissions.decline', $this->submission), ['note' => 'Please use the official template.'])
        ->assertRedirect()->assertSessionHas('success');
    expect($this->submission->refresh()->status)->toBe(SubmissionStatus::Declined)->and($this->submission->reviewer_note)->toBe('Please use the official template.');

    $this->actingAs($this->adviser)->get(route('adviser.folders.show', [$this->folder, 'status' => 'declined']))->assertSee('Please use the official template.');
});

it('forbids advisers of other classes', function () {
    $other = User::factory()->adviser()->create();

    $this->actingAs($other)->post(route('adviser.submissions.approve', $this->submission))->assertForbidden();
    $this->actingAs($other)->post(route('adviser.submissions.decline', $this->submission), ['note' => 'x'])->assertForbidden();
    expect($this->submission->refresh()->status)->toBe(SubmissionStatus::Pending);
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="ReviewClassSubmission|SubmissionReview"`
Expected: FAIL.

- [ ] **Step 3: Action, request, notification**

`app/Actions/ReviewClassSubmission.php`:

```php
<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\ClassSubmission;
use App\Models\User;
use App\Notifications\ClassSubmissionReviewed;
use InvalidArgumentException;

/** Approve or decline. A decision may be changed later; the intern is told each time. */
class ReviewClassSubmission
{
    public function __invoke(ClassSubmission $submission, User $reviewer, SubmissionStatus $decision, ?string $note = null): ClassSubmission
    {
        if ($decision === SubmissionStatus::Pending) {
            throw new InvalidArgumentException('A review must approve or decline the submission.');
        }

        $note = trim((string) $note) ?: null;

        if ($decision === SubmissionStatus::Declined && $note === null) {
            throw new DomainRuleViolation('Tell the intern why the document was declined.');
        }

        $submission->update([
            'status' => $decision,
            'reviewer_note' => $note,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $submission->intern->notify(new ClassSubmissionReviewed($submission));

        return $submission;
    }
}
```

`app/Http/Requests/Adviser/DeclineSubmissionRequest.php`:

```php
<?php

namespace App\Http\Requests\Adviser;

use Illuminate\Foundation\Http\FormRequest;

class DeclineSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('submission'));
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
        return ['note.required' => 'Tell the intern why the document was declined.'];
    }
}
```

`app/Notifications/ClassSubmissionReviewed.php`:

```php
<?php

namespace App\Notifications;

use App\Enums\SubmissionStatus;
use App\Models\ClassSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClassSubmissionReviewed extends Notification
{
    use Queueable;

    public function __construct(public ClassSubmission $submission) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $approved = $this->submission->status === SubmissionStatus::Approved;
        $folder = $this->submission->folder;

        return [
            'title' => "“{$this->submission->title}” was ".($approved ? 'approved' : 'declined'),
            'body' => $this->submission->reviewer_note ?? "Your upload in {$folder->name} was ".($approved ? 'approved.' : 'declined.'),
            'url' => route('intern.folders.show', $folder),
            'icon' => $approved ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle',
        ];
    }
}
```

- [ ] **Step 4: Controller, routes, view**

`app/Http/Controllers/Adviser/SubmissionReviewController.php`:

```php
<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\ReviewClassSubmission;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\DeclineSubmissionRequest;
use App\Models\ClassSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SubmissionReviewController extends Controller
{
    public function approve(Request $request, ClassSubmission $submission, ReviewClassSubmission $review): RedirectResponse
    {
        Gate::authorize('review', $submission);

        $review($submission, $request->user(), SubmissionStatus::Approved);

        return back()->with('success', "“{$submission->title}” approved.");
    }

    public function decline(DeclineSubmissionRequest $request, ClassSubmission $submission, ReviewClassSubmission $review): RedirectResponse
    {
        $review($submission, $request->user(), SubmissionStatus::Declined, $request->validated('note'));

        return back()->with('success', "“{$submission->title}” declined. The intern has been notified.");
    }
}
```

Routes — add inside the adviser group:

```php
        Route::post('submissions/{submission}/approve', [Adviser\SubmissionReviewController::class, 'approve'])->name('submissions.approve');
        Route::post('submissions/{submission}/decline', [Adviser\SubmissionReviewController::class, 'decline'])->name('submissions.decline');
```

In `resources/views/adviser/folders/show.blade.php` replace `{{-- Task 11 adds the approve and decline controls here --}}` with:

```blade
                        @can('review', $submission)
                            <div class="flex items-center justify-end gap-1"
                                 x-data x-init="@if ($errors->has('note') && (string) old('submission_id') === (string) $submission->id) $nextTick(() => $dispatch('open-modal', 'decline-{{ $submission->id }}')) @endif">
                                @if ($submission->status !== \App\Enums\SubmissionStatus::Approved)
                                    <form method="POST" action="{{ route('adviser.submissions.approve', $submission) }}">
                                        @csrf
                                        <x-button variant="ghost" icon="heroicon-o-check" class="text-emerald-700 dark:text-emerald-300">Approve</x-button>
                                    </form>
                                @endif
                                @if ($submission->status !== \App\Enums\SubmissionStatus::Declined)
                                    <x-button type="button" variant="ghost" icon="heroicon-o-x-mark" class="text-rose-600" @click="$dispatch('open-modal', 'decline-{{ $submission->id }}')">Decline</x-button>
                                @endif
                            </div>
                            <x-modal :name="'decline-'.$submission->id" title="Decline submission">
                                <form method="POST" action="{{ route('adviser.submissions.decline', $submission) }}" class="space-y-4">
                                    @csrf
                                    <input type="hidden" name="submission_id" value="{{ $submission->id }}">
                                    <p class="text-sm text-stone-600 dark:text-stone-300">Declining <span class="font-medium">{{ $submission->title }}</span> by {{ $submission->intern->name }}. The note is sent to the intern.</p>
                                    <div>
                                        <label for="note-{{ $submission->id }}" class="label">Reason <span class="text-rose-500">*</span></label>
                                        <textarea id="note-{{ $submission->id }}" name="note" rows="3" required maxlength="500" class="input">{{ (string) old('submission_id') === (string) $submission->id ? old('note') : '' }}</textarea>
                                        @if ((string) old('submission_id') === (string) $submission->id)<x-form.error name="note" />@endif
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <x-button type="button" variant="secondary" @click="$dispatch('close-modal', 'decline-{{ $submission->id }}')">Cancel</x-button>
                                        <x-button variant="danger" icon="heroicon-o-x-mark">Decline</x-button>
                                    </div>
                                </form>
                            </x-modal>
                        @endcan
```

(The modal lives inside the table cell; it is `position: fixed`, so it renders correctly regardless of the table layout.)

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="ReviewClassSubmission|SubmissionReview|Adviser.*FoldersTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: approve or decline class submissions with a note and notify the intern"
```

---

### Task 12: Shared class resources (adviser uploads, everyone downloads)

**Files:**
- Create: `app/Actions/AddClassResource.php`, `app/Actions/DeleteClassResource.php`, `app/Http/Requests/Adviser/ResourceRequest.php`, `app/Http/Controllers/Adviser/ResourceController.php`
- Modify: `app/Http/Controllers/Adviser/ClassSectionController.php` (`documents()` loads resources), `app/Http/Controllers/Intern/ClassController.php` (`documents()` loads resources), `routes/web.php`, `resources/views/adviser/classes/documents.blade.php`, `resources/views/intern/class/documents.blade.php`
- Test: `tests/Unit/Actions/ResourceActionsTest.php`, `tests/Feature/Adviser/ResourcesTest.php`

**Interfaces:**
- Consumes: `ClassSectionPolicy::manage`, `ClassResourcePolicy::delete`, `files.show` kind `class-resource`.
- Produces: `AddClassResource(ClassSection, User $uploader, string $title, UploadedFile $file): ClassResource` (stores `classroom/{class}/resources/{uuid}.pdf`), `DeleteClassResource(ClassResource): void`; routes `adviser.resources.store` (POST `/adviser/classes/{classSection}/resources`), `adviser.resources.destroy` (DELETE `/adviser/resources/{resource}`).

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Actions/ResourceActionsTest.php`:

```php
<?php

use App\Actions\AddClassResource;
use App\Actions\DeleteClassResource;
use App\Models\ClassResource;
use App\Models\ClassSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('stores a resource PDF privately and deletes it with the record', function () {
    Storage::fake('local');
    $section = ClassSection::factory()->create();

    $resource = app(AddClassResource::class)($section, $section->adviser, ' OJT Guidelines ', UploadedFile::fake()->create('guide.pdf', 50, 'application/pdf'));

    expect($resource->title)->toBe('OJT Guidelines')->and($resource->uploader_id)->toBe($section->adviser_id)
        ->and($resource->file_path)->toStartWith("classroom/{$section->id}/resources/")->toEndWith('.pdf');
    Storage::disk('local')->assertExists($resource->file_path);

    $path = $resource->file_path;
    app(DeleteClassResource::class)($resource);
    expect(ClassResource::whereKey($resource->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);
});
```

`tests/Feature/Adviser/ResourcesTest.php`:

```php
<?php

use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->adviser = User::factory()->adviser()->create();
    $this->section = ClassSection::factory()->for($this->adviser, 'adviser')->create();
    $this->intern = User::factory()->intern()->create();
    $this->intern->internProfile->update(['class_section_id' => $this->section->id]);
});

it('uploads a resource that interns can see and download', function () {
    $this->actingAs($this->adviser)->post(route('adviser.resources.store', $this->section), [
        'title' => 'OJT Guidelines', 'file' => UploadedFile::fake()->create('guide.pdf', 50, 'application/pdf'),
    ])->assertRedirect(route('adviser.classes.documents', $this->section))->assertSessionHas('success');

    $resource = ClassResource::firstOrFail();
    $this->actingAs($this->adviser)->get(route('adviser.classes.documents', $this->section))->assertSee('OJT Guidelines')->assertSee(route('files.show', ['class-resource', $resource->id]));
    $this->actingAs($this->intern)->get(route('intern.class.documents'))->assertSee('OJT Guidelines')->assertSee(route('files.show', ['class-resource', $resource->id]));
    $this->actingAs($this->intern)->get(route('files.show', ['class-resource', $resource->id]))->assertOk();
});

it('validates the upload and forbids other advisers', function () {
    $this->actingAs($this->adviser)->post(route('adviser.resources.store', $this->section), [
        'title' => '', 'file' => UploadedFile::fake()->create('guide.txt', 5, 'text/plain'),
    ])->assertSessionHasErrors(['title', 'file']);

    $this->actingAs(User::factory()->adviser()->create())->post(route('adviser.resources.store', $this->section), [
        'title' => 'X', 'file' => UploadedFile::fake()->create('g.pdf', 5, 'application/pdf'),
    ])->assertForbidden();
    expect(ClassResource::count())->toBe(0);
});

it('deletes its own resources and their files', function () {
    Storage::disk('local')->put('classroom/x/r.pdf', 'r');
    $resource = ClassResource::factory()->for($this->section)->for($this->adviser, 'uploader')->create(['file_path' => 'classroom/x/r.pdf']);
    $foreign = ClassResource::factory()->create();

    $this->actingAs($this->adviser)->delete(route('adviser.resources.destroy', $foreign))->assertForbidden();
    $this->actingAs($this->adviser)->delete(route('adviser.resources.destroy', $resource))->assertRedirect(route('adviser.classes.documents', $this->section));

    expect(ClassResource::whereKey($resource->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('classroom/x/r.pdf');
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="ResourceActions|Adviser.*ResourcesTest"`
Expected: FAIL.

- [ ] **Step 3: Request and actions**

`app/Http/Requests/Adviser/ResourceRequest.php`:

```php
<?php

namespace App\Http\Requests\Adviser;

use Illuminate\Foundation\Http\FormRequest;

class ResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('classSection'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['title' => trim((string) $this->input('title'))]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('wiis.uploads.max_pdf_kb')],
        ];
    }

    public function messages(): array
    {
        return ['file.mimetypes' => 'The resource must be a PDF file.'];
    }
}
```

`app/Actions/AddClassResource.php`:

```php
<?php

namespace App\Actions;

use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AddClassResource
{
    public function __invoke(ClassSection $section, User $uploader, string $title, UploadedFile $file): ClassResource
    {
        $path = $file->storeAs("classroom/{$section->id}/resources", Str::uuid().'.pdf', 'local');

        return $section->resources()->create([
            'uploader_id' => $uploader->id,
            'title' => trim($title),
            'file_path' => $path,
        ]);
    }
}
```

`app/Actions/DeleteClassResource.php`:

```php
<?php

namespace App\Actions;

use App\Models\ClassResource;
use Illuminate\Support\Facades\Storage;

class DeleteClassResource
{
    public function __invoke(ClassResource $resource): void
    {
        $path = $resource->file_path;

        $resource->delete();

        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
```

- [ ] **Step 4: Controller, routes, views**

`app/Http/Controllers/Adviser/ResourceController.php`:

```php
<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\AddClassResource;
use App\Actions\DeleteClassResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\ResourceRequest;
use App\Models\ClassResource;
use App\Models\ClassSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ResourceController extends Controller
{
    public function store(ResourceRequest $request, ClassSection $classSection, AddClassResource $add): RedirectResponse
    {
        $resource = $add($classSection, $request->user(), $request->validated('title'), $request->file('file'));

        return redirect()->route('adviser.classes.documents', $classSection)->with('success', "“{$resource->title}” shared with the class.");
    }

    public function destroy(ClassResource $resource, DeleteClassResource $delete): RedirectResponse
    {
        Gate::authorize('delete', $resource);

        $section = $resource->classSection;
        $delete($resource);

        return redirect()->route('adviser.classes.documents', $section)->with('success', "“{$resource->title}” removed.");
    }
}
```

Routes — add inside the adviser group:

```php
        Route::post('classes/{classSection}/resources', [Adviser\ResourceController::class, 'store'])->name('resources.store');
        Route::delete('resources/{resource}', [Adviser\ResourceController::class, 'destroy'])->name('resources.destroy');
```

In `Adviser\ClassSectionController::documents()` add to the view data:

```php
            'resources' => $classSection->resources()->with('uploader')->latest()->get(),
```

In `Intern\ClassController::documents()` add to the view data:

```php
            'resources' => $section->resources()->with('uploader')->latest()->get(),
```

In `resources/views/adviser/classes/documents.blade.php` replace `{{-- Task 12 adds the shared resources card here --}}` with:

```blade
            <x-card title="Shared resources" subtitle="Templates, guidelines and other PDFs every intern can download." :padding="false">
                @can('manage', $class)
                    <x-slot:actions>
                        <x-button type="button" variant="secondary" icon="heroicon-o-arrow-up-tray" @click="$dispatch('open-modal', 'share-resource')">Share a PDF</x-button>
                    </x-slot:actions>
                @endcan
                @forelse ($resources as $resource)
                    <div class="flex items-center justify-between gap-3 border-b border-stone-100 px-5 py-3 last:border-0 dark:border-stone-800">
                        <a href="{{ route('files.show', ['class-resource', $resource]) }}" target="_blank" class="flex min-w-0 items-center gap-3 hover:underline">
                            <x-heroicon-o-document-text class="size-5 shrink-0 text-stone-400" />
                            <span class="truncate font-medium">{{ $resource->title }}</span>
                            <span class="hidden text-xs text-stone-500 sm:inline">{{ $resource->uploader->name }} · {{ $resource->created_at->format('M j, Y') }}</span>
                        </a>
                        @can('delete', $resource)
                            <x-confirm-form :action="route('adviser.resources.destroy', $resource)" method="DELETE" :confirm="'Remove “'.$resource->title.'”?'">
                                <button class="btn-ghost p-1.5 text-stone-400 hover:text-rose-600" aria-label="Remove resource"><x-heroicon-o-trash class="size-4" /></button>
                            </x-confirm-form>
                        @endcan
                    </div>
                @empty
                    <x-empty-state title="No shared resources" description="Share templates or guidelines as PDFs." icon="heroicon-o-paper-clip" class="py-8" />
                @endforelse
            </x-card>

            @can('manage', $class)
                <x-modal name="share-resource" title="Share a PDF with the class">
                    <form method="POST" action="{{ route('adviser.resources.store', $class) }}" enctype="multipart/form-data" class="space-y-4"
                          x-init="@if ($errors->hasAny(['title', 'file'])) $nextTick(() => $dispatch('open-modal', 'share-resource')) @endif">
                        @csrf
                        <x-form.input name="title" label="Title" placeholder="OJT Guidelines" required maxlength="150" />
                        <x-form.file name="file" label="PDF file" accept="application/pdf" required />
                        <div class="flex justify-end gap-2">
                            <x-button type="button" variant="secondary" @click="$dispatch('close-modal', 'share-resource')">Cancel</x-button>
                            <x-button icon="heroicon-o-arrow-up-tray">Share</x-button>
                        </div>
                    </form>
                </x-modal>
            @endcan
```

(The folder form and the resource form both have a field that could carry an error. The folder form uses `name`; the resource form uses `title` and `file`, so each form shows only its own errors.)

In `resources/views/intern/class/documents.blade.php` replace `{{-- Task 12 adds the shared resources card here --}}` with:

```blade
            <x-card title="Shared resources" subtitle="PDFs your adviser shared with the class." :padding="false">
                @forelse ($resources as $resource)
                    <a href="{{ route('files.show', ['class-resource', $resource]) }}" target="_blank" class="flex items-center gap-3 border-b border-stone-100 px-5 py-3 last:border-0 hover:bg-stone-50 dark:border-stone-800 dark:hover:bg-stone-900">
                        <x-heroicon-o-document-text class="size-5 shrink-0 text-stone-400" />
                        <span class="min-w-0 flex-1 truncate font-medium">{{ $resource->title }}</span>
                        <span class="text-xs text-stone-500">{{ $resource->created_at->format('M j, Y') }}</span>
                    </a>
                @empty
                    <x-empty-state title="No shared resources yet" icon="heroicon-o-paper-clip" class="py-8" />
                @endforelse
            </x-card>
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="ResourceActions|Adviser.*ResourcesTest|Adviser.*FoldersTest|Intern.*SubmissionsTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: share class resources as PDFs"
```

---

### Task 13: Portal route isolation, demo data, documentation and phase wrap-up

**Files:**
- Create: `tests/Feature/ClassroomRouteIsolationTest.php`
- Modify: `database/seeders/DemoSeeder.php`, `tests/Feature/SeederTest.php`, `README.md`, `CLAUDE.md`
- Verify: full suite, Pint, build, route list, browser walk.

**Interfaces:**
- Consumes: every `adviser.*` and `intern.*` route from Tasks 3–12.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/ClassroomRouteIsolationTest.php`:

```php
<?php

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\ClassFolder;
use App\Models\ClassResource;
use App\Models\ClassSection;
use App\Models\ClassSubmission;
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

    $this->params = [
        'classSection' => $section->id,
        'announcement' => $announcement->id,
        'comment' => AnnouncementComment::factory()->for($announcement)->for($intern, 'author')->create()->id,
        'folder' => $folder->id,
        'submission' => ClassSubmission::factory()->for($folder, 'folder')->for($intern, 'intern')->create()->id,
        'resource' => ClassResource::factory()->for($section)->for($adviser, 'uploader')->create()->id,
    ];
});

it('discovers the classroom routes', function () {
    expect(count(portalRoutes('adviser.')))->toBeGreaterThan(20)
        ->and(count(portalRoutes('intern.')))->toBeGreaterThan(8);
});

it('forbids every adviser route to other roles and every intern route to other roles', function (string $prefix, array $others) {
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
]);

it('redirects guests from every classroom route to the login page', function () {
    foreach ([...portalRoutes('adviser.'), ...portalRoutes('intern.')] as $route) {
        $this->call($route->methods()[0], portalRouteUrl($route, $this->params))->assertRedirect(route('login'));
    }
});
```

Update `tests/Feature/SeederTest.php`: add `Storage::fake('local');` as the first line of the test (import `Illuminate\Support\Facades\Storage`) and extend the expectation chain with:

```php
        ->and(\App\Models\ClassSubmission::count())->toBe(3)
        ->and(\App\Models\ClassSubmission::where('is_late', true)->count())->toBe(1)
        ->and(\App\Models\ClassResource::count())->toBe(1)
        ->and(\App\Models\AnnouncementComment::count())->toBe(1)
```

and after the chain:

```php
    Storage::disk('local')->assertExists(\App\Models\ClassSubmission::firstOrFail()->file_path);
    Storage::disk('local')->assertExists(\App\Models\ClassResource::firstOrFail()->file_path);
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --filter="ClassroomRouteIsolation|SeederTest"`
Expected: isolation tests PASS already (they verify Tasks 3–12 — if any route is reachable by the wrong role, fix that route's authorization now); `SeederTest` FAILS on the new counts.

- [ ] **Step 3: Demo data**

In `database/seeders/DemoSeeder.php` add imports `use App\Models\AnnouncementComment; use App\Models\ClassResource; use App\Models\ClassSubmission; use Illuminate\Support\Facades\Storage;`, capture the folders and announcement, and append classroom data. Replace the last three statements of `run()` (the two folders and the announcement) with:

```php
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
```

and add the helper to the class:

```php
    /** A one-page blank PDF so demo downloads open in a viewer. */
    private function placeholderPdf(): string
    {
        return "%PDF-1.4\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >> endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";
    }
```

- [ ] **Step 4: README and CLAUDE.md**

In `README.md`:
- Roadmap: tick Phase 3 — "Classroom: classes, stream, folders, submissions".
- Status line: "Phases 1–3 complete — … and the classroom module."
- Add a **Classroom** subsection under Features (after Admin imports): advisers create classes or claim imported ones by join code and can leave (history is kept); the stream holds rich-text announcements with comments; folders collect PDF uploads, locking a folder marks later uploads late, and the adviser approves or declines each upload with a note; shared resources are PDFs for the whole class; the People tab shows every intern's hours and prints.
- In "Architecture notes" add: rich text is cleaned by `App\Services\HtmlSanitizer` (HTML Purifier) on save and render; classroom access is decided by Policies built on `ClassSection::isAdvisedBy/enrolls/hasMember`.

In `CLAUDE.md` under Architecture add:

```
- **Classroom**: adviser/intern controllers under their portals plus the shared `App\Http\Controllers\Classroom\CommentController`;
  access is decided by Policies on each classroom model, all built on `ClassSection::isAdvisedBy|enrolls|hasMember`.
  Rich text goes through `App\Services\HtmlSanitizer` on save and is rendered only via `<x-rich-text>`. Private PDFs are
  served through `files.show` kinds `class-submission` and `class-resource`.
```

- [ ] **Step 5: Final verification**

Run:

```bash
php artisan test
vendor/bin/pint --test
npm run build
php artisan route:list --except-vendor --name=adviser.
php artisan route:list --except-vendor --name=intern.
```

Expected: all green; the adviser list contains `dashboard, classes.index/create/store/join/edit/update/leave/show/people/print/documents, announcements.store/edit/update/destroy, comments.store/destroy, folders.store/show/lock/destroy, submissions.approve/decline, resources.store/destroy`; the intern list contains `dashboard, class.show/join/people/documents, comments.store/destroy, folders.show, submissions.index/store/destroy`.

Then `php artisan migrate:fresh --seed` on a disposable database (or confirm with the user before resetting the dev database), start `composer dev`, and walk:
- as `adviser@wiis.test`: My classes shows CC101 · SBIT-4C; open it; post an announcement with bold text and a link; Documents tab shows both folders, "Endorsement Letter" has 1 pending; open it, approve intern2's upload, decline with a note; share a PDF; People tab shows hours and tiers; Print list opens the print layout; leave the class and claim it back with `SBIT4C26`.
- as `intern2@wiis.test`: My class stream shows the announcement, comment on it; Documents tab shows the shared PDF and the folders; upload a PDF into the locked "Weekly Report 1" and see the Late badge; withdraw it; My submissions lists everything; notifications show the review decisions.
- Check both portals at phone width and in dark mode (Trix toolbar readable).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "docs: document the classroom module and seed classroom demo data"
```

---

## Spec coverage check (Phase 3 scope)

| Spec item | Task |
|---|---|
| Adviser: my classes (create/edit) | 3 |
| Adviser: join-by-code (blocked if it already has an adviser) / leave (logs history) | 4 |
| Class page tabs (stream, documents, people) | 5, 9 |
| Announcements + comments (Trix + sanitizer) | 1, 7, 8 |
| Folders (create/lock/unlock/delete) | 9 |
| Submission review queue grouped pending/approved/declined/late, approve/decline with a note | 9, 11 |
| Shared resources | 12 |
| Interns list with hours + print view | 5 |
| Intern: join class (one class per intern), stream + comments | 6, 8 |
| Intern: folder upload (late flag), my submissions, roster | 10, 6 |
| Policies: ClassSection, ClassFolder, ClassSubmission, Announcement (+ comment, resource) | 2 |
| Notifications: comment/upload → class, plus announcement and review decisions | 7, 8, 10, 11 |
| Private files served after a policy check | 2 |
| Role isolation for every new route | 13 |

Decisions recorded: comments are plain text (max 1000 chars); only advisers post announcements; an adviser's edit rights on an announcement end when they leave the class; a reviewed submission can be re-decided by the adviser but can no longer be withdrawn by the intern; deleting a folder removes its submissions and files; attachments inside the Trix editor are disabled (documents go through folders); an intern whose class was archived may join another class.
