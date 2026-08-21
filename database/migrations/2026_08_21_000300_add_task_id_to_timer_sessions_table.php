<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('timer_sessions', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->after('invoice_id')->constrained('tasks')->nullOnDelete();
            $table->index(['task_id', 'stopped_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timer_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_id');
        });
    }
};
