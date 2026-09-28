<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Task;
use App\Models\WorkEntry;
use Tests\TestCase;

class TaskBillingInheritanceTest extends TestCase
{
    public function test_task_without_config_inherits_every_field_from_its_project(): void
    {
        $task = $this->makeTask([], [
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_label' => 'apartment',
            'unit_rate' => 70,
        ]);

        $this->assertSame(WorkEntry::MODE_UNIT, $task->resolvedBillingMode());
        $this->assertSame('apartment', $task->resolvedUnitLabel());
        $this->assertSame(70.0, $task->resolvedUnitRate());
        $this->assertTrue($task->billingConfig()['is_inherited']);
    }

    public function test_task_can_override_a_single_field_and_inherit_the_rest(): void
    {
        $task = $this->makeTask([
            'unit_rate' => 85,
        ], [
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_label' => 'apartment',
            'unit_rate' => 70,
        ]);

        $this->assertSame(WorkEntry::MODE_UNIT, $task->resolvedBillingMode());
        $this->assertSame('apartment', $task->resolvedUnitLabel());
        $this->assertSame(85.0, $task->resolvedUnitRate());
    }

    public function test_billing_mode_defaults_to_time_when_nothing_is_configured(): void
    {
        $task = $this->makeTask([], []);

        $this->assertSame(WorkEntry::MODE_TIME, $task->resolvedBillingMode());
        $this->assertNull($task->resolvedUnitLabel());
        $this->assertNull($task->resolvedUnitRate());
    }

    public function test_task_mode_overrides_project_mode(): void
    {
        $task = $this->makeTask([
            'billing_mode' => WorkEntry::MODE_TIME,
        ], [
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_label' => 'apartment',
            'unit_rate' => 70,
        ]);

        $this->assertSame(WorkEntry::MODE_TIME, $task->resolvedBillingMode());
        $this->assertFalse($task->billingConfig()['is_inherited']);
    }

    public function test_plural_label_falls_back_to_the_singular_with_an_s(): void
    {
        $task = $this->makeTask([], [
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_label' => 'apartment',
            'unit_rate' => 70,
        ]);

        $this->assertSame('apartments', $task->resolvedUnitLabelPlural());
    }

    public function test_explicit_plural_label_wins_over_the_naive_fallback(): void
    {
        $task = $this->makeTask([], [
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_label' => 'load of laundry',
            'unit_label_plural' => 'loads of laundry',
            'unit_rate' => 25,
        ]);

        $this->assertSame('loads of laundry', $task->resolvedUnitLabelPlural());
    }

    public function test_unit_task_without_a_resolvable_rate_is_reported_as_unconfigured(): void
    {
        $task = $this->makeTask([
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_label' => 'window',
        ], []);

        $this->assertFalse($task->billingConfig()['is_configured']);
    }

    public function test_zero_is_a_valid_unit_rate_and_is_not_treated_as_missing(): void
    {
        $task = $this->makeTask([
            'unit_rate' => 0,
        ], [
            'billing_mode' => WorkEntry::MODE_UNIT,
            'unit_label' => 'callout',
            'unit_rate' => 70,
        ]);

        $this->assertSame(0.0, $task->resolvedUnitRate());
    }

    private function makeTask(array $taskAttributes, array $projectAttributes): Task
    {
        $project = (new Project())->forceFill($projectAttributes + [
            'id' => 1,
            'name' => 'Weekly Clean',
        ]);

        $task = (new Task())->forceFill($taskAttributes + [
            'id' => 2,
            'project_id' => 1,
            'name' => 'Standard Clean',
        ]);
        $task->setRelation('project', $project);

        return $task;
    }
}
