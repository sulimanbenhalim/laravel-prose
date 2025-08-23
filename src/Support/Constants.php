<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

class Constants
{
    public const LARAVEL_TIMESTAMP_FIELDS = [
        'created_at',
        'updated_at',
        'deleted_at',
        'email_verified_at',
        'last_used_at',
        'remember_token_expires_at',
    ];

    public const COMMON_DATE_FIELDS = [
        'created_at',
        'updated_at',
        'deleted_at',
        'email_verified_at',
        'last_used_at',
        'published_at',
        'scheduled_at',
        'expires_at',
    ];

    public const DATE_CAST_TYPES = [
        'date',
        'datetime',
        'timestamp',
        'time',
    ];

    public const DOCTRINE_DATE_TYPES = [
        'date',
        'datetime',
        'datetimetz',
        'time',
        'timestamp',
    ];

    public const NUMERIC_CAST_TYPES = [
        'int',
        'integer',
        'real',
        'float',
        'double',
        'decimal',
    ];

    public const DOCTRINE_NUMERIC_TYPES = [
        'integer',
        'bigint',
        'decimal',
        'float',
    ];

    public const BOOLEAN_FIELD_PREFIXES = [
        'is_',
        'has_',
        'can_',
        'should_',
        'will_',
        'was_',
        'were_',
    ];

    public const CURRENCY_SUFFIXES = [
        'usd',
        'eur',
        'gbp',
        'jpy',
        'cad',
        'aud',
    ];

    public const SIZE_UNITS = [
        'bytes' => 'in bytes',
        'kilobytes' => 'in KB',
        'megabytes' => 'in MB',
        'gigabytes' => 'in GB',
        'terabytes' => 'in TB',
    ];

    public const TIMESTAMP_PATTERNS = [
        '_verified_at' => ' verification',
        '_deleted_at' => ' deletion',
        '_sent_at' => ' sending',
        '_published_at' => ' publication',
        '_activated_at' => ' activation',
        '_deactivated_at' => ' deactivation',
        '_locked_at' => ' locking',
        '_unlocked_at' => ' unlocking',
    ];

    public const COMPLEX_QUERY_PATTERNS = [
        '/select\s+count\s*\(\s*\*\s*\)\s+from/i',
        '/exists\s*\(/i',
        '/select.*from.*where.*in\s*\(/i',
        '/group\s+by/i',
        '/having\s+count/i',
    ];

    public const SYSTEM_TABLES = [
        'information_schema',
        'mysql',
        'performance_schema',
    ];

    public const MONTH_NAMES = [
        1 => 'January', 2 => 'February', 3 => 'March',
        4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September',
        10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public const CARBON_OPERATION_TOLERANCES = [
        'subMinutes' => 60,
        'addMinutes' => 60,
        'subHours' => 600,
        'addHours' => 600,
        'subDays' => 3600,
        'addDays' => 3600,
        'subWeeks' => 7200,
        'addWeeks' => 7200,
        'subMonths' => 86400,
        'addMonths' => 86400,
        'subYears' => 86400,
        'addYears' => 86400,
    ];
}
