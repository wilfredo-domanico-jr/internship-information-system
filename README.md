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

Re-seeding requires `php artisan migrate:fresh --seed` — the demo seeder is not idempotent, so running
`db:seed` again on a populated database will fail or create duplicates.

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

The demo seeder is for local development and demos only; never run it against a production database.

## Architecture notes

- **Roles** — one `users` table with a `role` enum; role data lives in `intern_profiles` and `companies`.
  `companies.user_id` is nullable: partner (COS) companies exist without a login.
- **Layers** — thin controllers per portal (`App\Http\Controllers\{Admin,Adviser,Company,Intern}`) →
  Form Requests → single-purpose `App\Actions` → Eloquent. Statuses are PHP backed enums; authorization is Policies.
- **Hours ledger** — only the `ApproveDtr` action (planned for Phase 4) will add hours, inside a transaction, on the pending→approved transition.
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
POST/PUT/DELETE with CSRF on state changes, real foreign keys, and one normalized table per concept.
