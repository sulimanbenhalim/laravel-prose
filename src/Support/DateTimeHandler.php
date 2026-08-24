<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class DateTimeHandler
{
    private RelativeTimeFormatter $formatter;

    public function __construct(
        private Inflector $inflector,
        private FieldTypeDetector $fieldTypeDetector
    ) {
        $this->formatter = new RelativeTimeFormatter;
    }

    /**
     * Translate a comparison against a datetime value into natural language,
     * or null when the value is not date-like or the column is not a date field.
     *
     * $dateOnly marks date-granular comparisons (whereDate), which are always
     * phrased against the calendar.
     */
    public function describeDateCondition(string $column, string $operator, mixed $value, $builder = null, bool $dateOnly = false): ?string
    {
        if (! $this->fieldTypeDetector->isDateField($column, $builder)) {
            return null;
        }

        $carbon = $this->toCarbon($value);
        if ($carbon === null) {
            return null;
        }

        $phrase = $this->formatter->conditionPhrase($carbon, $operator, $dateOnly);

        return $this->composeWithColumn($column, $phrase, $builder);
    }

    /** Kept for backward compatibility with the old entry point. */
    public function detectCarbonOperation(string $column, string $operator, mixed $value, $builder = null): ?string
    {
        return $this->describeDateCondition($column, $operator, $value, $builder);
    }

    public function translateCarbonBetween(string $column, string $originalColumn, CarbonInterface $start, CarbonInterface $end): string
    {
        $phrase = $this->formatter->rangePhrase($start, $end);

        return $this->composeWithColumn($originalColumn, $phrase, null, $column);
    }

    public function describePoint(mixed $value): ?string
    {
        $carbon = $this->toCarbon($value);

        return $carbon === null ? null : $this->formatter->describePoint($carbon);
    }

    /**
     * "created within the last 7 days" for verb-like columns,
     * "with scheduled datetime within the last 7 days" otherwise.
     */
    private function composeWithColumn(string $column, string $phrase, $builder = null, ?string $humanized = null): string
    {
        $verb = $this->verbFor($column);

        if ($verb !== null) {
            return "{$verb} {$phrase}";
        }

        $humanized ??= $this->inflector->humanizeFieldName($column, $builder);

        return "with {$humanized} {$phrase}";
    }

    private function verbFor(string $column): ?string
    {
        $special = match ($column) {
            'created_at' => 'created',
            'updated_at' => 'updated',
            'deleted_at' => 'deleted',
            'email_verified_at' => 'who verified their email',
            'last_used_at' => 'last used',
            'last_login_at' => 'with last login',
            default => null,
        };

        if ($special !== null) {
            return $special;
        }

        if (! preg_match('/^(.+?)_(?:date|at|datetime|on)$/', $column, $matches)) {
            return null;
        }

        $words = explode('_', $matches[1]);
        $lastWord = end($words);

        // Only use verb phrasing when the field name itself carries a past
        // participle ("created", "scheduled", "estimated"), so we never
        // conjugate nouns like "arrival" or "work".
        if (strlen($lastWord) <= 3 || ! str_ends_with($lastWord, 'ed')) {
            return null;
        }

        $prefix = count($words) > 1 ? implode(' ', array_slice($words, 0, -1)).' ' : '';

        return $prefix.$lastWord;
    }

    private function toCarbon(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value) && ! (is_object($value) && method_exists($value, '__toString'))) {
            return null;
        }

        $string = (string) $value;

        if (! preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', $string)) {
            return null;
        }

        try {
            return Carbon::parse($string);
        } catch (\Exception) {
            return null;
        }
    }
}
