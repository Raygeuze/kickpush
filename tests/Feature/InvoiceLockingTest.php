<?php

namespace Tests\Feature;

use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\TimerSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceLockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalized_invoice_cannot_be_reassigned_to_another_financial_year(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $invoice = $this->createInvoice($user, 'finalized');
        $financialYear = FinancialYear::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'start_year' => 2027,
            'end_year' => 2028,
            'label' => '2027/2028',
            'start_date' => '2027-04-01',
            'end_date' => '2028-03-31',
        ]);

        $this->actingAs($user)
            ->postJson(route('invoices.financialYear.assign', $invoice->id), [
                'financial_year_id' => $financialYear->id,
            ])
            ->assertUnprocessable();

        $this->assertNull($invoice->fresh()->financial_year_id);
    }

    public function test_repeated_finalization_does_not_attach_an_active_session_to_locked_invoice(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $invoice = $this->createInvoice($user, 'finalized');
        $session = TimerSession::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'started_at' => now()->subMinutes(10),
            'active_started_at' => now()->subMinutes(10),
            'accumulated_seconds' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('invoices.finalize', $invoice->id))
            ->assertOk();

        $session->refresh();
        $this->assertNull($session->invoice_id);
        $this->assertNull($session->stopped_at);
    }

    private function createInvoice(User $user, string $status): Invoice
    {
        return Invoice::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'invoice_number' => 'LOCK-'.$status,
            'status' => $status,
        ]);
    }
}