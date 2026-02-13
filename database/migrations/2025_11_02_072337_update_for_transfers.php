<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // update days table to add transfer_complete boolean
        // and transfer_id string
        Schema::table('days', function (Blueprint $table) {
            $table->boolean('transfer_complete')->default(false);
            $table->string('transfer_id')->nullable();
        });

        // Add a default value for existing users
        DB::table('days')->update(['transfer_complete' => false]);

        // Update users table to add can_accept_payouts boolean
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_accept_payouts')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('days', function (Blueprint $table) {
            $table->dropColumn('transfer_complete');
            $table->dropColumn('transfer_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_accept_payouts');
        });
    }
};
