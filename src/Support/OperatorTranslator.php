<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

class OperatorTranslator
{
    public function translateBasicOperator(string $operator): string
    {
        return match ($operator) {
            '=' => 'is',
            '!=' => 'not equal to',
            '>' => 'greater than',
            '>=' => 'greater than or equal to',
            '<' => 'less than',
            '<=' => 'less than or equal to',
            'like' => 'like',
            'not like' => 'not like',
            default => $operator,
        };
    }

    public function translateDateOperator(string $operator): string
    {
        return match ($operator) {
            '=' => 'on',
            '!=' => 'not on',
            '>' => 'after',
            '>=' => 'on or after',
            '<' => 'before',
            '<=' => 'on or before',
            default => $operator,
        };
    }

    public function translateTimeOperator(string $operator): string
    {
        return match ($operator) {
            '=' => 'at exactly',
            '!=' => 'not at',
            '>' => 'after',
            '>=' => 'at or after',
            '<' => 'before',
            '<=' => 'at or before',
            default => $operator,
        };
    }

    public function translateMonthOperator(string $operator): string
    {
        return match ($operator) {
            '=' => 'in',
            '!=' => 'not in',
            '>' => 'after',
            '>=' => 'in or after',
            '<' => 'before',
            '<=' => 'in or before',
            default => $operator,
        };
    }

    public function translateDayOperator(string $operator): string
    {
        return match ($operator) {
            '=' => 'on day',
            '!=' => 'not on day',
            '>' => 'after day',
            '>=' => 'on or after day',
            '<' => 'before day',
            '<=' => 'on or before day',
            default => "day {$operator}",
        };
    }

    public function translateComparisonPhrase(string $operator, mixed $value): string
    {
        $formattedValue = $this->formatValue($value);

        return match ($operator) {
            '=' => "= {$formattedValue}",
            '!=' => "!= {$formattedValue}",
            '>' => "> {$formattedValue}",
            '>=' => ">= {$formattedValue}",
            '<' => "< {$formattedValue}",
            '<=' => "<= {$formattedValue}",
            'not like' => "not like {$formattedValue}",
            default => "{$operator} {$formattedValue}",
        };
    }

    public function buildConditionPhrase(string $column, string $operator, mixed $value): string
    {
        $operatorText = $this->translateBasicOperator($operator);
        $formattedValue = $this->formatValue($value);

        return "with {$column} {$operatorText} {$formattedValue}";
    }

    public function buildDateConditionPhrase(string $column, string $operator, mixed $value): string
    {
        $operatorText = $this->translateDateOperator($operator);
        $formattedValue = $this->formatValue($value);

        return "with {$column} {$operatorText} {$formattedValue}";
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
