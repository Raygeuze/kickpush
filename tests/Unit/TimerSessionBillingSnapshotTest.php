<?php

namespace Tests\Unit;

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProjectController;
use App\Models\Client;
use App\Models\TimerSession;
use App\Models\User;
use App\Services\TimerSessionBillingSnapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class TimerSessionBillingSnapshotTest extends TestCase
{
    public static function reportingControllerProvider(): array
    {
        return [
            [InvoiceController::class],
            [ProjectController::class],
        ];
    }

    public function test_user_rate_takes_precedence_in_snapshot(): void
    {
        $user = (new User())->forceFill(['hourly_rate' => 85]);
        $client = (new Client())->forceFill([
            'hourly_rate' => 120,
            'currency' => 'nzd',
        ]);

        $attributes = app(TimerSessionBillingSnapshot::class)->attributes($user, $client);

        $this->assertSame(85.0, $attributes['hourly_rate_snapshot']);
        $this->assertSame('user', $attributes['hourly_rate_source']);
        $this->assertSame('NZD', $attributes['currency_snapshot']);
        $this->assertNotNull($attributes['rate_snapshot_at']);
    }

    public function test_client_rate_is_snapshotted_when_user_has_no_override(): void
    {
        $user = (new User())->forceFill(['hourly_rate' => 0]);
        $client = (new Client())->forceFill([
            'hourly_rate' => 120,
            'currency' => 'AUD',
        ]);

        $attributes = app(TimerSessionBillingSnapshot::class)->attributes($user, $client);

        $this->assertSame(120.0, $attributes['hourly_rate_snapshot']);
        $this->assertSame('client', $attributes['hourly_rate_source']);
        $this->assertSame('AUD', $attributes['currency_snapshot']);
    }

    #[DataProvider('reportingControllerProvider')]
    public function test_reporting_uses_snapshot_before_current_rates(string $controllerClass): void
    {
        $session = new TimerSession([
            'user_id' => 10,
            'hourly_rate_snapshot' => 75,
        ]);
        $method = new ReflectionMethod($controllerClass, 'resolveSessionHourlyRate');

        $resolvedRate = $method->invoke(
            app($controllerClass),
            $session,
            200.0,
            [10 => 150.0]
        );

        $this->assertSame(75.0, $resolvedRate);
    }

    #[DataProvider('reportingControllerProvider')]
    public function test_legacy_rows_fall_back_to_current_rate_resolution(string $controllerClass): void
    {
        $session = new TimerSession([
            'user_id' => 10,
            'hourly_rate_snapshot' => null,
        ]);
        $method = new ReflectionMethod($controllerClass, 'resolveSessionHourlyRate');

        $resolvedRate = $method->invoke(
            app($controllerClass),
            $session,
            200.0,
            [10 => 150.0]
        );

        $this->assertSame(150.0, $resolvedRate);
    }
}