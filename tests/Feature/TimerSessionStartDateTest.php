<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\TimerSession;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimerSessionStartDateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_stop_and_restart_preserve_the_original_start_date_and_exclude_the_stopped_gap(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $originalStart = CarbonImmutable::parse('2026-09-07 23:30:00', 'UTC');
        $session = $this->createSession($user, [
            'started_at' => $originalStart,
            'active_started_at' => $originalStart,
        ]);

        Carbon::setTestNow('2026-09-07 23:45:00');
        $this->actingAs($user)->postJson(route('timer.stop'))->assertOk();

        $session->refresh();
        $this->assertTrue($session->started_at->equalTo($originalStart));
        $this->assertNull($session->active_started_at);
        $this->assertSame(900, $session->duration_seconds);

        Carbon::setTestNow('2026-09-08 09:00:00');
        $this->postJson(route('timer.sessions.restart', $session->id))->assertOk();

        $session->refresh();
        $this->assertTrue($session->started_at->equalTo($originalStart));
        $this->assertSame(900, $session->accumulated_seconds);
        $this->assertSame('2026-09-08 09:00:00', $session->active_started_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-09-08 09:30:00');
        $this->postJson(route('timer.stop'))->assertOk();

        $session->refresh();
        $this->assertTrue($session->started_at->equalTo($originalStart));
        $this->assertNull($session->active_started_at);
        $this->assertSame(2700, $session->duration_seconds);
    }

    public function test_existing_running_session_without_active_start_uses_original_start_as_fallback(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $originalStart = CarbonImmutable::parse('2026-09-07 10:00:00', 'UTC');
        $session = $this->createSession($user, [
            'started_at' => $originalStart,
            'active_started_at' => null,
        ]);

        Carbon::setTestNow('2026-09-07 10:20:00');
        $this->actingAs($user)->postJson(route('timer.stop'))->assertOk();

        $session->refresh();
        $this->assertSame(1200, $session->duration_seconds);
        $this->assertTrue($session->started_at->equalTo($originalStart));
    }

    public function test_resuming_a_stopped_invoice_session_preserves_its_original_start_date(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $invoice = Invoice::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'invoice_number' => 'START-DATE-1',
            'status' => 'draft',
        ]);
        $originalStart = CarbonImmutable::parse('2026-09-01 08:00:00', 'UTC');
        $session = $this->createSession($user, [
            'invoice_id' => $invoice->id,
            'started_at' => $originalStart,
            'stopped_at' => $originalStart->addHour(),
            'duration_seconds' => 3600,
        ]);

        Carbon::setTestNow('2026-09-08 09:00:00');
        $this->actingAs($user)
            ->postJson(route('invoices.sessions.resume', [$invoice->id, $session->id]))
            ->assertOk();

        $session->refresh();
        $this->assertTrue($session->started_at->equalTo($originalStart));
        $this->assertSame('2026-09-08 09:00:00', $session->active_started_at->format('Y-m-d H:i:s'));
        $this->assertSame(3600, $session->accumulated_seconds);
        $this->assertNull($session->stopped_at);
        $this->assertNull($session->duration_seconds);
    }

    private function createSession(User $user, array $attributes = []): TimerSession
    {
        return TimerSession::create(array_merge([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'started_at' => now(),
            'accumulated_seconds' => 0,
        ], $attributes));
    }
}