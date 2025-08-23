<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Feature;

use SulimanBenhalim\Prose\Tests\AsylumSeeker;
use SulimanBenhalim\Prose\Tests\Customer;
use SulimanBenhalim\Prose\Tests\GymMember;
use SulimanBenhalim\Prose\Tests\GymSubscription;
use SulimanBenhalim\Prose\Tests\LegalAppointment;
use SulimanBenhalim\Prose\Tests\MedicalAppointment;
use SulimanBenhalim\Prose\Tests\Order;
use SulimanBenhalim\Prose\Tests\Patient;
use SulimanBenhalim\Prose\Tests\Product;
use SulimanBenhalim\Prose\Tests\TestCase;

class RealWorldEdgeCasesTest extends TestCase
{
    public function test_complex_boolean_patterns_across_systems(): void
    {
        $description1 = AsylumSeeker::where('has_interpreter_required', false)
            ->where('is_minor_unaccompanied', true)
            ->describe();

        $this->assertStringContainsString("that don't have interpreter required", $description1);
        $this->assertStringContainsString('that are minor unaccompanied', $description1);

        $description2 = GymMember::where('has_medical_clearance', true)
            ->where('emergency_contact_on_file', false)
            ->describe();

        $this->assertStringContainsString('that have medical clearance', $description2);
        $this->assertStringContainsString('without emergency contact on file', $description2);

        $description3 = Product::where('is_currently_available', false)
            ->where('requires_shipping', true)
            ->describe();

        $this->assertStringContainsString('that are not currently available', $description3);
        $this->assertStringContainsString('with requires shipping', $description3);
    }

    public function test_complex_date_time_scenarios(): void
    {
        $description = LegalAppointment::whereDate('scheduled_datetime', today())
            ->whereTime('scheduled_datetime', '>=', '09:00')
            ->whereTime('scheduled_datetime', '<=', '17:00')
            ->whereYear('scheduled_datetime', now()->year)
            ->whereMonth('scheduled_datetime', now()->month)
            ->describe();

        $this->assertStringContainsString('Find legal appointments', $description);
        $this->assertStringContainsString('scheduled datetime today', $description);
        $this->assertStringContainsString('scheduled datetime in', $description);
    }

    public function test_extreme_numeric_values(): void
    {
        $description1 = Customer::where('total_lifetime_spending_usd', '>', 50000.99)
            ->whereBetween('loyalty_points_balance', [10000, 999999])
            ->describe();

        $this->assertStringContainsString('total lifetime spending in USD greater than 50000.99', $description1);
        $this->assertStringContainsString('loyalty points balance ranging from 10000 to 999999', $description1);

        $description2 = GymSubscription::where('monthly_fee_usd', 0)
            ->where('months_remaining', '<', 0)
            ->describe();

        $this->assertStringContainsString('monthly fee in USD is 0', $description2);
        $this->assertStringContainsString('months remaining less than 0', $description2);
    }

    public function test_long_descriptive_field_names(): void
    {
        $description = Patient::where('emergency_contact_phone', 'like', '+1%')
            ->whereNotNull('insurance_policy_number')
            ->describe();

        $this->assertStringContainsString('emergency contact phone starting with', $description);
        $this->assertStringContainsString('with an insurance policy number', $description);
    }

    public function test_deeply_nested_relationships(): void
    {
        $description = Order::whereHas('orderItems', function ($query) {
            $query->whereHas('product', function ($productQuery) {
                $productQuery->whereHas('category', function ($categoryQuery) {
                    $categoryQuery->where('is_featured_category', true)
                        ->where('display_order', '<', 5);
                });
            });
        })
            ->with(['customer', 'orderItems.product.category'])
            ->describe();

        $this->assertStringContainsString('Find orders', $description);
        $this->assertStringContainsString('who have order items', $description);
        $this->assertStringContainsString('including their customer and order items product category', $description);
    }

    public function test_multiple_in_clauses_different_types(): void
    {
        $description = MedicalAppointment::whereIn('appointment_type', ['consultation', 'follow_up', 'emergency'])
            ->whereIn('appointment_status', ['scheduled', 'completed'])
            ->whereIn('estimated_duration_minutes', [30, 45, 60])
            ->describe();

        $this->assertStringContainsString('appointment type being one of', $description);
        $this->assertStringContainsString('consultation', $description);
        $this->assertStringContainsString('follow_up', $description);
        $this->assertStringContainsString('emergency', $description);
        $this->assertStringContainsString('appointment status being one of', $description);
        $this->assertStringContainsString('estimated duration minutes being one of', $description);
    }

    public function test_json_field_queries(): void
    {
        $description = GymMember::whereJsonContains('fitness_goals', 'weight_loss')
            ->whereJsonLength('fitness_goals', '>', 2)
            ->describe();

        $this->assertStringContainsString('Find gym members', $description);
    }

    public function test_large_dataset_pagination(): void
    {
        $description = Product::where('stock_quantity_available', '>', 0)
            ->orderBy('price_usd', 'desc')
            ->orderBy('product_name', 'asc')
            ->limit(500)
            ->offset(2500)
            ->describe();

        $this->assertStringContainsString('Find first 500 products', $description);
        $this->assertStringContainsString('stock quantity available greater than 0', $description);
        $this->assertStringContainsString('sorted by price in USD (highest to lowest) then product name (A to Z)', $description);
        $this->assertStringContainsString('starting from position 2500', $description);
    }

    public function test_mixed_null_conditions(): void
    {
        $description = AsylumSeeker::whereNull('special_needs_description')
            ->whereNotNull('emergency_contact_phone')
            ->whereNull('special_needs_description')
            ->describe();

        $this->assertStringContainsString('without a special needs description', $description);
        $this->assertStringContainsString('with an emergency contact phone', $description);
    }

    public function test_raw_query_fallback(): void
    {
        $description = Customer::whereRaw('DATEDIFF(NOW(), last_login_at) > 90')
            ->where('email_notifications_enabled', true)
            ->describe();

        $this->assertStringContainsString('Find customers', $description);
        $this->assertStringContainsString('with custom database operations', $description);
        $this->assertStringContainsString('with email notifications enabled', $description);
    }

    public function test_extremely_complex_query_chain(): void
    {
        $description = LegalAppointment::where('appointment_status', 'scheduled')
            ->where('interpreter_requested', true)
            ->where('duration_minutes', '>', 60)
            ->whereDate('scheduled_datetime', '>=', today())
            ->whereTime('scheduled_datetime', '>=', '09:00')
            ->whereTime('scheduled_datetime', '<=', '17:00')
            ->whereHas('asylumSeeker', function ($query) {
                $query->where('is_minor_unaccompanied', true)
                    ->where('country_of_origin', '!=', 'Unknown')
                    ->whereNotNull('emergency_contact_phone');
            })
            ->whereHas('legalAdvisor', function ($query) {
                $query->where('is_available_for_pro_bono', true)
                    ->where('years_practicing_immigration_law', '>', 3)
                    ->whereJsonContains('specializations', 'asylum_law');
            })
            ->with(['asylumSeeker', 'legalAdvisor'])
            ->orderBy('scheduled_datetime', 'asc')
            ->orderBy('duration_minutes', 'desc')
            ->limit(50)
            ->describe();

        $this->assertStringContainsString('Find first 50 legal appointments', $description);
        $this->assertStringContainsString('appointment status is', $description);
        $this->assertStringContainsString('with interpreter requested', $description);
        $this->assertStringContainsString('duration minutes greater than 60', $description);
        $this->assertStringContainsString('who have asylum seeker', $description);
        $this->assertStringContainsString('who have legal advisor', $description);
        $this->assertStringContainsString('including their asylum seeker and legal advisor', $description);
        $this->assertStringContainsString('sorted by scheduled datetime (oldest to newest) then duration minutes (Z to A)', $description);

        $conditionCount = substr_count($description, ' and ');
        $this->assertLessThan(20, $conditionCount, 'Should truncate excessive conditions for readability');
    }

    public function test_prose_disabled_complex_query(): void
    {
        config(['prose.enabled' => false]);

        $description = Order::where('is_gift_order', true)
            ->where('express_shipping_requested', true)
            ->whereHas('customer', function ($query) {
                $query->where('is_premium_member', true);
            })
            ->with(['customer', 'orderItems'])
            ->describe();

        $this->assertEquals('', $description);

        config(['prose.enabled' => true]);
    }

    public function test_challenging_field_names(): void
    {
        $description1 = GymMember::where('fitness_goals', 'like', '%fitness%')->describe();
        $this->assertStringContainsString('fitness goals containing', $description1);

        $description2 = Customer::where('date_of_birth', '>', '1990-01-01')->describe();
        $this->assertStringContainsString('date of birth after', $description2);

        $description3 = Product::where('sku_code', 'like', 'ELC%')->describe();
        $this->assertStringContainsString('sku code starting with', $description3);
    }
}
