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
        Schema::table('business_expenses', function (Blueprint $table) {
            $table->foreignId('financial_year_id')
                ->nullable()
                ->after('user_id')
                ->constrained('financial_years')
                ->nullOnDelete();
        });

        $this->backfillFinancialYearAssignments();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('financial_year_id');
        });
    }

    private function backfillFinancialYearAssignments(): void
    {
        $expenses = DB::table('business_expenses')
            ->select(['id', 'user_id', 'incurred_on', 'created_at'])
            ->get();

        if ($expenses->isEmpty()) {
            return;
        }

        $yearRows = [];
        $expenseKeys = [];
        $now = now();

        foreach ($expenses as $expense) {
            if (!$expense->user_id) {
                continue;
            }

            $reference = $expense->incurred_on ?: $expense->created_at;

            if (!$reference) {
                continue;
            }

            $date = CarbonImmutable::parse($reference, 'Pacific/Auckland');
            $startYear = $date->month >= 4 ? $date->year : $date->subYear()->year;
            $endYear = $startYear + 1;
            $key = $expense->user_id . ':' . $startYear;

            $yearRows[$key] = [
                'user_id' => $expense->user_id,
                'start_year' => $startYear,
                'end_year' => $endYear,
                'label' => $startYear . '/' . $endYear,
                'start_date' => sprintf('%04d-04-01', $startYear),
                'end_date' => sprintf('%04d-03-31', $endYear),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $expenseKeys[$expense->id] = $key;
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

        foreach ($expenseKeys as $expenseId => $key) {
            if (!isset($financialYearIdByKey[$key])) {
                continue;
            }

            DB::table('business_expenses')
                ->where('id', $expenseId)
                ->update(['financial_year_id' => $financialYearIdByKey[$key]]);
        }
    }
};
