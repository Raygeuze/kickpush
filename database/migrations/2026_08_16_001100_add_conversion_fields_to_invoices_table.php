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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('conversion_source_currency', 3)->nullable()->after('client_id');
            $table->string('conversion_target_currency', 3)->nullable()->after('conversion_source_currency');
            $table->decimal('conversion_rate', 18, 8)->nullable()->after('conversion_target_currency');
            $table->timestamp('conversion_rate_fetched_at')->nullable()->after('conversion_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'conversion_source_currency',
                'conversion_target_currency',
                'conversion_rate',
                'conversion_rate_fetched_at',
            ]);
        });
    }
};
