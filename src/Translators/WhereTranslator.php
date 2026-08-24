<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Translators;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use SulimanBenhalim\Prose\Support\BooleanFieldHandler;
use SulimanBenhalim\Prose\Support\ExpressionHandler;
use SulimanBenhalim\Prose\Support\Inflector;
use SulimanBenhalim\Prose\Support\LaravelAttributeHandler;
use SulimanBenhalim\Prose\Support\LikePatternHandler;

class WhereTranslator extends BaseTranslator
{
    /** Sentinel appended when a translated where already expresses its own negation. */
    private const NEGATION_APPLIED = "\0NEGATION_APPLIED";

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
        $this->booleanHandler = new BooleanFieldHandler($this->fieldTypeDetector, $inflector);
        $this->likeHandler = new LikePatternHandler;
        $this->laravelAttributeHandler = new LaravelAttributeHandler;
        $this->expressionHandler = new ExpressionHandler($this->inflector);
        $this->existsTranslator = $existsTranslator;
    }

    public function setExistsTranslator(ExistsTranslator $existsTranslator): void
    {
        $this->existsTranslator = $existsTranslator;
    }

    /**
     * Translate a list of wheres into one sentence fragment, honoring each
     * where's and/or/not connective.
     */
    public function translateToSentence(array $wheres, $builder = null): string
    {
        return $this->joinConditionPairs($this->translateWithBooleans($wheres, $builder));
    }

    /** @return string[] condition texts only (legacy shape, AND-joined by callers) */
    public function translate(array $wheres, $builder = null): array
    {
        return array_column($this->translateWithBooleans($wheres, $builder), 'text');
    }

    /** @return array<int, array{text: string, boolean: string}> */
    public function translateWithBooleans(array $wheres, $builder = null): array
    {
        $conditions = [];

        foreach ($wheres as $where) {
            $condition = $this->translateWhere($where, $builder);
            if ($condition === null || $condition === '') {
                continue;
            }

            $boolean = strtolower($where['boolean'] ?? 'and');

            // The translated text already carries its own negation
            if (str_ends_with($condition, self::NEGATION_APPLIED)) {
                $condition = substr($condition, 0, -strlen(self::NEGATION_APPLIED));
                $boolean = str_starts_with($boolean, 'or') ? 'or' : 'and';
            }

            $conditions[] = [
                'text' => $condition,
                'boolean' => $boolean,
            ];

            if (count($conditions) >= $this->config['max_conditions']) {
                $conditions[] = ['text' => $this->config['truncation_indicator'], 'boolean' => 'and'];
                break;
            }
        }

        return $conditions;
    }

    /** @param array<int, array{text: string, boolean: string}> $pairs */
    public function joinConditionPairs(array $pairs): string
    {
        if (empty($pairs)) {
            return '';
        }

        $onlyPlainAnd = true;
        foreach ($pairs as $i => $pair) {
            if ($i > 0 && $pair['boolean'] !== 'and') {
                $onlyPlainAnd = false;
                break;
            }
        }

        if ($onlyPlainAnd && ! str_contains($pairs[0]['boolean'], 'not')) {
            return $this->inflector->joinWithConnector(array_column($pairs, 'text'), $this->config['connectors']['and'] ?? 'and');
        }

        $sentence = '';
        foreach ($pairs as $i => $pair) {
            $boolean = $pair['boolean'];

            if ($i === 0) {
                $sentence = str_contains($boolean, 'not') ? 'not '.$pair['text'] : $pair['text'];

                continue;
            }

            $connector = str_starts_with($boolean, 'or') ? ($this->config['connectors']['or'] ?? 'or') : ($this->config['connectors']['and'] ?? 'and');
            if (str_contains($boolean, 'not')) {
                $connector .= ' not';
            }

            $sentence .= " {$connector} {$pair['text']}";
        }

        return $sentence;
    }

    private function translateWhere(array $where, $builder = null): ?string
    {
        return match (strtolower($where['type'])) {
            'basic' => $this->translateBasic($where, $builder),
            'like' => $this->translateLikeType($where, $builder),
            'in' => $this->translateIn($where, $builder),
            'notin' => $this->translateNotIn($where, $builder),
            'inraw' => $this->translateIn($where, $builder),
            'notinraw' => $this->translateNotIn($where, $builder),
            'null' => $this->translateNull($where, $builder),
            'notnull' => $this->translateNotNull($where, $builder),
            'between' => $this->translateBetween($where, $builder),
            'notbetween' => $this->translateNotBetween($where, $builder),
            'betweencolumns' => $this->translateBetweenColumns($where, $builder),
            'column' => $this->translateColumn($where, $builder),
            'nested' => $this->translateNested($where, $builder),
            'exists' => $this->translateExists($where),
            'notexists' => $this->translateNotExists($where),
            'date' => $this->translateDate($where, $builder),
            'month' => $this->translateMonth($where, $builder),
            'day' => $this->translateDay($where, $builder),
            'year' => $this->translateYear($where, $builder),
            'time' => $this->translateTime($where),
            'expression' => $this->translateExpression($where, $builder),
            'jsoncontains' => $this->translateJsonContains($where),
            'jsonlength' => $this->translateJsonLength($where),
            'fulltext' => $this->translateFullText($where),
            'raw' => $this->translateRaw($where),
            default => null,
        };
    }

    private function translateBasic(array $where, $builder = null): string
    {
        if (is_object($where['column'])) {
            return $this->handleObjectExpression($where, $builder);
        }

        $column = $this->resolveDottedColumn($where['column'], $builder, $related);
        $operator = strtolower($where['operator']);
        $value = $where['value'];

        // Raw expression values ("price > DB::raw('cost * 2')")
        if (is_object($value) && ! ($value instanceof \DateTimeInterface)) {
            return $this->translateExpressionValue($column, $operator, $value, $builder, $related);
        }

        $condition = $this->buildBasicCondition($column, $operator, $value, $builder);

        return $this->applyRelatedContext($condition, $related);
    }

    private function buildBasicCondition(string $column, string $operator, mixed $value, $builder = null): string
    {
        // Datetime values get natural relative/calendar phrasing
        $carbonResult = $this->dateTimeHandler->describeDateCondition($column, $operator, $value, $builder);
        if ($carbonResult) {
            return $carbonResult;
        }

        // Laravel-specific attributes (password, remember_token, ...)
        $laravelResult = $this->laravelAttributeHandler->translateLaravelAttribute($column, $operator, $value);
        if ($laravelResult) {
            return $laravelResult;
        }

        $humanizedColumn = $this->inflector->humanizeFieldName($column, $builder);

        if ($this->booleanHandler->shouldUseBooleanTranslation($column, $value, $operator, $builder)) {
            return $this->booleanHandler->translateBooleanCondition($humanizedColumn, $operator, $value);
        }

        if (in_array($operator, ['like', 'not like', 'ilike', 'not ilike'])) {
            $likeOperator = str_contains($operator, 'not') ? 'not like' : 'like';

            return $this->likeHandler->translateLikePattern($humanizedColumn, $likeOperator, $value);
        }

        return $this->operatorTranslator->buildConditionPhrase($humanizedColumn, $operator, $value);
    }

    private function translateLikeType(array $where, $builder = null): string
    {
        $column = $this->resolveDottedColumn($where['column'], $builder, $related);
        $humanizedColumn = $this->inflector->humanizeFieldName($column, $builder);
        $operator = ($where['not'] ?? false) ? 'not like' : 'like';

        $condition = $this->likeHandler->translateLikePattern($humanizedColumn, $operator, $where['value']);

        return $this->applyRelatedContext($condition, $related);
    }

    private function translateExpressionValue(string $column, string $operator, object $value, $builder, ?string $related): string
    {
        $expressionValue = $this->expressionHandler->extractExpressionValue($value);

        if ($expressionValue !== null && $this->expressionHandler->isSimpleColumnExpression($expressionValue)) {
            $humanizedColumn = $this->inflector->humanizeFieldName($column, $builder);
            $humanizedOther = $this->inflector->humanizeFieldName($expressionValue, $builder);
            $operatorText = $this->operatorTranslator->translateColumnOperator($operator);

            return $this->applyRelatedContext("where {$humanizedColumn} {$operatorText} {$humanizedOther}", $related);
        }

        return $this->config['include_raw_fallback'] ? $this->config['raw_fallback_text'] : '';
    }

    private function handleObjectExpression(array $where, $builder): string
    {
        $expressionValue = $this->expressionHandler->extractExpressionValue($where['column']);

        if ($expressionValue && $this->expressionHandler->isSimpleColumnExpression($expressionValue)) {
            $column = $this->inflector->humanizeFieldName($expressionValue, $builder);

            return $this->expressionHandler->buildConditionText($column, $where['operator'], $where['value']);
        }

        if ($this->expressionHandler->isWhereHasExpression($where)) {
            return $this->expressionHandler->handleWhereHasExpression($where);
        }

        return $this->config['include_raw_fallback'] ? $this->config['raw_fallback_text'] : '';
    }

    private function translateIn(array $where, $builder = null): string
    {
        $column = $this->resolveDottedColumn($where['column'], $builder, $related);
        $humanized = $this->inflector->humanizeFieldName($column, $builder);
        $values = is_array($where['values']) ? array_values($where['values']) : [$where['values']];

        if (count($values) === 1) {
            return $this->applyRelatedContext(
                $this->operatorTranslator->buildConditionPhrase($humanized, '=', $values[0]), $related
            );
        }

        $formattedValues = $this->formatInValues($values);

        return $this->applyRelatedContext("with {$humanized} being one of: {$formattedValues}", $related);
    }

    private function translateNotIn(array $where, $builder = null): string
    {
        $column = $this->resolveDottedColumn($where['column'], $builder, $related);
        $humanized = $this->inflector->humanizeFieldName($column, $builder);
        $values = is_array($where['values']) ? array_values($where['values']) : [$where['values']];

        if (count($values) === 1) {
            return $this->applyRelatedContext(
                $this->operatorTranslator->buildConditionPhrase($humanized, '!=', $values[0]), $related
            );
        }

        $formattedValues = $this->formatInValues($values);

        return $this->applyRelatedContext("with {$humanized} not being any of: {$formattedValues}", $related);
    }

    private function translateNull(array $where, $builder = null): string
    {
        $column = is_string($where['column']) ? $where['column'] : '';

        $specialCondition = $this->laravelAttributeHandler->translateLaravelAttributeNull($column, true);
        if ($specialCondition) {
            return $specialCondition;
        }

        if ($column === 'last_login_at') {
            return 'who have never logged in';
        }

        if (str_ends_with($column, '_by')) {
            $verb = str_replace('_', ' ', substr($column, 0, -3));

            return "not {$verb} by anyone";
        }

        $column = $this->stripForeignKeySuffix($column);
        $humanized = $this->inflector->humanizeFieldName($column, $builder);
        $article = $this->inflector->getProperArticle($humanized);

        return 'without '.($article ? "{$article} " : '').$humanized;
    }

    private function translateNotNull(array $where, $builder = null): string
    {
        $column = is_string($where['column']) ? $where['column'] : '';

        $specialCondition = $this->laravelAttributeHandler->translateLaravelAttributeNull($column, false);
        if ($specialCondition) {
            return $specialCondition;
        }

        if (str_ends_with($column, '_by')) {
            $verb = str_replace('_', ' ', substr($column, 0, -3));

            return "{$verb} by someone";
        }

        $column = $this->stripForeignKeySuffix($column);
        $humanized = $this->inflector->humanizeFieldName($column, $builder);
        $article = $this->inflector->getProperArticle($humanized);

        return 'with '.($article ? "{$article} " : '').$humanized;
    }

    private function stripForeignKeySuffix(string $column): string
    {
        if (str_ends_with($column, '_id') && strlen($column) > 3) {
            return substr($column, 0, -3);
        }

        return $column;
    }

    private function translateBetween(array $where, $builder = null): string
    {
        $column = $this->resolveDottedColumn($where['column'], $builder, $related);
        $humanized = $this->inflector->humanizeFieldName($column, $builder);
        $values = array_values($where['values']);
        $negated = (bool) ($where['not'] ?? false);

        // Datetime ranges
        if ($this->fieldTypeDetector->isDateField($column, $builder) || $this->areBothCarbonDates($values[0], $values[1])) {
            $start = $this->toCarbonOrNull($values[0]);
            $end = $this->toCarbonOrNull($values[1]);

            if ($start !== null && $end !== null) {
                $condition = $this->dateTimeHandler->translateCarbonBetween($humanized, $column, $start, $end);

                if ($negated) {
                    $condition = preg_match('/^(with|whose) /', $condition)
                        ? preg_replace('/^(with|whose) (.*?) /', '$1 $2 not ', $condition, 1)
                        : "not {$condition}";
                }

                return $this->applyRelatedContext($condition, $related);
            }
        }

        $min = $this->formatValue($values[0]);
        $max = $this->formatValue($values[1]);

        $condition = $negated
            ? "with {$humanized} outside the range {$min} to {$max}"
            : "with {$humanized} ranging from {$min} to {$max}";

        return $this->applyRelatedContext($condition, $related);
    }

    private function translateNotBetween(array $where, $builder = null): string
    {
        $where['not'] = true;

        return $this->translateBetween($where, $builder);
    }

    private function translateBetweenColumns(array $where, $builder = null): string
    {
        $column = $this->inflector->humanizeFieldName((string) $where['column'], $builder);
        $values = array_values($where['values']);
        $low = $this->inflector->humanizeFieldName((string) $values[0], $builder);
        $high = $this->inflector->humanizeFieldName((string) $values[1], $builder);
        $negated = (bool) ($where['not'] ?? false);

        return $negated
            ? "with {$column} outside the range of {$low} to {$high}"
            : "with {$column} between {$low} and {$high}";
    }

    private function translateColumn(array $where, $builder = null): string
    {
        $first = $where['first'];
        $second = $where['second'];
        $operator = $where['operator'];

        if (is_object($first) || is_object($second)) {
            $firstValue = is_object($first) ? $this->expressionHandler->extractExpressionValue($first) : $first;
            $secondValue = is_object($second) ? $this->expressionHandler->extractExpressionValue($second) : $second;

            if (! $firstValue || ! $secondValue ||
                ! $this->expressionHandler->isSimpleColumnExpression($firstValue) ||
                ! $this->expressionHandler->isSimpleColumnExpression($secondValue)) {
                return $this->config['include_raw_fallback'] ? $this->config['raw_fallback_text'] : '';
            }

            $first = $firstValue;
            $second = $secondValue;
        }

        $datesInvolved = $this->fieldTypeDetector->isDateField($first, $builder)
            && $this->fieldTypeDetector->isDateField($second, $builder);

        $firstHuman = $this->humanizePossiblyDottedColumn($first, $builder);
        $secondHuman = $this->humanizePossiblyDottedColumn($second, $builder);
        $operatorText = $this->operatorTranslator->translateColumnOperator($operator, $datesInvolved);

        return "where {$firstHuman} {$operatorText} {$secondHuman}";
    }

    private function translateNested(array $where, $builder = null): ?string
    {
        if (! isset($where['query']) || ! ($where['query'] instanceof Builder)) {
            return null;
        }

        $nestedWheres = $where['query']->wheres ?? [];

        if (empty($nestedWheres)) {
            return null;
        }

        $negated = str_contains(strtolower($where['boolean'] ?? 'and'), 'not');

        // whereAny / whereAll / whereNone produce uniform nested comparisons.
        // The "none" flavor embeds its own negation, so the outer "not" is spent.
        $multiColumnPattern = $this->detectMultiColumnPattern($where, $nestedWheres);
        if ($multiColumnPattern) {
            return $negated ? $multiColumnPattern.self::NEGATION_APPLIED : $multiColumnPattern;
        }

        // whereNot() around a single simple condition reads best inverted:
        // "whose status is not 'active'" instead of "not (whose status is 'active')"
        if ($negated && count($nestedWheres) === 1) {
            $inverted = $this->invertWhere($nestedWheres[0]);
            if ($inverted !== null) {
                $text = $this->translateWhere($inverted, $builder);
                if ($text) {
                    return $text.self::NEGATION_APPLIED;
                }
            }
        }

        $inner = $this->translateToSentence($nestedWheres, $builder);

        if ($inner === '') {
            return null;
        }

        if (count($nestedWheres) === 1) {
            return $inner;
        }

        return "({$inner})";
    }

    private function invertWhere(array $where): ?array
    {
        $type = strtolower($where['type']);

        if ($type === 'basic') {
            $map = ['=' => '!=', '!=' => '=', '<>' => '=', '>' => '<=', '<' => '>=', '>=' => '<', '<=' => '>', 'like' => 'not like', 'not like' => 'like'];
            if (isset($map[strtolower($where['operator'])])) {
                $where['operator'] = $map[strtolower($where['operator'])];
                $where['boolean'] = 'and';

                return $where;
            }

            return null;
        }

        $typeSwap = ['in' => 'NotIn', 'notin' => 'In', 'null' => 'NotNull', 'notnull' => 'Null'];
        if (isset($typeSwap[$type])) {
            $where['type'] = $typeSwap[$type];
            $where['boolean'] = 'and';

            return $where;
        }

        if ($type === 'between') {
            $where['not'] = ! ($where['not'] ?? false);
            $where['boolean'] = 'and';

            return $where;
        }

        return null;
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
        $column = $this->resolveDottedColumn($where['column'], $builder, $related);
        $operator = $where['operator'];
        $value = $where['value'];

        $result = $this->dateTimeHandler->describeDateCondition($column, $operator, $value, $builder, dateOnly: true);
        if ($result) {
            return $this->applyRelatedContext($result, $related);
        }

        $laravelResult = $this->laravelAttributeHandler->translateLaravelAttributeDate($column, $operator, $value);
        if ($laravelResult) {
            return $laravelResult;
        }

        $humanizedColumn = $this->inflector->humanizeFieldName($column, $builder);

        return $this->applyRelatedContext(
            $this->operatorTranslator->buildDateConditionPhrase($humanizedColumn, $operator, $value), $related
        );
    }

    private function translateMonth(array $where, $builder = null): string
    {
        $column = $this->inflector->humanizeFieldName($where['column'], $builder);
        $operator = $where['operator'] ?? '=';
        $month = $this->formatMonthValue($where['value']);

        return match ($operator) {
            '=' => "with {$column} in {$month}",
            '!=', '<>' => "with {$column} not in {$month}",
            '>' => "with {$column} after {$month}",
            '>=' => "with {$column} in {$month} or later",
            '<' => "with {$column} before {$month}",
            '<=' => "with {$column} in {$month} or earlier",
            default => "with {$column} in {$month}",
        };
    }

    private function translateDay(array $where, $builder = null): string
    {
        $column = $this->inflector->humanizeFieldName($where['column'], $builder);
        $operator = $where['operator'] ?? '=';
        $day = $this->ordinal((int) $where['value']);

        $operatorText = $this->operatorTranslator->translateDayOperator($operator);

        return "with {$column} {$operatorText} {$day} of the month";
    }

    private function ordinal(int $number): string
    {
        if (in_array($number % 100, [11, 12, 13], true)) {
            return $number.'th';
        }

        return $number.match ($number % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    private function translateYear(array $where, $builder = null): string
    {
        $column = $where['column'];
        $operator = $where['operator'] ?? '=';
        $year = (string) $where['value'];
        $humanized = $this->inflector->humanizeFieldName($column, $builder);

        $phrase = match ($operator) {
            '=' => "in {$year}",
            '!=', '<>' => "not in {$year}",
            '>' => "after {$year}",
            '>=' => "in {$year} or later",
            '<' => "before {$year}",
            '<=' => "in {$year} or earlier",
            default => "in {$year}",
        };

        $verb = $this->extractPastParticiple($column);
        if ($verb !== null) {
            return "{$verb} {$phrase}";
        }

        return "with {$humanized} {$phrase}";
    }

    private function extractPastParticiple(string $column): ?string
    {
        if (! preg_match('/^(.+?)_(?:date|at|datetime)$/', $column, $matches)) {
            return null;
        }

        $words = explode('_', $matches[1]);
        $lastWord = end($words);

        if (strlen($lastWord) <= 3 || ! str_ends_with($lastWord, 'ed')) {
            return null;
        }

        $prefix = count($words) > 1 ? implode(' ', array_slice($words, 0, -1)).' ' : '';

        return $prefix.$lastWord;
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
        if ($this->isComplexQueryExpression($where)) {
            return $this->expressionHandler->handleWhereHasExpression($where);
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

            return $isNegated
                ? "whose {$humanizedColumn} {$readablePath} does not contain {$formattedValue}"
                : "whose {$humanizedColumn} {$readablePath} contains {$formattedValue}";
        }

        $humanizedColumn = $this->inflector->humanizeFieldName($column);
        $formattedValue = $this->formatValue($value);
        $verb = $this->inflector->isPlural($humanizedColumn) ? 'include' : 'includes';

        return $isNegated
            ? "whose {$humanizedColumn} ".($verb === 'include' ? "don't include" : "doesn't include")." {$formattedValue}"
            : "whose {$humanizedColumn} {$verb} {$formattedValue}";
    }

    private function translateJsonLength(array $where): string
    {
        $column = $this->inflector->humanizeFieldName((string) $where['column']);
        $operator = $where['operator'] ?? '=';
        $count = (int) $where['value'];
        $noun = $count === 1 ? 'item' : 'items';

        $phrase = match ($operator) {
            '=' => "exactly {$count} {$noun}",
            '!=', '<>' => "a number of items other than {$count}",
            '>' => "more than {$count} {$noun}",
            '>=' => "at least {$count} {$noun}",
            '<' => "fewer than {$count} {$noun}",
            '<=' => "at most {$count} {$noun}",
            default => "{$count} {$noun}",
        };

        return "whose {$column} has {$phrase}";
    }

    private function translateFullText(array $where): string
    {
        $columns = array_map(
            fn ($column) => $this->inflector->humanizeFieldName((string) $column),
            (array) ($where['columns'] ?? [])
        );
        $value = $this->formatValue($where['value'] ?? '');

        if (empty($columns)) {
            return "matching {$value}";
        }

        return 'matching '.$value.' in '.$this->inflector->joinWithConnector($columns, 'or');
    }

    private function translateRaw(array $where): string
    {
        return $this->config['include_raw_fallback'] ? $this->config['raw_fallback_text'] : '';
    }

    /** "products.price_usd" → "price in USD" or "the product's price in USD". */
    private function humanizePossiblyDottedColumn(string $column, $builder): string
    {
        $field = $this->resolveDottedColumn($column, $builder, $related);
        $humanized = $this->inflector->humanizeFieldName($field, $builder);

        if ($related !== null) {
            return "the {$related}'s {$humanized}";
        }

        return $humanized;
    }

    /**
     * "orders.order_status" → "order_status" when it targets the query's own
     * table; for other tables the related entity is captured so the condition
     * can be prefixed with context.
     */
    private function resolveDottedColumn(mixed $column, $builder, ?string &$related = null): string
    {
        $related = null;

        if (! is_string($column) || ! str_contains($column, '.')) {
            return is_string($column) ? $column : (string) $column;
        }

        [$table, $field] = explode('.', $column, 2);

        $mainTable = null;
        if ($builder && method_exists($builder, 'getModel')) {
            try {
                $mainTable = $builder->getModel()->getTable();
            } catch (\Throwable) {
                $mainTable = null;
            }
        }

        if ($mainTable === null || $table === $mainTable || str_starts_with($table, 'laravel_reserved')) {
            return $field;
        }

        $related = str_replace(['_', '-'], ' ', $this->inflector->singularize($table));

        return $field;
    }

    private function applyRelatedContext(string $condition, ?string $related): string
    {
        if ($related === null || $condition === '') {
            return $condition;
        }

        $condition = $this->inflector->singularizeConditionPhrase($condition);
        $article = $this->inflector->getProperArticle($related);

        return 'with '.($article ? "{$article} " : '')."{$related} {$condition}";
    }

    private function toCarbonOrNull(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        $string = is_scalar($value) || (is_object($value) && method_exists($value, '__toString')) ? (string) $value : '';

        if (! preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', $string)) {
            return null;
        }

        try {
            return Carbon::parse($string);
        } catch (\Exception) {
            return null;
        }
    }
}
