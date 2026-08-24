<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

class OperatorTranslator
{
    public function __construct(private ?Inflector $inflector = null) {}

    public function translateBasicOperator(string $operator): string
    {
        return match ($operator) {
            '=' => 'is',
            '!=', '<>' => 'is not',
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
            '!=', '<>' => 'not on',
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
            '!=', '<>' => 'not at',
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
            '!=', '<>' => 'not in',
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
            '=' => 'on the',
            '!=', '<>' => 'not on the',
            '>' => 'after the',
            '>=' => 'on or after the',
            '<' => 'before the',
            '<=' => 'on or before the',
            default => "day {$operator}",
        };
    }

    /** Comparison between two columns: "equals", "is greater than", ... */
    public function translateColumnOperator(string $operator, bool $datesInvolved = false): string
    {
        if ($datesInvolved) {
            return match ($operator) {
                '=' => 'is the same as',
                '!=', '<>' => 'differs from',
                '>', '>=' => 'is after',
                '<', '<=' => 'is before',
                default => $operator,
            };
        }

        return match ($operator) {
            '=' => 'equals',
            '!=', '<>' => 'differs from',
            '>' => 'is greater than',
            '>=' => 'is greater than or equal to',
            '<' => 'is less than',
            '<=' => 'is less than or equal to',
            default => $operator,
        };
    }

    public function buildConditionPhrase(string $column, string $operator, mixed $value): string
    {
        $formattedValue = $this->formatValue($value);

        return match ($operator) {
            '=' => "whose {$column} is {$formattedValue}",
            '!=', '<>' => "whose {$column} is not {$formattedValue}",
            default => "with {$column} {$this->translateBasicOperator($operator)} {$formattedValue}",
        };
    }

    public function buildDateConditionPhrase(string $column, string $operator, mixed $value): string
    {
        $operatorText = $this->translateDateOperator($operator);
        $formattedValue = $this->formatValue($value);

        return "with {$column} {$operatorText} {$formattedValue}";
    }

    private function formatValue(mixed $value): string
    {
        if ($this->inflector !== null) {
            return $this->inflector->formatValue($value);
        }

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
