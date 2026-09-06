<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\TimerSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TimerSessionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_manage_any_mutable_team_session(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $admin = $this->addTeamMember($owner, 'admin');
        $employee = $this->addTeamMember($owner, 'employee');
        $session = $this->createSession($employee);

        foreach ([$owner, $admin] as $actor) {
            $this->assertTrue(Gate::forUser($actor)->allows('manageTeam', TimerSession::class));
            $this->assertTrue(Gate::forUser($actor)->allows('view', $session));
            $this->assertTrue(Gate::forUser($actor)->allows('update', $session));
            $this->assertTrue(Gate::forUser($actor)->allows('delete', $session));
        }
    }

    public function test_editor_can_view_all_team_sessions_but_only_manage_their_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $editor = $this->addTeamMember($owner, 'editor');
        $employee = $this->addTeamMember($owner, 'employee');
        $editorSession = $this->createSession($editor);
        $employeeSession = $this->createSession($employee);

        $this->assertTrue(Gate::forUser($editor)->allows('viewTeam', TimerSession::class));
        $this->assertFalse(Gate::forUser($editor)->allows('manageTeam', TimerSession::class));
        $this->assertTrue(Gate::forUser($editor)->allows('view', $employeeSession));
        $this->assertFalse(Gate::forUser($editor)->allows('update', $employeeSession));
        $this->assertFalse(Gate::forUser($editor)->allows('delete', $employeeSession));
        $this->assertTrue(Gate::forUser($editor)->allows('update', $editorSession));
        $this->assertTrue(Gate::forUser($editor)->allows('delete', $editorSession));
    }

    public function test_employee_can_only_view_and_manage_their_own_sessions(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $employee = $this->addTeamMember($owner, 'employee');
        $otherEmployee = $this->addTeamMember($owner, 'employee');
        $ownSession = $this->createSession($employee);
        $otherSession = $this->createSession($otherEmployee);

        $this->assertFalse(Gate::forUser($employee)->allows('viewTeam', TimerSession::class));
        $this->assertTrue(Gate::forUser($employee)->allows('view', $ownSession));
        $this->assertTrue(Gate::forUser($employee)->allows('update', $ownSession));
        $this->assertFalse(Gate::forUser($employee)->allows('view', $otherSession));
        $this->assertFalse(Gate::forUser($employee)->allows('update', $otherSession));
        $this->assertFalse(Gate::forUser($employee)->allows('delete', $otherSession));
    }

    public function test_no_role_can_mutate_a_session_on_a_finalized_or_paid_invoice(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $admin = $this->addTeamMember($owner, 'admin');
        $employee = $this->addTeamMember($owner, 'employee');

        foreach (['finalized', 'paid'] as $status) {
            $invoice = Invoice::create([
                'user_id' => $owner->id,
                'team_id' => $owner->currentTeam->id,
                'invoice_number' => 'POLICY-'.strtoupper($status),
                'status' => $status,
            ]);
            $session = $this->createSession($employee, ['invoice_id' => $invoice->id]);

            foreach ([$owner, $admin, $employee] as $actor) {
                $this->assertFalse(Gate::forUser($actor)->allows('update', $session));
                $this->assertFalse(Gate::forUser($actor)->allows('delete', $session));
                $this->assertFalse(Gate::forUser($actor)->allows('operate', $session));
            }
        }
    }

    private function addTeamMember(User $owner, string $role): User
    {
        $user = User::factory()->create([
            'current_team_id' => $owner->currentTeam->id,
        ]);
        $owner->currentTeam->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function createSession(User $user, array $attributes = []): TimerSession
    {
        return TimerSession::create(array_merge([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'started_at' => now()->subHour(),
            'stopped_at' => now(),
            'duration_seconds' => 3600,
            'accumulated_seconds' => 0,
        ], $attributes));
    }
}