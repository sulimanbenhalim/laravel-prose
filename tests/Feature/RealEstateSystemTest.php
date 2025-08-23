<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Feature;

use SulimanBenhalim\Prose\Tests\Property;
use SulimanBenhalim\Prose\Tests\PropertyOffer;
use SulimanBenhalim\Prose\Tests\PropertyViewing;
use SulimanBenhalim\Prose\Tests\RealEstateAgent;
use SulimanBenhalim\Prose\Tests\TestCase;

class RealEstateSystemTest extends TestCase
{
    public function test_available_properties_for_sale()
    {
        $description = Property::where('listing_status', 'active')
            ->where('property_type', 'sale')
            ->describe();

        $this->assertStringContainsString('Find properties', $description);
        $this->assertStringContainsString("with listing status is 'active'", $description);
        $this->assertStringContainsString("with property type is 'sale'", $description);
    }

    public function test_properties_by_price_range_and_bedrooms()
    {
        $description = Property::whereBetween('listing_price_usd', [200000, 500000])
            ->where('bedroom_count', '>=', 3)
            ->where('bathroom_count', '>=', 2)
            ->describe();

        $this->assertStringContainsString('Find properties', $description);
        $this->assertStringContainsString('with listing price in USD ranging from 200000 to 500000', $description);
        $this->assertStringContainsString('with bedroom count greater than or equal to 3', $description);
        $this->assertStringContainsString('with bathroom count greater than or equal to 2', $description);
    }

    public function test_properties_with_specific_features()
    {
        $description = Property::where('has_garage', true)
            ->where('has_garden', true)
            ->where('square_footage', '>', 2000)
            ->describe();

        $this->assertStringContainsString('Find properties', $description);
        $this->assertStringContainsString('that have garage', $description);
        $this->assertStringContainsString('that have garden', $description);
        $this->assertStringContainsString('with square footage greater than 2000', $description);
    }

    public function test_properties_by_location_pattern()
    {
        $description = Property::whereAny(['city', 'neighborhood'], 'like', '%downtown%')
            ->where('year_built', '>=', 2000)
            ->describe();

        $this->assertStringContainsString('Find properties', $description);
        $this->assertStringContainsString('whose either city or neighborhood contain', $description);
        $this->assertStringContainsString('with year built greater than or equal to 2000', $description);
    }

    public function test_agents_with_recent_sales()
    {
        $description = RealEstateAgent::where('total_sales_last_year', '>', 10)
            ->where('commission_rate_percentage', '<=', 3.0)
            ->orderBy('total_sales_last_year', 'desc')
            ->describe();

        $this->assertStringContainsString('Find real estate agents', $description);
        $this->assertStringContainsString('with total sales last year greater than 10', $description);
        $this->assertStringContainsString('with commission rate percentage less than or equal to 3', $description);
        $this->assertStringContainsString('sorted by total sales last year (Z to A)', $description);
    }

    public function test_recent_property_viewings()
    {
        $description = PropertyViewing::where('viewing_date', '>=', now()->subDays(7))
            ->where('viewing_status', 'completed')
            ->whereNotNull('client_feedback')
            ->describe();

        $this->assertStringContainsString('Find property viewings', $description);
        $this->assertStringContainsString('with viewing within the last', $description);
        $this->assertStringContainsString("with viewing status is 'completed'", $description);
        $this->assertStringContainsString('with a client feedback', $description);
    }

    public function test_pending_property_offers()
    {
        $description = PropertyOffer::where('offer_status', 'pending')
            ->where('offer_amount_usd', '>', 450000)
            ->with(['property', 'buyer_agent'])
            ->describe();

        $this->assertStringContainsString('Find property offers', $description);
        $this->assertStringContainsString("with offer status is 'pending'", $description);
        $this->assertStringContainsString('with offer amount in USD greater than 450000', $description);
        $this->assertStringContainsString('including their property and buyer agent', $description);
    }

    public function test_properties_with_viewing_activity()
    {
        $description = Property::whereHas('viewings', function ($query) {
            $query->where('viewing_date', '>=', now()->subDays(30))
                ->where('viewing_status', 'completed');
        })
            ->where('listing_status', 'active')
            ->orderBy('listed_date', 'desc')
            ->describe();

        $this->assertStringContainsString('Find properties', $description);
        $this->assertStringContainsString('who have property viewings', $description);
        $this->assertStringContainsString("with listing status is 'active'", $description);
        $this->assertStringContainsString('sorted by listed (newest to oldest)', $description);
    }
}
