<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Readme;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use SulimanBenhalim\Prose\Tests\TestCase;

/**
 * Every example shown in README.md, asserted byte-for-byte so the README
 * can never drift from real output. The clock is frozen mid-afternoon so
 * relative phrases are deterministic.
 */
class ReadmeExamplesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-24 14:30:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_basic_query_translation(): void
    {
        $this->assertSame(
            'Find users created after January 1, 2024',
            User::where('created_at', '>', '2024-01-01')->describe()
        );

        $this->assertSame(
            'Find first 10 products that are featured sorted by price in USD (highest to lowest)',
            Product::where('is_featured', true)->orderBy('price_usd', 'desc')->limit(10)->describe()
        );
    }

    public function test_advanced_operations(): void
    {
        $this->assertSame(
            'Count orders created within the last 14 days',
            Order::where('created_at', '>', now()->subWeeks(2))->describeCount()
        );

        $this->assertSame(
            'Find customers who have orders with total amount in USD greater than 1000',
            Customer::whereHas('orders', fn ($q) => $q->where('total_amount_usd', '>', 1000))->describe()
        );

        $this->assertSame(
            "Find products whose either name or description contain 'laptop'",
            Product::whereAny(['name', 'description'], 'like', '%laptop%')->describe()
        );
    }

    public function test_action_descriptions(): void
    {
        $this->assertSame(
            "Delete customers whose status is 'banned'",
            Customer::where('status', 'banned')->describeDelete()
        );

        $this->assertSame(
            'Delete users with last login more than 2 years ago and with unverified email',
            User::where('last_login_at', '<', now()->subYears(2))->whereNull('email_verified_at')->describeDelete()
        );

        $this->assertSame(
            'Update customers with unverified email to be verified',
            Customer::where('email_verified_at', null)->describeUpdate(['email_verified_at' => now()])
        );

        $this->assertSame(
            'Update products whose stock quantity available is 0 and that are currently available to not be currently available',
            Product::where('stock_quantity_available', 0)->where('is_currently_available', true)->describeUpdate(['is_currently_available' => false])
        );

        $this->assertSame(
            "Update orders whose order status is 'pending' and created more than 24 hours ago to have cancelled order status",
            Order::where('order_status', 'pending')->where('created_at', '<', now()->subHours(24))->describeUpdate(['order_status' => 'cancelled'])
        );

        $this->assertSame(
            'Delete products whose stock quantity available is 0',
            Product::where('stock_quantity_available', 0)->describeDelete()
        );

        $this->assertSame(
            "Sum total amount in USD for orders whose order status is 'completed'",
            Order::where('order_status', 'completed')->describeSum('total_amount_usd')
        );

        $this->assertSame(
            'Average total lifetime spending in USD for customers that are premium members',
            Customer::where('is_premium_member', true)->describeAvg('total_lifetime_spending_usd')
        );
    }

    public function test_relationship_intelligence(): void
    {
        $this->assertSame(
            'Find orders including their customer and products',
            Order::with('customer', 'products')->describe()
        );

        $this->assertSame(
            "Find customers who don't have orders",
            Customer::whereDoesntHave('orders')->describe()
        );

        $this->assertSame(
            "Find orders whose order status is 'shipped' including their customer and products",
            Order::where('order_status', 'shipped')->with('customer', 'products')->describe()
        );
    }

    public function test_time_intelligence_table(): void
    {
        $describe = fn ($value) => Order::where('created_at', '>', $value)->describe();

        $this->assertSame('Find orders created within the last 30 minutes', $describe(now()->subMinutes(30)));
        $this->assertSame('Find orders created within the last 7 days', $describe(now()->subDays(7)));
        $this->assertSame('Find orders created within the last 14 days', $describe(now()->subWeeks(2)));
        $this->assertSame('Find orders created after January 1, 2024', $describe('2024-01-01'));

        $this->assertSame(
            'Find orders with estimated delivery in the next 14 days',
            Order::where('estimated_delivery_date', '<', now()->addDays(14))->describe()
        );

        $this->assertSame(
            'Find orders created today',
            Order::whereDate('created_at', today())->describe()
        );
    }

    public function test_field_intelligence(): void
    {
        $this->assertSame(
            'Find customers that are premium members',
            Customer::where('is_premium_member', true)->describe()
        );

        $this->assertSame(
            "Find products that don't have warranty",
            Product::where('has_warranty', false)->describe()
        );

        $this->assertSame(
            'Find customers who verified their email within the last 30 days',
            Customer::where('email_verified_at', '>', now()->subDays(30))->describe()
        );

        $this->assertSame(
            'Find customers with unverified email',
            Customer::whereNull('email_verified_at')->describe()
        );

        $this->assertSame(
            'Find products with price in USD greater than 100',
            Product::where('price_usd', '>', 100)->describe()
        );

        $this->assertSame(
            'Find customers with total lifetime spending in USD ranging from 500 to 5000',
            Customer::whereBetween('total_lifetime_spending_usd', [500, 5000])->describe()
        );
    }

    public function test_realworld_scenarios(): void
    {
        $this->assertSame(
            'Find first 20 products that are featured, with stock quantity available greater than 0 '
            .'and who have reviews with rating greater than or equal to 4 '
            .'including their category and reviews sorted by sales count (highest to lowest)',
            Product::where('is_featured', true)
                ->where('stock_quantity_available', '>', 0)
                ->whereHas('reviews', fn ($q) => $q->where('rating', '>=', 4))
                ->with('category', 'reviews')
                ->orderBy('sales_count', 'desc')
                ->limit(20)
                ->describe()
        );

        $this->assertSame(
            'Find customers that are premium members, with last login within the last 7 days '
            .'and with verified email including their subscriptions '
            .'sorted by total lifetime spending in USD (highest to lowest)',
            Customer::where('is_premium_member', true)
                ->where('last_login_at', '>', now()->subDays(7))
                ->whereNotNull('email_verified_at')
                ->with('subscriptions')
                ->orderBy('total_lifetime_spending_usd', 'desc')
                ->describe()
        );
    }
}

/**
 * Lightweight demo models matching the field names used in README examples.
 * describe() never runs the query, so missing tables are fine; casts stand in
 * for schema information.
 */
class User extends Model
{
    protected $casts = ['created_at' => 'datetime', 'last_login_at' => 'datetime', 'email_verified_at' => 'datetime'];
}

class Product extends Model
{
    protected $casts = [
        'is_featured' => 'boolean',
        'has_warranty' => 'boolean',
        'is_currently_available' => 'boolean',
        'price_usd' => 'decimal:2',
        'stock_quantity_available' => 'integer',
        'sales_count' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}

class Category extends Model {}

class Review extends Model
{
    protected $casts = ['rating' => 'integer'];
}

class Order extends Model
{
    protected $casts = [
        'total_amount_usd' => 'decimal:2',
        'created_at' => 'datetime',
        'estimated_delivery_date' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }
}

class Customer extends Model
{
    protected $casts = [
        'is_premium_member' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'total_lifetime_spending_usd' => 'decimal:2',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}

class Subscription extends Model {}
