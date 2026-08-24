<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Translators;

use SulimanBenhalim\Prose\Support\FieldTypeDetector;
use SulimanBenhalim\Prose\Support\Inflector;

class OrderTranslator
{
    private FieldTypeDetector $fieldTypeDetector;

    public function __construct(
        private Inflector $inflector,
        private array $config
    ) {
        $this->fieldTypeDetector = new FieldTypeDetector;
    }

    public function translate(array $orders, $builder = null): string
    {
        if (empty($orders)) {
            return '';
        }

        $orderPhrases = [];
        $random = false;

        foreach ($orders as $order) {
            if (! isset($order['column']) || ! is_string($order['column'])) {
                if ($this->isRandomOrder($order)) {
                    $random = true;
                }

                continue;
            }

            $phrase = $this->translateSingleOrder($order, $builder);
            if ($phrase) {
                $orderPhrases[] = $phrase;
            }
        }

        if (empty($orderPhrases)) {
            return $random ? 'in random order' : '';
        }

        $connector = $this->config['connectors']['ordering'] ?? 'sorted by';

        return $connector.' '.$this->inflector->joinWithConnector($orderPhrases, 'then');
    }

    private function isRandomOrder(array $order): bool
    {
        $sql = strtolower((string) ($order['sql'] ?? ''));

        return str_contains($sql, 'random') || str_contains($sql, 'rand(');
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
        return $this->fieldTypeDetector->getFieldType($fieldName, $builder);
    }

    private function isDateColumn(string $column, $builder = null): bool
    {
        return $this->fieldTypeDetector->isDateField($column, $builder);
    }
}
