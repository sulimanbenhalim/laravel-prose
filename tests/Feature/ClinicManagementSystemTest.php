<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Feature;

use SulimanBenhalim\Prose\Tests\Doctor;
use SulimanBenhalim\Prose\Tests\MedicalAppointment;
use SulimanBenhalim\Prose\Tests\Patient;
use SulimanBenhalim\Prose\Tests\TestCase;

class ClinicManagementSystemTest extends TestCase
{
    public function test_patients_with_chronic_conditions_and_allergies(): void
    {
        $description = Patient::where('has_chronic_conditions', true)
            ->whereNotNull('known_allergies')
            ->whereNotNull('insurance_provider')
            ->describe();

        $this->assertStringContainsString('Find patients', $description);
        $this->assertStringContainsString('that have chronic conditions', $description);
        $this->assertStringContainsString('with known allergies', $description);
        $this->assertStringContainsString('with an insurance provider', $description);
    }

    public function test_upcoming_medical_appointments_covered_by_insurance(): void
    {
        $description = MedicalAppointment::where('insurance_covers_visit', true)
            ->where('appointment_status', 'scheduled')
            ->whereDate('scheduled_datetime', '>=', today())
            ->where('consultation_fee_usd', '>', 150)
            ->describe();

        $this->assertStringContainsString('Find medical appointments', $description);
        $this->assertStringContainsString('with insurance covers visit', $description);
        $this->assertStringContainsString('appointment status is', $description);
        $this->assertStringContainsString('scheduled today or later', $description);
        $this->assertStringContainsString('consultation fee in USD greater than 150', $description);
    }

    public function test_cardiologists_accepting_new_patients(): void
    {
        $description = Doctor::where('specialty', 'cardiology')
            ->where('is_accepting_new_patients', true)
            ->where('years_of_practice', '>', 10)
            ->whereJsonContains('hospital_affiliations', 'General Hospital')
            ->describe();

        $this->assertStringContainsString('Find doctors', $description);
        $this->assertStringContainsString('specialty is', $description);
        $this->assertStringContainsString('that are accepting new patients', $description);
        $this->assertStringContainsString('years of practice greater than 10', $description);
    }

    public function test_emergency_appointments_without_insurance_coverage(): void
    {
        $description = MedicalAppointment::where('appointment_type', 'emergency')
            ->where('insurance_covers_visit', false)
            ->whereNull('diagnosis_notes')
            ->whereHas('patient', function ($query) {
                $query->whereNull('insurance_provider');
            })
            ->describe();

        $this->assertStringContainsString('Find medical appointments', $description);
        $this->assertStringContainsString('appointment type is', $description);
        $this->assertStringContainsString('without insurance covers visit', $description);
        $this->assertStringContainsString('without diagnosis notes', $description);
        $this->assertStringContainsString('who have a patient without an insurance provider', $description);
    }

    public function test_pediatric_patients_under_specific_age(): void
    {
        $description = Patient::where('date_of_birth', '>', now()->subYears(16))
            ->whereHas('medicalAppointments', function ($query) {
                $query->whereHas('doctor', function ($doctorQuery) {
                    $doctorQuery->where('specialty', 'pediatrics');
                });
            })
            ->with('medicalAppointments.doctor')
            ->describe();

        $this->assertStringContainsString('Find patients', $description);
        $this->assertStringContainsString('date of birth within the last 16 years', $description);
        $this->assertStringContainsString('who have medical appointments', $description);
        $this->assertStringContainsString('including their medical appointments and their doctor', $description);
    }

    public function test_high_cost_procedures_requiring_follow_up(): void
    {
        $description = MedicalAppointment::where('appointment_type', 'procedure')
            ->where('consultation_fee_usd', '>', 500)
            ->where('estimated_duration_minutes', '>', 120)
            ->whereDate('scheduled_datetime', '>=', today())
            ->orderBy('consultation_fee_usd', 'desc')
            ->describe();

        $this->assertStringContainsString('Find medical appointments', $description);
        $this->assertStringContainsString('appointment type is', $description);
        $this->assertStringContainsString('consultation fee in USD greater than 500', $description);
        $this->assertStringContainsString('estimated duration in minutes greater than 120', $description);
        $this->assertStringContainsString('sorted by consultation fee in USD (highest to lowest)', $description);
    }

    public function test_doctors_with_multiple_hospital_affiliations(): void
    {
        $description = Doctor::whereJsonLength('hospital_affiliations', '>', 2)
            ->where('consultation_fee_usd', '<', 200)
            ->whereIn('specialty', ['general_practice', 'internal_medicine'])
            ->describe();

        $this->assertStringContainsString('Find doctors', $description);
        $this->assertStringContainsString('consultation fee in USD less than 200', $description);
        $this->assertStringContainsString('specialty being one of', $description);
        $this->assertStringContainsString('general_practice', $description);
        $this->assertStringContainsString('internal_medicine', $description);
    }

    public function test_cancelled_appointments_with_chronic_patients(): void
    {
        $description = MedicalAppointment::where('appointment_status', 'cancelled')
            ->whereHas('patient', function ($query) {
                $query->where('has_chronic_conditions', true)
                    ->where('gender', 'female');
            })
            ->whereMonth('scheduled_datetime', now()->month)
            ->describe();

        $this->assertStringContainsString('Find medical appointments', $description);
        $this->assertStringContainsString('appointment status is', $description);
        $this->assertStringContainsString('who have a patient that has chronic conditions', $description);
        $this->assertStringContainsString('scheduled datetime in August', $description);
    }

    public function test_patients_missing_emergency_contact_information(): void
    {
        $description = Patient::whereNull('emergency_contact_phone')
            ->orWhere('emergency_contact_name', '')
            ->where('has_chronic_conditions', true)
            ->orderBy('date_of_birth', 'asc')
            ->limit(25)
            ->describe();

        $this->assertStringContainsString('Find first 25 patients', $description);
        $this->assertStringContainsString('without an emergency contact phone', $description);
        $this->assertStringContainsString('that have chronic conditions', $description);
        $this->assertStringContainsString('sorted by date of birth (oldest to newest)', $description);
    }

    public function test_overbooked_doctors_this_week(): void
    {
        $description = Doctor::whereHas('medicalAppointments', function ($query) {
            $query->whereBetween('scheduled_datetime', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])->where('appointment_status', 'scheduled');
        }, '>', 40)
            ->where('is_accepting_new_patients', false)
            ->describe();

        $this->assertStringContainsString('Find doctors', $description);
        $this->assertStringContainsString('who have more than 40 medical appointments', $description);
        $this->assertStringContainsString('that are not accepting new patients', $description);
    }
}
