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
        Schema::table('user_additional_taxes', function (Blueprint $table): void {
            $table->string('currency', 3)->nullable()->after('value');
            $table->index(['user_id', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_additional_taxes', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'currency']);
            $table->dropColumn('currency');
        });
    }
};
