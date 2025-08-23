<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

class LikePatternHandler
{
    public function translateLikePattern(string $column, string $operator, mixed $value): string
    {
        $pattern = (string) $value;

        if ($operator === 'like') {
            return $this->handleLikePattern($column, $pattern);
        } else { // 'not like'
            return $this->handleNotLikePattern($column, $pattern);
        }
    }

    private function handleLikePattern(string $column, string $pattern): string
    {
        if (preg_match('/^%(.+)%$/', $pattern, $matches)) {
            $content = $this->formatValue($matches[1]);

            return "with {$column} containing {$content}";
        }

        if (preg_match('/^%(.+)$/', $pattern, $matches)) {
            $content = $this->formatValue($matches[1]);

            return "with {$column} ending with {$content}";
        }

        if (preg_match('/^(.+)%$/', $pattern, $matches)) {
            $content = $this->formatValue($matches[1]);

            return "with {$column} starting with {$content}";
        }

        $formattedValue = $this->formatValue($pattern);

        return "with {$column} matching {$formattedValue}";
    }

    private function handleNotLikePattern(string $column, string $pattern): string
    {
        if (preg_match('/^%(.+)%$/', $pattern, $matches)) {
            $content = $this->formatValue($matches[1]);

            return "with {$column} not containing {$content}";
        }

        if (preg_match('/^%(.+)$/', $pattern, $matches)) {
            $content = $this->formatValue($matches[1]);

            return "with {$column} not ending with {$content}";
        }

        if (preg_match('/^(.+)%$/', $pattern, $matches)) {
            $content = $this->formatValue($matches[1]);

            return "with {$column} not starting with {$content}";
        }

        $formattedValue = $this->formatValue($pattern);

        return "with {$column} not matching {$formattedValue}";
    }

    public function buildLikeDescription(string $type, array $columns, mixed $value): ?string
    {
        $pattern = (string) $value;

        if (preg_match('/^%(.+)%$/', $pattern, $matches)) {
            $content = $matches[1];

            return match ($type) {
                'any' => "whose {$this->buildEitherOrList($columns)} contain '{$content}'",
                'all' => "whose {$this->buildBothAndList($columns)} contain '{$content}'",
                'none' => "whose {$this->buildNeitherNorList($columns)} contain '{$content}'",
                default => "whose {$this->buildEitherOrList($columns)} contain '{$content}'",
            };
        }

        if (preg_match('/^(.+)%$/', $pattern, $matches)) {
            $content = $matches[1];

            return match ($type) {
                'any' => "whose {$this->buildEitherOrList($columns)} start with '{$content}'",
                'all' => "whose {$this->buildBothAndList($columns)} start with '{$content}'",
                'none' => "whose {$this->buildNeitherNorList($columns)} start with '{$content}'",
                default => "whose {$this->buildEitherOrList($columns)} start with '{$content}'",
            };
        }

        if (preg_match('/^%(.+)$/', $pattern, $matches)) {
            $content = $matches[1];

            return match ($type) {
                'any' => "whose {$this->buildEitherOrList($columns)} end with '{$content}'",
                'all' => "whose {$this->buildBothAndList($columns)} end with '{$content}'",
                'none' => "whose {$this->buildNeitherNorList($columns)} end with '{$content}'",
                default => "whose {$this->buildEitherOrList($columns)} end with '{$content}'",
            };
        }

        return null; // Not a LIKE pattern
    }

    private function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return "'{$value}'";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        return (string) $value;
    }

    private function buildEitherOrList(array $columns): string
    {
        if (count($columns) === 1) {
            return $columns[0];
        }

        if (count($columns) === 2) {
            return "either {$columns[0]} or {$columns[1]}";
        }

        $last = array_pop($columns);

        return 'either '.implode(', ', $columns).", or {$last}";
    }

    private function buildBothAndList(array $columns): string
    {
        if (count($columns) === 1) {
            return $columns[0];
        }

        if (count($columns) === 2) {
            return "both {$columns[0]} and {$columns[1]}";
        }

        $last = array_pop($columns);

        return implode(', ', $columns).", and {$last}";
    }

    private function buildNeitherNorList(array $columns): string
    {
        if (count($columns) === 1) {
            return $columns[0];
        }

        if (count($columns) === 2) {
            return "neither {$columns[0]} nor {$columns[1]}";
        }

        $last = array_pop($columns);

        return 'neither '.implode(', ', $columns).", nor {$last}";
    }
}
