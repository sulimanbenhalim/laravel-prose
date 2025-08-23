<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Feature;

use SulimanBenhalim\Prose\Tests\Course;
use SulimanBenhalim\Prose\Tests\CourseEnrollment;
use SulimanBenhalim\Prose\Tests\Faculty;
use SulimanBenhalim\Prose\Tests\Student;
use SulimanBenhalim\Prose\Tests\TestCase;

class UniversitySystemTest extends TestCase
{
    public function test_international_students()
    {
        $description = Student::where('is_international_student', true)->describe();

        $this->assertStringContainsString('Find students', $description);
        $this->assertStringContainsString('that are international student', $description);
    }

    public function test_students_by_gpa_range()
    {
        $description = Student::whereBetween('gpa_score', [3.5, 4.0])
            ->where('academic_year', 'senior')
            ->describe();

        $this->assertStringContainsString('Find students', $description);
        $this->assertStringContainsString('with gpa score ranging from 3.5 to 4', $description);
        $this->assertStringContainsString("with academic year is 'senior'", $description);
    }

    public function test_students_with_scholarship()
    {
        $description = Student::where('scholarship_amount_usd', '>', 0)
            ->whereNotNull('advisor_id')
            ->with('advisor')
            ->describe();

        $this->assertStringContainsString('Find students', $description);
        $this->assertStringContainsString('with scholarship amount in USD greater than 0', $description);
        $this->assertStringContainsString('with an advisor', $description);
        $this->assertStringContainsString('including their advisor', $description);
    }

    public function test_courses_by_semester_and_capacity()
    {
        $description = Course::where('semester', 'fall')
            ->where('current_enrollment_count', '<', 30)
            ->orderBy('credit_hours', 'desc')
            ->describe();

        $this->assertStringContainsString('Find courses', $description);
        $this->assertStringContainsString("with semester is 'fall'", $description);
        $this->assertStringContainsString('with current enrollment count less than 30', $description);
        $this->assertStringContainsString('sorted by credit hours (highest to lowest)', $description);
    }

    public function test_faculty_department_heads()
    {
        $description = Faculty::where('is_department_head', true)
            ->where('tenure_status', 'tenured')
            ->describe();

        $this->assertStringContainsString('Find faculties', $description);
        $this->assertStringContainsString('that are department head', $description);
        $this->assertStringContainsString("with tenure status is 'tenured'", $description);
    }

    public function test_enrollments_with_high_attendance()
    {
        $description = CourseEnrollment::where('attendance_percentage', '>=', 95.0)
            ->whereNotNull('final_grade')
            ->whereHas('student', function ($query) {
                $query->where('gpa_score', '>', 3.8);
            })
            ->describe();

        $this->assertStringContainsString('Find course enrollments', $description);
        $this->assertStringContainsString('with attendance percentage greater than or equal to 95', $description);
        $this->assertStringContainsString('with a final grade', $description);
        $this->assertStringContainsString('who have students', $description);
    }

    public function test_students_by_major_field()
    {
        $description = Student::whereAny(['major_field', 'minor_field'], 'like', '%computer%')
            ->where('credit_hours_completed', '>=', 60)
            ->describe();

        $this->assertStringContainsString('Find students', $description);
        $this->assertStringContainsString('whose either major field or minor field contain', $description);
        $this->assertStringContainsString('with credit hours completed greater than or equal to 60', $description);
    }

    public function test_faculty_with_research_interests()
    {
        $description = Faculty::where('research_interests', 'like', '%artificial intelligence%')
            ->where('salary_usd', '>', 75000)
            ->orderBy('hire_date', 'asc')
            ->describe();

        $this->assertStringContainsString('Find faculties', $description);
        $this->assertStringContainsString('with research interests containing', $description);
        $this->assertStringContainsString('with salary in USD greater than 75000', $description);
        $this->assertStringContainsString('sorted by hire (oldest to newest)', $description);
    }

    public function test_courses_by_time_schedule()
    {
        $description = Course::where('start_time', '>=', '09:00:00')
            ->where('end_time', '<=', '15:00:00')
            ->where('credit_hours', 3)
            ->describe();

        $this->assertStringContainsString('Find courses', $description);
        $this->assertStringContainsString('with start time greater than or equal to', $description);
        $this->assertStringContainsString('with end time less than or equal to', $description);
        $this->assertStringContainsString('with credit hours is 3', $description);
    }

    public function test_complex_student_advisor_relationship()
    {
        $description = Student::whereHas('advisor', function ($query) {
            $query->where('department', 'Computer Science')
                ->where('is_department_head', false);
        })
            ->whereHas('enrollments', function ($query) {
                $query->where('final_grade', 'A')
                    ->whereHas('course', function ($subQuery) {
                        $subQuery->where('credit_hours', '>=', 3);
                    });
            })
            ->with(['advisor', 'enrollments.course'])
            ->describe();

        $this->assertStringContainsString('Find students', $description);
        $this->assertStringContainsString('who have faculties', $description);
        $this->assertStringContainsString('who have course enrollments', $description);
        $this->assertStringContainsString('including their advisor and enrollments', $description);
    }
}
