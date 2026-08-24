<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

class BooleanFieldHandler
{
    public function __construct(
        private FieldTypeDetector $fieldTypeDetector,
        private ?Inflector $inflector = null
    ) {}

    public function isBooleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        if (is_string($value)) {
            $normalizedValue = strtolower(trim($value));

            return in_array($normalizedValue, ['true', 'false']);
        }

        return false;
    }

    public function translateBooleanCondition(string $column, string $operator, mixed $value): string
    {
        $isTrue = ($value === true || $value === 1 || $value === '1' || $value === 'true');
        $isPositiveCondition = (in_array($operator, ['=']) && $isTrue) || (in_array($operator, ['!=', '<>']) && ! $isTrue);

        return $this->buildNaturalBooleanPhrase($column, $isPositiveCondition);
    }

    public function buildNaturalBooleanPhrase(string $column, bool $isPositive): string
    {
        if (preg_match('/^has\s+(.+)/', $column, $matches)) {
            return $isPositive
                ? "that have {$matches[1]}"
                : "that don't have {$matches[1]}";
        }

        if (preg_match('/^is\s+(.+)/', $column, $matches)) {
            $complement = $this->inflector?->pluralizeNounPhrase($matches[1]) ?? $matches[1];

            return $isPositive
                ? "that are {$complement}"
                : "that are not {$complement}";
        }

        if (preg_match('/^can\s+(.+)/', $column, $matches)) {
            return $isPositive
                ? "that can {$matches[1]}"
                : "that can't {$matches[1]}";
        }

        // "requires shipping" → "that require shipping" (plural subject)
        if (preg_match('/^(requires|needs|allows|accepts|supports)\s+(.+)/', $column, $matches)) {
            $baseVerb = substr($matches[1], 0, -1);

            return $isPositive
                ? "that {$baseVerb} {$matches[2]}"
                : "that don't {$baseVerb} {$matches[2]}";
        }

        if (str_ends_with($column, ' eligible')) {
            return $isPositive
                ? "that are {$column}"
                : "that are not {$column}";
        }

        return $isPositive
            ? "with {$column}"
            : "without {$column}";
    }

    public function shouldUseBooleanTranslation(string $fieldName, mixed $value, string $operator, $builder = null): bool
    {
        if ($this->fieldTypeDetector->isBooleanField($fieldName, $builder) && in_array($operator, ['=', '!=', '<>'])) {
            return true;
        }

        if ($this->isBooleanValue($value) && in_array($operator, ['=', '!=', '<>'])) {
            return true;
        }

        return false;
    }
}
