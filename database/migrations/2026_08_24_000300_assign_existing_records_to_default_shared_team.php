<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DEFAULT_TEAM_NAME = 'Default Shared Team';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaultTeam = DB::table('teams')
            ->where('name', self::DEFAULT_TEAM_NAME)
            ->where('personal_team', false)
            ->first();

        if (!$defaultTeam) {
            return;
        }

        $teamId = (int) $defaultTeam->id;

        // Re-home existing team-scoped domain records into the shared default team.
        DB::table('clients')->update(['team_id' => $teamId]);
        DB::table('projects')->update(['team_id' => $teamId]);
        DB::table('tasks')->update(['team_id' => $teamId]);
        DB::table('invoices')->update(['team_id' => $teamId]);
        DB::table('timer_sessions')->update(['team_id' => $teamId]);
        DB::table('financial_years')->update(['team_id' => $teamId]);
        DB::table('business_expenses')->update(['team_id' => $teamId]);

        DB::table('users')->update(['current_team_id' => $teamId]);

        $users = DB::table('users')
            ->select('id')
            ->get();

        if ($users->isNotEmpty()) {
            $members = $users->map(function ($user) use ($teamId): array {
                return [
                    'team_id' => $teamId,
                    'user_id' => (int) $user->id,
                    'role' => 'editor',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();

            DB::table('team_user')->upsert(
                $members,
                ['team_id', 'user_id'],
                ['updated_at']
            );
        }

        if ($defaultTeam->user_id) {
            DB::table('team_user')->updateOrInsert(
                [
                    'team_id' => $teamId,
                    'user_id' => (int) $defaultTeam->user_id,
                ],
                [
                    'role' => 'admin',
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally non-reversible because original team ownership
        // distribution of records cannot be reconstructed safely.
    }
};
