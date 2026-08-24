# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`sulimanbenhalim/laravel-prose` — a Laravel package that turns Eloquent query builder state into natural-language descriptions (e.g. `User::where('created_at', '>', '2024-01-01')->describe()` → "Find users created after January 1, 2024"). The directory is named `query-explainer` but the package name is `laravel-prose` and the namespace is `SulimanBenhalim\Prose`. Descriptions are generated purely from builder state; no query is ever executed.

Requires PHP 8.2+ and Laravel 12. Code style is PSR-12 via Pint; translations must be deterministic (same query → same description) and fail gracefully to `raw_fallback_text` for unsupported constructs.

## Commands

```bash
composer test                                   # run all tests (PHPUnit)
vendor/bin/phpunit --filter testMethodName      # run a single test
vendor/bin/phpunit tests/Feature/EcommerceSystemTest.php   # run one file
composer format                                 # Laravel Pint (fix style)
vendor/bin/pint --test                          # style check only (what CI runs)
composer analyse                                # PHPStan level 5 (larastan), covers src/ and tests/
PROSE_BENCH=1 vendor/bin/phpunit tests/Corpus/BenchmarkTest.php   # describe() throughput benchmark
CORPUS_REPORT_PATH=/tmp/corpus.md vendor/bin/phpunit tests/Corpus/CorpusQualityTest.php  # corpus + full output report
```

The corpus (`tests/Corpus/`) runs 750+ realistic queries under three frozen clock anchors and lint-checks every output (leaked snake_case, dangling connectors, dropped negations, time-of-day-dependent output, per-entry must/must-not substrings). It is part of the normal test suite — any phrasing change must keep it green. `tests/Feature/ReadmeExamplesTest.php` asserts every README example byte-for-byte; if you change output phrasing, update both the test and the README together.

CI (`.github/workflows/tests.yml`) runs all three: phpunit, `pint --test`, and phpstan on PHP 8.2/8.3.

## Architecture

The flow is: **service provider macros → `Prose` orchestrator → translators → support handlers**.

1. **`src/ProseServiceProvider.php`** registers `describe`, `describeCount`, `describeSum/Avg/Max/Min`, `describeUpdate`, `describeDelete` as macros on `Illuminate\Database\Eloquent\Builder`. Non-select macros work by cloning the builder and injecting a fake aggregate onto the underlying query builder (`aggregate = ['function' => 'update', 'values' => [...]]`) — update and delete are pseudo-aggregates, not real Laravel aggregate functions. This is how a single `describe()` entry point knows which action to render.

2. **`src/Prose.php`** is the orchestrator. It decomposes the builder into sentence parts (`action`, `model`, `conditions`, `updateFields`, `relationships`, `ordering`, `limit`), delegates each to a translator, then fills the configurable `sentence_template` from `config/prose.php`. It also repositions limits ("Find first 10 products ..." rather than trailing), splits exists-type wheres out from regular wheres, and filters redundant eager loads (drops `customer` if `customer.orders` is loaded).

3. **`src/Translators/`** — one translator per query aspect. `WhereTranslator` is the core: a big `match` on `$where['type']` (`basic`, `in`, `null`, `between`, `nested`, `date`, `expression`, `jsoncontains`, `raw`, ...). `ExistsTranslator` handles `whereHas`/`whereDoesntHave` and has a circular dependency with `WhereTranslator`, wired via `setExistsTranslator()` in the `Prose` constructor. `BaseTranslator` holds shared formatting (value quoting, LIKE-pattern phrasing, multi-column any/all/none patterns for `whereAny`/`whereAll`, join-condition detection). Condition lists truncate at `max_conditions` with the `truncation_indicator`.

4. **`src/Support/`** — the "intelligence" layer the translators call into:
   - `Inflector` — central helper injected everywhere: pluralization (doctrine/inflector plus `custom_pluralization` config), field-name humanization (`price_usd` → "price in USD"), date humanization, connector joining.
   - `FieldTypeDetector` — detects boolean/date fields from model casts and database schema (doctrine/dbal).
   - `BooleanFieldHandler` — turns `is_premium_member = true` into "that are premium member", `has_warranty = false` into "that don't have warranty".
   - `DateTimeHandler` — Carbon-aware relative phrasing ("within the last 7 days", "in the next 14 days").
   - `LaravelAttributeHandler` — Laravel-convention smarts, e.g. `email_verified_at IS NULL` → "with unverified email".
   - `LikePatternHandler`, `OperatorTranslator`, `UpdateFieldHandler`, `VerbInflector`, `ExpressionHandler`, `Constants`.

All user-facing wording (action verbs, connectors, date formats, sentence template) comes from `config/prose.php` — honor config rather than hardcoding phrases.

## Tests

Tests use Orchestra Testbench with in-memory SQLite. `tests/TestCase.php` is load-bearing: it creates every table schema **and defines all test models inline in the same file** (Customer, Product, Order, GymMember, Patient, Student, Property, Employee, and many more across ~10 business domains). To test against a new field or model, add it there. Feature tests are organized as realistic domain scenarios (`EcommerceSystemTest`, `ClinicManagementSystemTest`, `RefugeeAsylumSystemTest`, ...) that assert exact output strings; `tests/Unit/` covers individual components.

When adding support for a new query method: add translator logic, add config if new wording is needed, add feature tests asserting the exact description, and update the README examples.
