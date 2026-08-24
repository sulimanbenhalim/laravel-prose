<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Feature;

use SulimanBenhalim\Prose\Tests\Employee;
use SulimanBenhalim\Prose\Tests\Project;
use SulimanBenhalim\Prose\Tests\TestCase;
use SulimanBenhalim\Prose\Tests\Timesheet;

class HRProjectManagementSystemTest extends TestCase
{
    public function test_active_employees_by_department()
    {
        $description = Employee::where('employment_status', 'active')
            ->where('department', 'Engineering')
            ->describe();

        $this->assertStringContainsString('Find employees', $description);
        $this->assertStringContainsString("whose employment status is 'active'", $description);
        $this->assertStringContainsString("whose department is 'Engineering'", $description);
    }

    public function test_employees_by_salary_and_experience()
    {
        $description = Employee::whereBetween('annual_salary_usd', [80000, 150000])
            ->where('years_of_experience', '>=', 5)
            ->where('is_remote_worker', true)
            ->describe();

        $this->assertStringContainsString('Find employees', $description);
        $this->assertStringContainsString('with annual salary in USD ranging from 80000 to 150000', $description);
        $this->assertStringContainsString('with years of experience greater than or equal to 5', $description);
        $this->assertStringContainsString('that are remote worker', $description);
    }

    public function test_employees_by_skill_pattern()
    {
        $description = Employee::whereAny(['primary_skill', 'secondary_skill'], 'like', '%python%')
            ->where('performance_rating', '>=', 4.0)
            ->describe();

        $this->assertStringContainsString('Find employees', $description);
        $this->assertStringContainsString('whose either primary skill or secondary skill contain', $description);
        $this->assertStringContainsString('with performance rating greater than or equal to 4', $description);
    }

    public function test_ongoing_projects_with_budget()
    {
        $description = Project::where('project_status', 'in_progress')
            ->where('budget_usd', '>', 100000)
            ->where('deadline_date', '>', now())
            ->describe();

        $this->assertStringContainsString('Find projects', $description);
        $this->assertStringContainsString("whose project status is 'in_progress'", $description);
        $this->assertStringContainsString('with budget in USD greater than 100000', $description);
        $this->assertStringContainsString('with deadline date in the future', $description);
    }

    public function test_overdue_projects_by_priority()
    {
        $description = Project::where('deadline_date', '<', now())
            ->where('project_status', '!=', 'completed')
            ->whereIn('priority_level', ['high', 'critical'])
            ->orderBy('deadline_date', 'asc')
            ->describe();

        $this->assertStringContainsString('Find projects', $description);
        $this->assertStringContainsString('with deadline date in the past', $description);
        $this->assertStringContainsString("whose project status is not 'completed'", $description);
        $this->assertStringContainsString('with priority level being one of', $description);
        $this->assertStringContainsString('sorted by deadline date (oldest to newest)', $description);
    }

    public function test_recent_timesheet_entries()
    {
        $description = Timesheet::where('work_date', '>=', now()->subDays(7))
            ->where('hours_worked', '>', 8)
            ->whereNotNull('task_description')
            ->describe();

        $this->assertStringContainsString('Find timesheets', $description);
        $this->assertStringContainsString('with work date within the last', $description);
        $this->assertStringContainsString('with hours worked greater than 8', $description);
        $this->assertStringContainsString('with a task description', $description);
    }

    public function test_employee_project_assignments()
    {
        $description = Employee::whereHas('projects', function ($query) {
            $query->where('project_status', 'in_progress')
                ->where('priority_level', 'high');
        })
            ->where('employment_status', 'active')
            ->with(['projects', 'timesheets'])
            ->describe();

        $this->assertStringContainsString('Find employees', $description);
        $this->assertStringContainsString('who have projects', $description);
        $this->assertStringContainsString("whose employment status is 'active'", $description);
        $this->assertStringContainsString('including their projects and timesheets', $description);
    }

    public function test_project_time_tracking_analysis()
    {
        $description = Project::whereHas('timesheets', function ($query) {
            $query->where('work_date', '>=', now()->subDays(30))
                ->where('hours_worked', '>', 0);
        })
            ->where('project_status', 'in_progress')
            ->with('timesheets')
            ->orderBy('start_date', 'desc')
            ->describe();

        $this->assertStringContainsString('Find projects', $description);
        $this->assertStringContainsString('who have timesheets', $description);
        $this->assertStringContainsString("whose project status is 'in_progress'", $description);
        $this->assertStringContainsString('including their timesheets', $description);
        $this->assertStringContainsString('sorted by start date (newest to oldest)', $description);
    }

    public function test_high_performing_team_analysis()
    {
        $description = Employee::where('performance_rating', '>=', 4.5)
            ->where('years_of_experience', '>=', 3)
            ->whereHas('timesheets', function ($query) {
                $query->where('work_date', '>=', now()->subDays(90))
                    ->whereBetween('hours_worked', [6, 10]);
            })
            ->whereHas('projects', function ($query) {
                $query->where('project_status', 'completed')
                    ->where('completion_date', '>=', now()->subMonths(6));
            })
            ->with('projects')
            ->describe();

        $this->assertStringContainsString('Find employees', $description);
        $this->assertStringContainsString('with performance rating greater than or equal to 4.5', $description);
        $this->assertStringContainsString('with years of experience greater than or equal to 3', $description);
        $this->assertStringContainsString('who have timesheets', $description);
        $this->assertStringContainsString('who have projects', $description);
        $this->assertStringContainsString('including their projects', $description);
    }
}
