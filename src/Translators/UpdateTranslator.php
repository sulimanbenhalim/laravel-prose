<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Translators;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use SulimanBenhalim\Prose\Support\FieldTypeDetector;
use SulimanBenhalim\Prose\Support\Inflector;
use SulimanBenhalim\Prose\Support\UpdateFieldHandler;

class UpdateTranslator
{
    private UpdateFieldHandler $updateFieldHandler;

    public function __construct(
        private Inflector $inflector,
        private array $config
    ) {
        $fieldTypeDetector = new FieldTypeDetector;
        $this->updateFieldHandler = new UpdateFieldHandler($this->inflector, $fieldTypeDetector);
    }

    public function translateUpdateAction(QueryBuilder $query, EloquentBuilder $builder): string
    {
        return $this->config['actions']['update'] ?? 'Update';
    }

    public function translateUpdateFields(QueryBuilder $query, EloquentBuilder $builder): string
    {
        // Extract update data from query
        $updateData = $this->extractUpdateData($query);

        if (empty($updateData)) {
            return '';
        }

        // Translate the fields being updated
        return $this->updateFieldHandler->translateUpdateFields($updateData, $builder);
    }

    private function extractUpdateData(QueryBuilder $query): array
    {
        $aggregate = $query->aggregate ?? null;
        // Our describeUpdate macro adds 'values' key to the aggregate array
        /** @var array{function: string, columns: array, values?: array} $aggregate */
        if (isset($aggregate['values'])) {
            return $aggregate['values'];
        }

        return [];
    }
}
