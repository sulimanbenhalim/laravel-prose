<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Corpus;

use Carbon\Carbon;
use SulimanBenhalim\Prose\Tests\Customer;
use SulimanBenhalim\Prose\Tests\Order;
use SulimanBenhalim\Prose\Tests\Product;
use SulimanBenhalim\Prose\Tests\TestCase;

/**
 * Throughput benchmark for describe(). Run with:
 *   PROSE_BENCH=1 vendor/bin/phpunit tests/Corpus/BenchmarkTest.php
 */
class BenchmarkTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_describe_throughput(): void
    {
        if (! getenv('PROSE_BENCH')) {
            $this->markTestSkipped('Set PROSE_BENCH=1 to run the benchmark.');
        }

        Carbon::setTestNow(Carbon::parse('2026-08-24 14:30:00'));

        $queries = [
            fn () => Product::where('price_usd', '>', 100)->describe(),
            fn () => Customer::where('is_premium_member', true)->describe(),
            fn () => Order::where('order_status', 'pending')->where('total_amount_usd', '>', 500)->describe(),
            fn () => Order::where('created_at', '>', now()->subDays(7))->describe(),
            fn () => Customer::where('last_login_at', '<', now()->subMonths(3))->describe(),
            fn () => Order::whereDate('created_at', '>=', today())->describe(),
            fn () => Order::whereBetween('created_at', [now()->subDays(30), now()])->describe(),
            fn () => Customer::whereHas('orders', fn ($q) => $q->where('total_amount_usd', '>', 1000))->describe(),
            fn () => Customer::whereDoesntHave('orders')->describe(),
            fn () => Product::whereIn('sku_code', ['A', 'B', 'C'])->describe(),
            fn () => Customer::whereNull('email_verified_at')->describe(),
            fn () => Product::where('is_currently_available', true)->orderBy('price_usd', 'desc')->limit(10)->describe(),
            fn () => Product::whereAny(['product_name', 'product_description'], 'like', '%laptop%')->describe(),
            fn () => Order::where('order_status', 'pending')->describeUpdate(['order_status' => 'cancelled']),
            fn () => Customer::where('last_login_at', '<', now()->subYears(2))->describeDelete(),
            fn () => Order::where('order_status', 'completed')->describeSum('total_amount_usd'),
        ];

        // Warm up caches and JIT paths
        foreach ($queries as $query) {
            $query();
        }

        $iterations = (int) (getenv('PROSE_BENCH_ITERATIONS') ?: 500);

        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            foreach ($queries as $query) {
                $query();
            }
        }
        $elapsedSeconds = (hrtime(true) - $start) / 1e9;

        $total = $iterations * count($queries);
        $perSecond = (int) round($total / $elapsedSeconds);
        $microsEach = round($elapsedSeconds / $total * 1e6, 1);

        fwrite(STDERR, sprintf(
            "\nPROSE_BENCH: %d describes in %.3fs — %s describes/sec, %.1fµs each\n",
            $total,
            $elapsedSeconds,
            number_format($perSecond),
            $microsEach
        ));

        $this->assertGreaterThan(0, $perSecond);
    }
}
