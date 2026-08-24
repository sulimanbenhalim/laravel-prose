<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Corpus;

use Carbon\Carbon;
use SulimanBenhalim\Prose\Tests\TestCase;

/**
 * Runs the whole query corpus through describe() under several frozen clock
 * anchors and fails on any lint defect, unmet expectation, crash, or output
 * that varies with the time of day.
 *
 * Set CORPUS_REPORT_PATH to also write a full human-readable report of every
 * (query, description) pair.
 */
class CorpusQualityTest extends TestCase
{
    /** Same calendar date, three very different times of day. */
    private const ANCHORS = [
        '2026-08-24 14:30:00',
        '2026-08-24 00:20:00',
        '2026-08-24 23:50:00',
    ];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_corpus_produces_clean_natural_language(): void
    {
        $entries = QueryCorpus::entries();
        $this->assertGreaterThan(600, count($entries), 'corpus should stay large');

        $results = [];   // name => description from first anchor
        $defects = [];   // name => defect strings
        $fallbacks = []; // names that used the raw fallback text

        foreach (self::ANCHORS as $i => $anchor) {
            Carbon::setTestNow(Carbon::parse($anchor));

            foreach ($entries as $name => [$fn, $must, $mustNot]) {
                try {
                    $description = $fn();
                } catch (\Throwable $e) {
                    if ($i === 0) {
                        $results[$name] = '<<CRASH>> '.$e::class.': '.$e->getMessage();
                        $defects[$name][] = 'crash: '.$e::class.': '.$e->getMessage();
                    }

                    continue;
                }

                if ($i === 0) {
                    $results[$name] = $description;

                    foreach (CorpusLinter::lint($description) as $defect) {
                        $defects[$name][] = $defect;
                    }
                    foreach ($must as $substring) {
                        if (! str_contains($description, $substring)) {
                            $defects[$name][] = "expected_missing: '{$substring}'";
                        }
                    }
                    foreach ($mustNot as $substring) {
                        if (str_contains($description, $substring)) {
                            $defects[$name][] = "forbidden_present: '{$substring}'";
                        }
                    }
                    if (str_contains($description, 'with custom database operations')) {
                        $fallbacks[] = $name;
                    }
                } elseif (($results[$name] ?? null) !== $description && ! str_starts_with($results[$name] ?? '', '<<CRASH>>')) {
                    $defects[$name][] = "time_dependent: anchor {$anchor} gave '{$description}' vs '{$results[$name]}'";
                }
            }
        }

        Carbon::setTestNow();

        $this->writeReport($results, $defects, $fallbacks);

        $flat = [];
        foreach ($defects as $name => $list) {
            foreach (array_unique($list) as $defect) {
                $flat[] = "[{$name}] {$defect}\n    output: ".($results[$name] ?? '');
            }
        }

        $summary = count($flat).' defect(s) across '.count($defects).' of '.count($entries)." corpus queries.\n\n"
            .implode("\n", array_slice($flat, 0, 40))
            .(count($flat) > 40 ? "\n... and ".(count($flat) - 40).' more (see report)' : '');

        $this->assertSame([], $flat, $summary);
    }

    private function writeReport(array $results, array $defects, array $fallbacks): void
    {
        $path = getenv('CORPUS_REPORT_PATH');
        if (! $path) {
            return;
        }

        $lines = ["# Prose corpus report\n"];
        $lines[] = count($results).' queries, '.count($defects).' with defects, '.count($fallbacks)." using raw fallback.\n";

        $lines[] = "\n## Defects\n";
        foreach ($defects as $name => $list) {
            $lines[] = "- **{$name}**: ".implode('; ', array_unique($list));
            $lines[] = "  - `{$results[$name]}`";
        }

        $lines[] = "\n## Fallback usage (coverage gaps)\n";
        foreach ($fallbacks as $name) {
            $lines[] = "- **{$name}**: `{$results[$name]}`";
        }

        $lines[] = "\n## All outputs\n";
        $currentGroup = '';
        foreach ($results as $name => $description) {
            $group = explode('/', $name)[0];
            if ($group !== $currentGroup) {
                $lines[] = "\n### {$group}\n";
                $currentGroup = $group;
            }
            $lines[] = "- `{$name}` → {$description}";
        }

        file_put_contents($path, implode("\n", $lines)."\n");
    }
}
