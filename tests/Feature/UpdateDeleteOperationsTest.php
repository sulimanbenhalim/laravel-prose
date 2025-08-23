<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Feature;

use SulimanBenhalim\Prose\Tests\Customer;
use SulimanBenhalim\Prose\Tests\TestCase;

class UpdateDeleteOperationsTest extends TestCase
{
    public function test_delete_operations_produce_professional_natural_language(): void
    {
        // Test basic delete with conditions
        $deleteQuery = Customer::where('status', 'banned');

        // Since we can't actually call delete() and still have the query available,
        // we'll test the underlying functionality by mocking the aggregate structure
        $query = $deleteQuery->getQuery();
        /** @phpstan-ignore-next-line assign.propertyType */
        $query->aggregate = ['function' => 'delete'];

        $description = $deleteQuery->describe();

        $this->assertStringContainsString('Delete customers', $description);
        $this->assertStringContainsString('with status is', $description);
        $this->assertStringContainsString('banned', $description);
    }

    public function test_delete_with_multiple_conditions(): void
    {
        $deleteQuery = Customer::where('status', 'inactive')
            ->where('last_login_at', '<', now()->subYears(2));

        // Mock as delete operation
        $query = $deleteQuery->getQuery();
        /** @phpstan-ignore-next-line assign.propertyType */
        $query->aggregate = ['function' => 'delete'];

        $description = $deleteQuery->describe();

        $this->assertStringContainsString('Delete customers', $description);
        $this->assertStringContainsString('with status is', $description);
        $this->assertStringContainsString('inactive', $description);
        $this->assertStringContainsString('with last login more than 2 years ago', $description);
    }

    public function test_delete_with_date_conditions(): void
    {
        $deleteQuery = Customer::where('created_at', '<', now()->subMonths(6))
            ->whereNull('email_verified_at');

        // Mock as delete operation
        $query = $deleteQuery->getQuery();
        /** @phpstan-ignore-next-line assign.propertyType */
        $query->aggregate = ['function' => 'delete'];

        $description = $deleteQuery->describe();

        $this->assertStringContainsString('Delete customers', $description);
        $this->assertStringContainsString('created older than 6 months ago', $description);
        $this->assertStringContainsString('with unverified email', $description);
    }

    public function test_update_operations_with_basic_functionality(): void
    {
        // For now, UPDATE operations will use basic functionality
        // until we can solve the challenge of extracting update data from QueryBuilder
        $updateQuery = Customer::where('status', 'pending');

        // Mock as update operation
        $query = $updateQuery->getQuery();
        /** @phpstan-ignore-next-line assign.propertyType */
        $query->aggregate = ['function' => 'update'];

        $description = $updateQuery->describe();

        $this->assertStringContainsString('Update customers', $description);
        $this->assertStringContainsString('with status is', $description);
        $this->assertStringContainsString('pending', $description);
    }

    public function test_delete_without_conditions_indicates_all_records(): void
    {
        $deleteQuery = Customer::query();

        // Mock as delete operation
        $query = $deleteQuery->getQuery();
        /** @phpstan-ignore-next-line assign.propertyType */
        $query->aggregate = ['function' => 'delete'];

        $description = $deleteQuery->describe();

        // Should indicate this deletes all records
        $this->assertStringContainsString('Delete all customers', $description);
    }

    public function test_existing_select_operations_still_work(): void
    {
        // Ensure we haven't broken existing SELECT functionality
        $description = Customer::where('active', true)
            ->where('role', 'admin')
            ->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString('with active', $description);
        $this->assertStringContainsString('role is', $description);
        $this->assertStringContainsString('admin', $description);
    }

    public function test_existing_count_operations_still_work(): void
    {
        // Test that count operations haven't been affected
        $countQuery = Customer::where('status', 'active');

        // Mock as count operation (this is how Laravel sets up count queries)
        $query = $countQuery->getQuery();
        $query->aggregate = ['function' => 'count', 'columns' => ['*']];

        $description = $countQuery->describe();

        $this->assertStringContainsString('Count customers', $description);
        $this->assertStringContainsString('with status is', $description);
        $this->assertStringContainsString('active', $description);
    }
}
