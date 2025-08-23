<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Feature;

use Illuminate\Database\Query\Builder;
use SulimanBenhalim\Prose\Tests\Customer;
use SulimanBenhalim\Prose\Tests\TestCase;

class WhereExistsTest extends TestCase
{
    public function test_where_exists_basic()
    {
        $description = Customer::whereExists(function (Builder $query) {
            $query->selectRaw('1')
                ->from('orders')
                ->whereColumn('orders.customer_id', 'customers.id');
        })->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString('who have orders', $description);
    }

    public function test_where_not_exists_basic()
    {
        $description = Customer::whereNotExists(function (Builder $query) {
            $query->selectRaw('1')
                ->from('orders')
                ->whereColumn('orders.customer_id', 'customers.id');
        })->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString("who don't have orders", $description);
    }

    public function test_where_exists_with_conditions()
    {
        $description = Customer::whereExists(function (Builder $query) {
            $query->selectRaw('1')
                ->from('orders')
                ->whereColumn('orders.customer_id', 'customers.id')
                ->where('orders.status', 'completed');
        })->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString('who have orders', $description);
        $this->assertStringContainsString("status is 'completed'", $description);
    }

    public function test_where_not_exists_with_conditions()
    {
        $description = Customer::whereNotExists(function (Builder $query) {
            $query->selectRaw('1')
                ->from('orders')
                ->whereColumn('orders.customer_id', 'customers.id')
                ->where('orders.status', 'completed');
        })->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString("who don't have orders", $description);
        $this->assertStringContainsString("status is 'completed'", $description);
    }

    public function test_where_exists_multiple_conditions()
    {
        $description = Customer::whereExists(function (Builder $query) {
            $query->selectRaw('1')
                ->from('orders')
                ->whereColumn('orders.customer_id', 'customers.id')
                ->where('orders.status', 'completed')
                ->where('orders.total_amount_usd', '>', 100);
        })->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString('who have orders', $description);
        $this->assertStringContainsString("status is 'completed'", $description);
        $this->assertStringContainsString('total amount in USD greater than 100', $description);
    }

    public function test_where_exists_combined_with_other_conditions()
    {
        $description = Customer::where('is_active', true)
            ->whereExists(function (Builder $query) {
                $query->selectRaw('1')
                    ->from('orders')
                    ->whereColumn('orders.customer_id', 'customers.id')
                    ->where('orders.status', 'completed');
            })
            ->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString('that are active', $description);
        $this->assertStringContainsString('who have orders', $description);
        $this->assertStringContainsString("status is 'completed'", $description);
    }
}
