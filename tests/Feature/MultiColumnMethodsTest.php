<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Feature;

use SulimanBenhalim\Prose\Tests\Customer;
use SulimanBenhalim\Prose\Tests\TestCase;

class MultiColumnMethodsTest extends TestCase
{
    public function test_where_any_structure()
    {
        $description = Customer::whereAny(['full_name', 'email_address'], 'like', '%john%')->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString('either full name or email address', $description);
        $this->assertStringContainsString("contain 'john'", $description);
    }

    public function test_where_all_structure()
    {
        $description = Customer::whereAll(['full_name', 'email_address'], 'like', '%john%')->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString('full name and email address', $description);
        $this->assertStringContainsString("contain 'john'", $description);
    }

    public function test_where_none_structure()
    {
        $description = Customer::whereNone(['full_name', 'email_address'], 'like', '%spam%')->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString('neither full name nor email address', $description);
        $this->assertStringContainsString("contain 'spam'", $description);
    }
}
