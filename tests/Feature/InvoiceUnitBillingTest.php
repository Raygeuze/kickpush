<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\LineItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceUnitBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_entries_contribute_to_the_invoice_total(): void
    {
        [$user, $invoice, $task] = $this->makeInvoiceAndTask();

        $this->makeUnitEntry($user, $invoice, $task, 3, 70);

        $this->assertSummary($user, $invoice, [
            'billable_units_amount' => 210.0,
            'billable_time_amount' => 0.0,
            'subtotal_amount' => 210.0,
            'total_billable_amount' => 210.0,
        ]);
    }

    public function test_time_and_unit_work_are_reported_separately_but_summed(): void
    {
        [$user, $invoice, $task] = $this->makeInvoiceAndTask();

        $this->makeUnitEntry($user, $invoice, $task, 2, 70);
        $this->makeTimeEntry($user, $invoice, $task, 7200, 50);

        $this->assertSummary($user, $invoice, [
            'billable_units_amount' => 140.0,
            'billable_time_amount' => 100.0,
            'billable_work_amount' => 240.0,
            'subtotal_amount' => 240.0,
            'total_billable_amount' => 240.0,
        ]);
    }

    public function test_expenses_and_discounts_apply_on_top_of_unit_revenue(): void
    {
        [$user, $invoice, $task] = $this->makeInvoiceAndTask();

        $this->makeUnitEntry($user, $invoice, $task, 4, 70);
        LineItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Parking',
            'amount' => 20,
        ]);
        $invoice->update(['discount_type' => 'percentage', 'discount_value' => 10]);

        $this->assertSummary($user, $invoice, [
            'billable_units_amount' => 280.0,
            'subtotal_amount' => 300.0,
            'discount_amount' => 30.0,
            'total_billable_amount' => 270.0,
        ]);
    }

    public function test_fractional_quantities_are_priced_correctly(): void
    {
        [$user, $invoice, $task] = $this->makeInvoiceAndTask();

        $this->makeUnitEntry($user, $invoice, $task, 1.5, 25);

        $this->assertSummary($user, $invoice, [
            'billable_units_amount' => 37.5,
            'total_billable_amount' => 37.5,
        ]);
    }

    public function test_entries_at_different_rates_are_priced_with_their_own_snapshots(): void
    {
        [$user, $invoice, $task] = $this->makeInvoiceAndTask();

        $this->makeUnitEntry($user, $invoice, $task, 2, 70);
        $this->makeUnitEntry($user, $invoice, $task, 3, 90);

        $this->assertSummary($user, $invoice, [
            'billable_units_amount' => 410.0,
        ]);
    }

    public function test_unit_quantity_is_reported_in_the_summary(): void
    {
        [$user, $invoice, $task] = $this->makeInvoiceAndTask();

        $this->makeUnitEntry($user, $invoice, $task, 3, 70);
        $this->makeTimeEntry($user, $invoice, $task, 3600, 50);

        $response = $this->actingAs($user)->getJson(route('invoices.details', $invoice->id));

        $response->assertOk()->assertJsonPath('summary.total_quantity', 3);
    }

    private function assertSummary(User $user, Invoice $invoice, array $expected): void
    {
        $response = $this->actingAs($user)->getJson(route('invoices.details', $invoice->id));
        $response->assertOk();

        $summary = $response->json('summary');

        foreach ($expected as $key => $value) {
            $this->assertEqualsWithDelta($value, (float) data_get($summary, $key), 0.001, "summary.{$key}");
        }
    }

    private function makeUnitEntry(User $user, Invoice $invoice, Task $task, float $quantity, float $rate): WorkEntry
    {
        return WorkEntry::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
            'billing_mode' => WorkEntry::MODE_UNIT,
            'quantity' => $quantity,
            'unit_rate_snapshot' => $rate,
            'unit_label_snapshot' => 'apartment',
            'started_at' => now()->subHour(),
            'stopped_at' => now(),
            'duration_seconds' => 0,
        ]);
    }

    private function makeTimeEntry(User $user, Invoice $invoice, Task $task, int $seconds, float $rate): WorkEntry
    {
        return WorkEntry::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
            'billing_mode' => WorkEntry::MODE_TIME,
            'hourly_rate_snapshot' => $rate,
            'started_at' => now()->subHours(3),
            'stopped_at' => now()->subHour(),
            'duration_seconds' => $seconds,
        ]);
    }

    /**
     * @return array{0: User, 1: Invoice, 2: Task}
     */
    private function makeInvoiceAndTask(): array
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

        $invoice = Invoice::create([
            'user_id' => $user->id,
            'team_id' => $teamId,
            'client_id' => $client->id,
            'invoice_number' => 'UNIT-1',
            'status' => 'draft',
        ]);

        return [$user, $invoice, $task];
    }
}
