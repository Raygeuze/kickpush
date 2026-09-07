<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\TimerSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TimerSessionService
{
    private TimerSessionBillingSnapshot $billingSnapshots;

    public function __construct(TimerSessionBillingSnapshot $billingSnapshots)
    {
        $this->billingSnapshots = $billingSnapshots;
    }

    public function createManual(
        User $actor,
        int $teamId,
        ?Task $task,
        CarbonInterface $startedAt,
        int $durationSeconds,
        ?Invoice $invoice = null,
        ?Client $client = null
    ): TimerSession {
        $resolvedClient = $client
            ?: ($invoice ? $invoice->client : optional(optional($task)->project)->client);

        $start = $startedAt->copy();

        return TimerSession::create(array_merge([
            'user_id' => (int) $actor->id,
            'team_id' => $teamId,
            'invoice_id' => optional($invoice)->id,
            'task_id' => optional($task)->id,
            'started_at' => $start,
            'stopped_at' => $start->copy()->addSeconds($durationSeconds),
            'duration_seconds' => $durationSeconds,
        ], $this->billingSnapshots->attributes($actor, $resolvedClient, $task)));
    }

    public function start(User $actor, int $teamId, Task $task, ?CarbonInterface $startedAt = null): TimerSession
    {
        $task->loadMissing('project.client');
        $now = now();

        return TimerSession::create(array_merge([
            'user_id' => (int) $actor->id,
            'team_id' => $teamId,
            'task_id' => (int) $task->id,
            // started_at drives day attribution; active_started_at is always the live segment.
            'started_at' => $startedAt ? $startedAt->copy() : $now,
            'active_started_at' => $now,
            'accumulated_seconds' => 0,
        ], $this->billingSnapshots->attributes($actor, optional($task->project)->client, $task)));
    }

    public function updateTask(TimerSession $session, Task $task): TimerSession
    {
        $session->task_id = (int) $task->id;
        $session->save();

        return $session;
    }

    /**
     * Re-dates a stopped session, preserving its local time of day and duration.
     */
    public function moveToDate(TimerSession $session, CarbonInterface $date, ?string $timezone = null): TimerSession
    {
        $zone = $timezone ?: (string) config('app.timezone', 'UTC');
        $durationSeconds = max(0, (int) ($session->duration_seconds ?? $session->elapsedSeconds()));

        $localStart = $session->started_at
            ? $session->started_at->copy()->setTimezone($zone)
            : $date->copy()->setTimezone($zone);

        $localStart = $localStart->setDate($date->year, $date->month, $date->day);

        $session->started_at = $localStart->copy()->setTimezone('UTC');

        if ($session->stopped_at) {
            $session->stopped_at = $localStart->copy()->addSeconds($durationSeconds)->setTimezone('UTC');
            $session->duration_seconds = $durationSeconds;
        }

        $session->save();

        return $session;
    }

    public function updateDuration(TimerSession $session, int $durationSeconds): TimerSession
    {
        if ($session->started_at) {
            $session->stopped_at = $session->started_at->copy()->addSeconds($durationSeconds);
        } elseif ($session->stopped_at) {
            $session->started_at = $session->stopped_at->copy()->subSeconds($durationSeconds);
        }

        $session->duration_seconds = $durationSeconds;
        $session->accumulated_seconds = 0;
        $session->active_started_at = null;
        $session->paused_at = null;
        $session->save();

        return $session;
    }

    public function stop(TimerSession $session): TimerSession
    {
        $stoppedAt = now();

        $session->stopped_at = $stoppedAt;
        $session->duration_seconds = $session->elapsedSeconds($stoppedAt);
        $session->active_started_at = null;
        $session->paused_at = null;
        $session->save();

        return $session;
    }

    /**
     * Reopens a stopped session, carrying its recorded duration forward as accumulated time.
     */
    public function restart(TimerSession $session): TimerSession
    {
        $session->active_started_at = now();
        $session->paused_at = null;
        $session->stopped_at = null;
        $session->accumulated_seconds = max(0, (int) ($session->duration_seconds ?? 0));
        $session->duration_seconds = null;
        $session->save();

        return $session;
    }

    public function attachToInvoice(TimerSession $session, Invoice $invoice): TimerSession
    {
        $invoice->loadMissing('client');
        $this->billingSnapshots->applyIfMissing($session, $invoice->client);
        $session->invoice_id = (int) $invoice->id;
        $session->save();

        return $session;
    }

    public function detachFromInvoice(TimerSession $session): TimerSession
    {
        $session->invoice_id = null;
        $session->save();

        return $session;
    }

    /**
     * Attaches the session to the latest draft invoice for its client, creating one when none exists.
     * Returns null when the session has no resolvable client.
     */
    public function assignToLatestDraftInvoice(TimerSession $session, int $teamId, int $userId): ?Invoice
    {
        $session->loadMissing('task.project');
        $clientId = optional(optional($session->task)->project)->client_id;

        if ($clientId === null) {
            return null;
        }

        return DB::transaction(function () use ($session, $teamId, $userId, $clientId): Invoice {
            $draftInvoice = Invoice::query()
                ->where('team_id', $teamId)
                ->where('client_id', (int) $clientId)
                ->where('status', 'draft')
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if (!$draftInvoice) {
                $draftInvoice = $this->createDraftInvoiceForClient($userId, $teamId, (int) $clientId);
            }

            $this->attachToInvoice($session, $draftInvoice);

            return $draftInvoice;
        });
    }

    public function createDraftInvoiceForClient(int $userId, int $teamId, int $clientId): Invoice
    {
        $financialYear = $this->findOrCreateFinancialYearForTeam($userId, $teamId, $this->defaultNzFinancialYearStart());

        $invoice = Invoice::create([
            'user_id' => $userId,
            'team_id' => $teamId,
            'client_id' => $clientId,
            'financial_year_id' => $financialYear->id,
            'invoice_number' => 'TMP-'.(string) Str::uuid(),
            'status' => 'draft',
        ]);

        $invoice->invoice_number = (string) $invoice->id;
        $invoice->save();

        return $invoice;
    }

    private function defaultNzFinancialYearStart(): int
    {
        $nowNz = CarbonImmutable::now('Pacific/Auckland');

        return $nowNz->month >= 4 ? $nowNz->year : $nowNz->subYear()->year;
    }

    private function findOrCreateFinancialYearForTeam(int $userId, int $teamId, int $startYear): FinancialYear
    {
        $start = CarbonImmutable::create($startYear, 4, 1, 0, 0, 0, 'Pacific/Auckland');
        $end = $start->addYear()->subDay();

        return FinancialYear::query()->firstOrCreate(
            [
                'team_id' => $teamId,
                'start_year' => $startYear,
            ],
            [
                'user_id' => $userId,
                'end_year' => $startYear + 1,
                'label' => $startYear.'/'.($startYear + 1),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]
        );
    }

    public function findActiveSessionForUser(int $userId, int $teamId): ?TimerSession
    {
        return TimerSession::query()
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->whereNull('stopped_at')
            ->latest('started_at')
            ->first();
    }

    /**
     * Resolves an explicit task, or the default task of a project, constrained to the team
     * and (when given) a client.
     */
    public function resolveTaskForTeam(int $teamId, ?int $clientId, ?int $projectId, ?int $taskId): ?Task
    {
        if ($taskId !== null) {
            return $this->taskBaseQuery($teamId, $clientId)
                ->whereKey($taskId)
                ->first();
        }

        if ($projectId === null) {
            return null;
        }

        return $this->taskBaseQuery($teamId, $clientId)
            ->where('project_id', $projectId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();
    }

    private function taskBaseQuery(int $teamId, ?int $clientId): Builder
    {
        return Task::query()
            ->with('project.client')
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->when($clientId !== null, fn (Builder $query): Builder => $query->where('client_id', $clientId))
            ->whereHas('project', fn (Builder $query): Builder => $query->where('team_id', $teamId));
    }
}
