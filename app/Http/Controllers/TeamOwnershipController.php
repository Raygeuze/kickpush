<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TeamOwnershipController extends Controller
{
    public function transfer(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('update', $team);

        abort_if($team->personal_team, 422, 'Personal team ownership cannot be transferred.');
        abort_unless($user->belongsToTeam($team), 422, 'Selected user must already be a team member.');

        if ((int) $team->user_id === (int) $user->id) {
            return back()->with('flash.banner', 'This user already owns the team.');
        }

        $previousOwnerId = (int) $team->user_id;

        $team->forceFill([
            'user_id' => $user->id,
        ])->save();

        // Keep both users as members and preserve admin-level access for continuity.
        $team->users()->syncWithoutDetaching([
            $previousOwnerId => ['role' => 'admin'],
            (int) $user->id => ['role' => 'admin'],
        ]);

        $user->switchTeam($team);

        return back()->with('flash.banner', 'Team ownership transferred.');
    }
}
