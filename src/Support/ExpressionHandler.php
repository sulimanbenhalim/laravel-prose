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

        if (! empty($relationshipNames)) {
            $humanizedNames = array_map(function ($name) {
                return str_replace(['_', '-'], ' ', $name);
            }, $relationshipNames);

            $relationships = $this->inflector->joinWithConnector($humanizedNames, 'and');

            $operator = $where['operator'] ?? '=';
            $value = $where['value'] ?? 0;

            $isPositive = $this->isPositiveRelationshipCondition($operator, $value);

            if ($isPositive) {
                return "who have {$relationships}";
            } else {
                return "who don't have {$relationships}";
            }
        }

        return 'with related records';
    }

    public function buildConditionText(string $column, string $operator, mixed $value): string
    {
        return match ($operator) {
            '=' => "with {$column} is {$this->formatValue($value)}",
            '!=' => "with {$column} not equal to {$this->formatValue($value)}",
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
                if (! in_array(strtolower($tableName), Constants::SYSTEM_TABLES)) {
                    $tableNames[] = $tableName;
                }
            }
        }

        if (preg_match_all('/["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?\.["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?/i', $expression, $matches)) {
            foreach ($matches[1] as $tableName) {
                if (! in_array($tableName, $tableNames) &&
                    ! in_array(strtolower($tableName), Constants::SYSTEM_TABLES)) {
                    $tableNames[] = $tableName;
                }
            }
        }

        return array_unique($tableNames);
    }

    private function isPositiveRelationshipCondition(string $operator, mixed $value): bool
    {
        if (is_object($value)) {
            $expressionValue = $this->extractExpressionValue($value);
            if ($expressionValue && is_numeric($expressionValue)) {
                $value = (int) $expressionValue;
            } else {
                return true;
            }
        } else {
            $value = (int) $value;
        }

        switch ($operator) {
            case '>':
            case '>=':
                return $value >= 0;
            case '=':
                return $value > 0;
            case '<':
            case '<=':
                return $value < 0;
            case '!=':
            case '<>':
                return $value == 0;
            default:
                return true;
        }
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
