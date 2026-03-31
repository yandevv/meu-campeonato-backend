---
name: project-testing
description: "Apply this skill whenever you work on PHPUnit tests, phpunit.xml, Composer test scripts, test base classes, or database-backed test setup in this repository. Covers this project's Unit, Feature, and Integration suite policy, PostgreSQL-first testing rules, and parallel test workflow."
---

# Project Testing

Use this skill for all testing work in this repository.

## Project Defaults

- Use PHPUnit only.
- Standardize on three suites: `Unit`, `Feature`, and `Integration`.
- Keep `Unit` tests pure PHP with no Laravel boot and no database access.
- Prefer `Feature` tests for Laravel application behavior.
- Use `Integration` tests only when real PostgreSQL semantics or a real external-process boundary are part of the behavior being validated.
- Use PostgreSQL for database-backed tests and keep the base test database as `testing`.
- Do not switch DB-backed tests to SQLite just to make tests faster.

## Execution

- Prefer `php artisan test` over calling PHPUnit directly.
- Create new tests with `php artisan make:test --phpunit {name}` or `php artisan make:test --phpunit --unit {name}`.
- Use suite filters for routine runs:
  - `php artisan test --compact --testsuite=Unit`
  - `php artisan test --compact --testsuite=Feature`
  - `php artisan test --compact --testsuite=Integration`
- Use `php artisan test --parallel` for parallel runs.
- If the parallel databases need a clean rebuild, use `--recreate-databases`.

## Test Design

- Most tests should be `Feature` tests.
- Use existing factories before creating manual test data.
- Use `assertModelExists()` over raw `assertDatabaseHas()` where it proves the behavior cleanly.
- Prefer factory states and sequences over manual attribute overrides when factories already express the scenario.
- Prefer `Exceptions::fake()` over `withoutExceptionHandling()` when the goal is to assert reporting behavior.
- Use fakes only after factory setup when model events are required for IDs or related boot logic.
- Use `recycle()` when nested factories should share the same related model instance.
- Keep integration tests narrow and focused on the real dependency that justifies them.
