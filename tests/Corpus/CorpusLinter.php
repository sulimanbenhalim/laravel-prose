<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Corpus;

/**
 * Heuristic quality checks applied to every generated description.
 * Each returned defect is "code: detail".
 */
class CorpusLinter
{
    private const ACTION_WORDS = [
        'find', 'count', 'sum', 'average', 'maximum', 'minimum', 'update', 'delete',
    ];

    /** @return string[] defect codes for one description */
    public static function lint(string $description): array
    {
        $defects = [];

        if (trim($description) === '') {
            return ['empty: description is empty'];
        }

        // Contraction apostrophes (don't, doesn't, O'Brien inside double
        // quotes) are not value delimiters.
        $decontracted = preg_replace("/(\\p{L})'(\\p{L})/u", '$1$2', $description);
        $decontracted = preg_replace('/"[^"]*"/', '"VAL"', $decontracted);

        // Values inside single quotes are user data and may legitimately contain
        // underscores, capitals, SQL words, etc. Lint only the prose around them.
        $prose = preg_replace("/'[^']*'/", "'VAL'", $decontracted);

        if (substr_count($decontracted, "'") % 2 !== 0) {
            $defects[] = 'unbalanced_quotes: odd number of single quotes';
        }

        if (substr_count($prose, '(') !== substr_count($prose, ')')) {
            $defects[] = 'unbalanced_parens';
        }

        if (str_contains($prose, '{') || str_contains($prose, '}')) {
            $defects[] = 'placeholder_leak: unreplaced template placeholder';
        }

        if (preg_match('/\s{2,}/', $description)) {
            $defects[] = 'double_space';
        }

        if (preg_match('/\b(\p{L}+)\s+\1\b/iu', $prose, $m) && ! in_array(strtolower($m[1]), ['had'], true)) {
            $defects[] = "repeated_word: '{$m[1]} {$m[1]}'";
        }

        if (preg_match('/[a-z0-9]_[a-z0-9]/i', $prose, $m)) {
            $defects[] = "snake_case_leak: raw column name near '{$m[0]}'";
        }

        if (preg_match('/\b[a-z]+[A-Z][a-zA-Z]*\b/', $prose, $m)) {
            $defects[] = "camel_case_leak: '{$m[0]}'";
        }

        $firstWord = strtolower(explode(' ', trim($description))[0]);
        if (! in_array($firstWord, self::ACTION_WORDS, true)) {
            $defects[] = "bad_start: begins with '{$firstWord}'";
        }

        if (preg_match('/\b(and|or|with|to|by|for|of|the|a|an|whose|who|that|then|is|are)[.]?$/i', trim($prose), $m)) {
            $defects[] = "dangling_connector: ends with '{$m[1]}'";
        }

        if (preg_match('/\b(is|are|equals?)\s+(true|false)\b/i', $prose, $m)) {
            $defects[] = "boolean_leak: '{$m[0]}'";
        }

        if (preg_match('/\b(select|null)\b|`/i', $prose, $m)) {
            $defects[] = "sql_leak: '{$m[0]}'";
        }

        if (str_contains($prose, 'Array') || str_contains($prose, 'stdClass')) {
            $defects[] = 'array_to_string_leak';
        }

        if (preg_match('/\ba [aeiou]/i', $prose) || preg_match('/\ban (?!hour|honest|honor|heir)[^aeiou]/i', $prose)) {
            $defects[] = 'wrong_article';
        }

        if (preg_match('/\(\s*\)/', $prose)) {
            $defects[] = 'empty_parens';
        }

        return $defects;
    }
}
