<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Feature;

use SulimanBenhalim\Prose\Tests\GymMember;
use SulimanBenhalim\Prose\Tests\GymSubscription;
use SulimanBenhalim\Prose\Tests\PersonalTrainer;
use SulimanBenhalim\Prose\Tests\TestCase;
use SulimanBenhalim\Prose\Tests\WorkoutSession;

class GymManagementSystemTest extends TestCase
{
    public function test_premium_gym_members_with_medical_clearance(): void
    {
        $description = GymMember::where('has_medical_clearance', true)
            ->where('emergency_contact_on_file', true)
            ->whereJsonContains('fitness_goals', 'weight_loss')
            ->describe();

        $this->assertStringContainsString('Find gym members', $description);
        $this->assertStringContainsString('that have medical clearance', $description);
        $this->assertStringContainsString('with emergency contact on file', $description);
    }

    public function test_active_premium_subscriptions_expiring_soon(): void
    {
        $description = GymSubscription::where('is_active_subscription', true)
            ->where('subscription_type', 'premium')
            ->whereDate('next_billing_date', '<=', now()->addDays(7))
            ->where('auto_renewal_enabled', false)
            ->describe();

        $this->assertStringContainsString('Find gym subscriptions', $description);
        $this->assertStringContainsString('that are active subscription', $description);
        $this->assertStringContainsString('subscription type is', $description);
        $this->assertStringContainsString('next billing in the next', $description);
        $this->assertStringContainsString('without auto renewal enabled', $description);
    }

    public function test_high_value_gym_subscriptions(): void
    {
        $description = GymSubscription::where('monthly_fee_usd', '>', 100)
            ->where('months_remaining', '>', 6)
            ->whereHas('gymMember', function ($query) {
                $query->where('membership_start_date', '>=', '2023-01-01');
            })
            ->describe();

        $this->assertStringContainsString('Find gym subscriptions', $description);
        $this->assertStringContainsString('monthly fee in USD greater than 100', $description);
        $this->assertStringContainsString('months remaining greater than 6', $description);
        $this->assertStringContainsString('who have gym member', $description);
    }

    public function test_intensive_workout_sessions_with_trainers(): void
    {
        $description = WorkoutSession::where('duration_minutes', '>', 90)
            ->whereNotNull('personal_trainer_id')
            ->where('calories_burned_estimate', '>', 500)
            ->whereDate('check_in_time', today())
            ->describe();

        $this->assertStringContainsString('Find workout sessions', $description);
        $this->assertStringContainsString('duration minutes greater than 90', $description);
        $this->assertStringContainsString('with a personal trainer', $description);
        $this->assertStringContainsString('calories burned estimate greater than 500', $description);
        $this->assertStringContainsString('check in time today', $description);
    }

    public function test_personal_trainers_specializing_in_weight_loss(): void
    {
        $description = PersonalTrainer::whereJsonContains('specialization_areas', 'weight_loss')
            ->where('is_currently_available', true)
            ->where('years_of_experience', '>=', 3)
            ->where('hourly_rate_usd', '<', 80)
            ->describe();

        $this->assertStringContainsString('Find personal trainers', $description);
        $this->assertStringContainsString('that are currently available', $description);
        $this->assertStringContainsString('years of experience greater than or equal to 3', $description);
        $this->assertStringContainsString('hourly rate in USD less than 80', $description);
    }

    public function test_members_without_recent_workout_activity(): void
    {
        $description = GymMember::doesntHave('workoutSessions')
            ->orWhereHas('workoutSessions', function ($query) {
                $query->whereDate('check_in_time', '<', now()->subDays(30));
            })
            ->where('membership_start_date', '<', now()->subMonths(2))
            ->describe();

        $this->assertStringContainsString('Find gym members', $description);
        $this->assertStringContainsString("who don't have workout sessions", $description);
        $this->assertStringContainsString('membership start more than', $description);
    }

    public function test_expired_subscriptions_needing_renewal(): void
    {
        $description = GymSubscription::where('is_active_subscription', false)
            ->where('auto_renewal_enabled', false)
            ->whereHas('gymMember', function ($query) {
                $query->where('has_medical_clearance', true);
            })
            ->with('gymMember')
            ->orderBy('next_billing_date', 'desc')
            ->describe();

        $this->assertStringContainsString('Find gym subscriptions', $description);
        $this->assertStringContainsString('that are not active subscription', $description);
        $this->assertStringContainsString('without auto renewal enabled', $description);
        $this->assertStringContainsString('who have gym member', $description);
        $this->assertStringContainsString('including their gym member', $description);
        $this->assertStringContainsString('sorted by next billing (newest to oldest)', $description);
    }

    public function test_peak_hours_workout_sessions(): void
    {
        $description = WorkoutSession::whereTime('check_in_time', '>=', '17:00')
            ->whereTime('check_in_time', '<=', '19:00')
            ->whereNotNull('check_out_time')
            ->where('duration_minutes', '>', 45)
            ->describe();

        $this->assertStringContainsString('Find workout sessions', $description);
        $this->assertStringContainsString('with a check out time', $description);
        $this->assertStringContainsString('duration minutes', $description);
    }

    public function test_trainers_with_high_client_capacity(): void
    {
        $description = PersonalTrainer::where('max_clients_per_day', '>=', 10)
            ->where('certification_type', '!=', 'basic')
            ->whereJsonContains('specialization_areas', 'muscle_building')
            ->orderBy('hourly_rate_usd', 'asc')
            ->limit(5)
            ->describe();

        $this->assertStringContainsString('Find first 5 personal trainers', $description);
        $this->assertStringContainsString('max clients per day greater than or equal to 10', $description);
        $this->assertStringContainsString('certification type not equal to', $description);
        $this->assertStringContainsString('sorted by hourly rate in USD (lowest to highest)', $description);
    }

    public function test_members_with_multiple_fitness_goals(): void
    {
        $description = GymMember::whereJsonLength('fitness_goals', '>', 2)
            ->where('date_of_birth', '<', now()->subYears(35))
            ->whereHas('gymSubscriptions', function ($query) {
                $query->where('subscription_type', 'vip');
            })
            ->describe();

        $this->assertStringContainsString('Find gym members', $description);
        $this->assertStringContainsString('date of birth more than', $description);
        $this->assertStringContainsString('who have gym subscriptions', $description);
    }
}
