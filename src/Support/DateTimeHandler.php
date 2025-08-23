<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

use Carbon\Carbon;

class DateTimeHandler
{
    public function __construct(
        private Inflector $inflector,
        private FieldTypeDetector $fieldTypeDetector
    ) {}

    public function detectCarbonOperation(string $column, string $operator, mixed $value, $builder = null): ?string
    {
        if (! $this->fieldTypeDetector->isDateField($column, $builder)) {
            return null;
        }

        if (! $this->looksLikeDate((string) $value)) {
            return null;
        }

        if ($this->isStaticDateString($value)) {
            return $this->handleStaticDateString($column, $operator, $value);
        }

        // Try to detect if this is a Carbon operation result
        $relativeTime = $this->inflector->humanizeDate($value);

        if ($relativeTime && ! $this->looksLikeDate($relativeTime)) {
            $naturalPhrase = $this->buildNaturalCarbonPhrase($column, $operator, $relativeTime);
            if ($naturalPhrase) {
                return $naturalPhrase;
            }

            $humanizedColumn = $this->inflector->humanizeFieldName($column);
            $beforePhrase = $this->convertToBeforePhrase($relativeTime, $column);

            return match ($operator) {
                '>' => "with {$humanizedColumn} {$relativeTime}",
                '>=' => "with {$humanizedColumn} {$relativeTime}",
                '<' => "with {$humanizedColumn} {$beforePhrase}",
                '<=' => "with {$humanizedColumn} {$beforePhrase}",
                default => null,
            };
        }

        return null;
    }

    public function translateCarbonBetween(string $column, string $originalColumn, Carbon $start, Carbon $end): string
    {
        $now = Carbon::now();

        // Handle long time ranges (over 1 year)
        if ($end->diffInYears($now) > 1) {
            $startFormatted = $this->inflector->humanizeDate($start);
            $endFormatted = $this->inflector->humanizeDate($end);

            return "with {$column} between {$startFormatted} and {$endFormatted}";
        }

        // Handle special date ranges
        if ($start->isStartOfDay() && $end->isEndOfDay() && $start->isToday() && $end->isToday()) {
            return "with {$column} today";
        }

        if ($start->isStartOfDay() && $end->isEndOfDay() && $start->isYesterday() && $end->isYesterday()) {
            return "with {$column} yesterday";
        }

        if ($start->isStartOfWeek() && $end->isEndOfWeek() && $start->isSameWeek($now) && $end->isSameWeek($now)) {
            return "with {$column} this week";
        }

        if ($start->isStartOfWeek() && $end->isEndOfWeek() &&
            $start->isSameWeek($now->copy()->subWeek()) && $end->isSameWeek($now->copy()->subWeek())) {
            return "with {$column} last week";
        }

        if ($start->isStartOfMonth() && $end->isEndOfMonth() && $start->isSameMonth($now) && $end->isSameMonth($now)) {
            return "with {$column} this month";
        }

        // Try to detect relative ranges
        $relativeRange = $this->detectRelativeRange($column, $originalColumn, $start, $end, $now);
        if ($relativeRange) {
            return $relativeRange;
        }

        // Format the dates naturally
        $startFormatted = $this->formatDateForBetween($start, $now);
        $endFormatted = $this->formatDateForBetween($end, $now);

        return "with {$column} between {$startFormatted} and {$endFormatted}";
    }

    public function convertToBeforePhrase(string $relativeTime, ?string $column = null): string
    {
        // Handle "in the next" phrases
        if (preg_match('/^in the next (.+)$/', $relativeTime)) {
            return $relativeTime;
        }

        // Convert "within the last" to "more than X ago"
        if (preg_match('/^within the last (.+)$/', $relativeTime, $matches)) {
            $timeUnit = $matches[1];

            if ($this->fieldTypeDetector->isLaravelTimestampField($column)) {
                return "older than {$timeUnit} ago";
            }

            return "more than {$timeUnit} ago";
        }

        // Convert other relative times to "before" phrases
        return "before {$relativeTime}";
    }

    public function buildLaravelTimestampPhrase(string $column, string $operator, string $relativeTime, string $beforePhrase): ?string
    {
        if (! $this->fieldTypeDetector->isLaravelTimestampField($column)) {
            return null;
        }

        return match ($column) {
            'created_at' => match ($operator) {
                '>' => "created {$relativeTime}",
                '>=' => "created {$relativeTime}",
                '<' => "created {$beforePhrase}",
                '<=' => "created {$beforePhrase}",
                '=' => "created exactly {$relativeTime}",
                '!=' => "not created {$relativeTime}",
                default => null,
            },
            'updated_at' => match ($operator) {
                '>' => "updated {$relativeTime}",
                '>=' => "updated {$relativeTime}",
                '<' => "updated {$beforePhrase}",
                '<=' => "updated {$beforePhrase}",
                '=' => "updated exactly {$relativeTime}",
                '!=' => "not updated {$relativeTime}",
                default => null,
            },
            'deleted_at' => match ($operator) {
                '>' => "deleted {$relativeTime}",
                '>=' => "deleted {$relativeTime}",
                '<' => "deleted {$beforePhrase}",
                '<=' => "deleted {$beforePhrase}",
                '=' => "deleted exactly {$relativeTime}",
                '!=' => "not deleted {$relativeTime}",
                default => null,
            },
            'email_verified_at' => match ($operator) {
                '>' => "who verified their email {$relativeTime}",
                '>=' => "who verified their email {$relativeTime}",
                '<' => "who verified their email {$beforePhrase}",
                '<=' => "who verified their email {$beforePhrase}",
                '=' => "who verified their email exactly {$relativeTime}",
                '!=' => "who didn't verify their email {$relativeTime}",
                default => null,
            },
            'last_used_at' => match ($operator) {
                '>' => "last used {$relativeTime}",
                '>=' => "last used {$relativeTime}",
                '<' => "last used {$beforePhrase}",
                '<=' => "last used {$beforePhrase}",
                '=' => "last used exactly {$relativeTime}",
                '!=' => "not used {$relativeTime}",
                default => null,
            },
            default => null,
        };
    }

    private function looksLikeDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?/', $value);
    }

    private function isStaticDateString(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        if (! preg_match('/^\d{4}-\d{1,2}-\d{1,2}(\s+\d{1,2}:\d{2}:\d{2})?$/', $value)) {
            return false;
        }

        try {
            $carbon = Carbon::parse($value);

            if ($this->couldBeCarbonOperation($carbon)) {
                return false;
            }

            $diffInMinutes = abs($carbon->diffInMinutes(Carbon::now()));
            if ($diffInMinutes < 2) {
                return false;
            }

            return true;
        } catch (\Exception) {
            return true;
        }
    }

    private function couldBeCarbonOperation(Carbon $carbon): bool
    {
        $now = Carbon::now();

        $operations = [
            ['method' => 'subMinutes', 'range' => range(1, 59)],
            ['method' => 'addMinutes', 'range' => range(1, 59)],
            ['method' => 'subHours', 'range' => range(1, 23)],
            ['method' => 'addHours', 'range' => range(1, 23)],
            ['method' => 'subDays', 'range' => range(1, 30)],
            ['method' => 'addDays', 'range' => range(1, 30)],
            ['method' => 'subWeeks', 'range' => range(1, 12)],
            ['method' => 'addWeeks', 'range' => range(1, 12)],
            ['method' => 'subMonths', 'range' => range(1, 12)],
            ['method' => 'addMonths', 'range' => range(1, 12)],
            ['method' => 'subYears', 'range' => range(1, 5)],
            ['method' => 'addYears', 'range' => range(1, 5)],
        ];

        foreach ($operations as $operation) {
            foreach ($operation['range'] as $amount) {
                $testDate = $now->copy()->{$operation['method']}($amount);

                // For date-only comparisons (when time is 00:00:00), normalize both dates
                $carbonToCompare = $carbon;
                $testDateToCompare = $testDate;

                if ($carbon->format('H:i:s') === '00:00:00') {
                    // Input is date-only, normalize test date to start of day for comparison
                    $testDateToCompare = $testDate->copy()->startOfDay();
                }

                $tolerance = match ($operation['method']) {
                    'subMinutes', 'addMinutes' => 60,
                    'subHours', 'addHours' => 600,
                    'subDays', 'addDays' => 3600,
                    'subWeeks', 'addWeeks' => 7200,
                    'subMonths', 'addMonths', 'subYears', 'addYears' => 86400,
                };

                if (abs($carbonToCompare->diffInSeconds($testDateToCompare)) <= $tolerance) {
                    return true;
                }
            }
        }

        return false;
    }

    private function handleStaticDateString(string $column, string $operator, mixed $value): ?string
    {
        try {
            $carbon = Carbon::parse((string) $value);
            $humanizedColumn = $this->inflector->humanizeFieldName($column);

            $formattedDate = $carbon->format('F j, Y');
            if ($carbon->format('H:i:s') !== '00:00:00') {
                $formattedDate .= ' at '.$carbon->format('g:i A');
            }

            return match ($operator) {
                '>' => "with {$humanizedColumn} after {$formattedDate}",
                '>=' => "with {$humanizedColumn} on or after {$formattedDate}",
                '<' => "with {$humanizedColumn} before {$formattedDate}",
                '<=' => "with {$humanizedColumn} on or before {$formattedDate}",
                '=' => "with {$humanizedColumn} on {$formattedDate}",
                '!=' => "with {$humanizedColumn} not on {$formattedDate}",
                default => null,
            };
        } catch (\Exception) {
            return null;
        }
    }

    private function buildNaturalCarbonPhrase(string $column, string $operator, string $relativeTime): ?string
    {
        $beforePhrase = $this->convertToBeforePhrase($relativeTime, $column);

        $laravelTimestampPhrase = $this->buildLaravelTimestampPhrase($column, $operator, $relativeTime, $beforePhrase);
        if ($laravelTimestampPhrase) {
            return $laravelTimestampPhrase;
        }

        $humanizedColumn = $this->inflector->humanizeFieldName($column);

        return match ($operator) {
            '>' => "with {$humanizedColumn} {$relativeTime}",
            '>=' => "with {$humanizedColumn} {$relativeTime}",
            '<' => "with {$humanizedColumn} {$beforePhrase}",
            '<=' => "with {$humanizedColumn} {$beforePhrase}",
            '=' => "with {$humanizedColumn} exactly {$relativeTime}",
            '!=' => "with {$humanizedColumn} not {$relativeTime}",
            default => null,
        };
    }

    private function detectRelativeRange(string $column, string $originalColumn, Carbon $start, Carbon $end, Carbon $now): ?string
    {
        // Check if end is approximately now
        if (abs($end->diffInMinutes($now)) <= 5) {
            // Test for various time ranges
            for ($days = 1; $days <= 365; $days++) {
                $testStart = $now->copy()->subDays($days);
                if (abs($start->diffInMinutes($testStart)) <= 60) {
                    if ($days === 1) {
                        return "with {$column} in the last day";
                    } elseif ($days === 7) {
                        return "with {$column} in the last week";
                    } elseif ($days === 30 || $days === 31) {
                        return "with {$column} in the last month";
                    } elseif ($days === 365) {
                        return "with {$column} in the last year";
                    } else {
                        return "with {$column} in the last {$days} days";
                    }
                }
            }

            // Test for weeks
            for ($weeks = 1; $weeks <= 12; $weeks++) {
                $testStart = $now->copy()->subWeeks($weeks);
                if (abs($start->diffInHours($testStart)) <= 2) {
                    return $weeks === 1 ? "with {$column} in the last week" : "with {$column} in the last {$weeks} weeks";
                }
            }

            // Test for months
            for ($months = 1; $months <= 12; $months++) {
                $testStart = $now->copy()->subMonths($months);
                if (abs($start->diffInDays($testStart)) <= 1) {
                    return $months === 1 ? "with {$column} in the last month" : "with {$column} in the last {$months} months";
                }
            }
        }

        return null;
    }

    private function formatDateForBetween(Carbon $date, Carbon $now): string
    {
        // Try to get natural relative time first
        if ($date->diffInDays($now) <= 30) {
            $relativeTime = $this->inflector->humanizeDate($date);

            if (! str_contains($relativeTime, 'within the last') && ! str_contains($relativeTime, 'days ago')) {
                return $relativeTime;
            }
        }

        // Format as date
        if ($date->isSameYear($now)) {
            return $date->format('F j');
        } else {
            return $date->format('F j, Y');
        }
    }
}
