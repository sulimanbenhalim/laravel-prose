<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

use Carbon\Carbon;
use Doctrine\Inflector\InflectorFactory;

class Inflector
{
    private $doctrineInflector;

    private VerbInflector $verbInflector;

    public function __construct(private array $config)
    {
        $this->doctrineInflector = InflectorFactory::createForLanguage('english')->build();
        $this->verbInflector = new VerbInflector;
    }

    public function pluralize(string $word): string
    {
        $customPlurals = $this->config['custom_pluralization'] ?? [];

        if (isset($customPlurals[$word])) {
            return $customPlurals[$word];
        }

        return $this->doctrineInflector->pluralize($word);
    }

    public function singularize(string $word): string
    {
        return $this->doctrineInflector->singularize($word);
    }

    public function humanizeFieldName(string $field, $builder = null): string
    {
        $field = preg_replace_callback('/([a-z])([A-Z])/', function ($matches) {
            return $matches[1].'_'.strtolower($matches[2]);
        }, $field);

        $humanized = preg_replace_callback('/_+/', function ($matches) {
            return str_repeat(' ', strlen($matches[0]));
        }, $field);

        return $this->enhanceFieldNameWithContext($humanized, $field, $builder);
    }

    public function humanizeDate(mixed $value): string
    {
        if (! $this->config['humanize_dates']) {
            return (string) $value;
        }

        try {
            $carbon = Carbon::parse($value);
            $formats = $this->config['date_formats'] ?? [];

            $relativeTime = $this->detectRelativeTime($carbon);
            if ($relativeTime) {
                return $relativeTime;
            }

            if ($carbon->isToday()) {
                return $formats['today'] ?? 'today';
            }

            if ($carbon->isYesterday()) {
                return $formats['yesterday'] ?? 'yesterday';
            }

            $daysAgo = (int) $carbon->diffInDays();
            if ($daysAgo <= 7) {
                return str_replace(':count', (string) $daysAgo, $formats['days_ago'] ?? ':count days ago');
            }

            if ($carbon->isCurrentYear()) {
                return $carbon->format($formats['this_year'] ?? 'M j');
            }

            return $carbon->format($formats['fallback'] ?? 'M j, Y');
        } catch (\Exception) {
            return (string) $value;
        }
    }

    public function joinWithConnector(array $items, string $connector = 'and'): string
    {
        if (count($items) === 0) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        if (count($items) === 2) {
            return implode(" {$connector} ", $items);
        }

        $last = array_pop($items);

        return implode(', ', $items)." {$connector} {$last}";
    }

    public function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return "'{$value}'";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_array($value)) {
            $formatted = array_map([$this, 'formatValue'], $value);

            return '['.implode(', ', $formatted).']';
        }

        return (string) $value;
    }

    public function detectBooleanField(string $fieldName, $builder = null): bool
    {
        if ($builder && method_exists($builder, 'getModel')) {
            try {
                $model = $builder->getModel();

                $casts = $model->getCasts();
                if (isset($casts[$fieldName]) && $casts[$fieldName] === 'boolean') {
                    return true;
                }

                $table = $model->getTable();
                $connection = $model->getConnection();
                $columnType = $connection->getDoctrineColumn($table, $fieldName)->getType();

                if ($columnType && class_exists('\Doctrine\DBAL\Types\BooleanType') && $columnType instanceof \Doctrine\DBAL\Types\BooleanType) {
                    return true;
                }

            } catch (\Exception $e) {

            }
        }

        return (bool) preg_match('/^(is|has|can|should|will|was|were)_/', $fieldName);
    }

    public function getProperArticle(string $fieldName): string
    {
        if ($this->isPlural($fieldName)) {
            return '';
        }

        $firstWord = strtolower(explode(' ', trim($fieldName))[0]);
        $vowels = ['a', 'e', 'i', 'o', 'u'];

        return in_array($firstWord[0] ?? '', $vowels) ? 'an' : 'a';
    }

    public function isPlural(string $fieldName): bool
    {
        $words = explode(' ', trim($fieldName));
        $lastWord = end($words);

        $units = ['seconds', 'minutes', 'hours', 'days', 'months', 'years', 'usd', 'eur', 'gbp'];
        if (in_array(strtolower($lastWord), $units)) {
            if (count($words) > 1) {
                $lastWord = $words[count($words) - 2];
            }
        }

        $singular = $this->doctrineInflector->singularize($lastWord);

        return $singular !== $lastWord;
    }

    private function enhanceFieldNameWithContext(string $fieldName, string $originalField, $builder = null): string
    {
        $fieldName = $this->enhanceLaravelTimestampFields($fieldName, $originalField);

        $fieldName = $this->enhanceDateTimeFields($fieldName, $originalField, $builder);

        $fieldName = $this->enhanceBooleanFields($fieldName, $originalField, $builder);

        $fieldName = preg_replace_callback('/\b(usd|eur|gbp|jpy|cad|aud)\b/i', function ($matches) {
            return 'in '.strtoupper($matches[1]);
        }, $fieldName);

        $sizeUnits = [
            'bytes' => 'in bytes',
            'kilobytes' => 'in KB',
            'megabytes' => 'in MB',
            'gigabytes' => 'in GB',
            'terabytes' => 'in TB',
        ];

        foreach ($sizeUnits as $unit => $replacement) {
            if (str_ends_with($fieldName, ' '.$unit)) {
                $fieldName = str_replace(' '.$unit, ' '.$replacement, $fieldName);
                break;
            }
        }

        return $fieldName;
    }

    private function enhanceLaravelTimestampFields(string $fieldName, string $originalField): string
    {
        $timestampPatterns = [
            '_verified_at' => ' verification',
            '_deleted_at' => ' deletion',
            '_sent_at' => ' sending',
            '_published_at' => ' publication',
            '_activated_at' => ' activation',
            '_deactivated_at' => ' deactivation',
            '_locked_at' => ' locking',
            '_unlocked_at' => ' unlocking',
        ];

        foreach ($timestampPatterns as $pattern => $replacement) {
            if (str_ends_with($originalField, $pattern)) {
                $base = str_replace($pattern, '', $fieldName);

                return trim($base.$replacement);
            }
        }

        return $fieldName;
    }

    private function enhanceBooleanFields(string $fieldName, string $originalField, $builder = null): string
    {
        if (! $this->detectBooleanField($originalField, $builder)) {
            return $fieldName;
        }

        return $fieldName;
    }

    private function detectRelativeTime(Carbon $carbon): ?string
    {
        $now = Carbon::now();

        $specialOperation = $this->detectSpecialCarbonOperations($carbon, $now);
        if ($specialOperation) {
            return $specialOperation;
        }

        $totalMinutes = abs($carbon->diffInMinutes($now));
        $totalHours = abs($carbon->diffInHours($now));
        $totalDays = abs($carbon->diffInDays($now));
        $totalWeeks = abs($carbon->diffInWeeks($now));
        $totalMonths = abs($carbon->diffInMonths($now));
        $totalYears = abs($carbon->diffInYears($now));

        if ($carbon->isPast()) {
            $specificOperation = $this->detectPastRelativeTime($totalMinutes, $totalHours, $totalDays, $totalWeeks, $totalMonths, $totalYears, $carbon, $now);
            if ($specificOperation) {
                return $specificOperation;
            }
        } elseif ($carbon->isFuture()) {
            $specificOperation = $this->detectFutureRelativeTime($totalMinutes, $totalHours, $totalDays, $totalWeeks, $totalMonths, $totalYears);
            if ($specificOperation) {
                return $specificOperation;
            }
        }

        if ($carbon->isToday()) {
            return $this->config['date_formats']['today'] ?? 'today';
        }

        if ($carbon->isYesterday()) {
            return $this->config['date_formats']['yesterday'] ?? 'yesterday';
        }

        if ($carbon->isTomorrow()) {
            return 'tomorrow';
        }

        return null;
    }

    private function detectSpecialCarbonOperations(Carbon $carbon, Carbon $now): ?string
    {

        for ($minutes = 1; $minutes <= 59; $minutes++) {
            $testDate = $now->copy()->subMinutes($minutes);
            if ($this->isDateClose($carbon, $testDate, 60)) {
                return $minutes === 1 ? 'within the last minute' : "within the last {$minutes} minutes";
            }

            $testDate = $now->copy()->addMinutes($minutes);
            if ($this->isDateClose($carbon, $testDate, 60)) {
                return $minutes === 1 ? 'in the next minute' : "in the next {$minutes} minutes";
            }
        }

        for ($hours = 1; $hours <= 23; $hours++) {
            $testDate = $now->copy()->subHours($hours);
            if ($this->isDateClose($carbon, $testDate, 600)) {
                return $hours === 1 ? 'within the last hour' : "within the last {$hours} hours";
            }

            $testDate = $now->copy()->addHours($hours);
            if ($this->isDateClose($carbon, $testDate, 600)) {
                return $hours === 1 ? 'in the next hour' : "in the next {$hours} hours";
            }
        }

        $testYesterday = $now->copy()->subDay();
        if ($this->isDateClose($carbon, $testYesterday, 3600)) {
            return 'yesterday';
        }

        $testTomorrow = $now->copy()->addDay();
        if ($this->isDateClose($carbon, $testTomorrow, 3600)) {
            return 'tomorrow';
        }

        $testFourteen = $now->copy()->subDays(14);
        if ($this->isDateClose($carbon, $testFourteen, 1)) {
            return 'within the last 14 days';
        }

        $testFourteenFuture = $now->copy()->addDays(14);
        if ($this->isDateClose($carbon, $testFourteenFuture, 1)) {
            return 'in the next 14 days';
        }

        $testSeven = $now->copy()->subDays(7);
        if ($this->isDateClose($carbon, $testSeven, 1)) {
            return 'within the last 7 days';
        }

        $testSevenFuture = $now->copy()->addDays(7);
        if ($this->isDateClose($carbon, $testSevenFuture, 1)) {
            return 'in the next 7 days';
        }

        for ($weeks = 1; $weeks <= 12; $weeks++) {
            $testDate = $now->copy()->subWeeks($weeks);
            if ($this->isDateClose($carbon, $testDate, 3600)) {
                return $weeks === 1 ? 'within the last week' : "within the last {$weeks} weeks";
            }

            $testDate = $now->copy()->addWeeks($weeks);
            if ($this->isDateClose($carbon, $testDate, 3600)) {
                return $weeks === 1 ? 'in the next week' : "in the next {$weeks} weeks";
            }
        }

        for ($days = 2; $days <= 30; $days++) {
            if ($days === 7 || $days === 14) {
                continue;
            }
            $testDate = $now->copy()->subDays($days);
            if ($this->isDateClose($carbon, $testDate, 3600)) {
                return "within the last {$days} days";
            }

            $testDate = $now->copy()->addDays($days);
            if ($this->isDateClose($carbon, $testDate, 3600)) {
                return "in the next {$days} days";
            }
        }

        for ($months = 1; $months <= 12; $months++) {
            $testDate = $now->copy()->subMonths($months);
            if ($this->isDateClose($carbon, $testDate, 86400)) {
                return $months === 1 ? 'within the last month' : "within the last {$months} months";
            }

            $testDate = $now->copy()->addMonths($months);
            if ($this->isDateClose($carbon, $testDate, 86400)) {
                return $months === 1 ? 'in the next month' : "in the next {$months} months";
            }
        }

        for ($years = 1; $years <= 5; $years++) {
            $testDate = $now->copy()->subYears($years);
            if ($this->isDateClose($carbon, $testDate, 86400)) {
                return $years === 1 ? 'within the last year' : "within the last {$years} years";
            }

            $testDate = $now->copy()->addYears($years);
            if ($this->isDateClose($carbon, $testDate, 86400)) {
                return $years === 1 ? 'in the next year' : "in the next {$years} years";
            }
        }

        return null;
    }

    private function detectPastRelativeTime(float $totalMinutes, float $totalHours, float $totalDays, float $totalWeeks, float $totalMonths, float $totalYears, Carbon $carbon, Carbon $now): ?string
    {

        if ($totalYears >= 1) {
            $years = (int) round($totalYears);
            $testDate = $now->copy()->subYears($years);
            if ($this->isDateClose($carbon, $testDate)) {
                return $years === 1 ? 'within the last year' : "within the last {$years} years";
            }
        }

        if ($totalMonths >= 1) {
            $months = (int) round($totalMonths);
            $testDate = $now->copy()->subMonths($months);
            if ($this->isDateClose($carbon, $testDate)) {
                return $months === 1 ? 'within the last month' : "within the last {$months} months";
            }
        }

        if ($totalWeeks >= 1) {
            $weeks = (int) round($totalWeeks);
            $testDate = $now->copy()->subWeeks($weeks);
            if ($this->isDateClose($carbon, $testDate)) {
                return $weeks === 1 ? 'within the last week' : "within the last {$weeks} weeks";
            }
        }

        if ($totalDays >= 1) {
            $days = (int) round($totalDays);
            $testDate = $now->copy()->subDays($days);
            if ($this->isDateClose($carbon, $testDate)) {
                return $days === 1 ? 'within the last day' : "within the last {$days} days";
            }
        }

        if ($totalHours >= 1) {
            $hours = (int) round($totalHours);
            $testDate = $now->copy()->subHours($hours);
            if ($this->isDateClose($carbon, $testDate, 600)) {
                return $hours === 1 ? 'within the last hour' : "within the last {$hours} hours";
            }
        }

        if ($totalMinutes >= 1) {
            $minutes = (int) round($totalMinutes);
            $testDate = $now->copy()->subMinutes($minutes);
            if ($this->isDateClose($carbon, $testDate, 60)) {
                return $minutes === 1 ? 'within the last minute' : "within the last {$minutes} minutes";
            }
        }

        return $this->detectStartEndOperations($carbon, $now);
    }

    private function detectFutureRelativeTime(float $totalMinutes, float $totalHours, float $totalDays, float $totalWeeks, float $totalMonths, float $totalYears): ?string
    {

        if ($totalYears >= 1) {
            $years = (int) round($totalYears);

            return $years === 1 ? 'in the next year' : "in the next {$years} years";
        }

        if ($totalMonths >= 1) {
            $months = (int) round($totalMonths);

            return $months === 1 ? 'in the next month' : "in the next {$months} months";
        }

        if ($totalWeeks >= 1) {
            $weeks = (int) round($totalWeeks);

            return $weeks === 1 ? 'in the next week' : "in the next {$weeks} weeks";
        }

        if ($totalDays >= 1) {
            $days = (int) round($totalDays);

            return $days === 1 ? 'in the next day' : "in the next {$days} days";
        }

        if ($totalHours >= 1) {
            $hours = (int) round($totalHours);

            return $hours === 1 ? 'in the next hour' : "in the next {$hours} hours";
        }

        if ($totalMinutes >= 1) {
            $minutes = (int) round($totalMinutes);

            return $minutes === 1 ? 'in the next minute' : "in the next {$minutes} minutes";
        }

        return null;
    }

    private function detectStartEndOperations(Carbon $carbon, Carbon $now): ?string
    {
        if ($carbon->isSameDay($now)) {
            if ($carbon->isStartOfDay()) {
                return 'start of today';
            }
            if ($carbon->isEndOfDay()) {
                return 'end of today';
            }
        }

        if ($carbon->isSameWeek($now)) {
            if ($carbon->isStartOfWeek()) {
                return 'start of this week';
            }
            if ($carbon->isEndOfWeek()) {
                return 'end of this week';
            }
        }

        if ($carbon->isSameMonth($now)) {
            if ($carbon->isStartOfMonth()) {
                return 'start of this month';
            }
            if ($carbon->isEndOfMonth()) {
                return 'end of this month';
            }
        }

        if ($carbon->isSameYear($now)) {
            if ($carbon->isStartOfYear()) {
                return 'start of this year';
            }
            if ($carbon->isEndOfYear()) {
                return 'end of this year';
            }
        }

        return null;
    }

    private function isDateClose(Carbon $date1, Carbon $date2, int $toleranceSeconds = 86400): bool
    {
        $diffSeconds = abs($date1->diffInSeconds($date2));

        return $diffSeconds <= $toleranceSeconds;
    }

    private function enhanceDateTimeFields(string $fieldName, string $originalField, $builder = null): string
    {

        if (! $this->isDateColumn($originalField, $builder)) {
            return $fieldName;
        }

        if (str_ends_with($originalField, '_at')) {
            $withoutAt = str_replace(' at', '', $fieldName);

            return trim($withoutAt);
        }

        if (str_ends_with($originalField, '_date')) {
            $withoutDate = str_replace(' date', '', $fieldName);

            return trim($withoutDate);
        }

        return $fieldName;
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

    public function convertVerbToPastTense(string $verb): string
    {
        return $this->verbInflector->toPastTense($verb);
    }
}
