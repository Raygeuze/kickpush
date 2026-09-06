<?php

namespace Tests\Feature;

use App\Models\TimerSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TimesheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_only_sees_their_own_weekly_sessions(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $employee = $this->addTeamMember($owner, 'employee');
        $otherEmployee = $this->addTeamMember($owner, 'employee');
        $ownSession = $this->createSession($employee, '2026-09-07 09:00:00');
        $this->createSession($otherEmployee, '2026-09-07 10:00:00');

        $this->actingAs($employee)
            ->get(route('timesheets.index', ['week' => '2026-09-07']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Timesheets/Weekly')
                ->where('canViewTeamSessions', false)
                ->has('sessions', 1)
                ->where('sessions.0.id', $ownSession->id));
    }

    public function test_editor_sees_all_team_sessions_but_cannot_mutate_another_users_session(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $editor = $this->addTeamMember($owner, 'editor');
        $employee = $this->addTeamMember($owner, 'employee');
        $this->createSession($editor, '2026-09-07 09:00:00');
        $employeeSession = $this->createSession($employee, '2026-09-07 10:00:00');

        $this->actingAs($editor)
            ->get(route('timesheets.index', ['week' => '2026-09-07']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewTeamSessions', true)
                ->has('sessions', 2)
                ->where('sessions.1.id', $employeeSession->id)
                ->where('sessions.1.can_update', false)
                ->where('sessions.1.can_delete', false));
    }

    public function test_week_boundaries_and_day_keys_use_the_team_timezone(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->currentTeam->forceFill(['timezone' => 'Pacific/Auckland'])->save();
        $included = $this->createSession($user, '2026-09-06 12:30:00');
        $this->createSession($user, '2026-09-06 11:30:00');

        $this->actingAs($user)
            ->get(route('timesheets.index', ['week' => '2026-09-07']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('timezone', 'Pacific/Auckland')
                ->where('weekStart', '2026-09-07')
                ->has('sessions', 1)
                ->where('sessions.0.id', $included->id)
                ->where('sessions.0.day_key', '2026-09-07'));
    }

    private function addTeamMember(User $owner, string $role): User
    {
        $user = User::factory()->create([
            'current_team_id' => $owner->currentTeam->id,
        ]);
        $owner->currentTeam->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function createSession(User $user, string $startedAt): TimerSession
    {
        return TimerSession::create([
            'user_id' => $user->id,
            'user_id_snapshot' => $user->id,
            'user_name_snapshot' => $user->name,
            'team_id' => $user->currentTeam->id,
            'started_at' => $startedAt,
            'stopped_at' => date('Y-m-d H:i:s', strtotime($startedAt) + 3600),
            'duration_seconds' => 3600,
            'accumulated_seconds' => 0,
        ]);
    }
}