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
        if (! isset($where['query'])) {
            return $this->config['raw_fallback_text'];
        }

        $query = $where['query'];
        $relation = $this->extractRelationName($query);

        if (! $relation) {
            if ($this->isNormalEloquentQuery($query)) {
                $inferredRelation = $this->inferRelationFromContext($query);
                if ($inferredRelation) {
                    $relation = $inferredRelation;
                } else {
                    return 'with related records';
                }
            } else {
                return $this->config['raw_fallback_text'];
            }
        }

        $conditions = $this->extractConditions($query);

        if (empty($conditions)) {
            return "who have {$relation}";
        }

        $conditionsText = $this->inflector->joinWithConnector($conditions, 'and');

        return "who have {$relation} {$conditionsText}";
    }

    public function translateNotExists(array $where): string
    {
        if (! isset($where['query'])) {
            return $this->config['raw_fallback_text'];
        }

        $query = $where['query'];
        $relation = $this->extractRelationName($query);

        if (! $relation) {
            if ($this->isNormalEloquentQuery($query)) {
                $inferredRelation = $this->inferRelationFromContext($query);
                if ($inferredRelation) {
                    $relation = $inferredRelation;
                } else {
                    return "who don't have related records";
                }
            } else {
                return $this->config['raw_fallback_text'];
            }
        }

        $conditions = $this->extractConditions($query);

        if (empty($conditions)) {
            return "who don't have {$relation}";
        }

        $conditionsText = $this->inflector->joinWithConnector($conditions, 'and');

        return "who don't have {$relation} {$conditionsText}";
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
            return $this->humanizeTableName($from);
        }

        return null;
    }

    private function tryJoinExtraction(QueryBuilder $query): ?string
    {
        $joins = $query->joins ?? [];
        foreach ($joins as $join) {
            $table = $join->table ?? '';
            if (! empty($table)) {
                return $this->humanizeTableName($table);
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
                if (count($parts) >= 2) {
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

                $potentialTable = $this->extractTableFromColumnDynamically($column);
                if ($potentialTable) {
                    return $potentialTable;
                }
            }
        }

        return null;
    }

    private function extractTableFromColumnDynamically(string $column): ?string
    {
        return $this->extractEntityFromColumnName($column);
    }

    private function humanizeTableName(string $tableName): string
    {
        $cleaned = trim(str_replace(['"', "'", '`'], '', $tableName));

        return str_replace(['_', '-'], ' ', $cleaned);
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

    private function extractConditions(QueryBuilder $query): array
    {
        $wheres = $query->wheres ?? [];

        if (empty($wheres)) {
            return [];
        }

        $filteredWheres = array_filter($wheres, function ($where) {
            return ! $this->isJoinCondition($where);
        });

        return $this->whereTranslator->translate(array_values($filteredWheres));
    }

    private function isJoinCondition(array $where): bool
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
                    $tableNames[] = $parts[0];
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
