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
        Schema::table('submissions', function (Blueprint $table) {
            $table->boolean('is_winner')->default(false)->after('votes');
            $table->boolean('is_second_place')->default(false)->after('is_winner');
            $table->boolean('is_third_place')->default(false)->after('is_second_place');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['is_winner', 'is_second_place', 'is_third_place']);
        });
    }
};
