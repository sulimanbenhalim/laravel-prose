<?php

declare(strict_types=1);

return [
    'sentence_template' => '{action} {model} {conditions} {relationships} {ordering} {limit}',

    'actions' => [
        'select' => 'Find',
        'count' => 'Count',
        'sum' => 'Sum',
        'avg' => 'Average',
        'max' => 'Maximum',
        'min' => 'Minimum',
        'update' => 'Update',
        'delete' => 'Delete',
    ],

    'connectors' => [
        'and' => 'and',
        'or' => 'or',
        'relationships' => 'including their',
        'ordering' => 'sorted by',
        'limit' => 'first',
        'offset' => 'starting from position',
    ],

    'max_conditions' => 12,

    'humanize_dates' => true,

    'enabled' => env('PROSE_ENABLED', true),

    'include_raw_fallback' => true,

    'raw_fallback_text' => 'with custom database operations',

    'truncation_indicator' => '...',

    'date_formats' => [
        'today' => 'today',
        'yesterday' => 'yesterday',
        'days_ago' => ':count days ago',
        'this_year' => 'M j',
        'fallback' => 'M j, Y',
    ],

    'custom_pluralization' => [
        'user' => 'users',
        'person' => 'people',
        'child' => 'children',
        'category' => 'categories',
        'company' => 'companies',
    ],
];
