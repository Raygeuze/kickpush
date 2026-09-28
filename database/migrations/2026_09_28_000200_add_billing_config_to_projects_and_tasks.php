<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Null on every column means "inherit": task falls back to project, project falls back to time mode.
     */
    public function up(): void
    {
        foreach (['projects', 'tasks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('billing_mode', 16)->nullable()->after('description');
                $table->string('unit_label', 40)->nullable()->after('billing_mode');
                $table->string('unit_label_plural', 40)->nullable()->after('unit_label');
                $table->decimal('unit_rate', 10, 2)->nullable()->after('unit_label_plural');
            });
        }
    }

    public function down(): void
    {
        foreach (['projects', 'tasks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['billing_mode', 'unit_label', 'unit_label_plural', 'unit_rate']);
            });
        }
    }
};
