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
        $this->operatorTranslator = new OperatorTranslator($this->inflector);
        $this->dateTimeHandler = new DateTimeHandler($this->inflector, $this->fieldTypeDetector);
    }

    abstract public function translate(array $wheres, $builder = null): array;

    protected function formatValue(mixed $value): string
    {
        return $this->inflector->formatValue($value);
    }

    protected function formatInValues(array $values): string
    {
        if (count($values) > 6) {
            $shown = array_map([$this, 'formatValue'], array_slice($values, 0, 5));

            return implode(', ', $shown).', or '.(count($values) - 5).' more';
        }

        $formatted = array_map([$this, 'formatValue'], $values);

        if (count($formatted) <= 2) {
            return $this->inflector->joinWithConnector($formatted, 'or');
        }

        return implode(', ', array_slice($formatted, 0, -1)).', or '.end($formatted);
    }

    protected function formatTimeValue(mixed $timeValue): string
    {
        if (is_string($timeValue) && preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $timeValue, $matches)) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];

            $ampm = $hour >= 12 ? 'PM' : 'AM';
            $displayHour = $hour === 0 ? 12 : ($hour > 12 ? $hour - 12 : $hour);

            return sprintf('%d:%02d %s', $displayHour, $minute, $ampm);
        }

        return "'{$timeValue}'";
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
            $verbByShape = null;
            if (preg_match('/^%(.+)%$/', $pattern, $matches)) {
                $verbByShape = ['contain', $matches[1]];
            } elseif (preg_match('/^(.+)%$/', $pattern, $matches)) {
                $verbByShape = ['start with', $matches[1]];
            } elseif (preg_match('/^%(.+)$/', $pattern, $matches)) {
                $verbByShape = ['end with', $matches[1]];
            }

            if ($verbByShape !== null) {
                [$verb, $content] = $verbByShape;
                $list = $this->buildColumnList($type, $columns);

                return "whose {$list} {$verb} '{$content}'";
            }
        }

        $valueDescription = $this->formatValue($value);
        $operatorText = $this->operatorTranslator->translateBasicOperator($operator);
        $list = $this->buildColumnList($type, $columns);

        return "whose {$list} {$operatorText} {$valueDescription}";
    }

    private function buildColumnList(string $type, array $columns): string
    {
        return match ($type) {
            'all' => $this->buildBothAndList($columns),
            'none' => $this->buildNeitherNorList($columns),
            default => $this->buildEitherOrList($columns),
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
            $first = is_string($where['first'] ?? null) ? $where['first'] : '';
            $second = is_string($where['second'] ?? null) ? $where['second'] : '';
            $operator = $where['operator'] ?? '';

            return $operator === '=' &&
                   str_contains($first, '.') &&
                   str_contains($second, '.') &&
                   (str_ends_with($first, '_id') || str_ends_with($second, '_id') ||
                    str_ends_with($first, '.id') || str_ends_with($second, '.id'));
        }

        if ($where['type'] === 'Basic') {
            $column = is_string($where['column'] ?? null) ? $where['column'] : '';
            $value = $where['value'] ?? '';

            return str_contains($column, '.') &&
                   (str_ends_with($column, '_id') || str_ends_with($column, '.id')) &&
                   (is_string($value) && str_contains($value, '.'));
        }

        return false;
    }

    protected function isComplexQueryExpression(array $where): bool
    {
        if (strtolower($where['type']) !== 'expression') {
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
        if (is_string($expression)) {
            return $expression;
        }

        if (! is_object($expression)) {
            return null;
        }

        if (method_exists($expression, '__toString')) {
            return (string) $expression;
        }

        try {
            $reflection = new \ReflectionProperty($expression, 'value');
            $value = $reflection->getValue($expression);

            return is_string($value) ? $value : (string) $value;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function looksLikeDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?/', $value);
    }

    protected function areBothCarbonDates($start, $end): bool
    {
        $stringable = fn ($value) => $value instanceof \DateTimeInterface
            || is_string($value)
            || (is_object($value) && method_exists($value, '__toString'));

        if (! $stringable($start) || ! $stringable($end)) {
            return false;
        }

        return ($start instanceof \DateTimeInterface || $this->looksLikeDate((string) $start))
            && ($end instanceof \DateTimeInterface || $this->looksLikeDate((string) $end));
    }

    protected function detectMultiColumnPattern(array $where, array $nestedWheres): ?string
    {
        if (empty($nestedWheres)) {
            return null;
        }

        $firstWhere = $nestedWheres[0];
        if ($firstWhere['type'] !== 'Basic' || ! is_string($firstWhere['column'] ?? null)) {
            return null;
        }

        $operator = $firstWhere['operator'];
        $value = $firstWhere['value'];

        // Boolean flags sharing a value is a coincidence, not a multi-column
        // search — leave those to per-condition phrasing.
        if (is_bool($value) || $value === null) {
            return null;
        }
        $columns = [];

        foreach ($nestedWheres as $nestedWhere) {
            if ($nestedWhere['type'] !== 'Basic' ||
                ! is_string($nestedWhere['column'] ?? null) ||
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
            if (str_contains($boolean, 'not')) {
                return $this->buildMultiColumnDescription('none', $columns, $operator, $value);
            }

            return $this->buildMultiColumnDescription('any', $columns, $operator, $value);
        }

        if ($nestedBoolean === 'and') {
            return $this->buildMultiColumnDescription('all', $columns, $operator, $value);
        }

        return null;
    }
}
