# Independent extraction delivery

Approved scope: provider-independent definition collectors for private VPS pilot. Preserve all legacy source/history/artifacts and the initial 69-file uncommitted baseline (verified byte-for-byte against saved project on 2026-09-22).

## Milestones

- [x] 1. Definition contract, drafts/test/activation, additive migrations, disabled AI, first feed.
- [x] 2. Isolated browser service, visual selection, pagination/details, connections/reconnect.
- [x] 3. JSON pagination, CSV/XML, transforms/validation.
- [x] 4. Scheduling/locking/recovery, comparisons/notifications/retention/status.
- [x] 5. VPS instructions and local regression/acceptance verification; target-host smoke checks documented below.

## Ownership

- Lead (Astra): integration, persistence, lifecycle, routes, execution, sequencing, final verification.
- definition_feeds (Sol): new definition contract, safe feed transport/adapters/mapping, focused tests.
- browser_service (Sol): new private Node service, website adapter, public-only network proxy, fixtures.
- ai_gates (Sol): optional assistant boundary, disabled AI dispatch/execution, pilot allowlist, focused tests.

Workers do not edit shared configuration, lockfiles or translations without reassignment. Backend tests use isolated in-memory SQLite; no production database access. No commits or deployment requested.

## Evidence

- Initial baseline preserved in /private/tmp/dataminer-initial-working.patch and /private/tmp/dataminer-baseline.json for this session.
- Official Laravel 13 queue and HTTP client and Playwright authentication documentation consulted; Boost search-docs tool not exposed.
- Dependencies were absent in the worktree; the saved project supplied the initial installed dependencies. The user subsequently approved the CommonMark security patch from 2.9.2 to 2.10.3; no other dependency version was changed.

## Shared contracts

RecipeDefinition schema v1 is configuration only with source type, URL, connection ID, records path, typed field mappings, fixed transforms, pagination, validation, limits and comparison keys. Immutable version checksum covers canonical normalized definition. Legacy versions default to legacy_js. Mutable draft remains separate. Adapter yields normalized scalar rows, completeness, diagnostics and counts. Partial datasets must never authorize missing-record comparisons.

## Verification checkpoint

- `make fix` and `make check` passed on 2026-09-22: PHPStan at max, formatter checks, Composer/npm audits, platform/lock validation, TypeScript, production build, 42 frontend unit tests, and 272 backend tests (6,642 assertions).
- One existing risky architecture test remains: “web index controllers declare a TAKE constant” performs no assertions when no matching controllers exist. No application test failed.
- Private browser service Node suite: 7 passed, covering public-address enforcement, isolation/authentication contract, controlled Chromium extraction, repeated pagination, duplicate rows, and partial detail-page failures.
- Collector UI browser suite: 5 passed, including sample mapping, draft save, immutable preview, activation, scheduling, result views and mobile layouts. Screenshots visually inspected. Full browser regression suite: 25 passed. The harness uses a dedicated temporary SQLite database and persistent encrypted cookie sessions; startup rejects unsafe database/session settings.
- Regression coverage also includes disabled provider calls, owner/origin credential boundaries, revision-aware reconnect and revocation, cancellation, rejected-preview races, JSON pagination modes, unsafe XML, DST scheduling, duplicate dispatch, incomplete comparisons, retention baseline preservation and stale-worker recovery.
- Final delivery has no commits, deployment, or production database migration. Docker is unavailable here; container startup, Linux Chromium sandbox and VPS resource limits still require the documented target-host smoke check. Database tests use isolated SQLite; validate simultaneous MySQL workers on the target environment before promotion.
