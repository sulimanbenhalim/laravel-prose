<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Feature;

use SulimanBenhalim\Prose\Tests\AsylumSeeker;
use SulimanBenhalim\Prose\Tests\LegalAdvisor;
use SulimanBenhalim\Prose\Tests\LegalAppointment;
use SulimanBenhalim\Prose\Tests\TestCase;

class RefugeeAsylumSystemTest extends TestCase
{
    public function test_asylum_seekers_requiring_interpreter(): void
    {
        $description = AsylumSeeker::where('has_interpreter_required', true)->describe();

        $this->assertStringContainsString('Find asylum seekers', $description);
        $this->assertStringContainsString('that have interpreter required', $description);
    }

    public function test_unaccompanied_minor_asylum_seekers(): void
    {
        $description = AsylumSeeker::where('is_minor_unaccompanied', true)
            ->whereNotNull('emergency_contact_phone')
            ->describe();

        $this->assertStringContainsString('Find asylum seekers', $description);
        $this->assertStringContainsString('that are minor unaccompanied', $description);
        $this->assertStringContainsString('with an emergency contact phone', $description);
    }

    public function test_asylum_seekers_from_specific_countries(): void
    {
        $description = AsylumSeeker::whereIn('country_of_origin', ['Syria', 'Afghanistan', 'Myanmar'])
            ->where('arrival_date', '>=', '2023-01-01')
            ->describe();

        $this->assertStringContainsString('Find asylum seekers', $description);
        $this->assertStringContainsString('country of origin being one of', $description);
        $this->assertStringContainsString('Syria', $description);
        $this->assertStringContainsString('arrival on or after', $description);
    }

    public function test_legal_appointments_needing_interpreters(): void
    {
        $description = LegalAppointment::where('interpreter_requested', true)
            ->where('appointment_status', 'scheduled')
            ->whereDate('scheduled_datetime', '>=', today())
            ->describe();

        $this->assertStringContainsString('Find legal appointments', $description);
        $this->assertStringContainsString('with interpreter requested', $description);
        $this->assertStringContainsString('appointment status is', $description);
        $this->assertStringContainsString('scheduled datetime within', $description);
    }

    public function test_pro_bono_legal_advisors_with_language_skills(): void
    {
        $description = LegalAdvisor::where('is_available_for_pro_bono', true)
            ->where('years_practicing_immigration_law', '>', 5)
            ->whereJsonContains('languages_spoken', 'Arabic')
            ->describe();

        $this->assertStringContainsString('Find legal advisors', $description);
        $this->assertStringContainsString('that are available for pro bono', $description);
        $this->assertStringContainsString('years practicing immigration law greater than 5', $description);
    }

    public function test_consultation_fees_waived_for_vulnerable_cases(): void
    {
        $description = LegalAppointment::where('consultation_fee_waived', '>', 0)
            ->whereHas('asylumSeeker', function ($query) {
                $query->where('is_minor_unaccompanied', true)
                    ->orWhere('has_interpreter_required', true);
            })
            ->describe();

        $this->assertStringContainsString('Find legal appointments', $description);
        $this->assertStringContainsString('consultation fee waived greater than 0', $description);
        $this->assertStringContainsString('who have asylum seeker', $description);
    }

    public function test_urgent_hearing_preparation_appointments(): void
    {
        $description = LegalAppointment::where('appointment_type', 'hearing_preparation')
            ->where('duration_minutes', '>', 90)
            ->whereDate('scheduled_datetime', today())
            ->with(['asylumSeeker', 'legalAdvisor'])
            ->describe();

        $this->assertStringContainsString('Find legal appointments', $description);
        $this->assertStringContainsString('appointment type is', $description);
        $this->assertStringContainsString('duration minutes greater than 90', $description);
        $this->assertStringContainsString('including their asylum seeker and legal advisor', $description);
    }

    public function test_asylum_seekers_with_special_needs(): void
    {
        $description = AsylumSeeker::whereNotNull('special_needs_description')
            ->where('preferred_language', '!=', 'English')
            ->orderBy('arrival_date', 'desc')
            ->limit(20)
            ->describe();

        $this->assertStringContainsString('Find first 20 asylum seekers', $description);
        $this->assertStringContainsString('with a special needs description', $description);
        $this->assertStringContainsString('preferred language not equal to', $description);
        $this->assertStringContainsString('sorted by arrival (newest to oldest)', $description);
    }

    public function test_overdue_appointment_follow_ups(): void
    {
        $description = LegalAppointment::where('appointment_status', 'no_show')
            ->whereDate('scheduled_datetime', '<', today())
            ->whereHas('asylumSeeker', function ($query) {
                $query->where('case_reference_number', 'like', 'URG%');
            })
            ->describe();

        $this->assertStringContainsString('Find legal appointments', $description);
        $this->assertStringContainsString('appointment status is', $description);
        $this->assertStringContainsString('scheduled datetime more than', $description);
        $this->assertStringContainsString('who have asylum seeker', $description);
    }

    public function test_legal_advisors_by_specialization(): void
    {
        $description = LegalAdvisor::whereJsonContains('specializations', 'asylum_law')
            ->where('hourly_rate_usd', '<', 200)
            ->orderBy('years_practicing_immigration_law', 'desc')
            ->describe();

        $this->assertStringContainsString('Find legal advisors', $description);
        $this->assertStringContainsString('hourly rate in USD less than 200', $description);
        $this->assertStringContainsString('sorted by years practicing immigration law (Z to A)', $description);
    }

    public function test_completed_initial_consultations_this_month(): void
    {
        $description = LegalAppointment::where('appointment_type', 'initial_consultation')
            ->where('appointment_status', 'completed')
            ->whereMonth('scheduled_datetime', now()->month)
            ->whereYear('scheduled_datetime', now()->year)
            ->describe();

        $this->assertStringContainsString('Find legal appointments', $description);
        $this->assertStringContainsString('appointment type is', $description);
        $this->assertStringContainsString('appointment status is', $description);
        $this->assertStringContainsString('scheduled datetime in August', $description);
    }
}
