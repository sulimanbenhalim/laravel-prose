<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Translators;

use SulimanBenhalim\Prose\Support\Constants;
use SulimanBenhalim\Prose\Support\DateTimeHandler;
use SulimanBenhalim\Prose\Support\FieldTypeDetector;
use SulimanBenhalim\Prose\Support\Inflector;
use SulimanBenhalim\Prose\Support\OperatorTranslator;

abstract class BaseTranslator
{
    protected FieldTypeDetector $fieldTypeDetector;

    protected OperatorTranslator $operatorTranslator;

    protected DateTimeHandler $dateTimeHandler;

    public function __construct(
        protected Inflector $inflector,
        protected array $config
    ) {
        $this->fieldTypeDetector = new FieldTypeDetector;
        $this->operatorTranslator = new OperatorTranslator;
        $this->dateTimeHandler = new DateTimeHandler($this->inflector, $this->fieldTypeDetector);
    }

    abstract public function translate(array $wheres, $builder = null): array;

    protected function formatValue(mixed $value): string
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

    protected function formatInValues(array $values): string
    {
        $formatted = array_map([$this, 'formatValue'], $values);

        if (count($formatted) <= 2) {
            return $this->inflector->joinWithConnector($formatted, 'or');
        }

        return implode(', ', array_slice($formatted, 0, -1)).', or '.end($formatted);
    }

    protected function formatTimeValue(mixed $timeValue): string
    {
        try {
            if (is_string($timeValue)) {
                if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $timeValue, $matches)) {
                    $hour = (int) $matches[1];
                    $minute = (int) $matches[2];

                    $ampm = $hour >= 12 ? 'PM' : 'AM';
                    $displayHour = $hour === 0 ? 12 : ($hour > 12 ? $hour - 12 : $hour);

                    return sprintf('%d:%02d %s', $displayHour, $minute, $ampm);
                }
            }

            return "'{$timeValue}'";
        } catch (\Exception) {
            return "'{$timeValue}'";
        }
    }

    protected function formatMonthValue(mixed $monthValue): string
    {
        if (is_numeric($monthValue)) {
            $monthNum = (int) $monthValue;
            if ($monthNum >= 1 && $monthNum <= 12) {
                return Constants::MONTH_NAMES[$monthNum];
            }
        }

        return $this->formatValue($monthValue);
    }

    protected function buildMultiColumnDescription(string $type, array $columns, string $operator, mixed $value): string
    {
        $pattern = (string) $value;

        if ($operator === 'like') {
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
        }

        // Handle exact match
        $valueDescription = $this->formatValue($value);
        $operatorText = $this->operatorTranslator->translateBasicOperator($operator);

        return match ($type) {
            'any' => "whose {$this->buildEitherOrList($columns)} {$operatorText} {$valueDescription}",
            'all' => "whose {$this->buildBothAndList($columns)} {$operatorText} {$valueDescription}",
            'none' => "whose {$this->buildNeitherNorList($columns)} {$operatorText} {$valueDescription}",
            default => "whose {$this->buildEitherOrList($columns)} {$operatorText} {$valueDescription}",
        };
    }

    protected function buildEitherOrList(array $items): string
    {
        if (count($items) === 2) {
            return "either {$items[0]} or {$items[1]}";
        }

        return $this->inflector->joinWithConnector($items, 'or');
    }

    protected function buildBothAndList(array $items): string
    {
        if (count($items) === 2) {
            return "both {$items[0]} and {$items[1]}";
        }

        return $this->inflector->joinWithConnector($items, 'and');
    }

    protected function buildNeitherNorList(array $items): string
    {
        if (count($items) === 2) {
            return "neither {$items[0]} nor {$items[1]}";
        }

        $lastItem = array_pop($items);

        return 'neither '.implode(', ', $items).', nor '.$lastItem;
    }

    protected function isJoinCondition(array $where): bool
    {
        if ($where['type'] === 'Column') {
            $first = $where['first'] ?? '';
            $second = $where['second'] ?? '';
            $operator = $where['operator'] ?? '';

            return $operator === '=' &&
                   str_contains($first, '.') &&
                   str_contains($second, '.') &&
                   (str_ends_with($first, '_id') || str_ends_with($second, '_id') ||
                    str_ends_with($first, '.id') || str_ends_with($second, '.id'));
        }

        if ($where['type'] === 'Basic') {
            $column = $where['column'] ?? '';
            $value = $where['value'] ?? '';

            return str_contains($column, '.') &&
                   (str_ends_with($column, '_id') || str_ends_with($column, '.id')) &&
                   (is_string($value) && str_contains($value, '.'));
        }

        return false;
    }

    protected function isComplexQueryExpression(array $where): bool
    {
        if ($where['type'] !== 'Expression') {
            return false;
        }

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

    protected function extractExpressionValue($expression): ?string
    {
        if (method_exists($expression, '__toString')) {
            return (string) $expression;
        }

        if (method_exists($expression, 'getValue')) {
            return (string) $expression->getValue();
        }

        return null;
    }

    protected function looksLikeDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?/', $value);
    }

    protected function areBothCarbonDates($start, $end): bool
    {
        return $this->looksLikeDate((string) $start) && $this->looksLikeDate((string) $end);
    }

    protected function detectMultiColumnPattern(array $where, array $nestedWheres): ?string
    {
        if (empty($nestedWheres)) {
            return null;
        }

        $firstWhere = $nestedWheres[0];
        if ($firstWhere['type'] !== 'Basic') {
            return null;
        }

        $operator = $firstWhere['operator'];
        $value = $firstWhere['value'];
        $columns = [];

        foreach ($nestedWheres as $nestedWhere) {
            if ($nestedWhere['type'] !== 'Basic' ||
                $nestedWhere['operator'] !== $operator ||
                $nestedWhere['value'] !== $value) {
                return null;
            }
            $columns[] = $this->inflector->humanizeFieldName($nestedWhere['column']);
        }

        if (count($columns) < 2) {
            return null;
        }

        $boolean = strtolower($where['boolean'] ?? 'and');
        $nestedBoolean = strtolower($nestedWheres[0]['boolean'] ?? 'and');

        if ($nestedBoolean === 'or') {
            if ($boolean === 'and not') {
                return $this->buildMultiColumnDescription('none', $columns, $operator, $value);
            } else {
                return $this->buildMultiColumnDescription('any', $columns, $operator, $value);
            }
        }

        if ($nestedBoolean === 'and') {
            return $this->buildMultiColumnDescription('all', $columns, $operator, $value);
        }

        return null;
    }

    protected function getValueDescription(string $operator, mixed $value): string
    {
        if ($operator === 'like') {
            $pattern = (string) $value;

            if (preg_match('/^%(.+)%$/', $pattern, $matches)) {
                return "containing '{$matches[1]}'";
            }
            if (preg_match('/^%(.+)$/', $pattern, $matches)) {
                return "ending with '{$matches[1]}'";
            }
            if (preg_match('/^(.+)%$/', $pattern, $matches)) {
                return "starting with '{$matches[1]}'";
            }

            return "matching {$this->formatValue($pattern)}";
        }

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
}
