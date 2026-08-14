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
            $table->timestamp('paused_at')->nullable()->after('started_at');
            $table->unsignedInteger('accumulated_seconds')->default(0)->after('stopped_at');
            $table->index(['user_id', 'paused_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timer_sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'paused_at']);
            $table->dropColumn(['paused_at', 'accumulated_seconds']);
        });
    }
};
