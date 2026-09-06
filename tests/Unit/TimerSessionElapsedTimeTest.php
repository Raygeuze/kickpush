<?php

namespace Tests\Unit;

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TimerSessionController;
use App\Models\TimerSession;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class TimerSessionElapsedTimeTest extends TestCase
{
    public static function controllerProvider(): array
    {
        return [
            [TimerSessionController::class],
            [InvoiceController::class],
        ];
    }

    #[DataProvider('controllerProvider')]
    public function test_elapsed_time_uses_the_active_segment_start(string $controllerClass): void
    {
        $session = new TimerSession([
            'started_at' => CarbonImmutable::parse('2026-09-07 23:30:00', 'UTC'),
            'active_started_at' => CarbonImmutable::parse('2026-09-08 09:00:00', 'UTC'),
            'accumulated_seconds' => 900,
        ]);

        $method = new ReflectionMethod($controllerClass, 'calculateElapsedSeconds');

        $elapsed = $method->invoke(
            new $controllerClass(),
            $session,
            CarbonImmutable::parse('2026-09-08 09:30:00', 'UTC')
        );

        $this->assertSame(2700, $elapsed);
    }

    #[DataProvider('controllerProvider')]
    public function test_legacy_active_session_falls_back_to_the_original_start(string $controllerClass): void
    {
        $session = new TimerSession([
            'started_at' => CarbonImmutable::parse('2026-09-07 10:00:00', 'UTC'),
            'active_started_at' => null,
            'accumulated_seconds' => 0,
        ]);

        $method = new ReflectionMethod($controllerClass, 'calculateElapsedSeconds');

        $elapsed = $method->invoke(
            new $controllerClass(),
            $session,
            CarbonImmutable::parse('2026-09-07 10:20:00', 'UTC')
        );

        $this->assertSame(1200, $elapsed);
    }
}