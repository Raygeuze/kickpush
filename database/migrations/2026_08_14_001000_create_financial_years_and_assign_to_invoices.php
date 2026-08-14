<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('start_year');
            $table->unsignedSmallInteger('end_year');
            $table->string('label', 32);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->unique(['user_id', 'start_year']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('financial_year_id')->nullable()->after('client_id')->constrained('financial_years')->nullOnDelete();
        });

        $this->backfillFinancialYearsForExistingInvoices();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('financial_year_id');
        });

        Schema::dropIfExists('financial_years');
    }

    private function backfillFinancialYearsForExistingInvoices(): void
    {
        $invoices = DB::table('invoices')
            ->select(['id', 'user_id', 'issued_at', 'created_at'])
            ->get();

        if ($invoices->isEmpty()) {
            return;
        }

        $yearRows = [];
        $invoiceKeys = [];
        $now = now();

        foreach ($invoices as $invoice) {
            $reference = $invoice->issued_at ?: $invoice->created_at;

            if (!$reference || !$invoice->user_id) {
                continue;
            }

            $date = CarbonImmutable::parse($reference, 'Pacific/Auckland');
            $startYear = $date->month >= 4 ? $date->year : $date->subYear()->year;
            $endYear = $startYear + 1;
            $key = $invoice->user_id . ':' . $startYear;

            $yearRows[$key] = [
                'user_id' => $invoice->user_id,
                'start_year' => $startYear,
                'end_year' => $endYear,
                'label' => $startYear . '/' . $endYear,
                'start_date' => sprintf('%04d-04-01', $startYear),
                'end_date' => sprintf('%04d-03-31', $endYear),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $invoiceKeys[$invoice->id] = $key;
        }

        if (!empty($yearRows)) {
            DB::table('financial_years')->upsert(
                array_values($yearRows),
                ['user_id', 'start_year'],
                ['end_year', 'label', 'start_date', 'end_date', 'updated_at']
            );
        }

        $financialYears = DB::table('financial_years')
            ->select(['id', 'user_id', 'start_year'])
            ->get();

        $financialYearIdByKey = [];

        foreach ($financialYears as $financialYear) {
            $key = $financialYear->user_id . ':' . $financialYear->start_year;
            $financialYearIdByKey[$key] = $financialYear->id;
        }

        foreach ($invoiceKeys as $invoiceId => $key) {
            if (!isset($financialYearIdByKey[$key])) {
                continue;
            }

            DB::table('invoices')
                ->where('id', $invoiceId)
                ->update(['financial_year_id' => $financialYearIdByKey[$key]]);
        }
    }
};
