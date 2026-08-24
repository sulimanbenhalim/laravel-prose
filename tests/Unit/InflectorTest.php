<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use SulimanBenhalim\Prose\Support\Inflector;

class InflectorTest extends TestCase
{
    private Inflector $inflector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inflector = new Inflector([
            'field_aliases' => [
                'created_at' => 'creation date',
                'updated_at' => 'last update',
            ],
            'pluralization' => [
                'person' => 'people',
                'category' => 'categories',
            ],
            'humanize_dates' => true,
            'date_formats' => [
                'today' => 'today',
                'yesterday' => 'yesterday',
                'days_ago' => ':count days ago',
                'this_year' => 'this year',
                'fallback' => 'Y-m-d',
            ],
        ]);
    }

    public function test_pluralize_regular_words(): void
    {
        $this->assertEquals('users', $this->inflector->pluralize('user'));
        $this->assertEquals('orders', $this->inflector->pluralize('order'));
        $this->assertEquals('products', $this->inflector->pluralize('product'));
    }

    public function test_pluralize_custom_mapping(): void
    {
        $this->assertEquals('people', $this->inflector->pluralize('person'));
        $this->assertEquals('categories', $this->inflector->pluralize('category'));
    }

    public function test_pluralize_irregular_words(): void
    {
        $this->assertEquals('children', $this->inflector->pluralize('child'));
        $this->assertEquals('men', $this->inflector->pluralize('man'));
        $this->assertEquals('women', $this->inflector->pluralize('woman'));
    }

    public function test_pluralize_words_ending_in_y(): void
    {
        $this->assertEquals('companies', $this->inflector->pluralize('company'));
        $this->assertEquals('cities', $this->inflector->pluralize('city'));
        $this->assertEquals('boys', $this->inflector->pluralize('boy'));
    }

    public function test_humanize_field_name_with_laravel_attributes(): void
    {
        $this->assertEquals('created at', $this->inflector->humanizeFieldName('created_at'));
        $this->assertEquals('updated at', $this->inflector->humanizeFieldName('updated_at'));
        $this->assertEquals('email verification', $this->inflector->humanizeFieldName('email_verified_at'));
    }

    public function test_humanize_field_name_without_aliases(): void
    {
        $this->assertEquals('user ID', $this->inflector->humanizeFieldName('user_id'));
        $this->assertEquals('email address', $this->inflector->humanizeFieldName('email_address'));
    }

    public function test_humanize_date_today(): void
    {
        $today = Carbon::today()->toDateString();
        $result = $this->inflector->humanizeDate($today);
        $this->assertTrue(
            $result === 'today' || $result === 'yesterday' || str_contains($result, 'within the last') || str_contains($result, 'hours'),
            "Expected 'today' or relative time phrase, got: {$result}"
        );
    }

    public function test_humanize_date_yesterday(): void
    {
        $yesterday = Carbon::yesterday()->toDateString();
        $result = $this->inflector->humanizeDate($yesterday);
        $this->assertTrue(
            $result === 'yesterday' || str_contains($result, 'within the last') || str_contains($result, 'days'),
            "Expected 'yesterday' or relative time phrase, got: {$result}"
        );
    }

    public function test_humanize_date_days_ago(): void
    {
        $threeDaysAgo = Carbon::now()->subDays(3)->toDateString();
        $result = $this->inflector->humanizeDate($threeDaysAgo);
        $this->assertTrue(
            str_contains($result, 'days ago'),
            "Expected relative time phrase with 'days ago', got: {$result}"
        );
    }

    public function test_join_with_connector_single_item(): void
    {
        $this->assertEquals('apple', $this->inflector->joinWithConnector(['apple']));
    }

    public function test_join_with_connector_two_items(): void
    {
        $this->assertEquals('apple and banana', $this->inflector->joinWithConnector(['apple', 'banana']));
    }

    public function test_join_with_connector_multiple_items(): void
    {
        $this->assertEquals(
            'apple, banana and cherry',
            $this->inflector->joinWithConnector(['apple', 'banana', 'cherry'])
        );
    }

    public function test_join_with_custom_connector(): void
    {
        $this->assertEquals(
            'apple or banana',
            $this->inflector->joinWithConnector(['apple', 'banana'], 'or')
        );
    }

    public function test_format_value_string(): void
    {
        $this->assertEquals("'hello'", $this->inflector->formatValue('hello'));
    }

    public function test_format_value_boolean(): void
    {
        $this->assertEquals('true', $this->inflector->formatValue(true));
        $this->assertEquals('false', $this->inflector->formatValue(false));
    }

    public function test_format_value_null(): void
    {
        $this->assertEquals('null', $this->inflector->formatValue(null));
    }

    public function test_format_value_array(): void
    {
        $this->assertEquals("[1, 2, 'three']", $this->inflector->formatValue([1, 2, 'three']));
    }

    public function test_format_value_number(): void
    {
        $this->assertEquals('123', $this->inflector->formatValue(123));
        $this->assertEquals('45.67', $this->inflector->formatValue(45.67));
    }
}
