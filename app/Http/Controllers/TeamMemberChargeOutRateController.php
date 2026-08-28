<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamMemberChargeOutRateController extends Controller
{
    public function update(Request $request, Team $team, User $user): JsonResponse
    {
        /** @var User|null $actor */
        $actor = Auth::user();
        abort_unless($actor, 401, 'Authentication required.');

        $canEditAny = $actor->ownsTeam($team)
            || $actor->hasTeamRole($team, 'admin')
            || $actor->hasTeamRole($team, 'editor');
        $canEditSelfAsEmployee = $actor->hasTeamRole($team, 'employee')
            && (int) $actor->id === (int) $user->id;

        abort_unless($canEditAny || $canEditSelfAsEmployee, 403, 'You are not allowed to update this member charge-out rate.');

        $isMember = $team->users()->whereKey($user->id)->exists()
            || (int) $team->user_id === (int) $user->id;

        abort_unless($isMember, 404, 'Team member not found.');

        $validated = $request->validate([
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $hourlyRate = isset($validated['hourly_rate'])
            ? (float) $validated['hourly_rate']
            : 0.0;

        $user->hourly_rate = $hourlyRate;
        $user->save();

        return response()->json([
            'message' => 'Charge-out hourly rate updated.',
            'user' => [
                'id' => (int) $user->id,
                'hourly_rate' => (float) ($user->hourly_rate ?? 0.0),
            ],
        ]);
    }
}
