<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use SulimanBenhalim\Prose\Support\Inflector;
use SulimanBenhalim\Prose\Translators\DeleteTranslator;
use SulimanBenhalim\Prose\Translators\ExistsTranslator;
use SulimanBenhalim\Prose\Translators\LimitTranslator;
use SulimanBenhalim\Prose\Translators\OrderTranslator;
use SulimanBenhalim\Prose\Translators\UpdateTranslator;
use SulimanBenhalim\Prose\Translators\WhereTranslator;

class Prose
{
    private Inflector $inflector;

    private WhereTranslator $whereTranslator;

    private ExistsTranslator $existsTranslator;

    private OrderTranslator $orderTranslator;

    private LimitTranslator $limitTranslator;

    private UpdateTranslator $updateTranslator;

    private DeleteTranslator $deleteTranslator;

    public function __construct(private array $config)
    {
        $this->inflector = new Inflector($config);
        $this->whereTranslator = new WhereTranslator($this->inflector, $config);
        $this->existsTranslator = new ExistsTranslator($this->inflector, $this->whereTranslator, $config);
        $this->whereTranslator->setExistsTranslator($this->existsTranslator);
        $this->orderTranslator = new OrderTranslator($this->inflector, $config);
        $this->limitTranslator = new LimitTranslator($config);
        $this->updateTranslator = new UpdateTranslator($this->inflector, $config);
        $this->deleteTranslator = new DeleteTranslator;
    }

    public function describe(EloquentBuilder $builder): string
    {
        if (! $this->config['enabled']) {
            return '';
        }

        $query = $builder->getQuery();

        $parts = [
            'action' => $this->getAction($query, $builder),
            'model' => $this->getModelName($builder),
            'conditions' => $this->getConditions($query, $builder),
            'grouping' => $this->getGrouping($query, $builder),
            'updateFields' => $this->getUpdateFields($query, $builder),
            'relationships' => $this->getRelationships($builder),
            'ordering' => $this->getOrdering($query, $builder),
            'limit' => $this->getLimit($query),
        ];

        return $this->buildSentence($parts);
    }

    private function getAction(QueryBuilder $query, EloquentBuilder $builder): string
    {
        $aggregate = $query->aggregate ?? null;

        if ($aggregate) {
            $function = $aggregate['function'] ?? 'count';

            if ($function === 'update') {
                return $this->updateTranslator->translateUpdateAction($query, $builder);
            }

            if ($function === 'delete') {
                return $this->deleteTranslator->translateDeleteAction($query, $builder);
            }

            $columns = $aggregate['columns'] ?? [];

            if ($function !== 'count' && ! empty($columns) && $columns[0] !== '*') {
                $column = $this->inflector->humanizeFieldName($columns[0], $builder);
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

    private function getUpdateFields(QueryBuilder $query, EloquentBuilder $builder): string
    {
        $aggregate = $query->aggregate ?? null;

        if ($aggregate && ($aggregate['function'] ?? '') === 'update') {
            return $this->updateTranslator->translateUpdateFields($query, $builder);
        }

        return '';
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

        return $this->whereTranslator->translateToSentence($wheres, $builder);
    }

    private function getGrouping(QueryBuilder $query, EloquentBuilder $builder): string
    {
        $groups = $query->groups ?? [];

        if (empty($groups)) {
            return '';
        }

        $names = [];
        foreach ($groups as $group) {
            if (is_string($group)) {
                $names[] = $this->inflector->humanizeFieldName($group, $builder);
            }
        }

        if (empty($names)) {
            return '';
        }

        $phrase = 'grouped by '.$this->inflector->joinWithConnector($names, 'and');

        $havings = $this->translateHavings($query->havings ?? [], $builder);
        if ($havings !== '') {
            $phrase .= ' '.$havings;
        }

        return $phrase;
    }

    private function translateHavings(array $havings, EloquentBuilder $builder): string
    {
        $translatable = [];

        foreach ($havings as $having) {
            if (($having['type'] ?? '') === 'Basic' && is_string($having['column'] ?? null)) {
                $translatable[] = $having;
            }
        }

        if (empty($translatable)) {
            return '';
        }

        return $this->whereTranslator->translateToSentence($translatable, $builder);
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
            $segments = array_map(
                fn ($segment) => $this->inflector->humanizeFieldName($segment),
                explode('.', $relation)
            );

            return implode(' and their ', $segments);
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

        if (! str_contains($template, '{grouping}') && ! empty($parts['grouping'])) {
            $template = str_replace('{conditions}', '{conditions} {grouping}', $template);
        }

        // Insert field updates after conditions for UPDATE operations
        if (! empty($parts['updateFields'])) {
            $template = str_replace('{conditions}', '{conditions} to {updateFields}', $template);
        }

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

        if (! preg_match('/first (\d+) results?/', $limitText, $matches)) {
            return [$template, $parts];
        }

        $number = (int) $matches[1];

        $offsetPart = '';
        if (preg_match('/(skipping the first \d+)/', $limitText, $offsetMatches)) {
            $offsetPart = ', '.$offsetMatches[1];
        }

        $template = str_replace(' {limit}', '', $template);

        if ($number === 1) {
            $template = str_replace('{action}', '{action} the first', $template);
            $parts['model'] = $this->inflector->singularize($parts['model']);
        } else {
            $template = str_replace('{action}', "{action} first {$number}", $template);
        }

        if ($offsetPart) {
            $template .= '{offset_part}';
            $parts['offset_part'] = $offsetPart;
        }

        $parts['limit'] = '';

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
