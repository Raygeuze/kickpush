<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectUnitReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_work_is_included_in_project_revenue(): void
    {
        [$user, $project, $task] = $this->makeProjectAndTask();
        $this->makeUnitEntry($user, $task, 3, 70, 3600);

        $this->actingAs($user)
            ->get(route('projects.show', $project->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total_billable_amount', 210)
                ->where('summary.total_quantity', 3));
    }

    public function test_effective_hourly_rate_reflects_unit_revenue_over_hours_worked(): void
    {
        [$user, $project, $task] = $this->makeProjectAndTask();
        // 4 apartments at $70 = $280 earned across 2 hours of work.
        $this->makeUnitEntry($user, $task, 4, 70, 7200);

        $this->actingAs($user)
            ->get(route('projects.show', $project->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.effective_hourly_rate', 140));
    }

    public function test_unit_work_recorded_without_time_does_not_break_the_effective_rate(): void
    {
        [$user, $project, $task] = $this->makeProjectAndTask();
        $this->makeUnitEntry($user, $task, 2, 70, 0);

        $this->actingAs($user)
            ->get(route('projects.show', $project->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total_billable_amount', 140)
                ->where('summary.effective_hourly_rate', 0));
    }

    public function test_task_summary_reports_quantity_alongside_hours(): void
    {
        [$user, $project, $task] = $this->makeProjectAndTask();
        $this->makeUnitEntry($user, $task, 5, 70, 1800);

        $this->actingAs($user)
            ->get(route('projects.show', $project->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('taskSummaries.0.total_quantity', 5)
                ->where('taskSummaries.0.billable_amount', 350));
    }

    private function makeUnitEntry(User $user, Task $task, float $quantity, float $rate, int $seconds): WorkEntry
    {
        return WorkEntry::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'task_id' => $task->id,
            'billing_mode' => WorkEntry::MODE_UNIT,
            'quantity' => $quantity,
            'unit_rate_snapshot' => $rate,
            'unit_label_snapshot' => 'apartment',
            'project_id_snapshot' => $task->project_id,
            'started_at' => now()->subHours(3),
            'stopped_at' => now(),
            'duration_seconds' => $seconds,
        ]);
    }

    /**
     * @return array{0: User, 1: Project, 2: Task}
     */
    private function makeProjectAndTask(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = $user->currentTeam->id;

        $client = Client::create([
            'user_id' => $user->id,
            'team_id' => $teamId,
            'name' => 'Housekeeping Co',
            'currency' => 'NZD',
            'hourly_rate' => 0,
        ]);

        $project = Project::create([
            'user_id' => $user->id,
            'team_id' => $teamId,
            'client_id' => $client->id,
            'name' => 'Weekly Clean',
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_label' => 'apartment',
            'unit_rate' => 70,
            'is_active' => true,
        ]);

        $task = Task::create([
            'team_id' => $teamId,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'Standard Clean',
            'is_active' => true,
            'is_default' => true,
        ]);

        return [$user, $project, $task];
    }
}
