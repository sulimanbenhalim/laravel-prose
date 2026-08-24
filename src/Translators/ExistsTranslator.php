<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Translators;

use Illuminate\Database\Query\Builder as QueryBuilder;
use SulimanBenhalim\Prose\Support\Inflector;

class ExistsTranslator
{
    public function __construct(
        private Inflector $inflector,
        private WhereTranslator $whereTranslator,
        private array $config
    ) {}

    public function translateExists(array $where): string
    {
        return $this->translateExistsWhere($where, positive: true);
    }

    public function translateNotExists(array $where): string
    {
        return $this->translateExistsWhere($where, positive: false);
    }

    private function translateExistsWhere(array $where, bool $positive): string
    {
        if (! isset($where['query'])) {
            return $this->config['raw_fallback_text'];
        }

        $query = $where['query'];

        $belongsTo = $this->detectBelongsTo($query);

        if ($belongsTo !== null) {
            return $this->buildBelongsToPhrase($query, $belongsTo, $positive);
        }

        $relation = $this->extractRelationName($query);

        if (! $relation) {
            if (! $this->isNormalEloquentQuery($query)) {
                return $this->config['raw_fallback_text'];
            }

            $relation = $this->inferRelationFromContext($query);
            if (! $relation) {
                return $positive ? 'with related records' : "who don't have related records";
            }
        }

        $conditionsText = $this->extractConditions($query);
        $prefix = $positive ? 'who have' : "who don't have";

        return trim("{$prefix} {$relation} {$conditionsText}");
    }

    /**
     * "who have a manager whose department is 'Engineering'" for
     * belongsTo-style whereHas, detected from the correlation join
     * parent.{name}_id = sub.id.
     */
    private function buildBelongsToPhrase(QueryBuilder $query, string $relationName, bool $positive): string
    {
        $humanized = str_replace(['_', '-'], ' ', $relationName);
        $article = $this->inflector->getProperArticle($humanized);
        $label = ($article ? "{$article} " : '').$humanized;

        $conditionsText = $this->extractConditions($query, singular: true);
        $prefix = $positive ? 'who have' : "who don't have";

        return trim("{$prefix} {$label} {$conditionsText}");
    }

    /**
     * Returns the foreign-key base name when the exists subquery correlates
     * through a foreign key on the parent table (belongsTo), null otherwise.
     */
    private function detectBelongsTo(QueryBuilder $query): ?string
    {
        $subTables = $this->subQueryTables($query);

        foreach ($query->wheres ?? [] as $where) {
            if (($where['type'] ?? '') !== 'Column' || ($where['operator'] ?? '') !== '=') {
                continue;
            }

            foreach ([$where['first'] ?? null, $where['second'] ?? null] as $side) {
                if (! is_string($side) || ! str_contains($side, '.') || ! str_ends_with($side, '_id')) {
                    continue;
                }

                [$table, $column] = explode('.', $side, 2);

                // Foreign key on the parent side of the correlation
                if (! in_array($table, $subTables, true)) {
                    return substr($column, 0, -3);
                }
            }
        }

        return null;
    }

    /** @return string[] table names that refer to the subquery side of the correlation */
    private function subQueryTables(QueryBuilder $query): array
    {
        $from = (string) ($query->from ?? '');
        $tables = [];

        if (preg_match('/^["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?(?:\s+as\s+["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?)?$/i', trim($from), $matches)) {
            // With an alias (self-referential relations), subquery columns use
            // the alias, so the bare table name refers to the parent.
            $tables[] = $matches[2] ?? $matches[1];
        }

        // Pivot tables joined inside the subquery (belongsToMany) are part of
        // the subquery side too.
        foreach ($query->joins ?? [] as $join) {
            $joinTable = (string) ($join->table ?? '');
            if ($joinTable !== '') {
                $tables[] = preg_split('/\s+as\s+/i', trim(str_replace(['"', '`'], '', $joinTable)))[0];
            }
        }

        return $tables;
    }

    private function extractRelationName(QueryBuilder $query): ?string
    {
        return $this->tryDirectExtraction($query)
            ?? $this->tryJoinExtraction($query)
            ?? $this->tryColumnAnalysis($query)
            ?? $this->tryBindingAnalysis($query)
            ?? $this->tryHeuristicInference($query);
    }

    private function tryDirectExtraction(QueryBuilder $query): ?string
    {
        $from = $query->from ?? '';
        if (! empty($from)) {
            return $this->humanizeTableName((string) $from);
        }

        return null;
    }

    private function tryJoinExtraction(QueryBuilder $query): ?string
    {
        $joins = $query->joins ?? [];
        foreach ($joins as $join) {
            $table = $join->table ?? '';
            if (! empty($table)) {
                return $this->humanizeTableName((string) $table);
            }
        }

        return null;
    }

    private function tryColumnAnalysis(QueryBuilder $query): ?string
    {
        $wheres = $query->wheres ?? [];
        $tableCandidates = [];

        foreach ($wheres as $where) {
            $column = $where['column'] ?? '';

            if (is_string($column) && str_contains($column, '.')) {
                $parts = explode('.', $column);
                if (count($parts) >= 2 && ! str_starts_with($parts[0], 'laravel_reserved')) {
                    $tableCandidates[] = $parts[0];
                }
            } elseif (is_string($column)) {
                $inferredTable = $this->inferTableFromColumn($column);
                if ($inferredTable) {
                    $tableCandidates[] = $inferredTable;
                }
            }
        }

        if (! empty($tableCandidates)) {
            $tableCounts = array_count_values($tableCandidates);
            arsort($tableCounts);
            $mostCommon = array_key_first($tableCounts);

            return $this->humanizeTableName($mostCommon);
        }

        return null;
    }

    private function tryBindingAnalysis(QueryBuilder $query): ?string
    {
        try {
            $sql = $query->toSql();
            if (preg_match('/from\s+["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?/i', $sql, $matches)) {
                return $this->humanizeTableName($matches[1]);
            }
        } catch (\Exception $e) {
            // Ignore SQL generation errors
        }

        return null;
    }

    private function tryHeuristicInference(QueryBuilder $query): ?string
    {
        $wheres = $query->wheres ?? [];

        foreach ($wheres as $where) {
            $column = $where['column'] ?? '';
            if (is_string($column) && ! empty($column)) {
                $potentialTable = $this->extractEntityFromColumnName($column);
                if ($potentialTable) {
                    return $potentialTable;
                }
            }
        }

        return null;
    }

    private function humanizeTableName(string $tableName): string
    {
        $cleaned = trim(str_replace(['"', "'", '`'], '', $tableName));

        // "employees as laravel_reserved_0" → "employees"
        $cleaned = preg_split('/\s+as\s+/i', $cleaned)[0];

        return str_replace(['_', '-'], ' ', trim($cleaned));
    }

    private function inferTableFromColumn(string $column): ?string
    {
        if (str_ends_with($column, '_id')) {
            $relationName = str_replace('_id', '', $column);

            return $this->inflector->pluralize($relationName);
        }

        return $this->extractEntityFromColumnName($column);
    }

    private function extractEntityFromColumnName(string $column): ?string
    {
        $parts = preg_split('/[_\-]/', strtolower($column));

        if (empty($parts)) {
            return null;
        }

        $candidateParts = [];
        foreach ($parts as $index => $part) {
            if (strlen($part) <= 2 || is_numeric($part)) {
                continue;
            }

            $candidateParts[] = [
                'part' => $part,
                'score' => strlen($part) * 10 - $index,
            ];
        }

        usort($candidateParts, fn ($a, $b) => $b['score'] <=> $a['score']);

        foreach ($candidateParts as $candidate) {
            $pluralized = $this->inflector->pluralize($candidate['part']);

            if ($pluralized !== $candidate['part']) {
                return $pluralized;
            }
        }

        if (! empty($candidateParts)) {
            return $this->inflector->pluralize($candidateParts[0]['part']);
        }

        return null;
    }

    private function extractConditions(QueryBuilder $query, bool $singular = false): string
    {
        $wheres = $query->wheres ?? [];

        if (empty($wheres)) {
            return '';
        }

        $filteredWheres = array_values(array_filter($wheres, function ($where) {
            return ! $this->isJoinCondition($where);
        }));

        $pairs = $this->whereTranslator->translateWithBooleans($filteredWheres);

        if ($singular) {
            foreach ($pairs as $i => $pair) {
                $pairs[$i]['text'] = $this->inflector->singularizeConditionPhrase($pair['text']);
            }
        }

        return $this->whereTranslator->joinConditionPairs($pairs);
    }

    private function isJoinCondition(array $where): bool
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

    private function isNormalEloquentQuery(QueryBuilder $query): bool
    {
        $wheres = $query->wheres ?? [];

        foreach ($wheres as $where) {
            if (in_array($where['type'] ?? '', ['Raw', 'Expression'])) {
                return false;
            }
        }

        return true;
    }

    private function inferRelationFromContext(QueryBuilder $query): ?string
    {
        $wheres = $query->wheres ?? [];

        $tableNames = [];
        foreach ($wheres as $where) {
            if (isset($where['column']) && is_string($where['column'])) {
                if (str_contains($where['column'], '.')) {
                    $parts = explode('.', $where['column']);
                    if (! str_starts_with($parts[0], 'laravel_reserved')) {
                        $tableNames[] = $parts[0];
                    }
                } elseif (str_ends_with($where['column'], '_id')) {
                    $relationName = str_replace('_id', '', $where['column']);
                    $tableNames[] = $this->inflector->pluralize($relationName);
                } else {
                    $extractedEntity = $this->extractEntityFromColumnName($where['column']);
                    if ($extractedEntity) {
                        $tableNames[] = $extractedEntity;
                    }
                }
            }
        }

        if (! empty($tableNames)) {
            $tableCounts = array_count_values($tableNames);
            $mostCommon = array_key_first($tableCounts);

            return str_replace(['_', '-'], ' ', $mostCommon);
        }

        return null;
    }
}
