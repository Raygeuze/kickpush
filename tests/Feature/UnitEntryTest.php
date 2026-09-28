<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_units_snapshots_the_rate_and_label_from_the_task(): void
    {
        [$user, $task] = $this->makeUnitTask(['unit_rate' => 70, 'unit_label' => 'apartment']);

        $response = $this->actingAs($user)->postJson('/unit-entries', [
            'task_id' => $task->id,
            'quantity' => 3,
        ]);

        $response->assertCreated()
            ->assertJsonPath('entry.billing_mode', WorkEntry::MODE_UNIT)
            ->assertJsonPath('entry.quantity', 3)
            ->assertJsonPath('entry.unit_rate', 70)
            ->assertJsonPath('entry.unit_label', 'apartment')
            ->assertJsonPath('entry.quantity_display', '3 apartments');

        $entry = WorkEntry::first();
        $this->assertSame(WorkEntry::MODE_UNIT, $entry->billing_mode);
        $this->assertSame('70.00', (string) $entry->unit_rate_snapshot);
        $this->assertSame('apartment', $entry->unit_label_snapshot);
    }

    public function test_snapshot_is_frozen_against_later_task_rate_changes(): void
    {
        [$user, $task] = $this->makeUnitTask(['unit_rate' => 70, 'unit_label' => 'apartment']);

        $this->actingAs($user)->postJson('/unit-entries', [
            'task_id' => $task->id,
            'quantity' => 2,
        ])->assertCreated();

        $task->update(['unit_rate' => 95]);

        $this->assertSame('70.00', (string) WorkEntry::first()->unit_rate_snapshot);
    }

    public function test_singular_label_is_used_for_a_quantity_of_one(): void
    {
        [$user, $task] = $this->makeUnitTask(['unit_rate' => 70, 'unit_label' => 'apartment']);

        $this->actingAs($user)->postJson('/unit-entries', [
            'task_id' => $task->id,
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('entry.quantity_display', '1 apartment');
    }

    public function test_units_cannot_be_recorded_against_an_hourly_task(): void
    {
        [$user, $task] = $this->makeUnitTask(['billing_mode' => WorkEntry::MODE_TIME]);

        $this->actingAs($user)->postJson('/unit-entries', [
            'task_id' => $task->id,
            'quantity' => 2,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'This task is not billed per unit. Change its billing mode before recording units.');

        $this->assertSame(0, WorkEntry::count());
    }

    public function test_units_cannot_be_recorded_when_no_rate_is_resolvable(): void
    {
        [$user, $task] = $this->makeUnitTask(['unit_label' => 'window', 'unit_rate' => null]);

        $this->actingAs($user)->postJson('/unit-entries', [
            'task_id' => $task->id,
            'quantity' => 4,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Set a rate per unit on this task or its project before recording units.');

        $this->assertSame(0, WorkEntry::count());
    }

    public function test_quantity_must_be_positive(): void
    {
        [$user, $task] = $this->makeUnitTask(['unit_rate' => 70, 'unit_label' => 'apartment']);

        $this->actingAs($user)->postJson('/unit-entries', [
            'task_id' => $task->id,
            'quantity' => 0,
        ])->assertUnprocessable();
    }

    public function test_fractional_quantities_are_supported(): void
    {
        [$user, $task] = $this->makeUnitTask(['unit_rate' => 25, 'unit_label' => 'load']);

        $this->actingAs($user)->postJson('/unit-entries', [
            'task_id' => $task->id,
            'quantity' => 1.5,
        ])->assertCreated()
            ->assertJsonPath('entry.quantity', 1.5)
            ->assertJsonPath('entry.quantity_display', '1.5 loads');
    }

    public function test_unit_entry_is_auto_assigned_to_a_draft_invoice(): void
    {
        [$user, $task] = $this->makeUnitTask(['unit_rate' => 70, 'unit_label' => 'apartment']);

        $this->actingAs($user)->postJson('/unit-entries', [
            'task_id' => $task->id,
            'quantity' => 3,
        ])->assertCreated();

        $this->assertNotNull(WorkEntry::first()->invoice_id);
    }

    public function test_quantity_can_be_corrected_after_the_fact(): void
    {
        [$user, $task] = $this->makeUnitTask(['unit_rate' => 70, 'unit_label' => 'apartment']);

        $this->actingAs($user)->postJson('/unit-entries', [
            'task_id' => $task->id,
            'quantity' => 3,
        ])->assertCreated();

        $entry = WorkEntry::first();

        $this->actingAs($user)->patchJson("/unit-entries/{$entry->id}", [
            'quantity' => 5,
        ])->assertOk()
            ->assertJsonPath('entry.quantity', 5);

        $this->assertSame('5.000', (string) $entry->fresh()->quantity);
    }

    public function test_a_timer_session_cannot_be_edited_through_the_unit_endpoint(): void
    {
        [$user, $task] = $this->makeUnitTask(['unit_rate' => 70, 'unit_label' => 'apartment']);

        $timerEntry = WorkEntry::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'task_id' => $task->id,
            'billing_mode' => WorkEntry::MODE_TIME,
            'started_at' => now()->subHour(),
            'stopped_at' => now(),
            'duration_seconds' => 3600,
        ]);

        $this->actingAs($user)->patchJson("/unit-entries/{$timerEntry->id}", [
            'quantity' => 5,
        ])->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Task}
     */
    private function makeUnitTask(array $taskBilling, array $projectBilling = []): array
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

        $task = Task::create($taskBilling + [
            'team_id' => $teamId,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'Standard Clean',
            'billing_mode' => WorkEntry::MODE_UNIT,
            'is_active' => true,
            'is_default' => true,
        ]);

        return [$user, $task];
    }
}
