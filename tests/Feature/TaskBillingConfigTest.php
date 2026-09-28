<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskBillingConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_created_without_billing_fields_inherits_the_project_configuration(): void
    {
        [$user, $client, $project] = $this->makeClientAndProject([
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_label' => 'apartment',
            'unit_rate' => 70,
        ]);

        $response = $this->actingAs($user)->postJson('/tasks/create', [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'Standard Clean',
        ]);

        $response->assertCreated()
            ->assertJsonPath('billing_config.billing_mode', WorkEntry::MODE_UNIT)
            ->assertJsonPath('billing_config.unit_label', 'apartment')
            ->assertJsonPath('billing_config.unit_rate', 70)
            ->assertJsonPath('billing_config.is_inherited', true);

        $this->assertNull(Task::first()->billing_mode);
    }

    public function test_task_can_override_the_inherited_unit_rate(): void
    {
        [$user, $client, $project] = $this->makeClientAndProject([
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_label' => 'apartment',
            'unit_rate' => 70,
        ]);

        $response = $this->actingAs($user)->postJson('/tasks/create', [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'Deep Clean',
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_rate' => 120,
        ]);

        $response->assertCreated()
            ->assertJsonPath('billing_config.unit_label', 'apartment')
            ->assertJsonPath('billing_config.unit_rate', 120)
            ->assertJsonPath('billing_config.is_inherited', false);
    }

    public function test_clearing_a_task_billing_mode_restores_inheritance(): void
    {
        [$user, $client, $project] = $this->makeClientAndProject([
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_label' => 'apartment',
            'unit_rate' => 70,
        ]);

        $task = Task::create([
            'team_id' => $user->currentTeam->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'Standard Clean',
            'billing_mode' => WorkEntry::MODE_TIME,
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->actingAs($user)->putJson("/tasks/{$task->id}", [
            'billing_mode' => null,
        ])->assertOk()
            ->assertJsonPath('billing_config.billing_mode', WorkEntry::MODE_UNIT)
            ->assertJsonPath('billing_config.is_inherited', true);
    }

    public function test_an_unknown_billing_mode_is_rejected(): void
    {
        [$user, $client, $project] = $this->makeClientAndProject([]);

        $this->actingAs($user)->postJson('/tasks/create', [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'Nonsense Mode',
            'billing_mode' => 'per_banana',
        ])->assertUnprocessable();
    }

    public function test_blank_unit_label_is_stored_as_null_so_it_keeps_inheriting(): void
    {
        [$user, $client, $project] = $this->makeClientAndProject([
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_label' => 'apartment',
            'unit_rate' => 70,
        ]);

        $this->actingAs($user)->postJson('/tasks/create', [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'Standard Clean',
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_label' => '   ',
        ])->assertCreated()
            ->assertJsonPath('billing_config.unit_label', 'apartment');

        $this->assertNull(Task::first()->unit_label);
    }

    /**
     * @return array{0: User, 1: Client, 2: Project}
     */
    private function makeClientAndProject(array $projectBilling): array
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

        $project = Project::create($projectBilling + [
            'user_id' => $user->id,
            'team_id' => $teamId,
            'client_id' => $client->id,
            'name' => 'Weekly Clean',
            'is_active' => true,
        ]);

        return [$user, $client, $project];
    }
}
