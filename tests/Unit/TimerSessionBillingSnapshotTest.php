<?php

namespace Tests\Unit;

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProjectController;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
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

    public function test_reporting_identity_is_snapshotted_with_relationship_ids(): void
    {
        $user = (new User())->forceFill([
            'id' => 10,
            'name' => 'Sam Worker',
            'hourly_rate' => 0,
        ]);
        $client = (new Client())->forceFill([
            'id' => 20,
            'name' => 'Acme Client',
            'hourly_rate' => 120,
            'currency' => 'NZD',
        ]);
        $project = (new Project())->forceFill([
            'id' => 30,
            'name' => 'Website Refresh',
        ]);
        $task = (new Task())->forceFill([
            'id' => 40,
            'name' => 'Development',
        ]);
        $task->setRelation('project', $project);

        $attributes = app(TimerSessionBillingSnapshot::class)->attributes($user, $client, $task);

        $this->assertSame(10, $attributes['user_id_snapshot']);
        $this->assertSame('Sam Worker', $attributes['user_name_snapshot']);
        $this->assertSame(40, $attributes['task_id_snapshot']);
        $this->assertSame('Development', $attributes['task_name_snapshot']);
        $this->assertSame(30, $attributes['project_id_snapshot']);
        $this->assertSame('Website Refresh', $attributes['project_name_snapshot']);
        $this->assertSame(20, $attributes['client_id_snapshot']);
        $this->assertSame('Acme Client', $attributes['client_name_snapshot']);
    }

    public function test_refresh_preserves_snapshots_when_live_relationships_are_missing(): void
    {
        $capturedAt = now()->subDay();
        $session = (new TimerSession())->forceFill([
            'hourly_rate_snapshot' => 95,
            'hourly_rate_source' => 'user',
            'currency_snapshot' => 'NZD',
            'rate_snapshot_at' => $capturedAt,
            'user_id_snapshot' => 10,
            'user_name_snapshot' => 'Former Member',
            'task_id_snapshot' => 20,
            'task_name_snapshot' => 'Historical Task',
            'project_id_snapshot' => 30,
            'project_name_snapshot' => 'Historical Project',
            'client_id_snapshot' => 40,
            'client_name_snapshot' => 'Historical Client',
        ]);
        $session->setRelation('user', null);
        $session->setRelation('task', null);

        app(TimerSessionBillingSnapshot::class)->apply($session);

        $this->assertSame(95.0, (float) $session->hourly_rate_snapshot);
        $this->assertSame('NZD', $session->currency_snapshot);
        $this->assertSame('Former Member', $session->user_name_snapshot);
        $this->assertSame('Historical Task', $session->task_name_snapshot);
        $this->assertSame('Historical Project', $session->project_name_snapshot);
        $this->assertSame('Historical Client', $session->client_name_snapshot);
        $this->assertSame(
            $capturedAt->format('Y-m-d H:i:s'),
            $session->rate_snapshot_at->format('Y-m-d H:i:s')
        );
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