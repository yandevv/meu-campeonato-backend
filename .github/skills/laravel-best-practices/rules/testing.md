# Testing Best Practices

## Use Explicit `Unit`, `Feature`, and `Integration` Suites

Use `Unit` for pure PHP domain logic that does not need Laravel to boot. Use `Feature` for HTTP endpoints, validation, resources, service wiring, and most application behavior. Use `Integration` when the test depends on real PostgreSQL semantics or a real external-process boundary.

Most tests should be `Feature` tests because they give the best confidence for Laravel applications.

## Use PostgreSQL for Database-Backed Tests

This project uses PostgreSQL in production and expects database-backed tests to match that behavior. Keep `DB_CONNECTION=pgsql` and use the `testing` database for automated tests instead of switching DB-backed tests to SQLite for speed.

## Use `LazilyRefreshDatabase` Over `RefreshDatabase`

`RefreshDatabase` runs all migrations every test run even when the schema hasn't changed. `LazilyRefreshDatabase` only migrates when needed, significantly speeding up large suites.

## Prefer `php artisan test` With Suite Filters

Use the Artisan test runner for normal workflows:

- `php artisan test --compact --testsuite=Unit`
- `php artisan test --compact --testsuite=Feature`
- `php artisan test --compact --testsuite=Integration`
- `php artisan test --parallel`

For parallel runs against PostgreSQL, Laravel will create per-process test databases automatically. Use `--recreate-databases` when those databases need a clean rebuild.

## Use Model Assertions Over Raw Database Assertions

Incorrect: `$this->assertDatabaseHas('users', ['id' => $user->id]);`

Correct: `$this->assertModelExists($user);`

More expressive, type-safe, and fails with clearer messages.

## Use Factory States and Sequences

Named states make tests self-documenting. Sequences eliminate repetitive setup.

Incorrect: `User::factory()->create(['email_verified_at' => null]);`

Correct: `User::factory()->unverified()->create();`

## Use `Exceptions::fake()` to Assert Exception Reporting

Instead of `withoutExceptionHandling()`, use `Exceptions::fake()` to assert the correct exception was reported while the request completes normally.

## Call `Event::fake()` After Factory Setup

Model factories rely on model events (e.g., `creating` to generate UUIDs). Calling `Event::fake()` before factory calls silences those events, producing broken models.

Incorrect: `Event::fake(); $user = User::factory()->create();`

Correct: `$user = User::factory()->create(); Event::fake();`

## Use `recycle()` to Share Relationship Instances Across Factories

Without `recycle()`, nested factories create separate instances of the same conceptual entity.

```php
Ticket::factory()
    ->recycle(Airline::factory()->create())
    ->create();
```

## Use Integration Tests for Real Database or Process Boundaries

If a code path depends on transactions, row locking, query grammar, or a real process invocation, cover it with an `Integration` test instead of downgrading it to a mocked `Feature` or `Unit` test. Keep those tests focused on the specific boundary that needs the real dependency.
