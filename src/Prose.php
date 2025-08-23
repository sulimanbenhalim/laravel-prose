<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use SulimanBenhalim\Prose\Support\Inflector;
use SulimanBenhalim\Prose\Translators\ExistsTranslator;
use SulimanBenhalim\Prose\Translators\LimitTranslator;
use SulimanBenhalim\Prose\Translators\OrderTranslator;
use SulimanBenhalim\Prose\Translators\WhereTranslator;

class Prose
{
    private Inflector $inflector;

    private WhereTranslator $whereTranslator;

    private ExistsTranslator $existsTranslator;

    private OrderTranslator $orderTranslator;

    private LimitTranslator $limitTranslator;

    public function __construct(private array $config)
    {
        $this->inflector = new Inflector($config);
        $this->whereTranslator = new WhereTranslator($this->inflector, $config);
        $this->existsTranslator = new ExistsTranslator($this->inflector, $this->whereTranslator, $config);
        $this->whereTranslator->setExistsTranslator($this->existsTranslator);
        $this->orderTranslator = new OrderTranslator($this->inflector, $config);
        $this->limitTranslator = new LimitTranslator($this->inflector, $config);
    }

    public function describe(EloquentBuilder $builder): string
    {
        if (! $this->config['enabled']) {
            return '';
        }

        $query = $builder->getQuery();

        $parts = [
            'action' => $this->getAction($query),
            'model' => $this->getModelName($builder),
            'conditions' => $this->getConditions($query, $builder),
            'relationships' => $this->getRelationships($builder),
            'ordering' => $this->getOrdering($query, $builder),
            'limit' => $this->getLimit($query),
        ];

        return $this->buildSentence($parts);
    }

    private function getAction(QueryBuilder $query): string
    {
        $aggregate = $query->aggregate ?? null;

        if ($aggregate) {
            $function = $aggregate['function'] ?? 'count';

            if ($function === 'update') {
                return $this->config['actions']['update'] ?? 'Update';
            }

            if ($function === 'delete') {
                return $this->config['actions']['delete'] ?? 'Delete';
            }

            $columns = $aggregate['columns'] ?? [];

            if ($function !== 'count' && ! empty($columns) && $columns[0] !== '*') {
                $column = $this->inflector->humanizeFieldName($columns[0]);
                $action = $this->config['actions'][$function] ?? ucfirst($function);

                return $action.' '.$column.' for';
            }

            return $this->config['actions'][$function] ?? ucfirst($function);
        }

        if ($query->distinct) {
            return 'Find unique';
        }

        return $this->config['actions']['select'] ?? 'Find';
    }

    private function getModelName(EloquentBuilder $builder): string
    {
        $model = $builder->getModel();

        $modelClassName = class_basename(get_class($model));

        $modelName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $modelClassName));

        $modelName = str_replace(['_', '-'], ' ', $modelName);

        return $this->inflector->pluralize($modelName);
    }

    private function getConditions(QueryBuilder $query, $builder): string
    {
        $wheres = $query->wheres ?? [];

        if (empty($wheres)) {
            return '';
        }

        $regularWheres = [];
        $existsWheres = [];

        foreach ($wheres as $where) {
            if (in_array(strtolower($where['type']), ['exists', 'notexists'])) {
                $existsWheres[] = $where;
            } else {
                $regularWheres[] = $where;
            }
        }

        $conditions = [];

        if (! empty($regularWheres)) {
            $regularConditions = $this->whereTranslator->translate($regularWheres, $builder);
            $conditions = array_merge($conditions, $regularConditions);
        }

        if (! empty($existsWheres)) {
            foreach ($existsWheres as $existsWhere) {
                if (strtolower($existsWhere['type']) === 'exists') {
                    $condition = $this->existsTranslator->translateExists($existsWhere);
                } else {
                    $condition = $this->existsTranslator->translateNotExists($existsWhere);
                }

                if ($condition) {
                    $conditions[] = $condition;
                }
            }
        }

        return $this->inflector->joinWithConnector($conditions, 'and');
    }

    private function getRelationships(EloquentBuilder $builder): string
    {
        $eagerLoads = $builder->getEagerLoads();

        if (empty($eagerLoads)) {
            return '';
        }

        $relationships = array_keys($eagerLoads);

        $filteredRelationships = $this->filterRedundantRelationships($relationships);

        $relationshipNames = array_map(function ($relation) {
            $relation = str_replace('.', ' ', $relation);
            $relation = $this->inflector->humanizeFieldName($relation);

            return $relation;
        }, $filteredRelationships);

        $connector = $this->config['connectors']['relationships'] ?? 'including their';

        return $connector.' '.$this->inflector->joinWithConnector($relationshipNames, 'and');
    }

    private function getOrdering(QueryBuilder $query, EloquentBuilder $builder): string
    {
        $orders = $query->orders ?? [];

        return $this->orderTranslator->translate($orders, $builder);
    }

    private function getLimit(QueryBuilder $query): string
    {
        return $this->limitTranslator->translate($query->limit, $query->offset);
    }

    private function buildSentence(array $parts): string
    {
        $template = $this->config['sentence_template'];

        if (! empty($parts['limit'])) {
            [$template, $parts] = $this->repositionLimitToBeginning($template, $parts);
        }

        $sentence = $template;

        foreach ($parts as $key => $value) {
            $placeholder = "{{$key}}";
            if (empty($value)) {
                $sentence = str_replace($placeholder, '', $sentence);
            } else {
                $sentence = str_replace($placeholder, $value, $sentence);
            }
        }

        $sentence = preg_replace('/\s+/', ' ', $sentence);
        $sentence = trim($sentence);

        return ucfirst($sentence);
    }

    private function repositionLimitToBeginning(string $template, array $parts): array
    {

        $limitText = $parts['limit'];

        if (preg_match('/first (\d+) results?/', $limitText, $matches)) {
            $number = $matches[1];

            $offsetPart = '';
            if (preg_match('/(and starting from position \d+)/', $limitText, $offsetMatches)) {
                $offsetPart = ' '.$offsetMatches[1];
            }

            $template = str_replace(' {limit}', '', $template);
            $template = str_replace('{action}', "{action} first {$number}", $template);

            if ($offsetPart) {
                $template .= ' {offset_part}';
                $parts['offset_part'] = $offsetPart;
            }

            $parts['limit'] = '';
        }

        return [$template, $parts];
    }

    private function filterRedundantRelationships(array $relationships): array
    {
        $filtered = [];

        foreach ($relationships as $relationship) {
            $isRedundant = false;

            foreach ($relationships as $otherRelationship) {
                if ($relationship !== $otherRelationship && str_starts_with($otherRelationship, $relationship.'.')) {
                    $isRedundant = true;
                    break;
                }
            }

            if (! $isRedundant) {
                $filtered[] = $relationship;
            }
        }

        return $filtered;
    }
}
