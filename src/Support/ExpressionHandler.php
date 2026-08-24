<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

class ExpressionHandler
{
    public function __construct(private Inflector $inflector) {}

    public function isSimpleColumnExpression(?string $value): bool
    {
        if (! $value) {
            return false;
        }

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $value)) {
            return true;
        }

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*\s*[+\-*\/]\s*\d+$/', $value)) {
            return true;
        }

        return false;
    }

    public function extractExpressionValue($expression): ?string
    {
        if (method_exists($expression, '__toString')) {
            return (string) $expression;
        }

        if (is_object($expression) && get_class($expression) === 'Illuminate\Database\Query\Expression') {
            try {
                $reflection = new \ReflectionProperty($expression, 'value');
                $reflection->setAccessible(true);
                $value = $reflection->getValue($expression);

                return is_string($value) ? $value : (string) $value;
            } catch (\Exception $e) {
                // Reflection failed
            }
        }

        return null;
    }

    public function isWhereHasExpression(array $where): bool
    {
        $expressionValue = $this->extractExpressionValue($where['column']);

        if (! $expressionValue) {
            return false;
        }

        foreach (Constants::COMPLEX_QUERY_PATTERNS as $pattern) {
            if (preg_match($pattern, $expressionValue)) {
                return true;
            }
        }

        return false;
    }

    public function handleWhereHasExpression(array $where): string
    {
        $expressionValue = $this->extractExpressionValue($where['column']);

        if (! $expressionValue) {
            return 'with related records';
        }

        $relationshipNames = $this->extractRelationshipNamesFromExpression($expressionValue);

        if (empty($relationshipNames)) {
            return 'with related records';
        }

        // The FROM table of the count subquery is the relation being counted
        $relation = str_replace(['_', '-'], ' ', $relationshipNames[0]);

        $operator = $where['operator'] ?? '=';
        $value = $where['value'] ?? 0;

        if (is_object($value)) {
            $extracted = $this->extractExpressionValue($value);
            $value = is_numeric($extracted) ? (int) $extracted : 1;
        }
        $count = (int) $value;
        $singular = $this->inflector->singularize($relation);
        $counted = fn (int $n) => $n === 1 ? "one {$singular}" : "{$n} {$relation}";

        return match ($operator) {
            '>=' => $count <= 1 ? "who have {$relation}" : "who have at least {$counted($count)}",
            '>' => $count === 0 ? "who have {$relation}" : "who have more than {$counted($count)}",
            '=' => $count === 0 ? "who don't have {$relation}" : "who have exactly {$counted($count)}",
            '!=', '<>' => $count === 0 ? "who have {$relation}" : "who don't have exactly {$counted($count)}",
            '<' => $count <= 1 ? "who don't have {$relation}" : "who have fewer than {$counted($count)}",
            '<=' => $count === 0 ? "who don't have {$relation}" : "who have at most {$counted($count)}",
            default => "who have {$relation}",
        };
    }

    public function buildConditionText(string $column, string $operator, mixed $value): string
    {
        return match ($operator) {
            '=' => "whose {$column} is {$this->formatValue($value)}",
            '!=', '<>' => "whose {$column} is not {$this->formatValue($value)}",
            '>' => "with {$column} greater than {$this->formatValue($value)}",
            '>=' => "with {$column} greater than or equal to {$this->formatValue($value)}",
            '<' => "with {$column} less than {$this->formatValue($value)}",
            '<=' => "with {$column} less than or equal to {$this->formatValue($value)}",
            default => "with {$column} {$operator} {$this->formatValue($value)}",
        };
    }

    private function extractRelationshipNamesFromExpression(string $expression): array
    {
        $tableNames = [];

        if (preg_match_all('/from\s+["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?/i', $expression, $matches)) {
            foreach ($matches[1] as $tableName) {
                if (! in_array(strtolower($tableName), Constants::SYSTEM_TABLES)
                    && ! str_starts_with(strtolower($tableName), 'laravel_reserved')) {
                    $tableNames[] = $tableName;
                }
            }
        }

        if (empty($tableNames) && preg_match_all('/["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?\.["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?/i', $expression, $matches)) {
            foreach ($matches[1] as $tableName) {
                if (! in_array($tableName, $tableNames) &&
                    ! in_array(strtolower($tableName), Constants::SYSTEM_TABLES) &&
                    ! str_starts_with(strtolower($tableName), 'laravel_reserved')) {
                    $tableNames[] = $tableName;
                }
            }
        }

        return array_unique($tableNames);
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

        if (is_array($value)) {
            $formatted = array_map([$this, 'formatValue'], $value);

            return '['.implode(', ', $formatted).']';
        }

        return (string) $value;
    }
}
