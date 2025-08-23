<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Translators;

use SulimanBenhalim\Prose\Support\Inflector;

class OrderTranslator
{
    public function __construct(
        private Inflector $inflector,
        private array $config
    ) {}

    public function translate(array $orders, $builder = null): string
    {
        if (empty($orders)) {
            return '';
        }

        $orderPhrases = [];

        foreach ($orders as $order) {
            $phrase = $this->translateSingleOrder($order, $builder);
            if ($phrase) {
                $orderPhrases[] = $phrase;
            }
        }

        if (empty($orderPhrases)) {
            return '';
        }

        $connector = $this->config['connectors']['ordering'] ?? 'sorted by';

        return $connector.' '.$this->inflector->joinWithConnector($orderPhrases, 'then');
    }

    private function translateSingleOrder(array $order, $builder = null): string
    {
        $originalColumn = $order['column'];
        $direction = strtolower($order['direction'] ?? 'asc');

        $column = $this->humanizeColumnForOrdering($originalColumn);

        if ($direction === 'desc') {
            return $this->getNaturalDescendingDescription($column, $originalColumn, $builder);
        } else {
            return $this->getNaturalAscendingDescription($column, $originalColumn, $builder);
        }
    }

    private function humanizeColumnForOrdering(string $column): string
    {
        return match ($column) {
            'created_at' => 'creation date',
            'updated_at' => 'last modified date',
            'deleted_at' => 'deletion date',
            'published_at' => 'publication date',
            'email_verified_at' => 'email verification date',
            'remember_token_expires_at' => 'remember token expiration',
            default => $this->inflector->humanizeFieldName($column),
        };
    }

    private function getNaturalDescendingDescription(string $column, string $originalColumn, $builder = null): string
    {

        if ($this->isDateColumn($originalColumn, $builder)) {
            return "{$column} (newest to oldest)";
        }

        $fieldType = $this->getFieldType($originalColumn, $builder);

        return match ($fieldType) {
            'datetime', 'timestamp', 'date' => "{$column} (newest to oldest)",
            'integer', 'bigint', 'decimal', 'float', 'double' => "{$column} (highest to lowest)",
            default => "{$column} (Z to A)",
        };
    }

    private function getNaturalAscendingDescription(string $column, string $originalColumn, $builder = null): string
    {

        if ($this->isDateColumn($originalColumn, $builder)) {
            return "{$column} (oldest to newest)";
        }

        $fieldType = $this->getFieldType($originalColumn, $builder);

        return match ($fieldType) {
            'datetime', 'timestamp', 'date' => "{$column} (oldest to newest)",
            'integer', 'bigint', 'decimal', 'float', 'double' => "{$column} (lowest to highest)",
            default => "{$column} (A to Z)",
        };
    }

    private function getFieldType(string $fieldName, $builder = null): string
    {
        if ($builder && method_exists($builder, 'getModel')) {
            try {
                $model = $builder->getModel();

                $casts = $model->getCasts();
                if (isset($casts[$fieldName])) {
                    return $this->normalizeCastType($casts[$fieldName]);
                }

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

        return 'string';
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

    private function isDateColumn(string $column, $builder = null): bool
    {
        if (in_array($column, ['created_at', 'updated_at', 'deleted_at', 'email_verified_at', 'last_used_at'])) {
            return true;
        }

        if ($builder && method_exists($builder, 'getModel')) {
            try {
                $model = $builder->getModel();

                $casts = $model->getCasts();
                if (isset($casts[$column])) {
                    $castType = strtolower($casts[$column]);

                    $castType = explode(':', $castType)[0];

                    if (in_array($castType, ['date', 'datetime', 'timestamp', 'time'])) {
                        return true;
                    }
                }

                $table = $model->getTable();
                $connection = $model->getConnection();
                $columnType = $connection->getDoctrineColumn($table, $column)->getType();

                if ($columnType) {
                    $typeName = $columnType->getName();
                    if (in_array($typeName, ['date', 'datetime', 'datetimetz', 'time', 'timestamp'])) {
                        return true;
                    }
                }
            } catch (\Exception $e) {

            }
        }

        return (bool) preg_match('/(date|time|at)$/i', $column);
    }
}
