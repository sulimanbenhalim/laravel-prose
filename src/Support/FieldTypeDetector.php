<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

class FieldTypeDetector
{
    /** @var array<string, array<string, string>> per-connection+table column => type_name */
    private static array $schemaCache = [];

    public function isDateField(string $fieldName, $builder = null): bool
    {
        if ($this->isLaravelTimestampField($fieldName)) {
            return true;
        }

        $cast = $this->castType($fieldName, $builder);
        if ($cast !== null) {
            return in_array($cast, ['date', 'datetime', 'timestamp', 'time', 'immutable_date', 'immutable_datetime'], true);
        }

        $schemaType = $this->schemaType($fieldName, $builder);
        if ($schemaType !== null) {
            return in_array($schemaType, ['date', 'datetime', 'datetimetz', 'time', 'timestamp'], true);
        }

        return (bool) preg_match('/(date|time|at)$/i', $fieldName);
    }

    public function isBooleanField(string $fieldName, $builder = null): bool
    {
        $cast = $this->castType($fieldName, $builder);
        if ($cast === 'boolean' || $cast === 'bool') {
            return true;
        }

        return (bool) preg_match('/^(is|has|can|should|will|was|were|requires|needs|allows|accepts|supports)_/', $fieldName);
    }

    public function getFieldType(string $fieldName, $builder = null): string
    {
        $cast = $this->castType($fieldName, $builder);
        if ($cast !== null) {
            return $this->normalizeCastType($cast);
        }

        $schemaType = $this->schemaType($fieldName, $builder);
        if ($schemaType !== null) {
            return $this->normalizeSchemaType($schemaType);
        }

        return 'string';
    }

    public function isNumericField(string $fieldName, $builder = null): bool
    {
        $fieldType = $this->getFieldType($fieldName, $builder);

        return in_array($fieldType, ['integer', 'bigint', 'decimal', 'float', 'double']);
    }

    public function isCurrencyField(string $fieldName): bool
    {
        return (bool) preg_match('/_(usd|eur|gbp|jpy|cad|aud)$/i', $fieldName);
    }

    public function isLaravelTimestampField(?string $fieldName): bool
    {
        return in_array($fieldName, [
            'created_at', 'updated_at', 'deleted_at',
            'email_verified_at', 'last_used_at', 'remember_token_expires_at',
        ], true);
    }

    private function castType(string $fieldName, $builder): ?string
    {
        if (! $builder || ! method_exists($builder, 'getModel')) {
            return null;
        }

        try {
            $casts = $builder->getModel()->getCasts();
        } catch (\Throwable) {
            return null;
        }

        if (! isset($casts[$fieldName])) {
            return null;
        }

        return explode(':', strtolower($casts[$fieldName]))[0];
    }

    private function schemaType(string $fieldName, $builder): ?string
    {
        if (! $builder || ! method_exists($builder, 'getModel')) {
            return null;
        }

        try {
            $model = $builder->getModel();
            $connection = $model->getConnection();
            $key = $connection->getName().'.'.$model->getTable();
        } catch (\Throwable) {
            return null;
        }

        if (! array_key_exists($key, self::$schemaCache)) {
            try {
                $columns = $connection->getSchemaBuilder()->getColumns($model->getTable());
                $types = [];
                foreach ($columns as $column) {
                    $types[$column['name']] = strtolower($column['type_name'] ?? $column['type'] ?? '');
                }
                self::$schemaCache[$key] = $types;
            } catch (\Throwable) {
                self::$schemaCache[$key] = [];
            }
        }

        return self::$schemaCache[$key][$fieldName] ?? null;
    }

    private function normalizeCastType(string $baseType): string
    {
        return match ($baseType) {
            'int', 'integer' => 'integer',
            'real', 'float', 'double' => 'float',
            'decimal' => 'decimal',
            'datetime', 'timestamp', 'immutable_datetime' => 'datetime',
            'date', 'immutable_date' => 'date',
            'bool', 'boolean' => 'boolean',
            default => 'string',
        };
    }

    private function normalizeSchemaType(string $typeName): string
    {
        return match (true) {
            str_contains($typeName, 'bigint'), str_contains($typeName, 'integer'), $typeName === 'int' => 'integer',
            str_contains($typeName, 'decimal'), str_contains($typeName, 'numeric') => 'decimal',
            str_contains($typeName, 'float'), str_contains($typeName, 'double'), str_contains($typeName, 'real') => 'float',
            str_contains($typeName, 'datetime'), str_contains($typeName, 'timestamp') => 'datetime',
            $typeName === 'date' => 'date',
            str_contains($typeName, 'bool') => 'boolean',
            default => 'string',
        };
    }
}
