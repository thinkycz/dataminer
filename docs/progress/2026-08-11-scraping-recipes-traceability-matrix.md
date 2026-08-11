# AI-Powered Scraping Recipes — Traceability Matrix

| ID  | Requirement                                                                                 | Phase | Status      | Verification                                      |
| --- | ------------------------------------------------------------------------------------------- | ----- | ----------- | ------------------------------------------------- |
| R1  | Upgrade Laravel AI and use current structured, conversational, queued, event, and fake APIs | 1     | implemented | focused AI tests + Composer lock                  |
| R2  | Persist SDK conversations polymorphically with approval state                               | 1     | implemented | compatibility migration and ownership tests       |
| R3  | Generate a structured Playwright recipe candidate                                           | 2     | implemented | agent schema and queued-fake tests                |
| R4  | Require human tool approval before browser testing                                          | 2     | implemented | pending/approve/reject/idempotency tests          |
| R5  | Version candidates immutably and approve tested versions separately                         | 2     | implemented | lifecycle and observer tests                      |
| R6  | Rerun approved recipes manually without AI                                                  | 2/3   | implemented | manual-run queue test; no agent prompt path       |
| R7  | Execute through a fixed JS/Playwright contract with enforced limits                         | 3     | implemented | syntax/capability validator and harness checks    |
| R8  | Block non-HTTP and private/reserved network targets and redirects                           | 3     | implemented | PHP guard tests + Node request interception       |
| R9  | Persist up to 100,000 immutable rows and private CSV/JSON artifacts                         | 3     | implemented | chunked ingestion and immutability tests          |
| R10 | Filter, sort, paginate, select columns, and download datasets                               | 3/4   | implemented | controller/query tests + production build         |
| R11 | Provide Recipes, Runs, Settings navigation and approval/run pages                           | 4     | implemented | controller tests + E2E recipe workspace spec      |
| R12 | Keep en/cs/sk translations synchronized                                                     | 4     | verified    | i18n parity tests                                 |
| R13 | Preserve auth, ownership, queue, Redis/supervisor, and architecture contracts               | all   | verified    | PHPStan max + architecture tests + full gates     |
| R14 | Document internal-only execution and defer public sandbox claims                            | 3/5   | verified    | docs plus warning on recipe create/detail screens |
