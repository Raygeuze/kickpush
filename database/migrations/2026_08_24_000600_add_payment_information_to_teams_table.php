<?php

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
        Schema::table('teams', function (Blueprint $table): void {
            $table->string('bank_account_name')->nullable()->after('personal_team');
            $table->string('bank_name')->nullable()->after('bank_account_name');
            $table->string('bsb_code', 32)->nullable()->after('bank_name');
            $table->string('bank_account_number', 64)->nullable()->after('bsb_code');
        });

        if (Schema::hasColumn('users', 'bank_account_name')) {
            DB::table('teams')
                ->join('users', 'users.id', '=', 'teams.user_id')
                ->select([
                    'teams.id',
                    'users.bank_account_name',
                    'users.bank_name',
                    'users.bsb_code',
                    'users.bank_account_number',
                ])
                ->whereNull('teams.bank_account_name')
                ->whereNull('teams.bank_name')
                ->whereNull('teams.bsb_code')
                ->whereNull('teams.bank_account_number')
                ->orderBy('teams.id')
                ->chunk(100, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('teams')
                            ->where('id', $row->id)
                            ->update([
                                'bank_account_name' => $row->bank_account_name,
                                'bank_name' => $row->bank_name,
                                'bsb_code' => $row->bsb_code,
                                'bank_account_number' => $row->bank_account_number,
                            ]);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn(['bank_account_name', 'bank_name', 'bsb_code', 'bank_account_number']);
        });
    }
};
