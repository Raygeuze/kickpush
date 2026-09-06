<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\TimerSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteTimerSessionTest extends TestCase
{
	use RefreshDatabase;

	public function test_user_can_delete_an_unassigned_timer_session(): void
	{
		$user = User::factory()->withPersonalTeam()->create();
		$session = $this->createSession($user);

		$response = $this->actingAs($user)->deleteJson(route('timer.destroy', $session->id));

		$response->assertOk()->assertJson([
			'message' => 'Timer session deleted.',
		]);
		$this->assertSoftDeleted($session);
	}

	public function test_user_can_delete_a_session_from_a_draft_invoice(): void
	{
		$user = User::factory()->withPersonalTeam()->create();
		$invoice = $this->createInvoice($user, 'draft', 'DRAFT-1');
		$session = $this->createSession($user, ['invoice_id' => $invoice->id]);

		$response = $this->actingAs($user)->deleteJson(route('timer.destroy', $session->id));

		$response->assertOk();
		$this->assertSoftDeleted($session);
	}

	public function test_user_cannot_delete_sessions_from_finalized_or_paid_invoices(): void
	{
		$user = User::factory()->withPersonalTeam()->create();

		foreach (['finalized', 'paid'] as $status) {
			$invoice = $this->createInvoice($user, $status, strtoupper($status).'-1');
			$session = $this->createSession($user, ['invoice_id' => $invoice->id]);

			$response = $this->actingAs($user)->deleteJson(route('timer.destroy', $session->id));

			$response->assertUnprocessable()->assertJson([
				'message' => 'Sessions on finalized or paid invoices cannot be deleted.',
			]);
			$this->assertNotSoftDeleted($session);
		}
	}

	public function test_team_owner_can_delete_another_users_timer_session(): void
	{
		$owner = User::factory()->withPersonalTeam()->create();
		$employee = $this->addTeamMember($owner, 'employee');
		$session = $this->createSession($employee);

		$response = $this->actingAs($owner)->deleteJson(route('timer.destroy', $session->id));

		$response->assertOk();
		$this->assertSoftDeleted($session);
	}

	public function test_team_admin_can_delete_another_users_timer_session(): void
	{
		$owner = User::factory()->withPersonalTeam()->create();
		$admin = $this->addTeamMember($owner, 'admin');
		$employee = $this->addTeamMember($owner, 'employee');
		$session = $this->createSession($employee);

		$response = $this->actingAs($admin)->deleteJson(route('timer.destroy', $session->id));

		$response->assertOk();
		$this->assertSoftDeleted($session);
	}

	public function test_employee_cannot_delete_another_users_timer_session(): void
	{
		$owner = User::factory()->withPersonalTeam()->create();
		$otherUser = $this->addTeamMember($owner, 'employee');
		$session = $this->createSession($owner);

		$response = $this->actingAs($otherUser)->deleteJson(route('timer.destroy', $session->id));

		$response->assertNotFound()->assertJson([
			'message' => 'Timer session not found for this user.',
		]);
		$this->assertNotSoftDeleted($session);
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
			'started_at' => now()->subMinutes(10),
			'accumulated_seconds' => 0,
		], $attributes));
	}

	private function createInvoice(User $user, string $status, string $invoiceNumber): Invoice
	{
		return Invoice::create([
			'user_id' => $user->id,
			'team_id' => $user->currentTeam->id,
			'invoice_number' => $invoiceNumber,
			'status' => $status,
		]);
	}
}
