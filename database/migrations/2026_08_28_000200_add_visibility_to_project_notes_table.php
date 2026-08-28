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
        Schema::table('project_notes', function (Blueprint $table) {
            $table->string('visibility', 20)
                ->default('team')
                ->after('user_id');

            $table->index(['project_id', 'visibility', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_notes', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'visibility', 'created_at']);
            $table->dropColumn('visibility');
        });
    }
};
