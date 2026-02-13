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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('submission_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->text('content');
            $table->foreignId('parent_id')->nullable();
            $table->foreignId('replying_to_id')->nullable();
            $table->boolean('is_disapproved')->default(false);
            $table->foreignId('disapproved_by')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
