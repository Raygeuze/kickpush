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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('reenable_requested')->default(false);
            $table->timestamp('reenable_requested_at')->nullable();
            $table->ipAddress('reenable_requested_ip')->nullable();
            $table->longText('reenable_requested_description')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'reenable_requested',
                'reenable_requested_at',
                'reenable_requested_ip',
                'reenable_requested_description',
            ]);
        });
    }
};
