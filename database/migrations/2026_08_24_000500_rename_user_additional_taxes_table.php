<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_additional_taxes') && !Schema::hasTable('team_additional_taxes')) {
            Schema::rename('user_additional_taxes', 'team_additional_taxes');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('team_additional_taxes') && !Schema::hasTable('user_additional_taxes')) {
            Schema::rename('team_additional_taxes', 'user_additional_taxes');
        }
    }
};
