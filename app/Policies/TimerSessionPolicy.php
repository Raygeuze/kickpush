<?php

namespace App\Policies;

use App\Models\TimerSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

class TimerSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function viewTeam(User $user): bool
    {
        return $this->hasTeamRole($user, ['owner', 'admin', 'editor']);
    }

    public function manageTeam(User $user): bool
    {
        return $this->hasTeamRole($user, ['owner', 'admin']);
    }

    public function view(User $user, TimerSession $session): bool
    {
        return $this->belongsToCurrentTeam($user, $session)
            && ((int) $session->user_id === (int) $user->id || $this->viewTeam($user));
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function operate(User $user, TimerSession $session): Response
    {
        if (!$this->belongsToCurrentTeam($user, $session) || (int) $session->user_id !== (int) $user->id) {
            return Response::denyAsNotFound();
        }

        return $this->mutableResponse($session);
    }

    public function update(User $user, TimerSession $session): Response
    {
        if (!$this->belongsToCurrentTeam($user, $session)) {
            return Response::denyAsNotFound();
        }

        if ((int) $session->user_id !== (int) $user->id && !$this->manageTeam($user)) {
            return Response::deny('You cannot edit another team member\'s timer session.');
        }

        return $this->mutableResponse($session);
    }

    public function delete(User $user, TimerSession $session): Response
    {
        if (!$this->belongsToCurrentTeam($user, $session)) {
            return Response::denyAsNotFound();
        }

        if ((int) $session->user_id !== (int) $user->id && !$this->manageTeam($user)) {
            return Response::deny('You cannot delete another team member\'s timer session.');
        }

        return $this->mutableResponse($session, 'deleted');
    }

    private function mutableResponse(TimerSession $session, string $action = 'changed'): Response
    {
        $session->loadMissing('invoice');

        if ($session->invoice && in_array($session->invoice->status, ['finalized', 'paid'], true)) {
            return Response::denyWithStatus(422, "Sessions on finalized or paid invoices cannot be {$action}.");
        }

        return Response::allow();
    }

    private function belongsToCurrentTeam(User $user, TimerSession $session): bool
    {
        return $user->current_team_id !== null
            && (int) $session->team_id === (int) $user->current_team_id;
    }

    private function hasTeamRole(User $user, array $roles): bool
    {
        $team = $user->currentTeam;

        if (!$team) {
            return false;
        }

        if ((int) $team->user_id === (int) $user->id) {
            return in_array('owner', $roles, true);
        }

        return DB::table('team_user')
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->whereIn('role', $roles)
            ->exists();
    }
}