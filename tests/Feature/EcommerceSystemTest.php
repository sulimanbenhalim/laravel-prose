<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Feature;

use SulimanBenhalim\Prose\Tests\Customer;
use SulimanBenhalim\Prose\Tests\Order;
use SulimanBenhalim\Prose\Tests\OrderItem;
use SulimanBenhalim\Prose\Tests\Product;
use SulimanBenhalim\Prose\Tests\ProductCategory;
use SulimanBenhalim\Prose\Tests\TestCase;

class EcommerceSystemTest extends TestCase
{
    public function test_premium_customers_with_high_lifetime_spending(): void
    {
        $description = Customer::where('is_premium_member', true)
            ->where('total_lifetime_spending_usd', '>', 1000)
            ->where('loyalty_points_balance', '>', 500)
            ->where('email_notifications_enabled', true)
            ->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString('that are premium member', $description);
        $this->assertStringContainsString('total lifetime spending in USD greater than 1000', $description);
        $this->assertStringContainsString('loyalty points balance greater than 500', $description);
        $this->assertStringContainsString('with email notifications enabled', $description);
    }

    public function test_out_of_stock_products_needing_reorder(): void
    {
        $description = Product::where('stock_quantity_available', '<=', 5)
            ->where('minimum_stock_threshold', '>', 10)
            ->where('is_currently_available', true)
            ->whereHas('category', function ($query) {
                $query->where('is_featured_category', true);
            })
            ->describe();

        $this->assertStringContainsString('Find products', $description);
        $this->assertStringContainsString('stock quantity available less than or equal to 5', $description);
        $this->assertStringContainsString('minimum stock threshold greater than 10', $description);
        $this->assertStringContainsString('that are currently available', $description);
        $this->assertStringContainsString('who have product categories', $description);
    }

    public function test_high_value_orders_with_express_shipping(): void
    {
        $description = Order::where('total_amount_usd', '>', 500)
            ->where('express_shipping_requested', true)
            ->where('order_status', 'processing')
            ->whereDate('estimated_delivery_date', '<=', now()->addDays(2))
            ->describe();

        $this->assertStringContainsString('Find orders', $description);
        $this->assertStringContainsString('total amount in USD greater than 500', $description);
        $this->assertStringContainsString('with express shipping requested', $description);
        $this->assertStringContainsString('order status is', $description);
        $this->assertStringContainsString('estimated delivery in the next', $description);
    }

    public function test_heavy_products_requiring_special_shipping(): void
    {
        $description = Product::where('weight_kg', '>', 10)
            ->where('requires_shipping', true)
            ->where('price_usd', '<', 200)
            ->whereJsonContains('product_tags', 'fragile')
            ->describe();

        $this->assertStringContainsString('Find products', $description);
        $this->assertStringContainsString('weight kg greater than 10', $description);
        $this->assertStringContainsString('with requires shipping', $description);
        $this->assertStringContainsString('price in USD less than 200', $description);
    }

    public function test_gift_orders_with_special_instructions(): void
    {
        $description = Order::where('is_gift_order', true)
            ->whereNotNull('special_instructions')
            ->where('payment_method', 'credit_card')
            ->whereHas('customer', function ($query) {
                $query->where('is_premium_member', true);
            })
            ->with(['customer', 'orderItems.product'])
            ->describe();

        $this->assertStringContainsString('Find orders', $description);
        $this->assertStringContainsString('that are gift order', $description);
        $this->assertStringContainsString('with special instructions', $description);
        $this->assertStringContainsString('payment method is', $description);
        $this->assertStringContainsString('who have customer', $description);
        $this->assertStringContainsString('including their customer and order items product', $description);
    }

    public function test_profitable_products_with_high_margins(): void
    {
        $description = Product::whereColumn('price_usd', '>', 'wholesale_cost_usd * 2')
            ->where('stock_quantity_available', '>', 50)
            ->whereHas('orderItems', function ($query) {
                $query->where('quantity_ordered', '>', 100);
            }, '>', 5)
            ->describe();

        $this->assertStringContainsString('Find products', $description);
        $this->assertStringContainsString('stock quantity available greater than 50', $description);
        $this->assertStringContainsString('who have order items', $description);
    }

    public function test_inactive_customers_needing_re_engagement(): void
    {
        $description = Customer::where('last_login_at', '<', now()->subMonths(6))
            ->where('total_lifetime_spending_usd', '>', 250)
            ->whereDoesntHave('orders', function ($query) {
                $query->where('created_at', '>', now()->subMonths(3));
            })
            ->orderBy('total_lifetime_spending_usd', 'desc')
            ->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString('last login more than', $description);
        $this->assertStringContainsString('total lifetime spending in USD greater than 250', $description);
        $this->assertStringContainsString("who don't have orders", $description);
        $this->assertStringContainsString('sorted by total lifetime spending in USD (highest to lowest)', $description);
    }

    public function test_featured_categories_with_multiple_products(): void
    {
        $description = ProductCategory::where('is_featured_category', true)
            ->whereHas('products', function ($query) {
                $query->where('is_currently_available', true);
            }, '>', 10)
            ->orderBy('display_order', 'asc')
            ->describe();

        $this->assertStringContainsString('Find product categories', $description);
        $this->assertStringContainsString('that are featured category', $description);
        $this->assertStringContainsString('who have products', $description);
        $this->assertStringContainsString('sorted by display order (A to Z)', $description);
    }

    public function test_bulk_order_items_with_discounts(): void
    {
        $description = OrderItem::where('quantity_ordered', '>', 20)
            ->whereColumn('unit_price_usd', '<', 'product.price_usd')
            ->whereHas('order', function ($query) {
                $query->where('order_status', 'delivered')
                    ->where('total_amount_usd', '>', 1000);
            })
            ->describe();

        $this->assertStringContainsString('Find order items', $description);
        $this->assertStringContainsString('quantity ordered greater than 20', $description);
        $this->assertStringContainsString('who have order', $description);
    }

    public function test_cancelled_orders_requiring_investigation(): void
    {
        $description = Order::where('order_status', 'cancelled')
            ->where('total_amount_usd', '>', 300)
            ->whereHas('orderItems', function ($query) {
                $query->whereHas('product', function ($productQuery) {
                    $productQuery->where('is_currently_available', true);
                });
            })
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->describe();

        $this->assertStringContainsString('Find orders', $description);
        $this->assertStringContainsString('order status is', $description);
        $this->assertStringContainsString('total amount in USD greater than 300', $description);
        $this->assertStringContainsString('who have order items', $description);
        $this->assertStringContainsString('created within', $description);
    }

    public function test_customers_approaching_birthday_for_promotions(): void
    {
        $description = Customer::whereMonth('date_of_birth', now()->addWeek()->month)
            ->whereDay('date_of_birth', '>=', now()->addWeek()->day)
            ->whereDay('date_of_birth', '<=', now()->addWeek()->addDays(7)->day)
            ->where('email_notifications_enabled', true)
            ->where('total_lifetime_spending_usd', '>', 100)
            ->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString('date of birth in August', $description);
        $this->assertStringContainsString('with email notifications enabled', $description);
        $this->assertStringContainsString('total lifetime spending in USD greater than 100', $description);
    }

    public function test_products_with_low_profit_margins(): void
    {
        $description = Product::whereRaw('(price_usd - wholesale_cost_usd) / price_usd < 0.2')
            ->where('stock_quantity_available', '>', 0)
            ->whereNotNull('product_tags')
            ->limit(15)
            ->orderBy('price_usd', 'desc')
            ->describe();

        $this->assertStringContainsString('Find first 15 products', $description);
        $this->assertStringContainsString('with custom database operations', $description);
        $this->assertStringContainsString('stock quantity available greater than 0', $description);
        $this->assertStringContainsString('with product tags', $description);
        $this->assertStringContainsString('sorted by price in USD (highest to lowest)', $description);
    }
}
