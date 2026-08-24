<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Doctrine\Inflector\InflectorFactory;

class Inflector
{
    private $doctrineInflector;

    private VerbInflector $verbInflector;

    private FieldTypeDetector $fieldTypeDetector;

    private RelativeTimeFormatter $timeFormatter;

    /** @var array<string, string> humanizeFieldName memoization */
    private array $fieldNameCache = [];

    private const ACRONYMS = [
        'id', 'gpa', 'sku', 'url', 'uri', 'vin', 'hoa', 'api', 'ssn', 'iban',
        'sms', 'cvv', 'vat', 'seo', 'ip', 'pdf', 'qr', 'faq',
    ];

    /** Unambiguous measurement units: always expanded to "in {unit}". */
    private const UNIT_SUFFIXES = [
        'km' => 'in km',
        'kg' => 'in kg',
        'cm' => 'in cm',
        'mm' => 'in mm',
        'sqft' => 'in sqft',
        'bytes' => 'in bytes',
        'kilobytes' => 'in KB',
        'megabytes' => 'in MB',
        'gigabytes' => 'in GB',
        'terabytes' => 'in TB',
    ];

    /**
     * Time units are only expanded after a measurement noun, so
     * "duration minutes" → "duration in minutes" but "credit hours" stays.
     */
    private const TIME_UNIT_SUFFIXES = ['minutes', 'hours', 'seconds', 'days'];

    private const MEASUREMENT_NOUNS = ['duration', 'delay', 'timeout', 'interval', 'wait', 'runtime', 'uptime', 'downtime', 'latency'];

    /** Word endings that signal an adjective, which must not be pluralized. */
    private const ADJECTIVE_SUFFIXES = ['able', 'ible', 'ed', 'ing', 'al', 'ous', 'ive', 'ic', 'ary', 'ful', 'less', 'ly'];

    public function __construct(private array $config)
    {
        $this->doctrineInflector = InflectorFactory::createForLanguage('english')->build();
        $this->verbInflector = new VerbInflector;
        $this->fieldTypeDetector = new FieldTypeDetector;
        $this->timeFormatter = new RelativeTimeFormatter;
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
        $cacheKey = $field.'|'.($builder && method_exists($builder, 'getModel') ? get_class($builder->getModel()) : '');

        if (isset($this->fieldNameCache[$cacheKey])) {
            return $this->fieldNameCache[$cacheKey];
        }

        $humanized = preg_replace_callback('/([a-z])([A-Z])/', function ($matches) {
            return $matches[1].'_'.strtolower($matches[2]);
        }, $field);

        $humanized = trim(preg_replace('/_+/', ' ', $humanized));

        $humanized = $this->enhanceFieldNameWithContext($humanized, $field, $builder);

        return $this->fieldNameCache[$cacheKey] = $humanized;
    }

    public function humanizeDate(mixed $value): string
    {
        if (! ($this->config['humanize_dates'] ?? true)) {
            return (string) $value;
        }

        try {
            $carbon = $value instanceof CarbonInterface ? $value : Carbon::parse((string) $value);

            return $this->timeFormatter->describePoint($carbon);
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
            if ($value === '') {
                return 'empty';
            }

            return str_contains($value, "'") ? "\"{$value}\"" : "'{$value}'";
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
        return $this->fieldTypeDetector->isBooleanField($fieldName, $builder);
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

        $units = ['seconds', 'minutes', 'hours', 'days', 'months', 'years', 'usd', 'eur', 'gbp', 'km', 'kg'];
        if (in_array(strtolower($lastWord), $units)) {
            if (count($words) > 1) {
                $lastWord = $words[count($words) - 2];
            }
        }

        $singular = $this->doctrineInflector->singularize($lastWord);

        return $singular !== $lastWord;
    }

    /**
     * Pluralize the trailing noun of a "that are X" complement so that
     * "customers that are premium member" becomes "premium members".
     * Leaves adjectives and prepositional phrases alone.
     */
    public function pluralizeNounPhrase(string $phrase): string
    {
        if (trim($phrase) === '') {
            return $phrase;
        }

        $words = explode(' ', trim($phrase));

        $prepositions = ['for', 'of', 'in', 'on', 'at', 'by', 'to', 'with', 'from'];
        foreach ($words as $word) {
            if (in_array(strtolower($word), $prepositions, true)) {
                return $phrase;
            }
        }

        $last = strtolower(end($words));

        foreach (self::ADJECTIVE_SUFFIXES as $suffix) {
            if (str_ends_with($last, $suffix)) {
                return $phrase;
            }
        }

        if ($this->isPlural($last)) {
            return $phrase;
        }

        $words[count($words) - 1] = $this->pluralize(end($words));

        return implode(' ', $words);
    }

    /**
     * Turn a plural-subject condition into a singular-subject one:
     * "that are premium members" → "that is a premium member",
     * "that have warranties" → "that has warranties".
     * Used when a condition applies to a single related record.
     */
    public function singularizeConditionPhrase(string $phrase): string
    {
        // Complement agreement only for a phrase-leading "that are X"
        $handledComplement = false;
        foreach (['that are not ', 'that are '] as $plural) {
            if (str_starts_with($phrase, $plural)) {
                $rest = $this->singularizeComplement(substr($phrase, strlen($plural)));
                $phrase = str_replace('are', 'is', $plural).$rest;
                $handledComplement = true;
                break;
            }
        }

        // All conditions in the phrase refer to the same singular entity, so
        // verb agreement applies throughout (including inside or-groups).
        $replacements = [
            "that don't have " => "that doesn't have ",
            'that have ' => 'that has ',
            "that don't " => "that doesn't ",
            'who have ' => 'who has ',
            "who don't have " => "who doesn't have ",
        ];

        if (! $handledComplement) {
            $replacements['that are not '] = 'that is not ';
            $replacements['that are '] = 'that is ';
        }

        return strtr($phrase, $replacements);
    }

    private function singularizeComplement(string $complement): string
    {
        $words = explode(' ', trim($complement));
        $last = end($words);
        $singular = $this->singularize($last);

        if ($singular === $last) {
            return $complement;
        }

        $words[count($words) - 1] = $singular;
        $phrase = implode(' ', $words);
        $article = $this->getProperArticle($phrase);

        return ($article ? "{$article} " : '').$phrase;
    }

    public function convertVerbToPastTense(string $verb): string
    {
        return $this->verbInflector->toPastTense($verb);
    }

    public function knowsVerb(string $verb): bool
    {
        return $this->verbInflector->hasMapping($verb);
    }

    private function enhanceFieldNameWithContext(string $fieldName, string $originalField, $builder = null): string
    {
        $fieldName = str_replace(
            [' * ', ' / ', ' + ', ' - '],
            [' times ', ' divided by ', ' plus ', ' minus '],
            $fieldName
        );

        $fieldName = $this->enhanceLaravelTimestampFields($fieldName, $originalField);

        $fieldName = $this->enhanceDateTimeFields($fieldName, $originalField, $builder);

        $fieldName = preg_replace_callback('/\b(usd|eur|gbp|jpy|cad|aud)\b/i', function ($matches) {
            return 'in '.strtoupper($matches[1]);
        }, $fieldName);

        foreach (self::UNIT_SUFFIXES as $unit => $replacement) {
            if (str_ends_with($fieldName, ' '.$unit)) {
                $fieldName = substr($fieldName, 0, -strlen($unit)).$replacement;
                break;
            }
        }

        foreach (self::TIME_UNIT_SUFFIXES as $unit) {
            if (str_ends_with($fieldName, ' '.$unit)) {
                $words = explode(' ', $fieldName);
                $precedingWord = $words[count($words) - 2] ?? '';

                if (in_array($precedingWord, self::MEASUREMENT_NOUNS, true)) {
                    $fieldName = substr($fieldName, 0, -strlen($unit))."in {$unit}";
                }
                break;
            }
        }

        $fieldName = preg_replace_callback('/\b('.implode('|', self::ACRONYMS).')\b/', function ($matches) {
            return strtoupper($matches[1]);
        }, $fieldName);

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
                $base = str_replace(trim(str_replace('_', ' ', $pattern)), '', $fieldName);

                return trim(rtrim($base).$replacement);
            }
        }

        return $fieldName;
    }

    /**
     * "next_billing_date" reads better as "next billing", but a single-word
     * remainder like "work" needs its suffix kept ("work date").
     */
    private function enhanceDateTimeFields(string $fieldName, string $originalField, $builder = null): string
    {
        if (! $this->isDateColumn($originalField, $builder)) {
            return $fieldName;
        }

        foreach ([' at', ' date'] as $suffix) {
            if (str_ends_with($fieldName, $suffix)) {
                $stripped = trim(substr($fieldName, 0, -strlen($suffix)));

                if (str_contains($stripped, ' ')) {
                    return $stripped;
                }

                return $fieldName;
            }
        }

        return $fieldName;
    }

    private function isDateColumn(string $column, $builder = null): bool
    {
        return $this->fieldTypeDetector->isDateField($column, $builder);
    }
}
