<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Translators;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use SulimanBenhalim\Prose\Support\Inflector;

class WhereTranslator
{
    public function __construct(
        private Inflector $inflector,
        private array $config,
        private ?ExistsTranslator $existsTranslator = null
    ) {}

    public function setExistsTranslator(ExistsTranslator $existsTranslator): void
    {
        $this->existsTranslator = $existsTranslator;
    }

    public function translate(array $wheres, $builder = null): array
    {
        $conditions = [];

        foreach ($wheres as $where) {
            $condition = $this->translateWhere($where, $builder);
            if ($condition) {
                $conditions[] = $condition;
            }

            if (count($conditions) >= $this->config['max_conditions']) {
                $conditions[] = $this->config['truncation_indicator'];
                break;
            }
        }

        return $conditions;
    }

    private function translateWhere(array $where, $builder = null): ?string
    {
        return match (strtolower($where['type'])) {
            'basic' => $this->translateBasic($where, $builder),
            'in' => $this->translateIn($where),
            'notin' => $this->translateNotIn($where),
            'null' => $this->translateNull($where),
            'notnull' => $this->translateNotNull($where),
            'between' => $this->translateBetween($where),
            'notbetween' => $this->translateNotBetween($where),
            'column' => $this->translateColumn($where),
            'date' => $this->translateDate($where, $builder),
            'year' => $this->translateYear($where),
            'month' => $this->translateMonth($where),
            'day' => $this->translateDay($where),
            'time' => $this->translateTime($where),
            'exists' => $this->translateExists($where),
            'notexists' => $this->translateNotExists($where),
            'jsoncontains' => $this->translateJsonContains($where),
            'nested' => $this->translateNested($where),
            'raw' => $this->translateRaw($where),
            default => null,
        };
    }

    private function translateBasic(array $where, $builder = null): string
    {
        if (is_object($where['column'])) {
            $expressionValue = $this->extractExpressionValue($where['column']);
            if ($expressionValue && $this->isSimpleColumnExpression($expressionValue)) {
                $column = $this->inflector->humanizeFieldName($expressionValue);
                $operator = $where['operator'];
                $value = $where['value'];
                $formattedValue = $this->formatValue($value);

                return $this->buildConditionText($column, $operator, $formattedValue);
            }

            if ($this->isWhereHasExpression($where)) {
                return $this->handleWhereHasExpression($where);
            }

            return $this->config['include_raw_fallback'] ? $this->config['raw_fallback_text'] : '';
        }

        $carbonOperation = $this->detectCarbonOperation($where['column'], $where['operator'], $where['value'], $builder);
        if ($carbonOperation) {
            return $carbonOperation;
        }

        $specialCondition = $this->translateLaravelAttribute($where['column'], $where['operator'], $where['value']);
        if ($specialCondition) {
            return $specialCondition;
        }

        $column = $this->inflector->humanizeFieldName($where['column']);
        $operator = $where['operator'];
        $value = $where['value'];
        $formattedValue = $this->formatValue($value);

        if ($this->inflector->detectBooleanField($where['column'], $builder) && in_array($operator, ['=', '!='])) {
            return $this->translateBooleanCondition($column, $operator, $value);
        }

        if ($this->isBooleanValue($value) && in_array($operator, ['=', '!='])) {
            return $this->translateBooleanCondition($column, $operator, $value);
        }
        if ($this->looksLikeDate((string) $value)) {
            return match ($operator) {
                '=' => "with {$column} on {$formattedValue}",
                '!=' => "with {$column} not on {$formattedValue}",
                '>' => "with {$column} after {$formattedValue}",
                '>=' => "with {$column} on or after {$formattedValue}",
                '<' => "with {$column} before {$formattedValue}",
                '<=' => "with {$column} on or before {$formattedValue}",
                'like' => $this->translateLikePattern($column, $value),
                'not like' => $this->translateNotLikePattern($column, $value),
                default => "with {$column} {$operator} {$formattedValue}",
            };
        }

        return match ($operator) {
            '=' => "with {$column} is {$formattedValue}",
            '!=' => "with {$column} not equal to {$formattedValue}",
            '>' => "with {$column} greater than {$formattedValue}",
            '>=' => "with {$column} greater than or equal to {$formattedValue}",
            '<' => "with {$column} less than {$formattedValue}",
            '<=' => "with {$column} less than or equal to {$formattedValue}",
            'like' => $this->translateLikePattern($column, $value),
            'not like' => $this->translateNotLikePattern($column, $value),
            default => "with {$column} {$operator} {$formattedValue}",
        };
    }

    private function translateBooleanCondition(string $column, string $operator, mixed $value): string
    {
        $isTrue = ($value === true || $value === 1 || $value === '1' || $value === 'true');
        $isPositiveCondition = ($operator === '=' && $isTrue) || ($operator === '!=' && ! $isTrue);

        return $this->buildNaturalBooleanPhrase($column, $isPositiveCondition);
    }

    private function translateIn(array $where): string
    {
        $column = $this->inflector->humanizeFieldName($where['column']);
        $values = is_array($where['values']) ? $where['values'] : [$where['values']];
        $formattedValues = $this->formatInValues($values);

        return "with {$column} being one of: {$formattedValues}";
    }

    private function formatInValues(array $values): string
    {
        if (count($values) === 1) {
            return $this->formatValue($values[0]);
        }

        $formatted = array_map([$this, 'formatValue'], $values);

        if (count($formatted) === 2) {
            return implode(' or ', $formatted);
        }

        $last = array_pop($formatted);

        return implode(', ', $formatted).', or '.$last;
    }

    private function translateNotIn(array $where): string
    {
        $column = $this->inflector->humanizeFieldName($where['column']);
        $values = is_array($where['values']) ? $where['values'] : [$where['values']];
        $formattedValues = $this->formatInValues($values);

        return "with {$column} not being any of: {$formattedValues}";
    }

    private function translateNull(array $where): string
    {
        $specialCondition = $this->translateLaravelAttributeNull($where['column'], true);
        if ($specialCondition) {
            return $specialCondition;
        }

        $column = $this->inflector->humanizeFieldName($where['column']);
        $article = $this->inflector->getProperArticle($column);

        return 'without '.($article ? "{$article} " : '').$column;
    }

    private function translateNotNull(array $where): string
    {
        $specialCondition = $this->translateLaravelAttributeNull($where['column'], false);
        if ($specialCondition) {
            return $specialCondition;
        }

        $column = $this->inflector->humanizeFieldName($where['column']);
        $article = $this->inflector->getProperArticle($column);

        return 'with '.($article ? "{$article} " : '').$column;
    }

    private function translateBetween(array $where): string
    {
        $column = $this->inflector->humanizeFieldName($where['column']);
        $values = $where['values'];
        $originalColumn = $where['column'];

        if ($this->areBothCarbonDates($values[0], $values[1])) {
            return $this->translateCarbonBetween($column, $originalColumn, $values[0], $values[1]);
        }

        $naturalRange = $this->detectNaturalTimeRange($column, $originalColumn, $values[0], $values[1]);
        if ($naturalRange) {
            return $naturalRange;
        }

        $min = $this->formatValue($values[0]);
        $max = $this->formatValue($values[1]);

        return "with {$column} ranging from {$min} to {$max}";
    }

    private function translateNotBetween(array $where): string
    {
        $column = $this->inflector->humanizeFieldName($where['column']);
        $values = $where['values'];
        $min = $this->formatValue($values[0]);
        $max = $this->formatValue($values[1]);

        return "with {$column} outside the range of {$min} to {$max}";
    }

    private function translateColumn(array $where): string
    {
        $first = $where['first'];
        $second = $where['second'];

        if (is_object($first) || is_object($second)) {
            $firstValue = is_object($first) ? $this->extractExpressionValue($first) : $first;
            $secondValue = is_object($second) ? $this->extractExpressionValue($second) : $second;

            if ($firstValue && $secondValue &&
                $this->isSimpleColumnExpression($firstValue) &&
                $this->isSimpleColumnExpression($secondValue)) {

                $firstColumn = $this->inflector->humanizeFieldName($firstValue);
                $secondColumn = $this->inflector->humanizeFieldName($secondValue);
                $operator = $where['operator'];

                return match ($operator) {
                    '=' => "where {$firstColumn} equals {$secondColumn}",
                    '!=' => "where {$firstColumn} does not equal {$secondColumn}",
                    '>' => "where {$firstColumn} is greater than {$secondColumn}",
                    '>=' => "where {$firstColumn} is greater than or equal to {$secondColumn}",
                    '<' => "where {$firstColumn} is less than {$secondColumn}",
                    '<=' => "where {$firstColumn} is less than or equal to {$secondColumn}",
                    default => "where {$firstColumn} {$operator} {$secondColumn}",
                };
            }

            return $this->config['include_raw_fallback'] ? $this->config['raw_fallback_text'] : '';
        }

        $first = $this->inflector->humanizeFieldName($where['first']);
        $second = $this->inflector->humanizeFieldName($where['second']);
        $operator = $where['operator'];

        return match ($operator) {
            '=' => "where {$first} equals {$second}",
            '!=' => "where {$first} does not equal {$second}",
            '>' => "where {$first} is greater than {$second}",
            '>=' => "where {$first} is greater than or equal to {$second}",
            '<' => "where {$first} is less than {$second}",
            '<=' => "where {$first} is less than or equal to {$second}",
            default => "where {$first} {$operator} {$second}",
        };
    }

    private function translateDate(array $where, $builder = null): string
    {

        $specialDateFunction = $this->detectSpecialDateFunction($where['column'], $where['operator'], $where['value'], $builder);
        if ($specialDateFunction) {
            return $specialDateFunction;
        }

        $carbonOperation = $this->detectCarbonOperation($where['column'], $where['operator'], $where['value'], $builder);
        if ($carbonOperation) {
            return $carbonOperation;
        }

        $specialCondition = $this->translateLaravelAttributeDate($where['column'], $where['operator'], $where['value']);
        if ($specialCondition) {
            return $specialCondition;
        }

        $column = $this->inflector->humanizeFieldName($where['column']);
        $operator = $where['operator'];
        $value = $this->formatValue($where['value']);

        $operatorText = match ($operator) {
            '=' => 'on',
            '>' => 'after',
            '>=' => 'on or after',
            '<' => 'before',
            '<=' => 'on or before',
            '!=' => 'not on',
            default => $operator
        };

        return "with {$column} {$operatorText} {$value}";
    }

    private function detectSpecialDateFunction(string $column, string $operator, mixed $value, $builder = null): ?string
    {

        if (! $this->looksLikeDate((string) $value)) {
            return null;
        }

        try {
            $carbon = Carbon::parse($value);
            $humanizedColumn = $this->inflector->humanizeFieldName($column, $builder);
            $now = Carbon::now();

            if ($carbon->isToday() && $operator === '=') {
                return $this->buildVerbPhrase($column, $humanizedColumn, 'today');
            }

            if ($carbon->isYesterday() && $operator === '=') {
                return $this->buildVerbPhrase($column, $humanizedColumn, 'yesterday');
            }

            if ($carbon->isTomorrow() && $operator === '=') {
                return $this->buildVerbPhrase($column, $humanizedColumn, 'tomorrow');
            }

            if (in_array($operator, ['>=', '>', '<=', '<'])) {
                $relativeTime = $this->inflector->humanizeDate($carbon);

                if ($relativeTime && ! $this->looksLikeDate($relativeTime)) {
                    $naturalPhrase = $this->buildNaturalCarbonPhrase($column, $operator, $relativeTime);
                    if ($naturalPhrase) {
                        return $naturalPhrase;
                    }

                    $beforePhrase = $this->convertToBeforePhrase($relativeTime, $column);

                    return match ($operator) {
                        '>' => "with {$humanizedColumn} {$relativeTime}",
                        '>=' => "with {$humanizedColumn} {$relativeTime}",
                        '<' => "with {$humanizedColumn} {$beforePhrase}",
                        '<=' => "with {$humanizedColumn} {$beforePhrase}", // @phpstan-ignore match.alwaysTrue
                        default => null,
                    };
                }
            }

        } catch (\Exception) {
            // Not a valid date
        }

        return null;
    }

    private function buildVerbPhrase(string $originalColumn, string $humanizedColumn, string $timePhrase): string
    {

        $verb = $this->extractVerbFromField($originalColumn);

        if ($verb) {
            return "{$verb} {$timePhrase}";
        }

        return "with {$humanizedColumn} {$timePhrase}";
    }

    private function extractVerbFromField(string $fieldName): ?string
    {
        if (preg_match('/^(.+?)_(?:date|at)$/', $fieldName, $matches)) {
            $verbPart = $matches[1];

            $words = explode('_', $verbPart);
            $lastWord = end($words);

            $verb = $lastWord;

            $pastTense = $this->getVerbPastTense($verb);

            if ($pastTense) {

                if (count($words) > 1) {
                    $prefix = implode(' ', array_slice($words, 0, -1));

                    return "{$prefix} {$pastTense}";
                }

                return $pastTense;
            }
        }

        return null;
    }

    private function getVerbPastTense(string $verb): string
    {

        return $this->inflector->convertVerbToPastTense($verb);
    }

    private function translateYear(array $where): string
    {
        $column = $this->inflector->humanizeFieldName($where['column']);
        $operator = $where['operator'] ?? '=';
        $value = $this->formatValue($where['value']);

        if ($operator === '=') {
            $laravelTimestamps = [
                'created at' => 'created',
                'updated at' => 'updated',
                'deleted at' => 'deleted',
            ];

            if (isset($laravelTimestamps[$column])) {
                return "{$laravelTimestamps[$column]} in {$value}";
            }

            if (str_contains($column, 'date')) {
                return "with {$column} in {$value}";
            }

            $verb = $this->extractAndConjugateVerb($column);
            if ($verb) {
                return "{$verb} in {$value}";
            }

            return "with {$column} in {$value}";
        }

        return "with {$column} year {$operator} {$value}";
    }

    private function extractAndConjugateVerb(string $column): ?string
    {

        $words = explode(' ', $column);
        $lastWord = end($words);

        $verb = $this->findVerbInText($column);
        if ($verb) {
            return $verb;
        }

        $verb = $this->findVerbInText($lastWord);
        if ($verb) {
            return $verb;
        }

        return null;
    }

    private function findVerbInText(string $text): ?string
    {
        $words = preg_split('/[\s_-]+/', strtolower($text));

        foreach ($words as $word) {
            if (strlen($word) >= 3) {
                $pastTense = $this->inflector->convertVerbToPastTense($word);
                if ($pastTense !== $word) {
                    return $pastTense;
                }
            }
        }

        return null;
    }

    private function translateMonth(array $where): string
    {
        $column = $this->inflector->humanizeFieldName($where['column'], null);
        $operator = $where['operator'] ?? '=';
        $monthValue = $where['value'];

        $formattedMonth = $this->formatMonthValue($monthValue);

        $operatorText = match ($operator) {
            '=' => 'in',
            '>' => 'after',
            '>=' => 'in or after',
            '<' => 'before',
            '<=' => 'in or before',
            '!=' => 'not in',
            default => $operator
        };

        return "with {$column} {$operatorText} {$formattedMonth}";
    }

    private function formatMonthValue(mixed $monthValue): string
    {
        if (is_numeric($monthValue)) {
            $monthNum = (int) $monthValue;
            if ($monthNum >= 1 && $monthNum <= 12) {
                $monthNames = [
                    1 => 'January', 2 => 'February', 3 => 'March',
                    4 => 'April', 5 => 'May', 6 => 'June',
                    7 => 'July', 8 => 'August', 9 => 'September',
                    10 => 'October', 11 => 'November', 12 => 'December',
                ];

                return $monthNames[$monthNum];
            }
        }

        return $this->formatValue($monthValue);
    }

    private function translateDay(array $where): string
    {
        $column = $this->inflector->humanizeFieldName($where['column']);
        $operator = $where['operator'] ?? '=';
        $value = $this->formatValue($where['value']);

        $operatorText = match ($operator) {
            '=' => 'on day',
            '>' => 'after day',
            '>=' => 'on or after day',
            '<' => 'before day',
            '<=' => 'on or before day',
            '!=' => 'not on day',
            default => "day {$operator}"
        };

        return "with {$column} {$operatorText} {$value}";
    }

    private function translateTime(array $where): string
    {
        $column = $this->inflector->humanizeFieldName($where['column'], null);
        $operator = $where['operator'] ?? '=';
        $timeValue = $where['value'];

        $formattedTime = $this->formatTimeValue($timeValue);

        $operatorText = match ($operator) {
            '=' => 'at exactly',
            '>' => 'after',
            '>=' => 'at or after',
            '<' => 'before',
            '<=' => 'at or before',
            '!=' => 'not at',
            default => $operator
        };

        return "with {$column} {$operatorText} {$formattedTime}";
    }

    private function formatTimeValue(mixed $timeValue): string
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

    private function translateExists(array $where): string
    {
        if ($this->existsTranslator) {
            return $this->existsTranslator->translateExists($where);
        }

        return $this->config['include_raw_fallback'] ? $this->config['raw_fallback_text'] : '';
    }

    private function translateNotExists(array $where): string
    {
        if ($this->existsTranslator) {
            return $this->existsTranslator->translateNotExists($where);
        }

        return $this->config['include_raw_fallback'] ? $this->config['raw_fallback_text'] : '';
    }

    private function translateNested(array $where): string
    {
        if (! isset($where['query']) || ! ($where['query'] instanceof Builder)) {
            return '';
        }

        $nestedWheres = $where['query']->wheres ?? [];

        if (empty($nestedWheres)) {
            return '';
        }

        $multiColumnPattern = $this->detectMultiColumnPattern($where, $nestedWheres);
        if ($multiColumnPattern) {
            return $multiColumnPattern;
        }

        $nestedConditions = $this->translate($nestedWheres);

        if (empty($nestedConditions)) {
            return '';
        }

        $boolean = strtolower($where['boolean'] ?? 'and');
        $connector = $this->config['connectors'][$boolean] ?? $boolean;

        return '('.$this->inflector->joinWithConnector($nestedConditions, $connector).')';
    }

    private function translateRaw(array $where): string
    {
        return $this->config['include_raw_fallback'] ? $this->config['raw_fallback_text'] : '';
    }

    private function buildNaturalBooleanPhrase(string $column, bool $isPositive): string
    {
        if (preg_match('/^has\s+(.+)/', $column, $matches)) {
            return $isPositive
                ? "that have {$matches[1]}"
                : "that don't have {$matches[1]}";
        }

        if (preg_match('/^is\s+(.+)/', $column, $matches)) {
            return $isPositive
                ? "that are {$matches[1]}"
                : "that are not {$matches[1]}";
        }

        if (preg_match('/^can\s+(.+)/', $column, $matches)) {
            return $isPositive
                ? "that can {$matches[1]}"
                : "that can't {$matches[1]}";
        }

        return $isPositive
            ? "with {$column}"
            : "without {$column}";
    }

    private function isBooleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        if (is_string($value)) {
            $normalizedValue = strtolower(trim($value));

            return in_array($normalizedValue, ['true', 'false']);
        }

        return false;
    }

    private function formatValue(mixed $value): string
    {
        if (is_string($value) && $this->looksLikeDate($value)) {
            return $this->inflector->humanizeDate($value);
        }

        return $this->inflector->formatValue($value);
    }

    private function looksLikeDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?/', $value);
    }

    private function extractExpressionValue($expression): ?string
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

    private function isSimpleColumnExpression(?string $value): bool
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

    private function buildConditionText(string $column, string $operator, mixed $value): string
    {
        return match ($operator) {
            '=' => "with {$column} is {$this->formatValue($value)}",
            '!=' => "with {$column} not equal to {$this->formatValue($value)}",
            '>' => "with {$column} greater than {$this->formatValue($value)}",
            '>=' => "with {$column} greater than or equal to {$this->formatValue($value)}",
            '<' => "with {$column} less than {$this->formatValue($value)}",
            '<=' => "with {$column} less than or equal to {$this->formatValue($value)}",
            'like' => $this->translateLikePattern($column, $value),
            'not like' => $this->translateNotLikePattern($column, $value),
            default => "with {$column} {$operator} {$this->formatValue($value)}",
        };
    }

    private function isWhereHasExpression(array $where): bool
    {

        $expressionValue = $this->extractExpressionValue($where['column']);

        if (! $expressionValue) {
            return false;
        }

        $patterns = [
            '/select\s+count\s*\(\s*\*\s*\)\s+from/i',
            '/exists\s*\(/i',
            '/select.*from.*where.*in\s*\(/i',
            '/group\s+by/i',
            '/having\s+count/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $expressionValue)) {
                return true;
            }
        }

        return false;
    }

    private function handleWhereHasExpression(array $where): string
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

    private function extractRelationshipNamesFromExpression(string $expression): array
    {
        $tableNames = [];

        if (preg_match_all('/from\s+["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?/i', $expression, $matches)) {
            foreach ($matches[1] as $tableName) {
                if (! in_array(strtolower($tableName), ['information_schema', 'mysql', 'performance_schema'])) {
                    $tableNames[] = $tableName;
                }
            }
        }

        if (preg_match_all('/["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?\.["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?/i', $expression, $matches)) {
            foreach ($matches[1] as $tableName) {
                if (! in_array($tableName, $tableNames) &&
                    ! in_array(strtolower($tableName), ['information_schema', 'mysql', 'performance_schema'])) {
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

    private function detectMultiColumnPattern(array $where, array $nestedWheres): ?string
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

        $columnList = $this->inflector->joinWithConnector($columns, 'or');
        $valueDescription = $this->getValueDescription($operator, $value);

        if ($nestedBoolean === 'or') {
            if ($boolean === 'and not') {
                return $this->buildNaturalMultiColumnDescription('none', $columns, $operator, $value);
            } else {
                return $this->buildNaturalMultiColumnDescription('any', $columns, $operator, $value);
            }
        }

        if ($nestedBoolean === 'and') {
            return $this->buildNaturalMultiColumnDescription('all', $columns, $operator, $value);
        }

        return null;
    }

    private function buildNaturalMultiColumnDescription(string $type, array $columns, string $operator, mixed $value): string
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

        $formattedValue = $this->formatValue($value);
        $operatorText = $this->translateOperator($operator);

        return match ($type) {
            'any' => "whose {$this->buildEitherOrList($columns)} {$operatorText} {$formattedValue}",
            'all' => "whose {$this->buildBothAndList($columns)} {$operatorText} {$formattedValue}",
            'none' => "whose {$this->buildNeitherNorList($columns)} {$operatorText} {$formattedValue}",
            default => "whose {$this->buildEitherOrList($columns)} {$operatorText} {$formattedValue}",
        };
    }

    private function translateOperator(string $operator): string
    {
        return match ($operator) {
            '=' => 'is',
            '!=' => 'is not',
            '>' => 'is greater than',
            '>=' => 'is greater than or equal to',
            '<' => 'is less than',
            '<=' => 'is less than or equal to',
            'like' => 'is like',
            'not like' => 'is not like',
            default => $operator,
        };
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
            return "{$columns[0]} and {$columns[1]}";
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

    private function getValueDescription(string $operator, mixed $value): string
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

    private function translateLikePattern(string $column, mixed $value): string
    {
        $pattern = (string) $value;

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

    private function translateNotLikePattern(string $column, mixed $value): string
    {
        $pattern = (string) $value;

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

    private function translateLaravelAttribute(string $column, string $operator, mixed $value): ?string
    {
        $formattedValue = $this->formatValue($value);

        return match ($column) {
            'created_at' => match ($operator) {
                '=' => "created on {$formattedValue}",
                '!=' => "not created on {$formattedValue}",
                '>' => "created after {$formattedValue}",
                '>=' => "created on or after {$formattedValue}",
                '<' => "created before {$formattedValue}",
                '<=' => "created on or before {$formattedValue}",
                default => null,
            },
            'updated_at' => match ($operator) {
                '=' => "last modified on {$formattedValue}",
                '!=' => "not modified on {$formattedValue}",
                '>' => "modified after {$formattedValue}",
                '>=' => "modified on or after {$formattedValue}",
                '<' => "modified before {$formattedValue}",
                '<=' => "modified on or before {$formattedValue}",
                default => null,
            },
            'deleted_at' => match ($operator) {
                '=' => "deleted on {$formattedValue}",
                '!=' => "not deleted on {$formattedValue}",
                '>' => "deleted after {$formattedValue}",
                '>=' => "deleted on or after {$formattedValue}",
                '<' => "deleted before {$formattedValue}",
                '<=' => "deleted on or before {$formattedValue}",
                default => null,
            },
            'email_verified_at' => match ($operator) {
                '=' => "verified their email on {$formattedValue}",
                '!=' => "didn't verify their email on {$formattedValue}",
                '>' => "verified their email after {$formattedValue}",
                '>=' => "verified their email on or after {$formattedValue}",
                '<' => "verified their email before {$formattedValue}",
                '<=' => "verified their email on or before {$formattedValue}",
                default => null,
            },
            'last_used_at' => match ($operator) {
                '=' => "last used on {$formattedValue}",
                '!=' => "not used on {$formattedValue}",
                '>' => "used after {$formattedValue}",
                '>=' => "used on or after {$formattedValue}",
                '<' => "used before {$formattedValue}",
                '<=' => "used on or before {$formattedValue}",
                default => null,
            },
            'password' => match ($operator) {
                '=' => "with password {$formattedValue}",
                '!=' => "without password {$formattedValue}",
                'like' => "with password containing {$formattedValue}",
                'not like' => "with password not containing {$formattedValue}",
                default => null,
            },
            'remember_token' => match ($operator) {
                '=' => "with remember token {$formattedValue}",
                '!=' => "without remember token {$formattedValue}",
                default => null,
            },
            'current_team_id' => match ($operator) {
                '=' => "currently in team {$formattedValue}",
                '!=' => "not in team {$formattedValue}",
                '>' => "in team with ID greater than {$formattedValue}",
                '<' => "in team with ID less than {$formattedValue}",
                default => null,
            },
            default => null,
        };
    }

    private function translateLaravelAttributeNull(string $column, bool $isNull): ?string
    {
        return match ($column) {
            'created_at' => $isNull ? 'not yet created' : 'that have been created',
            'updated_at' => $isNull ? 'never updated' : 'that have been updated',
            'deleted_at' => $isNull ? 'not deleted' : 'that have been deleted',
            'email_verified_at' => $isNull ? 'with unverified email' : 'with verified email',
            'last_used_at' => $isNull ? 'never used' : 'that have been used',
            'password' => $isNull ? 'without a password' : 'with a password',
            'remember_token' => $isNull ? 'without remember me token' : 'with remember me token',
            'current_team_id' => $isNull ? 'not assigned to any team' : 'assigned to a team',
            'profile_photo_path' => $isNull ? 'without a profile picture' : 'with a profile picture',
            'two_factor_secret' => $isNull ? 'without two-factor authentication' : 'with two-factor authentication enabled',
            'two_factor_recovery_codes' => $isNull ? 'without backup recovery codes' : 'with backup recovery codes',
            default => null,
        };
    }

    private function translateLaravelAttributeDate(string $column, string $operator, mixed $value): ?string
    {
        $formattedValue = $this->formatValue($value);

        return match ($column) {
            'created_at' => match ($operator) {
                '=' => "with creation date on {$formattedValue}",
                '!=' => "with creation date not on {$formattedValue}",
                '>' => "with creation date after {$formattedValue}",
                '>=' => "with creation date on or after {$formattedValue}",
                '<' => "with creation date before {$formattedValue}",
                '<=' => "with creation date on or before {$formattedValue}",
                default => null,
            },
            'updated_at' => match ($operator) {
                '=' => "with last modification date on {$formattedValue}",
                '!=' => "with last modification date not on {$formattedValue}",
                '>' => "with last modification date after {$formattedValue}",
                '>=' => "with last modification date on or after {$formattedValue}",
                '<' => "with last modification date before {$formattedValue}",
                '<=' => "with last modification date on or before {$formattedValue}",
                default => null,
            },
            'deleted_at' => match ($operator) {
                '=' => "with deletion date on {$formattedValue}",
                '!=' => "with deletion date not on {$formattedValue}",
                '>' => "with deletion date after {$formattedValue}",
                '>=' => "with deletion date on or after {$formattedValue}",
                '<' => "with deletion date before {$formattedValue}",
                '<=' => "with deletion date on or before {$formattedValue}",
                default => null,
            },
            'email_verified_at' => match ($operator) {
                '=' => "who verified their email on {$formattedValue}",
                '!=' => "who didn't verify their email on {$formattedValue}",
                '>' => "who verified their email after {$formattedValue}",
                '>=' => "who verified their email on or after {$formattedValue}",
                '<' => "who verified their email before {$formattedValue}",
                '<=' => "who verified their email on or before {$formattedValue}",
                default => null,
            },
            'last_used_at' => match ($operator) {
                '=' => "last used on {$formattedValue}",
                '!=' => "not used on {$formattedValue}",
                '>' => "used after {$formattedValue}",
                '>=' => "used on or after {$formattedValue}",
                '<' => "used before {$formattedValue}",
                '<=' => "used on or before {$formattedValue}",
                default => null,
            },
            default => null,
        };
    }

    private function detectCarbonOperation(string $column, string $operator, mixed $value, $builder = null): ?string
    {

        if (! $this->isDateColumn($column, $builder)) {
            return null;
        }

        if (! $this->looksLikeDate((string) $value)) {
            return null;
        }

        if ($this->isStaticDateString($value)) {

            return $this->handleStaticDateString($column, $operator, $value);
        }

        $relativeTime = $this->inflector->humanizeDate($value);

        if ($relativeTime && ! $this->looksLikeDate($relativeTime)) {

            $naturalPhrase = $this->buildNaturalCarbonPhrase($column, $operator, $relativeTime);
            if ($naturalPhrase) {
                return $naturalPhrase;
            }

            $humanizedColumn = $this->inflector->humanizeFieldName($column);
            $beforePhrase = $this->convertToBeforePhrase($relativeTime, $column);

            return match ($operator) {
                '>' => "with {$humanizedColumn} {$relativeTime}",
                '>=' => "with {$humanizedColumn} {$relativeTime}",
                '<' => "with {$humanizedColumn} {$beforePhrase}",
                '<=' => "with {$humanizedColumn} {$beforePhrase}",
                '=' => "with {$humanizedColumn} exactly {$relativeTime}",
                '!=' => "with {$humanizedColumn} not {$relativeTime}",
                default => null,
            };
        }

        return null;
    }

    private function isStaticDateString(mixed $value): bool
    {

        if (! is_string($value)) {
            return false;
        }

        if (! preg_match('/^\d{4}-\d{1,2}-\d{1,2}(\s+\d{1,2}:\d{2}:\d{2})?$/', $value)) {
            return false;
        }

        try {
            $carbon = Carbon::parse($value);

            if ($this->couldBeCarbonOperation($carbon)) {
                return false;
            }

            $diffInMinutes = abs($carbon->diffInMinutes(Carbon::now()));
            if ($diffInMinutes < 2) {
                return false;
            }

            return true;

        } catch (\Exception) {
            return true;
        }
    }

    private function couldBeCarbonOperation(Carbon $carbon): bool
    {
        $now = Carbon::now();

        $operations = [
            ['method' => 'subMinutes', 'range' => range(1, 59)],
            ['method' => 'addMinutes', 'range' => range(1, 59)],
            ['method' => 'subHours', 'range' => range(1, 23)],
            ['method' => 'addHours', 'range' => range(1, 23)],
            ['method' => 'subDays', 'range' => range(1, 30)],
            ['method' => 'addDays', 'range' => range(1, 30)],
            ['method' => 'subWeeks', 'range' => range(1, 12)],
            ['method' => 'addWeeks', 'range' => range(1, 12)],
            ['method' => 'subMonths', 'range' => range(1, 12)],
            ['method' => 'addMonths', 'range' => range(1, 12)],
            ['method' => 'subYears', 'range' => range(1, 5)],
            ['method' => 'addYears', 'range' => range(1, 5)],
        ];

        foreach ($operations as $operation) {
            foreach ($operation['range'] as $amount) {
                $testDate = $now->copy()->{$operation['method']}($amount);

                $tolerance = match ($operation['method']) {
                    'subMinutes', 'addMinutes' => 60,
                    'subHours', 'addHours' => 600,
                    'subDays', 'addDays' => 3600,
                    'subWeeks', 'addWeeks' => 7200,
                    'subMonths', 'addMonths', 'subYears', 'addYears' => 86400,
                };

                if (abs($carbon->diffInSeconds($testDate)) <= $tolerance) {
                    return true;
                }
            }
        }

        return false;
    }

    private function handleStaticDateString(string $column, string $operator, string $value): ?string
    {
        try {
            $carbon = Carbon::parse($value);
            $humanizedColumn = $this->inflector->humanizeFieldName($column);

            $formattedDate = $carbon->format('F j, Y');
            if ($carbon->format('H:i:s') !== '00:00:00') {
                $formattedDate .= ' at '.$carbon->format('g:i A');
            }

            return match ($operator) {
                '>' => "with {$humanizedColumn} after {$formattedDate}",
                '>=' => "with {$humanizedColumn} on or after {$formattedDate}",
                '<' => "with {$humanizedColumn} before {$formattedDate}",
                '<=' => "with {$humanizedColumn} on or before {$formattedDate}",
                '=' => "with {$humanizedColumn} on {$formattedDate}",
                '!=' => "with {$humanizedColumn} not on {$formattedDate}",
                default => null,
            };
        } catch (\Exception) {
            return null;
        }
    }

    private function isDateColumn(string $column, $builder = null): bool
    {
        if (in_array($column, ['created_at', 'updated_at', 'deleted_at', 'email_verified_at', 'last_used_at'])) {
            return true;
        }

        if ($builder && method_exists($builder, 'getModel')) {
            try {
                $model = $builder->getModel();

                $casts = $model->getCasts();
                if (isset($casts[$column])) {
                    $castType = strtolower($casts[$column]);

                    $castType = explode(':', $castType)[0];

                    if (in_array($castType, ['date', 'datetime', 'timestamp', 'time'])) {
                        return true;
                    }
                }

                $table = $model->getTable();
                $connection = $model->getConnection();
                $columnType = $connection->getDoctrineColumn($table, $column)->getType();

                if ($columnType) {
                    $typeName = $columnType->getName();
                    if (in_array($typeName, ['date', 'datetime', 'datetimetz', 'time', 'timestamp'])) {
                        return true;
                    }
                }
            } catch (\Exception $e) {

            }
        }

        return (bool) preg_match('/(date|time|at)$/i', $column);
    }

    private function convertToBeforePhrase(string $relativeTime, ?string $column = null): string
    {

        if (preg_match('/^in the next (.+)$/', $relativeTime)) {

            return $relativeTime;
        }

        if (preg_match('/^within the last (.+)$/', $relativeTime, $matches)) {
            $timeUnit = $matches[1];

            if ($this->isLaravelTimestampAttribute($column)) {
                return "older than {$timeUnit} ago";
            }

            return "more than {$timeUnit} ago";
        }

        if (in_array($relativeTime, ['yesterday', 'tomorrow', 'today'])) {
            return "before {$relativeTime}";
        }

        return "before {$relativeTime}";
    }

    private function isLaravelTimestampAttribute(?string $column): bool
    {
        if (! $column) {
            return false;
        }

        return in_array($column, ['created_at', 'updated_at', 'deleted_at', 'email_verified_at', 'last_used_at']);
    }

    private function buildNaturalCarbonPhrase(string $column, string $operator, string $relativeTime): ?string
    {
        $beforePhrase = $this->convertToBeforePhrase($relativeTime, $column);

        $laravelTimestampPhrase = $this->buildLaravelTimestampPhrase($column, $operator, $relativeTime, $beforePhrase);
        if ($laravelTimestampPhrase) {
            return $laravelTimestampPhrase;
        }

        $humanizedColumn = $this->inflector->humanizeFieldName($column);

        return match ($operator) {
            '>' => "with {$humanizedColumn} {$relativeTime}",
            '>=' => "with {$humanizedColumn} {$relativeTime}",
            '<' => "with {$humanizedColumn} {$beforePhrase}",
            '<=' => "with {$humanizedColumn} {$beforePhrase}",
            '=' => "with {$humanizedColumn} exactly {$relativeTime}",
            '!=' => "with {$humanizedColumn} not {$relativeTime}",
            default => null,
        };
    }

    private function buildLaravelTimestampPhrase(string $column, string $operator, string $relativeTime, string $beforePhrase): ?string
    {
        return match ($column) {
            'created_at' => match ($operator) {
                '>' => "created {$relativeTime}",
                '>=' => "created {$relativeTime}",
                '<' => "created {$beforePhrase}",
                '<=' => "created {$beforePhrase}",
                '=' => "created exactly {$relativeTime}",
                '!=' => "not created {$relativeTime}",
                default => null,
            },
            'updated_at' => match ($operator) {
                '>' => "updated {$relativeTime}",
                '>=' => "updated {$relativeTime}",
                '<' => "updated {$beforePhrase}",
                '<=' => "updated {$beforePhrase}",
                '=' => "updated exactly {$relativeTime}",
                '!=' => "not updated {$relativeTime}",
                default => null,
            },
            'deleted_at' => match ($operator) {
                '>' => "deleted {$relativeTime}",
                '>=' => "deleted {$relativeTime}",
                '<' => "deleted {$beforePhrase}",
                '<=' => "deleted {$beforePhrase}",
                '=' => "deleted exactly {$relativeTime}",
                '!=' => "not deleted {$relativeTime}",
                default => null,
            },
            'email_verified_at' => match ($operator) {
                '>' => "who verified their email {$relativeTime}",
                '>=' => "who verified their email {$relativeTime}",
                '<' => "who verified their email {$beforePhrase}",
                '<=' => "who verified their email {$beforePhrase}",
                '=' => "who verified their email exactly {$relativeTime}",
                '!=' => "who didn't verify their email {$relativeTime}",
                default => null,
            },
            'last_used_at' => match ($operator) {
                '>' => "last used {$relativeTime}",
                '>=' => "last used {$relativeTime}",
                '<' => "last used {$beforePhrase}",
                '<=' => "last used {$beforePhrase}",
                '=' => "last used exactly {$relativeTime}",
                '!=' => "not used {$relativeTime}",
                default => null,
            },
            default => null,
        };
    }

    private function translateJsonContains(array $where): string
    {
        $column = $where['column'];
        $value = $where['value'];
        $isNegated = $where['not'] ?? false;

        if (str_contains($column, '->')) {
            [$baseColumn, $jsonPath] = explode('->', $column, 2);
            $humanizedColumn = $this->inflector->humanizeFieldName($baseColumn);

            $readablePath = str_replace(['->', '"', "'"], [' ', '', ''], $jsonPath);
            $readablePath = $this->inflector->humanizeFieldName($readablePath);

            $formattedValue = $this->formatValue($value);

            if ($isNegated) {
                return "whose {$humanizedColumn} {$readablePath} does not contain {$formattedValue}";
            } else {
                return "whose {$humanizedColumn} {$readablePath} contains {$formattedValue}";
            }
        }

        $humanizedColumn = $this->inflector->humanizeFieldName($column);
        $formattedValue = $this->formatValue($value);

        if ($isNegated) {
            return "whose {$humanizedColumn} does not contain {$formattedValue}";
        } else {
            return "whose {$humanizedColumn} contains {$formattedValue}";
        }
    }

    private function areBothCarbonDates($value1, $value2): bool
    {
        return $value1 instanceof \Carbon\Carbon && $value2 instanceof \Carbon\Carbon;
    }

    private function translateCarbonBetween(string $column, string $originalColumn, \Carbon\Carbon $start, \Carbon\Carbon $end): string
    {
        $now = \Carbon\Carbon::now();

        if ($end->diffInYears($now) > 1) {
            $startFormatted = $this->inflector->humanizeDate($start);
            $endFormatted = $this->inflector->humanizeDate($end);

            return "with {$column} between {$startFormatted} and {$endFormatted}";
        }

        if ($start->isStartOfDay() && $end->isEndOfDay() && $start->isToday() && $end->isToday()) {
            return "with {$column} today";
        }

        if ($start->isStartOfDay() && $end->isEndOfDay() && $start->isYesterday() && $end->isYesterday()) {
            return "with {$column} yesterday";
        }

        if ($start->isStartOfWeek() && $end->isEndOfWeek() && $start->isSameWeek($now) && $end->isSameWeek($now)) {
            return "with {$column} this week";
        }

        if ($start->isStartOfWeek() && $end->isEndOfWeek() &&
            $start->isSameWeek($now->copy()->subWeek()) && $end->isSameWeek($now->copy()->subWeek())) {
            return "with {$column} last week";
        }

        if ($start->isStartOfMonth() && $end->isEndOfMonth() && $start->isSameMonth($now) && $end->isSameMonth($now)) {
            return "with {$column} this month";
        }

        $relativeRange = $this->detectRelativeRange($column, $originalColumn, $start, $end, $now);
        if ($relativeRange) {
            return $relativeRange;
        }

        $startFormatted = $this->formatDateForBetween($start, $now);
        $endFormatted = $this->formatDateForBetween($end, $now);

        return "with {$column} between {$startFormatted} and {$endFormatted}";
    }

    private function detectNaturalTimeRange(string $column, string $originalColumn, $start, $end): ?string
    {

        if (! $this->looksLikeDateColumn($originalColumn)) {
            return null;
        }

        if (! $this->looksLikeDate((string) $start) || ! $this->looksLikeDate((string) $end)) {
            return null;
        }

        try {
            $startCarbon = \Carbon\Carbon::parse($start);
            $endCarbon = \Carbon\Carbon::parse($end);

            return $this->translateCarbonBetween($column, $originalColumn, $startCarbon, $endCarbon);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function looksLikeDateColumn(string $column): bool
    {
        return (bool) preg_match('/(date|time|at|created|updated|deleted|born|scheduled|expires|valid)(_|$)/i', $column);
    }

    private function detectRelativeRange(string $column, string $originalColumn, \Carbon\Carbon $start, \Carbon\Carbon $end, \Carbon\Carbon $now): ?string
    {

        if (abs($end->diffInMinutes($now)) <= 5) {

            for ($days = 1; $days <= 365; $days++) {
                $testStart = $now->copy()->subDays($days);
                if (abs($start->diffInMinutes($testStart)) <= 60) {
                    if ($days === 1) {
                        return "with {$column} in the last day";
                    } elseif ($days === 7) {
                        return "with {$column} in the last week";
                    } elseif ($days === 30 || $days === 31) {
                        return "with {$column} in the last month";
                    } elseif ($days === 365) {
                        return "with {$column} in the last year";
                    } else {
                        return "with {$column} in the last {$days} days";
                    }
                }
            }

            for ($weeks = 1; $weeks <= 12; $weeks++) {
                $testStart = $now->copy()->subWeeks($weeks);
                if (abs($start->diffInHours($testStart)) <= 2) {
                    return $weeks === 1 ? "with {$column} in the last week" : "with {$column} in the last {$weeks} weeks";
                }
            }

            for ($months = 1; $months <= 12; $months++) {
                $testStart = $now->copy()->subMonths($months);
                if (abs($start->diffInDays($testStart)) <= 1) {
                    return $months === 1 ? "with {$column} in the last month" : "with {$column} in the last {$months} months";
                }
            }
        }

        return null;
    }

    private function formatDateForBetween(\Carbon\Carbon $date, \Carbon\Carbon $now): string
    {

        if ($date->diffInMonths($now) <= 3) {
            $relativeTime = $this->inflector->humanizeDate($date);

            if (! str_contains($relativeTime, 'within the last') && ! str_contains($relativeTime, 'days ago')) {
                return $relativeTime;
            }
        }

        if ($date->isSameYear($now)) {
            return $date->format('F j');
        } else {
            return $date->format('F j, Y');
        }
    }
}
