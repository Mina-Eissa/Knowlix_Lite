# Knowlix Lite — Core Service: Project Context (handoff)

Laravel 11 API backend for a multi-tenant knowledge base + ticketing system. This document summarizes everything built so far, so a new chat can pick up without re-deriving it. Paste this whole file as the first message in a new conversation.

## Background on me (the developer)
- CS graduate, background in Python/Django, learning Laravel/PHP through this project (backend internship).
- Working on Ubuntu/Linux, VS Code, MySQL, curl for testing (Insomnia had an unresolved config bug — abandoned in favor of curl).
- Prefer block-by-block builds: one piece at a time, verify, then move on — not everything dumped at once.
- Want explanations of *why*, not just code to paste.

## Stack
- Laravel 11, PHP 8.3, MySQL 8, Sanctum (token auth), `league/commonmark` (Markdown rendering, pinned `^2.8` for CVE fixes).
- Article bodies are stored as **Markdown**, not HTML. Validated on the way in (custom rule), rendered to HTML on the way out (`body_html` in API responses).
- Test DB: separate `knowlix_core_testing` database, configured via `.env.testing`, `QUEUE_CONNECTION=sync` for tests (important gotcha — see below).

## What's fully built and tested

### 1. Foundation
- `workspaces`, `users`, `categories`, `articles`, `article_versions`, `tickets` (not built yet — see below), `ticket_comments` (not built), `tags`/`taggables` (not built), `attachments` (not built), `webhook_events` — migrations exist for the ones marked built.
- `BelongsToWorkspace` trait: global scope on tenant-owned models, auto-filters every query to `auth()->user()->workspace_id`, auto-sets `workspace_id` on create. Applied to `User`, `Category`, `Article`, `WebhookEvent` (not yet `Ticket`/`Tag` — not built yet).
- Every model needs `use HasFactory;` explicitly (learned this the hard way — `make:model` skeleton includes it by default, but we overwrote full file contents each time and dropped it, causing "undefined method factory()" errors across the board).
- **Recurring bug pattern to watch for**: any field the service/controller sets explicitly via `update()`/`create()` (`status`, `published_at`, `attempts`, `delivered_at`, etc.) must be in the model's `$fillable`, or it's silently dropped and reads back as `null` even though the DB has the right value (if the DB has a default) or genuinely wasn't saved. Hit this three times (Article status, Article published_at, WebhookEvent fields). Always check `$fillable` first when a field mysteriously comes back null after a "successful" save.

### 2. Auth & invitations (fully done, tested)
- Register (`POST /api/v1/register`) creates workspace + admin user + Sanctum token in one transaction.
- Login/logout/me.
- Admin-only user invite flow: `POST /api/v1/users` creates user with random unusable password, generates a Laravel password-reset-broker token, emails an accept-invite link (`Mail::to(...)->send(new InviteUserMail(...))`, Blade view at `resources/views/emails/invite-user.blade.php`).
- `POST /api/v1/accept-invite` — public endpoint, validates the emailed token via `Password::broker()->reset()`, sets real password, returns a fresh Sanctum token.
- Resend invite endpoint (`POST /users/{user}/resend-invite`) — regenerates token, old one invalidated automatically by the broker.
- Validation guards: admin can't invite self, can't invite duplicate email in same workspace, admin's own email checked first.
- `MAIL_MAILER=log` in dev — invite links show up in `storage/logs/laravel.log`, extract with `grep -o 'http://localhost:5173/accept-invite[^"< ]*' storage/logs/laravel.log | tail -n 1`.
- `APP_FRONTEND_URL` config exists purely so the emailed link points at the React app's page (not the API, which returns JSON not HTML).

### 3. Categories (fully done, tested)
- Full CRUD, one level of parent/child nesting via `parent_id`.
- `Rule::exists('categories','id')->where('workspace_id', ...)` on `parent_id` — plain `exists` isn't tenant-safe on its own.
- Delete blocked if the category still has articles attached (`CategoryPolicy::delete`).

### 4. Articles — Wave 1 (CRUD) — fully done, tested
- Full CRUD, `SoftDeletes` for archiving (archiving is NOT a 4th status — it's `deleted_at` layered on top of whatever status the article had).
- `ArticleStatus` enum: `draft`, `in_review`, `published` only.
- Body content security (multi-layer, this took a lot of iteration):
  1. Custom `SafeHtmlContent` validation rule — rejects raw HTML tags outright, checks Markdown's own `[text](url)` link syntax for dangerous URL schemes (`javascript:`, `data:`, `vbscript:`) **in the raw source before rendering** (CommonMark neutralizes these during render, which would hide the attempt if checked only in rendered output — learned this from a failing test).
  2. `MarkdownRenderer` service wraps `league/commonmark` with `html_input: strip` and `allow_unsafe_links: false`.
  3. `max:50000` on body (DoS mitigation, also the official mitigation for a known CommonMark quadratic-complexity CVE).
- `ArticleResource` returns both `body` (raw Markdown, for edit forms) and `body_html` (rendered, safe HTML, for display).

### 5. Articles — Wave 2 (state machine, versioning, webhooks) — fully done, tests exist (see open items below)
- `ArticleService::submitForReview()` / `publish()` — enforces draft→in_review→published, throws `ValidationException` (auto-422) on invalid transitions.
- On publish (single DB transaction): status + `published_at` updated, `ArticleVersion` snapshot created (`version = max+1`), `WebhookEvent` outbox row created (`event_id` = ULID via `Str::ulid()`, `status = pending`).
- `ArticleService::publish()` returns `['article' => ..., 'event' => ...]` (changed from returning just `Article` — controller destructures this).
- `DeliverWebhookEvent` job: signs payload (`WebhookSigner`, HMAC-SHA256), POSTs to `config('services.intelligence.webhook_url')`, marks event `delivered`/increments `attempts`/`failed` after 5 tries via `backoff()` returning `[60, 300, 1800, 7200, 21600]`.
- Dispatch uses `->afterCommit()` so the job never fires for a rolled-back transaction.
- **Key architectural point**: the Intelligence Service (Supabase team's service) being down or not existing yet NEVER blocks or fails article publishing — only the `webhook_events` row ends up `failed` after retries. Proven with a dedicated test faking a 500 response and asserting the publish still returns 200.
- `GET /articles/{article}/versions` endpoint for version history.

## Known gotchas hit during this build (worth remembering)
1. **Laravel 11 doesn't auto-load `routes/api.php`** unless `bootstrap/app.php`'s `withRouting()` explicitly lists it — check this first if routes 404/return the welcome page unexpectedly.
2. **`php artisan migrate` only runs migrations not yet marked "ran"** — editing a migration file after it already ran does nothing until `migrate:fresh` (dev only, drops everything) or a new migration.
3. **Migration file order = timestamp in filename** — a table referencing a foreign key must have a later timestamp than the table it references, or `SQLSTATE[HY000]: 1824 Failed to open the referenced table`. Fix: delete and regenerate the out-of-order file so it gets a fresh (later) timestamp.
4. **`$request->validated()` only includes fields declared in `rules()`** — even if `prepareForValidation()` merges a field (like an auto-generated `slug`) onto the request, it silently won't appear in `validated()`'s output unless it's also listed in `rules()`. Caused a "Field 'slug' doesn't have a default value" 500 error.
5. **`$fillable` is not the security boundary — validation is.** A field can safely sit in `$fillable` even for sensitive data (`status`, `published_at`) as long as every write path uses `$request->validated()` (strict allow-list) or a hardcoded server-side value, never `$request->all()`.
6. **`QUEUE_CONNECTION=sync` in `.env.testing` runs jobs inline, immediately, during the test's HTTP request** — any test that triggers a job doing outbound HTTP (like publish → webhook delivery) will attempt a REAL network call unless `Http::fake()` is set (ideally in the test class's `setUp()`, not per-test, so nothing is missed).
7. **Mailable views (`->view('emails.invite-user', ...)`) aren't checked until runtime** — "showing code" for a Blade view doesn't mean the file exists on disk; always physically create it.
8. **`Model::factory()` requires `use HasFactory;` on the model** — not automatic, easy to drop when replacing a whole model file's contents.
9. `.env.testing` is completely separate from `.env` — any new env var (e.g. `INTELLIGENCE_WEBHOOK_URL`) must be added to both, or config resolves to `null` in tests.
10. `composer audit` is worth running periodically — caught real CVEs in `league/commonmark` (fixed by pinning `^2.8`) and led to dropping `mews/purifier` (old, CVE-laden, unmaintained) in favor of `league/commonmark` for content sanitization.

## Not yet built — what a new chat should tackle next, roughly in BRD order

1. **Tickets** (Epic 3 in the original BRD) — creation, `TicketStatus`/`TicketPriority` enums, requester/assignee, `BelongsToWorkspace` trait, full CRUD + assignment + status transitions. Ticket events (`ticket.created`, `ticket.status_changed`) need the same outbox pattern as articles.
2. **Ticket comments** — including the `is_internal` flag (agent/admin-only notes never visible to the customer, and never trigger a webhook event — this was discussed at length, the reasoning is: internal notes aren't customer-facing, so by definition not worth surfacing to the Intelligence Service).
3. **Tags** — polymorphic `morphToMany` shared between Articles and Tickets, `taggables` pivot table.
4. **Attachments** — polymorphic `morphMany`, private disk storage (never `public`), signed temporary download URLs (`Storage::disk('private')->temporaryUrl(...)`), MIME validation via actual file content (`finfo`), not just extension.
5. **Admin webhook-events management** — `GET /webhook-events` listing (a bare-bones version exists for the tenant-isolation test, needs pagination/filtering polish), `POST /webhook-events/{id}/retry` for manually retrying failed deliveries.
6. **Seeders** — BRD wants 2 workspaces, 20+ articles, 30+ tickets seeded for dev/demo purposes; factories already exist for Workspace/User/Category/Article, need Ticket/TicketComment/Tag/Attachment factories once those models exist.
7. **Full test coverage** for all of the above, following the same pattern used throughout this chat: happy path + role-denial (403) + validation failure (422) + tenant isolation (404) per endpoint, at minimum.

## Original design reference
A full architecture design document (folder structure, ERD, event-outbox design, migration order, testing plan) and the raw SQL schema were produced early in this project — if useful, ask me to reconstruct or re-derive specific pieces of that (routing table, full ERD, etc.) since the actual working code in the repo is now the source of truth, not that original doc.
