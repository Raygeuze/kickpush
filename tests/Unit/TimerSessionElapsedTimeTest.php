<?php

namespace Tests\Unit;

use App\Models\TimerSession;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TimerSessionElapsedTimeTest extends TestCase
{
    public function test_elapsed_time_uses_the_active_segment_start(): void
    {
        $session = new TimerSession([
            'started_at' => CarbonImmutable::parse('2026-09-07 23:30:00', 'UTC'),
            'active_started_at' => CarbonImmutable::parse('2026-09-08 09:00:00', 'UTC'),
            'accumulated_seconds' => 900,
        ]);

        $elapsed = $session->elapsedSeconds(CarbonImmutable::parse('2026-09-08 09:30:00', 'UTC'));

        $this->assertSame(2700, $elapsed);
    }

    public function test_legacy_active_session_falls_back_to_the_original_start(): void
    {
        $session = new TimerSession([
            'started_at' => CarbonImmutable::parse('2026-09-07 10:00:00', 'UTC'),
            'active_started_at' => null,
            'accumulated_seconds' => 0,
        ]);

        $elapsed = $session->elapsedSeconds(CarbonImmutable::parse('2026-09-07 10:20:00', 'UTC'));

        $this->assertSame(1200, $elapsed);
    }

    public function test_paused_session_uses_accumulated_time_without_wall_clock_growth(): void
    {
        $session = new TimerSession([
            'started_at' => CarbonImmutable::parse('2026-09-07 10:00:00', 'UTC'),
            'active_started_at' => null,
            'paused_at' => CarbonImmutable::parse('2026-09-07 10:30:00', 'UTC'),
            'accumulated_seconds' => 1800,
        ]);

        $this->assertSame(
            1800,
            $session->elapsedSeconds(CarbonImmutable::parse('2026-09-09 10:30:00', 'UTC'))
        );
    }

    public function test_stopped_session_uses_its_stored_duration(): void
    {
        $session = new TimerSession([
            'started_at' => CarbonImmutable::parse('2026-09-07 10:00:00', 'UTC'),
            'stopped_at' => CarbonImmutable::parse('2026-09-07 11:00:00', 'UTC'),
            'duration_seconds' => 2700,
        ]);

        $this->assertSame(
            2700,
            $session->elapsedSeconds(CarbonImmutable::parse('2026-09-09 10:30:00', 'UTC'))
        );
    }
}