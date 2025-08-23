<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Translators;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class DeleteTranslator
{
    public function translateDeleteAction(QueryBuilder $query, EloquentBuilder $builder): string
    {
        $hasConditions = ! empty($query->wheres);

        if (! $hasConditions) {
            return 'Delete all';
        }

        return 'Delete';
    }
}
