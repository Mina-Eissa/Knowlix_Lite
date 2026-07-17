# Knowlix Lite

A multi-tenant knowledge base and ticketing SaaS API, built with Laravel 11. Knowlix Lite lets workspaces manage help-center articles and support tickets through a clean, tenant-isolated REST API — with content safety, versioning, and event-driven webhook integrations built in from the ground up.

> **Status:** Active development. Auth, Articles, and Tickets are complete and tested. See [Roadmap](#roadmap) for what's next.

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Architecture Highlights](#architecture-highlights)
- [Getting Started](#getting-started)
- [Environment Variables](#environment-variables)
- [Running Tests](#running-tests)
- [API Documentation](#api-documentation)
- [Project Structure](#project-structure)
- [Roadmap](#roadmap)
- [Design Decisions & Gotchas](#design-decisions--gotchas)
- [License](#license)

---

## Overview

Knowlix Lite is a backend API service for a support platform where each **workspace** (tenant) manages its own:

- **Knowledge base articles** — Markdown-authored, versioned, with a draft → review → published workflow
- **Support tickets** — filed by workspace members, assigned to agents, tracked through a status lifecycle
- **Webhook events** — an outbox-pattern event system that notifies external services (e.g. an AI/Intelligence layer) whenever key actions happen, without ever blocking the core API on a downstream service being slow or down

Every resource is strictly isolated per workspace: no user can read, modify, or leak data belonging to another tenant, enforced at the model layer via a global query scope rather than scattered manual checks.

## Features

### Authentication & Workspace Management
- Workspace + admin user registration in a single transaction
- Sanctum token-based authentication (login/logout/me)
- Admin-driven user invitations via emailed, single-use, expiring tokens
- Invite resend flow with automatic old-token invalidation

### Knowledge Base Articles
- Full CRUD with nested categories (one level of parent/child)
- Markdown-authored content, rendered safely to HTML on read
- Multi-layer content security: raw-source scheme validation (blocks `javascript:`, `data:`, `vbscript:` links before rendering), HTML stripping, and size limits to mitigate known parser CVEs
- State machine: `draft → in_review → published`, enforced server-side with atomic transactions
- Full version history — every publish snapshots the article content
- Soft-delete archiving, independent of publish status

### Support Tickets
- Full CRUD with role-based edit/delete permissions (requesters can self-edit while a ticket is still open; agents/admins retain control once work begins)
- Assignment flow with tenant-safe validation (an agent can only be assigned tickets within their own workspace)
- Status lifecycle: `open → in_progress → resolved → closed`, with controlled reopening back to `open`
- All transitions validated server-side against an explicit allowed-transition graph — invalid jumps are rejected with a clear 422, not silently accepted

### Event-Driven Webhooks (Outbox Pattern)
- Every significant action (article published, ticket created, ticket status changed) writes a durable event row in the same database transaction as the action itself
- A queued job delivers each event via signed HMAC-SHA256 webhook, with exponential backoff retry (up to 5 attempts across 1 minute → 6 hours)
- **Downstream service outages never block the core API** — a failing or missing webhook receiver only affects the `webhook_events` table's delivery status, never the user-facing request

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11 (PHP 8.3) |
| Database | MySQL 8 |
| Auth | Laravel Sanctum (token-based) |
| Markdown rendering | `league/commonmark` (pinned `^2.8`) |
| Queue | Laravel Queue (sync for tests, configurable driver for production) |
| API Docs | [Scramble](https://scramble.dedoc.co) (auto-generated OpenAPI 3.1) |
| Testing | PHPUnit |

## Architecture Highlights

- **Tenant isolation by default, not by convention** — a `BelongsToWorkspace` trait applies a global Eloquent scope to every tenant-owned model, automatically filtering queries to the authenticated user's workspace and auto-populating `workspace_id` on create. There is no code path where a query can accidentally cross tenant boundaries.
- **Validation is the security boundary, not mass-assignment protection** — sensitive fields like `status` can safely live in a model's `$fillable` array because every write path goes through strict Form Request validation or a hardcoded server-side value; the API never trusts `$request->all()`.
- **Service-layer transactions for multi-step writes** — any action that touches more than one table (e.g. publishing an article: status change + version snapshot + webhook event) is wrapped in a single atomic `DB::transaction()`, dispatched from a dedicated Service class, not scattered across the controller.
- **Outbox pattern for webhooks** — event rows are created inside the same transaction as the triggering action and only dispatched for delivery `afterCommit()`, guaranteeing a rolled-back action never fires a phantom webhook.

## Getting Started

### Prerequisites
- PHP 8.3+
- Composer
- MySQL 8
- Node.js (if building any frontend assets)

### Installation

```bash
git clone <your-repo-url>
cd knowlix-lite
composer install
cp .env.example .env
php artisan key:generate
```

Configure your database credentials in `.env`, then:

```bash
php artisan migrate
php artisan db:seed   # optional — populates demo data
```

Start the dev server:

```bash
php artisan serve
```

## Environment Variables

Key variables beyond Laravel's defaults:

```env
APP_FRONTEND_URL=http://localhost:5173      # used to build invite-accept links in emails
MAIL_MAILER=log                              # dev default; invite links land in storage/logs/laravel.log
INTELLIGENCE_WEBHOOK_URL=                    # external service that receives webhook events
```

> **Note:** `.env.testing` is a completely separate file — any new environment variable must be added to both `.env` and `.env.testing`, or it resolves to `null` during tests.

## Running Tests

```bash
php artisan test
```

Run a specific suite:

```bash
php artisan test --filter=TicketTest
php artisan test --filter=ArticleTest
```

The test suite covers, per resource: happy path, role-denial (403), validation failure (422), and tenant isolation (404) — plus dedicated coverage for state machine transitions and webhook event delivery (`Http::fake()` is used to intercept outbound webhook calls; `QUEUE_CONNECTION=sync` in `.env.testing` runs queued jobs inline during requests).

## API Documentation

Interactive API docs are auto-generated via [Scramble](https://scramble.dedoc.co) — no manual annotation required for most endpoints.

```bash
php artisan serve
```

Then visit:

```
http://localhost:8000/docs/api
```

To export a static OpenAPI spec (importable into Insomnia/Postman):

```bash
php artisan scramble:export
```

## Project Structure

```
app/
├── Enums/              # ArticleStatus, TicketStatus, TicketPriority, WebhookEventStatus, ...
├── Http/
│   ├── Controllers/    # Thin HTTP-layer controllers
│   ├── Requests/       # Form Requests — validation + authorization per action
│   └── Resources/      # API response shaping
├── Jobs/                # DeliverWebhookEvent, ...
├── Models/              # Eloquent models
├── Policies/            # Authorization rules per resource
├── Services/            # Business logic + multi-step transactions
└── Traits/
    └── BelongsToWorkspace.php   # Global tenant-scoping trait

database/
├── factories/
├── migrations/
└── seeders/

tests/
└── Feature/             # Per-resource feature test suites
```

## Roadmap

- [x] Auth & workspace invitations
- [x] Knowledge base articles (CRUD, state machine, versioning, webhooks)
- [x] Support tickets (CRUD, assignment, status transitions, webhooks)
- [ ] Ticket comments (with internal/customer-facing visibility split)
- [ ] Tags (polymorphic, shared between articles & tickets)
- [ ] Attachments (polymorphic, private storage, signed download URLs)
- [ ] Webhook events admin panel (listing, filtering, manual retry)
- [ ] Database seeders for full demo dataset

## Design Decisions & Gotchas

A running log of non-obvious decisions and hard-won lessons from building this project — useful context for anyone picking up the codebase:

- Laravel 11 requires `routes/api.php` to be explicitly registered in `bootstrap/app.php`'s `withRouting()` — it isn't autoloaded by default.
- Migration execution order is timestamp-based; a table with a foreign key must have a later timestamp than the table it references.
- `$request->validated()` only returns fields declared in `rules()` — even fields merged in via `prepareForValidation()` are silently excluded unless also listed in the rule set.
- Any field a service/controller sets explicitly via `create()`/`update()` must be present in the model's `$fillable`, or it's silently dropped.
- Relying on a database column default (e.g. `priority` defaulting to `medium`) means the in-memory model object won't reflect that value until it's re-fetched — call `->fresh()` after a `create()` that depends on DB-level defaults.
- `QUEUE_CONNECTION=sync` in the testing environment runs jobs inline during the HTTP request itself — any test exercising a job that makes outbound HTTP calls needs `Http::fake()`, ideally in the test class's `setUp()`.

## License

[Specify your license here — MIT, proprietary, etc.]
