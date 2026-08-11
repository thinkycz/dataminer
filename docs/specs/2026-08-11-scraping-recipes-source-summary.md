# AI-Powered Scraping Recipes — Source Summary

## Source

- User-approved implementation prompt dated 2026-08-11.
- Existing Laravel 13 / Inertia 3 application and `AGENTS.md` conventions.
- Laravel AI SDK 0.10 documentation, changelog, and upgrade guide.

## Product outcome

Replace the generic assistant dashboard with an authenticated, internal scraping workspace. A user creates a recipe from a URL and instructions, an AI agent proposes versioned Playwright source, a human approves the SDK tool call before a bounded test, and a tested version must be approved before full manual runs. Successful runs expose immutable, filterable datasets and private CSV/JSON downloads.

## Fixed decisions

- Internal trusted prototype; same-host recipe execution is not a public sandbox.
- JavaScript + Playwright is the only recipe runtime.
- New candidates require Laravel AI human tool approval before test execution.
- Tested versions require a separate application approval before activation.
- Manual reruns only; approved reruns do not call AI.
- Read-only datasets, capped at 100,000 rows per full run.
- MySQL holds canonical rows; private filesystem artifacts provide CSV/JSON downloads.
- Existing auth, settings, Redis, queues, supervisor, Inertia, and i18n stay in place.
- Public SaaS, schedules, credentials, arbitrary dependencies, editable cells, and automatic AI repair are deferred.

## Repository constraints

- Web controllers are non-invokable named-method controllers and every controller requires a mirrored feature test.
- Routes use the core route registrar; internal navigation uses Inertia links/router.
- Web controllers resolve collaborators in method bodies through `Resolver`; no constructor or method injection.
- Persisted model data and relations use explicit typed getters.
- Every property, method, and function requires a docblock; PHPStan remains at max without suppressions.
- Backend and frontend translations remain in sync for English, Czech, and Slovak.
- `make fix`, `make check`, and `make e2e` are the final gates.

## Missing external prerequisites

- A real AI provider credential is not required for automated acceptance because agents must be faked in tests.
- Live third-party scraping is not acceptance evidence; deterministic fixtures own automated verification.
- Public execution remains blocked until a managed sandbox or equivalent isolation exists.
