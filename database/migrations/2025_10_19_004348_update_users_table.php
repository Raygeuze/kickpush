<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add the country column to the users table
            $table->string('country')->default('NZ')->after('email');
            // Add the stripe_account_id column to the users table
            $table->string('stripe_account_id')->nullable()->after('country');
            // Add the stripe_customer_id column to the users table
            $table->string('stripe_customer_id')->nullable()->after('stripe_account_id');
        });

        // Add a default value for existing users
        DB::table('users')->update(['country' => 'NZ']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the country column from the users table
            $table->dropColumn('country');
            // Drop the stripe_account_id column from the users table
            $table->dropColumn('stripe_account_id');
            // Drop the stripe_customer_id column from the users table
            $table->dropColumn('stripe_customer_id');
        });
    }
};
