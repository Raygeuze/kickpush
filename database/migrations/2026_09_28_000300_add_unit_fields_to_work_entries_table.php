<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_entries', function (Blueprint $table): void {
            $table->decimal('quantity', 10, 3)->nullable()->after('billing_mode');
            $table->decimal('unit_rate_snapshot', 10, 2)->nullable()->after('quantity');
            $table->string('unit_label_snapshot', 40)->nullable()->after('unit_rate_snapshot');
            $table->string('unit_label_plural_snapshot', 40)->nullable()->after('unit_label_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('work_entries', function (Blueprint $table): void {
            $table->dropColumn([
                'quantity',
                'unit_rate_snapshot',
                'unit_label_snapshot',
                'unit_label_plural_snapshot',
            ]);
        });
    }
};
