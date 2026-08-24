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
        $users = DB::table('users')
            ->select(['id', 'is_admin'])
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $defaultTeam = DB::table('teams')
            ->where('name', self::DEFAULT_TEAM_NAME)
            ->where('personal_team', false)
            ->first();

        $ownerId = $defaultTeam ? $defaultTeam->user_id : null;

        if ($ownerId === null || !$users->contains(fn ($user) => (int) $user->id === (int) $ownerId)) {
            $ownerId = (int) ($users->firstWhere('is_admin', true)->id ?? $users->first()->id);
        }

        if (!$defaultTeam) {
            $teamId = DB::table('teams')->insertGetId([
                'user_id' => $ownerId,
                'name' => self::DEFAULT_TEAM_NAME,
                'personal_team' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $teamId = (int) $defaultTeam->id;

            if ((int) $defaultTeam->user_id !== (int) $ownerId) {
                DB::table('teams')
                    ->where('id', $teamId)
                    ->update([
                        'user_id' => $ownerId,
                        'updated_at' => now(),
                    ]);
            }
        }

        $members = $users->map(function ($user) use ($teamId, $ownerId): array {
            return [
                'team_id' => $teamId,
                'user_id' => (int) $user->id,
                'role' => (int) $user->id === (int) $ownerId ? 'admin' : 'editor',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->all();

        DB::table('team_user')->upsert(
            $members,
            ['team_id', 'user_id'],
            ['role', 'updated_at']
        );

        DB::table('users')
            ->whereIn('id', $users->pluck('id')->all())
            ->update(['current_team_id' => $teamId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left non-destructive. Rolling this back could remove
        // team relationships that users may already depend on.
    }
};
