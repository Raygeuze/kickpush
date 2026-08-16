<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('hourly_rate', 10, 2)->default(0)->after('currency');
        });

        if (Schema::hasColumn('users', 'hourly_rate')) {
            DB::table('clients')
                ->join('users', 'users.id', '=', 'clients.user_id')
                ->select('clients.id', 'users.hourly_rate')
                ->orderBy('clients.id')
                ->chunk(100, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('clients')
                            ->where('id', $row->id)
                            ->update([
                                'hourly_rate' => (float) ($row->hourly_rate ?? 0),
                            ]);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('hourly_rate');
        });
    }
};
