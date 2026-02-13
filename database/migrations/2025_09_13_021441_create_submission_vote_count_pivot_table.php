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
        //pivot table
        Schema::create('submission_vote_count', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('vote_count_id')->constrained();
            $table->foreignId('submission_id')->constrained();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_vote_count');
    }
};
