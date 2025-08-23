<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

class BooleanFieldHandler
{
    public function __construct(private FieldTypeDetector $fieldTypeDetector) {}

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
        $isPositiveCondition = ($operator === '=' && $isTrue) || ($operator === '!=' && ! $isTrue);

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
            return $isPositive
                ? "that are {$matches[1]}"
                : "that are not {$matches[1]}";
        }

        if (preg_match('/^can\s+(.+)/', $column, $matches)) {
            return $isPositive
                ? "that can {$matches[1]}"
                : "that can't {$matches[1]}";
        }

        return $isPositive
            ? "with {$column}"
            : "without {$column}";
    }

    public function shouldUseBooleanTranslation(string $fieldName, mixed $value, string $operator, $builder = null): bool
    {
        // Check if field is detected as boolean field
        if ($this->fieldTypeDetector->isBooleanField($fieldName, $builder) && in_array($operator, ['=', '!='])) {
            return true;
        }

        // Check if value is boolean-like and operator is equality
        if ($this->isBooleanValue($value) && in_array($operator, ['=', '!='])) {
            return true;
        }

        return false;
    }
}
