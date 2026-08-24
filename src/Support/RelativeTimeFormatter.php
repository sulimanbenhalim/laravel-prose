<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Deterministic natural-language formatting of datetime values relative to now.
 *
 * Two families of values are treated differently:
 *  - "clock" values carry a time of day (usually derived from now(), e.g.
 *    now()->subDays(7)) and are phrased in rolling units: "within the last
 *    7 days", "more than 2 hours ago", "in the next 3 weeks".
 *  - "calendar" values sit at midnight (today(), whereDate() comparisons,
 *    plain date strings) and are phrased against the calendar: "today",
 *    "since yesterday", "before June 15, 2024" — never in clock units, so
 *    the description cannot change with the time of day.
 */
class RelativeTimeFormatter
{
    /** Slack for the second-level truncation between building and describing a query. */
    private const TOLERANCE_SECONDS = 5;

    private const NEAR_NOW_SECONDS = 60;

    /**
     * Full condition phrase for "column {op} value", excluding the column.
     * E.g. "within the last 7 days", "today or later", "after June 15, 2024".
     */
    public function conditionPhrase(CarbonInterface $value, string $operator, bool $dateOnly = false): string
    {
        $now = Carbon::now();

        if ($dateOnly || $this->isMidnight($value)) {
            return $this->calendarPhrase($value, $operator, $now);
        }

        if ($this->isEndOfPeriod($value, $now)) {
            return $this->endOfPeriodPhrase($value, $operator, $now);
        }

        $diff = $now->getTimestamp() - $value->getTimestamp();

        if (abs($diff) <= self::NEAR_NOW_SECONDS) {
            return $this->nearNowPhrase($operator);
        }

        $span = $this->exactSpan($value, $now);

        if ($span === null) {
            $span = $this->approximateSpan(abs($diff));
        }

        if ($span !== null) {
            return $diff > 0
                ? $this->pastPhrase($operator, $span)
                : $this->futurePhrase($operator, $span);
        }

        return $this->absolutePhrase($value, $operator, $now, false);
    }

    /**
     * Operator-free description of a point in time: "today", "3 days ago",
     * "a month from now", "June 15, 2024 at 9:30 AM".
     */
    public function describePoint(CarbonInterface $value): string
    {
        $now = Carbon::now();

        if ($this->isMidnight($value)) {
            return $this->calendarDayWord($value, $now);
        }

        $diff = $now->getTimestamp() - $value->getTimestamp();

        if (abs($diff) <= self::NEAR_NOW_SECONDS) {
            return 'now';
        }

        $span = $this->exactSpan($value, $now);
        if ($span !== null) {
            return $diff > 0
                ? $this->articled($span).' ago'
                : $this->articled($span).' from now';
        }

        return $this->absoluteWord($value, $now);
    }

    /**
     * Phrase for a between range. E.g. "in the last week", "in the next
     * 7 days", "today", "between January 1, 2024 and December 31, 2024".
     */
    public function rangePhrase(CarbonInterface $start, CarbonInterface $end): string
    {
        $now = Carbon::now();

        // Whole calendar periods
        if ($this->isMidnight($start) && $this->isEndOfDayTime($end)) {
            if ($start->isSameDay($end)) {
                if ($start->isToday()) {
                    return 'today';
                }
                if ($start->isYesterday()) {
                    return 'yesterday';
                }
                if ($start->isTomorrow()) {
                    return 'tomorrow';
                }

                return 'on '.$this->absoluteWord($start, $now, false);
            }

            if ($start->isSameWeek($now) && $end->isSameWeek($now)
                && $start->eq($now->copy()->startOfWeek()) && $this->sameSecond($end, $now->copy()->endOfWeek())) {
                return 'this week';
            }

            $lastWeek = $now->copy()->subWeek();
            if ($start->eq($lastWeek->copy()->startOfWeek()) && $this->sameSecond($end, $lastWeek->copy()->endOfWeek())) {
                return 'last week';
            }

            if ($start->eq($now->copy()->startOfMonth()) && $this->sameSecond($end, $now->copy()->endOfMonth())) {
                return 'this month';
            }

            $lastMonth = $now->copy()->subMonthNoOverflow();
            if ($start->eq($lastMonth->copy()->startOfMonth()) && $this->sameSecond($end, $lastMonth->copy()->endOfMonth())) {
                return 'last month';
            }

            if ($start->eq($now->copy()->startOfYear()) && $this->sameSecond($end, $now->copy()->endOfYear())) {
                return 'this year';
            }
        }

        $startDiff = $now->getTimestamp() - $start->getTimestamp();
        $endDiff = $now->getTimestamp() - $end->getTimestamp();

        // Rolling window ending now: "in the last N"
        if (abs($endDiff) <= self::NEAR_NOW_SECONDS && $startDiff > 0) {
            $span = $this->exactSpan($start, $now) ?? $this->approximateSpan($startDiff);
            if ($span !== null) {
                return 'in the last '.$this->bare($span);
            }
        }

        // Rolling window starting now: "in the next N"
        if (abs($startDiff) <= self::NEAR_NOW_SECONDS && $endDiff < 0) {
            $span = $this->exactSpan($end, $now) ?? $this->approximateSpan(-$endDiff);
            if ($span !== null) {
                return 'in the next '.$this->bare($span);
            }
        }

        // Calendar window starting today: "in the next N days"
        if ($this->isMidnight($start) && $start->isToday() && $this->isMidnight($end) && $end->isAfter($start)) {
            $days = (int) round($start->diffInDays($end, true));

            return $days === 1 ? 'today or tomorrow' : "in the next {$days} days";
        }

        return 'between '.$this->describePoint($start).' and '.$this->describePoint($end);
    }

    // ---------------------------------------------------------------
    // Calendar (date-granular) phrasing
    // ---------------------------------------------------------------

    private function calendarPhrase(CarbonInterface $value, string $operator, Carbon $now): string
    {
        $today = $now->copy()->startOfDay();
        $day = $value->copy()->startOfDay();
        $offset = (int) round($day->diffInDays($today, true)) * ($day->lessThan($today) ? 1 : -1);

        $periodStart = $this->startOfPeriodPhrase($day, $operator, $now);
        if ($periodStart !== null) {
            return $periodStart;
        }

        $word = fn () => $this->absoluteWord($day, $now, false);

        return match ($operator) {
            '=' => match (true) {
                $offset === 0 => 'today',
                $offset === 1 => 'yesterday',
                $offset === -1 => 'tomorrow',
                $offset >= 2 && $offset <= 30 => "{$offset} days ago",
                $offset <= -2 && $offset >= -30 => 'in '.(-$offset).' days',
                default => 'on '.$word(),
            },
            '>=' => match (true) {
                $offset === 0 => 'today or later',
                $offset === 1 => 'since yesterday',
                $offset === -1 => 'tomorrow or later',
                $offset >= 2 && $offset <= 30 => "within the last {$offset} days",
                default => 'on or after '.$word(),
            },
            '>' => match (true) {
                $offset === 0 => 'after today',
                $offset === 1 => 'today or later',
                $offset === -1 => 'after tomorrow',
                $offset === 2 => 'since yesterday',
                $offset >= 3 && $offset <= 31 => 'within the last '.($offset - 1).' days',
                default => 'after '.$word(),
            },
            '<' => match (true) {
                $offset === 0 => 'before today',
                $offset === 1 => 'before yesterday',
                $offset === -1 => 'before tomorrow',
                $offset >= 2 && $offset <= 30 => "more than {$offset} days ago",
                default => 'before '.$word(),
            },
            '<=' => match (true) {
                $offset === 0 => 'today or earlier',
                $offset === 1 => 'yesterday or earlier',
                $offset === -1 => 'by tomorrow',
                $offset >= 2 && $offset <= 30 => "{$offset} days ago or earlier",
                default => 'on or before '.$word(),
            },
            '!=' => match (true) {
                $offset === 0 => 'not today',
                $offset === 1 => 'not yesterday',
                $offset === -1 => 'not tomorrow',
                default => 'not on '.$word(),
            },
            default => 'on '.$word(),
        };
    }

    private function startOfPeriodPhrase(CarbonInterface $day, string $operator, Carbon $now): ?string
    {
        $period = match (true) {
            $day->eq($now->copy()->startOfYear()->startOfDay()) && ! $day->isToday() => 'this year',
            $day->eq($now->copy()->startOfMonth()->startOfDay()) && ! $day->isToday() => 'this month',
            $day->eq($now->copy()->startOfWeek()->startOfDay()) && ! $day->isToday() => 'this week',
            default => null,
        };

        if ($period === null) {
            return null;
        }

        return match ($operator) {
            '>', '>=' => $period,
            '<' => "before {$period}",
            '<=' => 'before '.$period,
            '=' => "at the start of {$period}",
            default => null,
        };
    }

    private function isEndOfPeriod(CarbonInterface $value, Carbon $now): bool
    {
        return $this->isEndOfDayTime($value) && (
            $value->isSameDay($now)
            || $this->sameSecond($value, $now->copy()->endOfWeek())
            || $this->sameSecond($value, $now->copy()->endOfMonth())
            || $this->sameSecond($value, $now->copy()->endOfYear())
        );
    }

    private function endOfPeriodPhrase(CarbonInterface $value, string $operator, Carbon $now): string
    {
        $period = match (true) {
            $value->isSameDay($now) => 'today',
            $this->sameSecond($value, $now->copy()->endOfWeek()) => 'this week',
            $this->sameSecond($value, $now->copy()->endOfMonth()) => 'this month',
            default => 'this year',
        };

        return match ($operator) {
            '<', '<=' => "by the end of {$period}",
            '>', '>=' => "after {$period}",
            default => "at the end of {$period}",
        };
    }

    // ---------------------------------------------------------------
    // Clock (rolling) phrasing
    // ---------------------------------------------------------------

    private function nearNowPhrase(string $operator): string
    {
        return match ($operator) {
            '>', '>=' => 'in the future',
            '<', '<=' => 'in the past',
            '!=' => 'not right now',
            default => 'right now',
        };
    }

    /** @param array{int, string} $span */
    private function pastPhrase(string $operator, array $span): string
    {
        return match ($operator) {
            '>', '>=' => 'within the last '.$this->bare($span),
            '<', '<=' => 'more than '.$this->articled($span).' ago',
            '=' => 'exactly '.$this->articled($span).' ago',
            '!=' => 'not exactly '.$this->articled($span).' ago',
            default => $this->articled($span).' ago',
        };
    }

    /** @param array{int, string} $span */
    private function futurePhrase(string $operator, array $span): string
    {
        return match ($operator) {
            '<', '<=' => 'in the next '.$this->bare($span),
            '>', '>=' => 'more than '.$this->articled($span).' from now',
            '=' => 'exactly '.$this->articled($span).' from now',
            '!=' => 'not exactly '.$this->articled($span).' from now',
            default => $this->articled($span).' from now',
        };
    }

    /**
     * Best exact unit for the distance between value and now, or null.
     *
     * @return array{int, string}|null [count, unit]
     */
    private function exactSpan(CarbonInterface $value, Carbon $now): ?array
    {
        $seconds = abs($now->getTimestamp() - $value->getTimestamp());

        // Calendar months/years first (variable length, so checked by construction)
        $months = (int) round($value->diffInMonths($now, true));
        if ($months >= 1) {
            $rebuilt = $value->greaterThan($now)
                ? $now->copy()->addMonthsNoOverflow($months)
                : $now->copy()->subMonthsNoOverflow($months);

            if (abs($rebuilt->getTimestamp() - $value->getTimestamp()) <= self::TOLERANCE_SECONDS) {
                if ($months % 12 === 0) {
                    return [intdiv($months, 12), 'year'];
                }

                return [$months, 'month'];
            }
        }

        $remainder = $seconds % 86400;
        if ($remainder <= self::TOLERANCE_SECONDS || $remainder >= 86400 - self::TOLERANCE_SECONDS) {
            $days = (int) round($seconds / 86400);
            if ($days >= 1) {
                if ($days > 30 && $days % 7 === 0) {
                    return [intdiv($days, 7), 'week'];
                }

                return $days === 1 ? [24, 'hour'] : [$days, 'day'];
            }
        }

        $remainder = $seconds % 3600;
        if ($remainder <= self::TOLERANCE_SECONDS || $remainder >= 3600 - self::TOLERANCE_SECONDS) {
            $hours = (int) round($seconds / 3600);
            if ($hours >= 1 && $hours < 48) {
                return [$hours, 'hour'];
            }
        }

        $remainder = $seconds % 60;
        if ($remainder <= self::TOLERANCE_SECONDS || $remainder >= 60 - self::TOLERANCE_SECONDS) {
            $minutes = (int) round($seconds / 60);
            if ($minutes >= 1 && $minutes < 60) {
                return [$minutes, 'minute'];
            }
        }

        return null;
    }

    /** @return array{int, string}|null */
    private function approximateSpan(int $seconds): ?array
    {
        if ($seconds < 3600) {
            return [max(1, (int) round($seconds / 60)), 'minute'];
        }

        if ($seconds < 48 * 3600) {
            return [(int) round($seconds / 3600), 'hour'];
        }

        return null;
    }

    /** @param array{int, string} $span */
    private function bare(array $span): string
    {
        [$count, $unit] = $span;

        if ($count === 1) {
            return $unit;
        }

        return "{$count} {$unit}s";
    }

    /** @param array{int, string} $span */
    private function articled(array $span): string
    {
        [$count, $unit] = $span;

        if ($count === 1) {
            return ($unit === 'hour' ? 'an ' : 'a ').$unit;
        }

        return "{$count} {$unit}s";
    }

    // ---------------------------------------------------------------
    // Absolute formatting
    // ---------------------------------------------------------------

    private function absolutePhrase(CarbonInterface $value, string $operator, Carbon $now, bool $dateOnly): string
    {
        $word = $this->absoluteWord($value, $now, $dateOnly);

        return match ($operator) {
            '=' => ($dateOnly ? 'on ' : 'on ').$word,
            '!=' => 'not on '.$word,
            '>' => 'after '.$word,
            '>=' => 'on or after '.$word,
            '<' => 'before '.$word,
            '<=' => 'on or before '.$word,
            default => 'on '.$word,
        };
    }

    public function absoluteWord(CarbonInterface $value, ?Carbon $now = null, bool $withTime = true): string
    {
        $now ??= Carbon::now();

        $date = $value->isSameYear($now) ? $value->format('F j') : $value->format('F j, Y');

        if ($withTime && ! $this->isMidnight($value)) {
            $date .= ' at '.$value->format('g:i A');
        }

        return $date;
    }

    private function calendarDayWord(CarbonInterface $value, Carbon $now): string
    {
        if ($value->isToday()) {
            return 'today';
        }
        if ($value->isYesterday()) {
            return 'yesterday';
        }
        if ($value->isTomorrow()) {
            return 'tomorrow';
        }

        $today = $now->copy()->startOfDay();
        $day = $value->copy()->startOfDay();
        $offset = (int) round($day->diffInDays($today, true));

        if ($day->lessThan($today) && $offset <= 30) {
            return "{$offset} days ago";
        }
        if ($day->greaterThan($today) && $offset <= 30) {
            return "in {$offset} days";
        }

        return $this->absoluteWord($day, $now, false);
    }

    // ---------------------------------------------------------------
    // Small helpers
    // ---------------------------------------------------------------

    private function isMidnight(CarbonInterface $value): bool
    {
        return $value->format('H:i:s') === '00:00:00';
    }

    private function isEndOfDayTime(CarbonInterface $value): bool
    {
        return $value->format('H:i:s') === '23:59:59';
    }

    private function sameSecond(CarbonInterface $a, CarbonInterface $b): bool
    {
        return $a->getTimestamp() === $b->getTimestamp();
    }
}
