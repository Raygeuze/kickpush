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
        Schema::table('user_additional_taxes', function (Blueprint $table): void {
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained('teams')->nullOnDelete();
            $table->index(['team_id', 'position']);
        });

        $this->backfillTeamIds();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_additional_taxes', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'position']);
            $table->dropConstrainedForeignId('team_id');
        });
    }

    private function backfillTeamIds(): void
    {
        $ownedTeamMap = DB::table('teams')
            ->selectRaw('MIN(id) as id, user_id')
            ->groupBy('user_id')
            ->pluck('id', 'user_id');

        $users = DB::table('users')
            ->select(['id', 'current_team_id'])
            ->get();

        foreach ($users as $user) {
            $teamId = $user->current_team_id ?: ($ownedTeamMap[$user->id] ?? null);

            if ($teamId === null) {
                continue;
            }

            DB::table('user_additional_taxes')
                ->where('user_id', (int) $user->id)
                ->whereNull('team_id')
                ->update(['team_id' => (int) $teamId]);
        }
    }
};
