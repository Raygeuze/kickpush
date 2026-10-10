<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->dropUnique(['project_id', 'name']);
            $table->index('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id']);
            $table->unique(['project_id', 'name']);
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
