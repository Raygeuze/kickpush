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
        Schema::create('behaviour_reports', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('reported_by')->constrained('users');
            $table->foreignId('reported_user_id')->constrained('users');
            $table->string('reason');
            $table->text('details')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->text('resolution_details')->nullable();
            $table->foreignId('submission_id')->nullable()->constrained('submissions');
            $table->foreignId('comment_id')->nullable()->constrained('comments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('behaviour_reports');
    }
};
