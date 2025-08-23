<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

class FieldTypeDetector
{
    public function __construct() {}

    public function isDateField(string $fieldName, $builder = null): bool
    {
        // Check common Laravel timestamp fields first
        if (in_array($fieldName, ['created_at', 'updated_at', 'deleted_at', 'email_verified_at', 'last_used_at'])) {
            return true;
        }

        // Check using builder if available
        if ($builder && method_exists($builder, 'getModel')) {
            try {
                $model = $builder->getModel();

                // Check model casts for date types
                $casts = $model->getCasts();
                if (isset($casts[$fieldName])) {
                    $castType = strtolower($casts[$fieldName]);
                    $castType = explode(':', $castType)[0];

                    if (in_array($castType, ['date', 'datetime', 'timestamp', 'time'])) {
                        return true;
                    }
                }

                // Check database schema using Doctrine DBAL
                $table = $model->getTable();
                $connection = $model->getConnection();
                $columnType = $connection->getDoctrineColumn($table, $fieldName)->getType();

                if ($columnType) {
                    $typeName = $columnType->getName();
                    if (in_array($typeName, ['date', 'datetime', 'datetimetz', 'time', 'timestamp'])) {
                        return true;
                    }
                }
            } catch (\Exception $e) {
                // Schema inspection failed, fall through to pattern matching
            }
        }

        // Fallback to pattern matching
        return (bool) preg_match('/(date|time|at)$/i', $fieldName);
    }

    public function isBooleanField(string $fieldName, $builder = null): bool
    {
        // Check using builder if available
        if ($builder && method_exists($builder, 'getModel')) {
            try {
                $model = $builder->getModel();

                // Check model casts first
                $casts = $model->getCasts();
                if (isset($casts[$fieldName]) && $casts[$fieldName] === 'boolean') {
                    return true;
                }

                // Check database schema
                $table = $model->getTable();
                $connection = $model->getConnection();
                $columnType = $connection->getDoctrineColumn($table, $fieldName)->getType();

                if ($columnType && class_exists('\Doctrine\DBAL\Types\BooleanType') && $columnType instanceof \Doctrine\DBAL\Types\BooleanType) {
                    return true;
                }
            } catch (\Exception $e) {
                // Schema inspection failed, fall through to pattern matching
            }
        }

        // Fallback to pattern matching
        return (bool) preg_match('/^(is|has|can|should|will|was|were)_/', $fieldName);
    }

    public function getFieldType(string $fieldName, $builder = null): string
    {
        if ($builder && method_exists($builder, 'getModel')) {
            try {
                $model = $builder->getModel();

                // Check model casts first
                $casts = $model->getCasts();
                if (isset($casts[$fieldName])) {
                    return $this->normalizeCastType($casts[$fieldName]);
                }

                // Check database schema
                $table = $model->getTable();
                $connection = $model->getConnection();
                $columnType = $connection->getDoctrineColumn($table, $fieldName)->getType();

                if ($columnType) {
                    return $this->normalizeDoctrineType($columnType);
                }
            } catch (\Exception $e) {
                // Schema inspection failed, return default
            }
        }

        return 'string'; // Default fallback
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

    public function isLaravelTimestampField(string $fieldName): bool
    {
        return in_array($fieldName, [
            'created_at', 'updated_at', 'deleted_at',
            'email_verified_at', 'last_used_at', 'remember_token_expires_at',
        ]);
    }

    private function normalizeCastType(string $castType): string
    {
        $castType = strtolower($castType);
        $baseType = explode(':', $castType)[0];

        return match ($baseType) {
            'int', 'integer' => 'integer',
            'real', 'float', 'double' => 'float',
            'decimal' => 'decimal',
            'datetime', 'timestamp' => 'datetime',
            'date' => 'date',
            default => 'string',
        };
    }

    private function normalizeDoctrineType($doctrineType): string
    {
        $typeName = strtolower(get_class($doctrineType));

        if (str_contains($typeName, 'integer') || str_contains($typeName, 'bigint')) {
            return 'integer';
        }

        if (str_contains($typeName, 'decimal') || str_contains($typeName, 'float')) {
            return 'decimal';
        }

        if (str_contains($typeName, 'datetime') || str_contains($typeName, 'timestamp')) {
            return 'datetime';
        }

        if (str_contains($typeName, 'date')) {
            return 'date';
        }

        return 'string';
    }
}
