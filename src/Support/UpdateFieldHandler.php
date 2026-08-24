<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

class UpdateFieldHandler
{
    public function __construct(
        private Inflector $inflector,
        private FieldTypeDetector $fieldTypeDetector
    ) {}

    public function translateUpdateFields(array $updateData, $builder = null): string
    {
        if (empty($updateData)) {
            return '';
        }

        $fieldTranslations = [];

        foreach ($updateData as $field => $value) {
            $translation = $this->translateSingleField($field, $value, $builder);
            if ($translation) {
                $fieldTranslations[] = $translation;
            }
        }

        if (empty($fieldTranslations)) {
            return '';
        }

        if (count($fieldTranslations) === 1) {
            return $fieldTranslations[0];
        }

        // Handle multiple fields with proper conjunction
        return $this->inflector->joinWithConnector($fieldTranslations, 'and');
    }

    private function translateSingleField(string $field, mixed $value, $builder = null): string
    {
        // Handle null values - field being unset
        if ($value === null) {
            return $this->translateNullField($field);
        }

        // Handle boolean fields using existing BooleanFieldHandler
        if ($this->fieldTypeDetector->isBooleanField($field, $builder)) {
            return $this->translateBooleanField($field, $value);
        }

        // Handle Laravel timestamp fields with special phrasing
        if ($this->fieldTypeDetector->isLaravelTimestampField($field)) {
            return $this->translateTimestampField($field, $value);
        }

        // Handle status-like fields
        if ($this->isStatusField($field)) {
            return $this->translateStatusField($field, $value);
        }

        // Handle date fields
        if ($this->fieldTypeDetector->isDateField($field, $builder)) {
            return $this->translateDateField($field, $value);
        }

        // Handle numeric fields with special phrasing
        if (is_numeric($value)) {
            return $this->translateNumericField($field, $value);
        }

        // Default string field handling
        return $this->translateStringField($field, $value);
    }

    private function translateNullField(string $field): string
    {
        $humanizedField = $this->inflector->humanizeFieldName($field);

        // Special cases for common null operations
        return match ($field) {
            'deleted_at' => 'not be deleted',
            'published_at' => 'be unpublished',
            'verified_at' => 'be unverified',
            'archived_at' => 'be unarchived',
            'banned_at' => 'be unbanned',
            default => "have no {$humanizedField}",
        };
    }

    private function translateBooleanField(string $field, mixed $value): string
    {
        $humanizedField = $this->inflector->humanizeFieldName($field);

        if (preg_match('/^(requires|needs|allows|accepts|supports)\s+(.+)/', $humanizedField, $matches)) {
            $baseVerb = substr($matches[1], 0, -1);

            return $value ? "{$baseVerb} {$matches[2]}" : "no longer {$baseVerb} {$matches[2]}";
        }

        if (preg_match('/^has\s+(.+)/', $humanizedField, $matches)) {
            return $value ? "have {$matches[1]}" : "not have {$matches[1]}";
        }

        if (preg_match('/^(is|should|will)\s+(.+)/', $humanizedField, $matches)) {
            return $value ? "be {$matches[2]}" : "not be {$matches[2]}";
        }

        if (preg_match('/^can\s+(.+)/', $humanizedField, $matches)) {
            return $value ? "be able to {$matches[1]}" : "not be able to {$matches[1]}";
        }

        return $value ? "have {$humanizedField}" : "not have {$humanizedField}";
    }

    private function translateTimestampField(string $field, mixed $value): string
    {
        return match ($field) {
            'created_at' => 'be created',
            'updated_at' => 'be updated',
            'deleted_at' => 'be deleted',
            'published_at' => 'be published',
            'verified_at', 'email_verified_at' => 'be verified',
            'archived_at' => 'be archived',
            'banned_at' => 'be banned',
            default => "have {$this->inflector->humanizeFieldName($field)}",
        };
    }

    private function translateStatusField(string $field, mixed $value): string
    {
        $humanizedField = $this->inflector->humanizeFieldName($field);
        $humanizedValue = $this->inflector->humanizeFieldName((string) $value);

        return "have {$humanizedValue} {$humanizedField}";
    }

    private function translateDateField(string $field, mixed $value): string
    {
        $humanizedField = $this->inflector->humanizeFieldName($field);
        $formattedValue = $this->formatDateValue($value);

        return "have {$humanizedField} set to {$formattedValue}";
    }

    private function translateNumericField(string $field, mixed $value): string
    {
        $humanizedField = $this->inflector->humanizeFieldName($field);

        // Special handling for common numeric fields
        return match ($field) {
            'priority' => "have priority {$value}",
            'sort_order' => "have sort order {$value}",
            'position' => "have position {$value}",
            'rank' => "have rank {$value}",
            'score' => "have score {$value}",
            'rating' => "have rating {$value}",
            'points' => "have {$value} points",
            'count' => "have count {$value}",
            'quantity' => "have quantity {$value}",
            'amount' => "have amount {$value}",
            default => "have {$humanizedField} {$value}",
        };
    }

    private function translateStringField(string $field, mixed $value): string
    {
        $humanizedField = $this->inflector->humanizeFieldName($field);
        $formattedValue = $this->formatStringValue($value);

        // For fields that are clearly identifiers or names
        if ($this->isIdentifierField($field)) {
            return "have {$humanizedField} {$formattedValue}";
        }

        // For description-like fields
        if ($this->isDescriptionField($field)) {
            return "have {$humanizedField} set to {$formattedValue}";
        }

        return "have {$humanizedField} {$formattedValue}";
    }

    private function isStatusField(string $field): bool
    {
        return (bool) preg_match('/(status|state|type|category|level|grade|phase|stage)$/i', $field);
    }

    private function isIdentifierField(string $field): bool
    {
        return (bool) preg_match('/(id|code|key|token|uuid|slug|handle)$/i', $field);
    }

    private function isDescriptionField(string $field): bool
    {
        return (bool) preg_match('/(description|comment|note|content|text|body|summary)$/i', $field);
    }

    private function formatDateValue(mixed $value): string
    {
        try {
            // If it's already a formatted date string, use the inflector to humanize it
            if (is_string($value) && $this->looksLikeDate($value)) {
                return $this->inflector->humanizeDate($value);
            }

            // Handle special cases
            if (is_object($value) && method_exists($value, 'format')) {
                return $this->inflector->humanizeDate($value);
            }

            return (string) $value;
        } catch (\Exception) {
            return (string) $value;
        }
    }

    private function formatStringValue(mixed $value): string
    {
        $stringValue = (string) $value;

        // Don't quote single words or identifiers
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $stringValue)) {
            return $stringValue;
        }

        // Quote strings with spaces or special characters
        return "'{$stringValue}'";
    }

    private function looksLikeDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?/', $value);
    }
}
