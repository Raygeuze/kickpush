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

        DB::table('teams')
            ->join('users', 'users.id', '=', 'teams.user_id')
            ->whereNull('teams.bank_account_name')
            ->whereNull('teams.bank_name')
            ->whereNull('teams.bsb_code')
            ->whereNull('teams.bank_account_number')
            ->update([
                'teams.bank_account_name' => DB::raw('users.bank_account_name'),
                'teams.bank_name' => DB::raw('users.bank_name'),
                'teams.bsb_code' => DB::raw('users.bsb_code'),
                'teams.bank_account_number' => DB::raw('users.bank_account_number'),
            ]);
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
