<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('timer_sessions') && !Schema::hasTable('work_entries')) {
            Schema::rename('timer_sessions', 'work_entries');
        }

        Schema::table('work_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('work_entries', 'billing_mode')) {
                $table->string('billing_mode', 16)->default('time')->after('task_id');
                $table->index(['team_id', 'billing_mode'], 'work_entries_team_id_billing_mode_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_entries', function (Blueprint $table) {
            if (Schema::hasColumn('work_entries', 'billing_mode')) {
                $table->dropIndex('work_entries_team_id_billing_mode_index');
                $table->dropColumn('billing_mode');
            }
        });

        if (Schema::hasTable('work_entries') && !Schema::hasTable('timer_sessions')) {
            Schema::rename('work_entries', 'timer_sessions');
        }
    }
};
