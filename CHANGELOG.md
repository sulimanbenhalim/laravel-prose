# Changelog

All notable changes to `laravel-prose` are documented here. The project follows [SemVer](https://semver.org); because this package's output *is* its contract, any change to generated description text ships as a major release.

## 2.0.0 - 2026-08-24

### Fixed

- `orWhere` chains and or-groups are now joined with "or" instead of "and".
- `whereNot()` and `whereNotBetween()` no longer drop their negation (previously the description stated the opposite of the query).
- `whereLike`, `whereJsonLength`, `whereIntegerInRaw`, and `whereBetweenColumns` are now translated instead of silently omitted.
- `inRandomOrder()`, `orderByRaw()`, and `DB::raw()` values no longer crash `describe()`.
- Relative date phrasing is deterministic and no longer depends on the time of day the code runs: `whereDate(..., '>=', today())` reads "today or later" (previously e.g. "within the last 870 minutes"), `subMinutes(5)` reads "5 minutes" (previously "4 minutes"), `> now()->addDays(3)` reads "more than 3 days from now" (previously the inverted "in the next 3 days").
- `has('relation', '>=', 3)` reads "who have at least 3 orders" (previously garbled table names).
- Self-referential `whereHas` no longer leaks `laravel_reserved_0` aliases; `belongsToMany` pivots no longer confuse relation detection.
- "createded" double past-tense conjugation.

### Changed

- Equality phrasing: "whose order status is 'pending'" replaces "with order status is 'pending'"; inequality reads "is not" instead of "not equal to".
- `belongsTo` `whereHas` reads naturally: "who have a customer that is a premium member".
- Boolean fields conjugate: "that require shipping", "that are premium members".
- Measurement units and acronyms: "weight in kg", "duration in minutes", "GPA score", "SKU code".
- Nested eager loads: "including their order items and their product".
- Static dates format absolutely ("between January 1, 2024 and December 31, 2024") instead of as rolling week counts.
- Offsets read "skipping the first 2500"; `limit(1)` reads "the first product".
- Long `whereIn` lists truncate after five values; single-value lists collapse to equality.
- `groupBy`/`having` are now described ("grouped by customer ID ...").
- Default `truncation_indicator` config value is now `"other conditions"`.

### Performance

- Roughly 9x faster (~780µs to ~85µs per description): brute-force Carbon scans replaced with O(1) arithmetic, dead Doctrine DBAL calls removed in favor of Laravel's native schema introspection with per-table caching, field-name humanization memoized.

### Removed

- `doctrine/dbal` dev dependency (schema detection uses Laravel's native `getColumns()`).

### Internal

- New corpus quality gate: 763 realistic queries across ten domains run under three frozen clock anchors on every test run; outputs are lint-checked for leaked column names, broken grammar, dropped negations, and time-of-day dependence.
- Every README example is asserted byte-for-byte in `tests/Feature/ReadmeExamplesTest.php`.

## 1.0.0 - 2025-08-23

- Initial stable release.
