<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\BusinessExpense;
use App\Models\Expense;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\TimerSession;
use App\Models\User;
use App\Models\UserAdditionalTax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceFinancialYearTaxSummaryCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_year_tax_summary_uses_per_invoice_converted_values_in_user_country_currency(): void
    {
        $user = User::factory()->create([
            'country' => 'NZ',
        ]);

        $this->actingAs($user);

        $financialYear = FinancialYear::create([
            'user_id' => $user->id,
            'start_year' => 2026,
            'end_year' => 2027,
            'label' => '2026/2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
        ]);

        UserAdditionalTax::create([
            'user_id' => $user->id,
            'name' => 'Income Tax',
            'category' => 'tax',
            'value_type' => 'percentage',
            'value' => 10,
            'currency' => null,
            'position' => 1,
        ]);

        $usdClient = Client::create([
            'user_id' => $user->id,
            'name' => 'US Client',
            'email' => 'us-client@example.com',
            'currency' => 'USD',
            'hourly_rate' => 100,
        ]);

        $eurClient = Client::create([
            'user_id' => $user->id,
            'name' => 'EU Client',
            'email' => 'eu-client@example.com',
            'currency' => 'EUR',
            'hourly_rate' => 200,
        ]);

        $invoiceOne = Invoice::create([
            'user_id' => $user->id,
            'client_id' => $usdClient->id,
            'financial_year_id' => $financialYear->id,
            'invoice_number' => '1001',
            'status' => 'paid',
            'discount_type' => 'fixed',
            'discount_value' => 10,
            'conversion_source_currency' => 'USD',
            'conversion_target_currency' => 'NZD',
            'conversion_rate' => 1.5,
            'conversion_rate_fetched_at' => now(),
        ]);

        $invoiceTwo = Invoice::create([
            'user_id' => $user->id,
            'client_id' => $eurClient->id,
            'financial_year_id' => $financialYear->id,
            'invoice_number' => '1002',
            'status' => 'paid',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'conversion_source_currency' => 'EUR',
            'conversion_target_currency' => 'NZD',
            'conversion_rate' => 2.0,
            'conversion_rate_fetched_at' => now(),
        ]);

        Invoice::create([
            'user_id' => $user->id,
            'client_id' => $usdClient->id,
            'financial_year_id' => $financialYear->id,
            'invoice_number' => '1003',
            'status' => 'finalized',
            'discount_type' => null,
            'discount_value' => 0,
            'conversion_source_currency' => 'USD',
            'conversion_target_currency' => 'NZD',
            'conversion_rate' => 1.5,
            'conversion_rate_fetched_at' => now(),
        ]);

        TimerSession::create([
            'user_id' => $user->id,
            'invoice_id' => $invoiceOne->id,
            'started_at' => now()->subHours(3),
            'stopped_at' => now()->subHours(2),
            'duration_seconds' => 3600,
            'accumulated_seconds' => 0,
        ]);

        TimerSession::create([
            'user_id' => $user->id,
            'invoice_id' => $invoiceTwo->id,
            'started_at' => now()->subHours(2),
            'stopped_at' => now()->subHours(1)->subMinutes(30),
            'duration_seconds' => 1800,
            'accumulated_seconds' => 0,
        ]);

        Expense::create([
            'invoice_id' => $invoiceOne->id,
            'name' => 'Hosting',
            'amount' => 50,
        ]);

        Expense::create([
            'invoice_id' => $invoiceTwo->id,
            'name' => 'Assets',
            'amount' => 20,
        ]);

        BusinessExpense::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'name' => 'Laptop',
            'amount' => 26,
            'tax_deductible' => true,
            'deductible_percentage' => 100,
        ]);

        $response = $this->get(route('invoices.financialYearTaxSummary', [
            'financial_year_start' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/FinancialYearTaxSummary')
            ->where('convertedTaxSummary.target_currency', 'NZD')
            ->where('convertedTaxSummary.total_invoices', 2)
            ->where('convertedTaxSummary.converted_invoices', 2)
            ->where('convertedTaxSummary.missing_rate_invoices', 0)
            ->where('convertedTaxSummary.billable_time_amount_converted', 350.0)
            ->where('convertedTaxSummary.total_expenses_amount_converted', 115.0)
            ->where('convertedTaxSummary.subtotal_amount_converted', 465.0)
            ->where('convertedTaxSummary.total_discount_amount_converted', 39.0)
            ->where('convertedTaxSummary.gross_amount_converted', 426.0)
            ->where('convertedTaxSummary.deductible_business_expenses_amount_converted', 26.0)
            ->where('convertedTaxSummary.taxable_amount_converted', 400.0)
            ->where('convertedTaxSummary.total_tax_before_deductible_expenses_amount_converted', 42.6)
            ->where('convertedTaxSummary.total_tax_after_deductible_expenses_amount_converted', 40.0)
            ->where('convertedTaxSummary.total_tax_amount_converted', 40.0)
            ->where('convertedTaxSummary.tax_savings_from_deductible_expenses_amount_converted', 2.6)
            ->where('convertedTaxSummary.net_after_tax_amount_converted', 386.0)
            ->where('convertedTaxSummary.net_amount_converted', 386.0)
            ->has('convertedTaxSummary.additional_tax_items', 1)
            ->where('convertedTaxSummary.additional_tax_items.0.name', 'Income Tax')
            ->where('convertedTaxSummary.additional_tax_items.0.amount', 42.6)
        );
    }
}
