<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Translators;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use SulimanBenhalim\Prose\Support\BooleanFieldHandler;
use SulimanBenhalim\Prose\Support\Constants;
use SulimanBenhalim\Prose\Support\ExpressionHandler;
use SulimanBenhalim\Prose\Support\Inflector;
use SulimanBenhalim\Prose\Support\LaravelAttributeHandler;
use SulimanBenhalim\Prose\Support\LikePatternHandler;

class WhereTranslator extends BaseTranslator
{
    private BooleanFieldHandler $booleanHandler;

    private LikePatternHandler $likeHandler;

    private LaravelAttributeHandler $laravelAttributeHandler;

    private ExpressionHandler $expressionHandler;

    private ?ExistsTranslator $existsTranslator = null;

    public function __construct(
        Inflector $inflector,
        array $config,
        ?ExistsTranslator $existsTranslator = null
    ) {
        parent::__construct($inflector, $config);
        $this->booleanHandler = new BooleanFieldHandler($this->fieldTypeDetector);
        $this->likeHandler = new LikePatternHandler;
        $this->laravelAttributeHandler = new LaravelAttributeHandler;
        $this->expressionHandler = new ExpressionHandler($this->inflector);
        $this->existsTranslator = $existsTranslator;
    }

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
            'between' => $this->translateBetween($where, $builder),
            'notbetween' => $this->translateNotBetween($where, $builder),
            'column' => $this->translateColumn($where),
            'nested' => $this->translateNested($where, $builder),
            'exists' => $this->translateExists($where),
            'notexists' => $this->translateNotExists($where),
            'date' => $this->translateDate($where, $builder),
            'month' => $this->translateMonth($where),
            'day' => $this->translateDay($where),
            'year' => $this->translateYear($where),
            'time' => $this->translateTime($where),
            'expression' => $this->translateExpression($where, $builder),
            'jsoncontains' => $this->translateJsonContains($where),
            'raw' => $this->translateRaw($where),
            default => null,
        };
    }

    private function translateBasic(array $where, $builder = null): string
    {
        // Handle object expressions
        if (is_object($where['column'])) {
            return $this->handleObjectExpression($where, $builder);
        }

        $column = $where['column'];
        $operator = $where['operator'];
        $value = $where['value'];

        // Delegate to DateTimeHandler for Carbon operations
        $carbonResult = $this->dateTimeHandler->detectCarbonOperation($column, $operator, $value, $builder);
        if ($carbonResult) {
            return $carbonResult;
        }

        // Delegate to LaravelAttributeHandler for Laravel attributes
        $laravelResult = $this->laravelAttributeHandler->translateLaravelAttribute($column, $operator, $value);
        if ($laravelResult) {
            return $laravelResult;
        }

        $humanizedColumn = $this->inflector->humanizeFieldName($column);

        // Delegate to BooleanFieldHandler for boolean fields
        if ($this->booleanHandler->shouldUseBooleanTranslation($column, $value, $operator, $builder)) {
            return $this->booleanHandler->translateBooleanCondition($humanizedColumn, $operator, $value);
        }

        // Delegate to LikePatternHandler for LIKE patterns
        if (in_array($operator, ['like', 'not like'])) {
            return $this->likeHandler->translateLikePattern($humanizedColumn, $operator, $value);
        }

        // Handle date-like values using OperatorTranslator
        if ($this->looksLikeDate((string) $value)) {
            return $this->operatorTranslator->buildDateConditionPhrase($humanizedColumn, $operator, $value);
        }

        // Default: delegate to OperatorTranslator
        return $this->operatorTranslator->buildConditionPhrase($humanizedColumn, $operator, $value);
    }

    private function handleObjectExpression(array $where, $builder): string
    {
        $expressionValue = $this->expressionHandler->extractExpressionValue($where['column']);

        if ($expressionValue && $this->expressionHandler->isSimpleColumnExpression($expressionValue)) {
            $column = $this->inflector->humanizeFieldName($expressionValue);

            return $this->expressionHandler->buildConditionText($column, $where['operator'], $where['value']);
        }

        if ($this->expressionHandler->isWhereHasExpression($where)) {
            return $this->expressionHandler->handleWhereHasExpression($where);
        }

        return $this->config['include_raw_fallback'] ? $this->config['raw_fallback_text'] : '';
    }

    private function translateIn(array $where): string
    {
        $column = $this->inflector->humanizeFieldName($where['column']);
        $values = is_array($where['values']) ? $where['values'] : [$where['values']];
        $formattedValues = $this->formatInValues($values);

        return "with {$column} being one of: {$formattedValues}";
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
        // Delegate to LaravelAttributeHandler for special Laravel attributes
        $specialCondition = $this->laravelAttributeHandler->translateLaravelAttributeNull($where['column'], true);
        if ($specialCondition) {
            return $specialCondition;
        }

        $column = $this->inflector->humanizeFieldName($where['column']);
        $article = $this->inflector->getProperArticle($column);

        return 'without '.($article ? "{$article} " : '').$column;
    }

    private function translateNotNull(array $where): string
    {
        // Delegate to LaravelAttributeHandler for special Laravel attributes
        $specialCondition = $this->laravelAttributeHandler->translateLaravelAttributeNull($where['column'], false);
        if ($specialCondition) {
            return $specialCondition;
        }

        $column = $this->inflector->humanizeFieldName($where['column']);
        $article = $this->inflector->getProperArticle($column);

        return 'with '.($article ? "{$article} " : '').$column;
    }

    private function translateBetween(array $where, $builder = null): string
    {
        $column = $this->inflector->humanizeFieldName($where['column'], $builder);
        $values = $where['values'];
        $originalColumn = $where['column'];

        // Delegate to DateTimeHandler for Carbon dates
        if ($this->areBothCarbonDates($values[0], $values[1])) {
            try {
                $startCarbon = Carbon::parse($values[0]);
                $endCarbon = Carbon::parse($values[1]);

                return $this->dateTimeHandler->translateCarbonBetween($column, $originalColumn, $startCarbon, $endCarbon);
            } catch (\Exception $e) {
                // Fall through to basic between
            }
        }

        // Try natural time range detection
        if ($this->looksLikeDateColumn($originalColumn) &&
            $this->looksLikeDate((string) $values[0]) &&
            $this->looksLikeDate((string) $values[1])) {
            try {
                $startCarbon = Carbon::parse($values[0]);
                $endCarbon = Carbon::parse($values[1]);

                return $this->dateTimeHandler->translateCarbonBetween($column, $originalColumn, $startCarbon, $endCarbon);
            } catch (\Exception $e) {
                // Fall through to basic between
            }
        }

        $min = $this->formatValue($values[0]);
        $max = $this->formatValue($values[1]);

        return "with {$column} ranging from {$min} to {$max}";
    }

    private function translateNotBetween(array $where, $builder = null): string
    {
        $column = $this->inflector->humanizeFieldName($where['column'], $builder);
        $values = $where['values'];
        $min = $this->formatValue($values[0]);
        $max = $this->formatValue($values[1]);

        return "with {$column} outside the range of {$min} to {$max}";
    }

    private function translateColumn(array $where): string
    {
        $first = $where['first'];
        $second = $where['second'];
        $operator = $where['operator'];

        // Handle object expressions
        if (is_object($first) || is_object($second)) {
            $firstValue = is_object($first) ? $this->expressionHandler->extractExpressionValue($first) : $first;
            $secondValue = is_object($second) ? $this->expressionHandler->extractExpressionValue($second) : $second;

            if ($firstValue && $secondValue &&
                $this->expressionHandler->isSimpleColumnExpression($firstValue) &&
                $this->expressionHandler->isSimpleColumnExpression($secondValue)) {

                $firstColumn = $this->inflector->humanizeFieldName($firstValue);
                $secondColumn = $this->inflector->humanizeFieldName($secondValue);
                $operatorText = $this->operatorTranslator->translateBasicOperator($operator);

                return "where {$firstColumn} {$operatorText} {$secondColumn}";
            }

            return $this->config['include_raw_fallback'] ? $this->config['raw_fallback_text'] : '';
        }

        $first = $this->inflector->humanizeFieldName($first);
        $second = $this->inflector->humanizeFieldName($second);
        $operatorText = $this->operatorTranslator->translateBasicOperator($operator);

        return "where {$first} {$operatorText} {$second}";
    }

    private function translateNested(array $where, $builder = null): string
    {
        if (! isset($where['query']) || ! ($where['query'] instanceof Builder)) {
            return '';
        }

        $nestedWheres = $where['query']->wheres ?? [];

        if (empty($nestedWheres)) {
            return '';
        }

        // Delegate to BaseTranslator for multi-column pattern detection
        $multiColumnPattern = $this->detectMultiColumnPattern($where, $nestedWheres);
        if ($multiColumnPattern) {
            return $multiColumnPattern;
        }

        $nestedConditions = $this->translate($nestedWheres, $builder);

        if (empty($nestedConditions)) {
            return '';
        }

        $boolean = strtolower($where['boolean'] ?? 'and');
        $connector = $this->config['connectors'][$boolean] ?? $boolean;

        return '('.$this->inflector->joinWithConnector($nestedConditions, $connector).')';
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

    private function translateDate(array $where, $builder = null): string
    {
        $column = $where['column'];
        $operator = $where['operator'];
        $value = $where['value'];

        // Try special date function detection first (today, yesterday, etc.)
        if ($this->looksLikeDate((string) $value)) {
            $specialResult = $this->handleSpecialDateFunction($column, $operator, $value, $builder);
            if ($specialResult) {
                return $specialResult;
            }
        }

        // Delegate to DateTimeHandler for Carbon operations
        $carbonResult = $this->dateTimeHandler->detectCarbonOperation($column, $operator, $value, $builder);
        if ($carbonResult) {
            return $carbonResult;
        }

        // Delegate to LaravelAttributeHandler for Laravel date attributes
        $laravelResult = $this->laravelAttributeHandler->translateLaravelAttributeDate($column, $operator, $value);
        if ($laravelResult) {
            return $laravelResult;
        }

        // Default: use OperatorTranslator
        $humanizedColumn = $this->inflector->humanizeFieldName($column);

        return $this->operatorTranslator->buildDateConditionPhrase($humanizedColumn, $operator, $value);
    }

    private function translateMonth(array $where): string
    {
        $column = $this->inflector->humanizeFieldName($where['column'], null);
        $operator = $where['operator'] ?? '=';
        $monthValue = $where['value'];

        $formattedMonth = $this->formatMonthValue($monthValue);
        $operatorText = $this->operatorTranslator->translateMonthOperator($operator);

        return "with {$column} {$operatorText} {$formattedMonth}";
    }

    private function translateDay(array $where): string
    {
        $column = $this->inflector->humanizeFieldName($where['column']);
        $operator = $where['operator'] ?? '=';
        $value = $this->formatValue($where['value']);

        $operatorText = $this->operatorTranslator->translateDayOperator($operator);

        return "with {$column} {$operatorText} {$value}";
    }

    private function translateYear(array $where): string
    {
        $column = $this->inflector->humanizeFieldName($where['column']);
        $operator = $where['operator'] ?? '=';
        $value = $this->formatValue($where['value']);

        if ($operator === '=') {
            // Handle Laravel timestamp shortcuts
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

            // Try verb extraction
            $verb = $this->extractAndConjugateVerb($column);
            if ($verb) {
                return "{$verb} in {$value}";
            }

            return "with {$column} in {$value}";
        }

        return "with {$column} year {$operator} {$value}";
    }

    private function translateTime(array $where): string
    {
        $column = $this->inflector->humanizeFieldName($where['column'], null);
        $operator = $where['operator'] ?? '=';
        $timeValue = $where['value'];

        $formattedTime = $this->formatTimeValue($timeValue);
        $operatorText = $this->operatorTranslator->translateTimeOperator($operator);

        return "with {$column} {$operatorText} {$formattedTime}";
    }

    private function translateExpression(array $where, $builder = null): string
    {
        // Delegate to BaseTranslator for complex query expression check
        if ($this->isComplexQueryExpression($where)) {
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

                if ($this->isPositiveRelationshipCondition($where['operator'] ?? '>', $where['value'] ?? 1)) {
                    return "who have {$relationships}";
                } else {
                    return "who don't have {$relationships}";
                }
            }
        }

        return $this->config['include_raw_fallback'] ? $this->config['raw_fallback_text'] : '';
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

    private function translateRaw(array $where): string
    {
        return $this->config['include_raw_fallback'] ? $this->config['raw_fallback_text'] : '';
    }

    // Minimal helper methods that aren't worth extracting

    private function handleSpecialDateFunction(string $column, string $operator, mixed $value, $builder = null): ?string
    {
        try {
            $carbon = Carbon::parse($value);
            $humanizedColumn = $this->inflector->humanizeFieldName($column, $builder);

            if ($carbon->isToday() && $operator === '=') {
                return $this->buildVerbPhrase($column, $humanizedColumn, 'today');
            }

            if ($carbon->isYesterday() && $operator === '=') {
                return $this->buildVerbPhrase($column, $humanizedColumn, 'yesterday');
            }

            if ($carbon->isTomorrow() && $operator === '=') {
                return $this->buildVerbPhrase($column, $humanizedColumn, 'tomorrow');
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
            $pastTense = $this->inflector->convertVerbToPastTense($lastWord);

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

    private function extractAndConjugateVerb(string $column): ?string
    {
        $words = preg_split('/[\s_-]+/', strtolower($column));

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

    private function looksLikeDateColumn(string $column): bool
    {
        return (bool) preg_match('/(date|time|at|created|updated|deleted|born|scheduled|expires|valid)(_|$)/i', $column);
    }

    // These methods delegate to BaseTranslator but are kept here for clarity
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
}
