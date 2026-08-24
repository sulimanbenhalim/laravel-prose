<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Feature;

use SulimanBenhalim\Prose\Tests\RentalLocation;
use SulimanBenhalim\Prose\Tests\TestCase;
use SulimanBenhalim\Prose\Tests\Vehicle;
use SulimanBenhalim\Prose\Tests\VehicleBooking;

class VehicleRentalSystemTest extends TestCase
{
    public function test_available_vehicles_basic()
    {
        $description = Vehicle::where('is_available_for_rent', true)->describe();

        $this->assertStringContainsString('Find vehicles', $description);
        $this->assertStringContainsString('that are available for rent', $description);
    }

    public function test_vehicle_by_fuel_type_and_transmission()
    {
        $description = Vehicle::where('fuel_type', 'hybrid')
            ->where('transmission_type', 'automatic')
            ->describe();

        $this->assertStringContainsString('Find vehicles', $description);
        $this->assertStringContainsString("whose fuel type is 'hybrid'", $description);
        $this->assertStringContainsString("whose transmission type is 'automatic'", $description);
    }

    public function test_vehicles_by_price_range()
    {
        $description = Vehicle::whereBetween('daily_rate_usd', [50, 150])
            ->where('is_available_for_rent', true)
            ->describe();

        $this->assertStringContainsString('Find vehicles', $description);
        $this->assertStringContainsString('with daily rate in USD ranging from 50 to 150', $description);
        $this->assertStringContainsString('that are available for rent', $description);
    }

    public function test_vehicles_with_maintenance_due()
    {
        $description = Vehicle::where('next_inspection_due', '<', now()->addDays(30))
            ->orderBy('next_inspection_due', 'asc')
            ->describe();

        $this->assertStringContainsString('Find vehicles', $description);
        $this->assertStringContainsString('with next inspection due in the next 30 days', $description);
        $this->assertStringContainsString('sorted by next inspection due (oldest to newest)', $description);
    }

    public function test_bookings_with_driver_verification()
    {
        $description = VehicleBooking::where('driver_license_verified', true)
            ->where('booking_status', 'confirmed')
            ->whereHas('vehicle', function ($query) {
                $query->where('fuel_type', 'electric');
            })
            ->describe();

        $this->assertStringContainsString('Find vehicle bookings', $description);
        $this->assertStringContainsString('with driver license verified', $description);
        $this->assertStringContainsString("whose booking status is 'confirmed'", $description);
        $this->assertStringContainsString('who have a vehicle', $description);
    }

    public function test_upcoming_bookings()
    {
        $description = VehicleBooking::where('booking_start_date', '>', now())
            ->where('payment_status', 'paid')
            ->with(['vehicle', 'customer'])
            ->orderBy('booking_start_date', 'asc')
            ->describe();

        $this->assertStringContainsString('Find vehicle bookings', $description);
        $this->assertStringContainsString('with booking start in the future', $description);
        $this->assertStringContainsString("whose payment status is 'paid'", $description);
        $this->assertStringContainsString('including their vehicle and customer', $description);
        $this->assertStringContainsString('sorted by booking start (oldest to newest)', $description);
    }

    public function test_airport_locations_with_capacity()
    {
        $description = RentalLocation::where('is_airport_location', true)
            ->where('parking_capacity', '>=', 50)
            ->describe();

        $this->assertStringContainsString('Find rental locations', $description);
        $this->assertStringContainsString('that are airport location', $description);
        $this->assertStringContainsString('with parking capacity greater than or equal to 50', $description);
    }

    public function test_vehicles_by_make_and_year()
    {
        $description = Vehicle::whereAny(['make', 'model_name'], 'like', '%toyota%')
            ->where('manufacture_year', '>=', 2020)
            ->describe();

        $this->assertStringContainsString('Find vehicles', $description);
        $this->assertStringContainsString('whose either make or model name contain', $description);
        $this->assertStringContainsString('with manufacture year greater than or equal to 2020', $description);
    }
}
