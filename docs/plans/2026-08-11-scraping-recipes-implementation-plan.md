# AI-Powered Scraping Recipes — Implementation Plan

## Phase 1 — Laravel AI 0.10 migration

- Upgrade `laravel/ai` from `^0.8.1` to the latest stable compatible release (resolved as 0.10.3 on 2026-08-11).
- Install dependencies and create the application lockfile.
- Add a compatibility migration for polymorphic conversation participants and `approval_state`.
- Refactor current conversation ownership and AI calls for the 0.10 contracts.
- Run existing AI and architecture tests before feature work.

## Phase 2 — Recipe generation and approval

- Add recipe, immutable version, and approval persistence with explicit ownership.
- Add a structured conversational `RecipeGenerationAgent` and approvable test-candidate tool.
- Persist and render pending approval arguments; resume with SDK `Decisions` exactly once.
- Queue a bounded test only after approval.

## Phase 3 — Secure run and dataset pipeline

- Add run, column, and row storage plus one-active-run enforcement.
- Add the fixed Node/Playwright runner and streamed NDJSON ingestion.
- Enforce source, URL, redirect, network, row, byte, request, and timeout limits.
- Produce private streaming JSON/CSV artifacts and authenticated downloads.

## Phase 4 — Inertia product surface

- Replace assistant-first navigation with Recipes, Runs, and Settings.
- Add recipe creation/detail, pending approval, version testing/approval, run history/detail, and dataset viewer pages.
- Reuse shared primitives and the existing run-event bridge pattern where appropriate.
- Add all UI strings to en/cs/sk resources.

## Phase 5 — Verification and closeout

- Add focused PHP, TypeScript, and deterministic browser-fixture coverage.
- Verify ownership, approval idempotency, no-AI reruns, runner limits, filtering, and downloads.
- Run `make fix`, `make check`, and `make e2e` serially.
- Record fresh evidence and any runtime/provider gaps under `docs/verification`.

## Migration risk audit

- **High:** 0.10 changes stored conversation ownership from `user_id` to polymorphic participant columns. Migrate and backfill before using the new SDK.
- **High:** human approval requires nullable `approval_state` on conversation messages and SDK-managed persisted conversation history.
- **High:** current `RunChatAgentJob` manually creates conversation messages; retaining it would compete with SDK persistence. Remove it from the active product flow after compatibility tests.
- **Medium:** current SSE code understands text/tool events but not approval events. Adapt one boundary rather than duplicating streams.
- **Medium:** SQLite tests and MySQL production differ for JSON filtering. Keep query construction isolated and cover both portable behavior and MySQL-specific SQL shape.
- **High:** same-host generated code can access the host if validation fails. Treat source validation and private-network request interception as mandatory prototype guardrails, while documenting that they are not a public sandbox.
