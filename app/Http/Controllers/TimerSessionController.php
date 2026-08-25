<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\FinancialYear;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimerSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TimerSessionController extends Controller
{
    private function currentTeamIdOrFail(): int
    {
        $user = Auth::user();

        abort_unless($user && $user->currentTeam, 403, 'Select a team to continue.');

        return (int) $user->currentTeam->id;
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'confirmed_only' => 'nullable|boolean',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
        ]);

        $limit = $validated['limit'] ?? 10;
        $confirmedOnly = (bool) ($validated['confirmed_only'] ?? false);
        $invoiceId = $validated['invoice_id'] ?? null;

        $query = $this->applyTeamScope(TimerSession::query());

        if ($confirmedOnly) {
            $query->whereNotNull('invoice_id');
        }

        if ($invoiceId) {
            $this->assertInvoiceBelongsToActor((int) $invoiceId);
            $query->where('invoice_id', $invoiceId);
        }

        $sessions = $query
            ->latest('started_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'sessions' => $sessions,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $session = $this->findActiveSession();

        if ($session) {
            $session->loadMissing('task.project.client');
        }

        $isRunning = $session !== null && $session->paused_at === null;
        $isPaused = $session !== null && $session->paused_at !== null;

        return response()->json([
            'running' => $isRunning,
            'paused' => $isPaused,
            'active' => $session !== null,
            'elapsed_seconds' => $this->calculateElapsedSeconds($session),
            'session' => $session,
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'task_id' => 'required|integer|exists:tasks,id',
        ]);

        $existing = $this->findActiveSession();

        if ($existing) {
            return response()->json([
                'message' => $existing->paused_at ? 'Timer is paused. Resume it to continue.' : 'Timer already running.',
                'session' => $existing,
            ], 409);
        }

        $project = $this->findProjectForActorOrFail((int) $validated['project_id']);

        if ($project->is_active === false) {
            return response()->json([
                'message' => 'Cannot start a timer session on an archived project.',
            ], 422);
        }

        $task = $this->findTaskForProjectOrFail((int) $validated['task_id'], $project);

        if ($task->is_active === false) {
            return response()->json([
                'message' => 'Cannot start a timer session on an archived task.',
            ], 422);
        }

        $session = TimerSession::create([
            'user_id' => Auth::id(),
            'team_id' => $this->currentTeamIdOrFail(),
            'task_id' => $task->id,
            'started_at' => now(),
            'accumulated_seconds' => 0,
        ]);

        return response()->json([
            'message' => 'Timer started.',
            'session' => $session,
        ], 201);
    }

    public function pause(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $session = $this->findRunningSession();

        if (!$session) {
            $pausedSession = $this->findPausedSession();

            if ($pausedSession) {
                return response()->json([
                    'message' => 'Timer is already paused.',
                    'session' => $pausedSession,
                ], 200);
            }

            return response()->json([
                'message' => 'No running timer found.',
            ], 404);
        }

        $pausedAt = now();
        $elapsedSinceStart = (int) floor($session->started_at->diffInSeconds($pausedAt));
        $session->accumulated_seconds = (int) ($session->accumulated_seconds ?? 0)
            + max(0, $elapsedSinceStart);
        $session->paused_at = $pausedAt;
        $session->save();

        return response()->json([
            'message' => 'Timer paused.',
            'session' => $session,
        ]);
    }

    public function resume(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $session = $this->findPausedSession();

        if (!$session) {
            $runningSession = $this->findRunningSession();

            if ($runningSession) {
                return response()->json([
                    'message' => 'Timer is already running.',
                    'session' => $runningSession,
                ], 200);
            }

            return response()->json([
                'message' => 'No paused timer found.',
            ], 404);
        }

        $session->started_at = now();
        $session->paused_at = null;
        $session->save();

        return response()->json([
            'message' => 'Timer resumed.',
            'session' => $session,
        ]);
    }

    public function stop(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $session = $this->findActiveSession();

        if (!$session) {
            return response()->json([
                'message' => 'No active timer found.',
            ], 404);
        }

        $stoppedAt = now();
        $session->stopped_at = $stoppedAt;
        $session->duration_seconds = $this->calculateElapsedSeconds($session, $stoppedAt);
        $session->paused_at = null;
        $session->save();

        return response()->json([
            'message' => 'Timer stopped.',
            'session' => $session,
        ]);
    }

    public function submitToInvoice(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'session_id' => 'required|integer|exists:timer_sessions,id',
        ]);

        $session = $this->applyCurrentUserScope(TimerSession::query())
            ->with('task.project')
            ->whereKey((int) $validated['session_id'])
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Timer session not found for this user.',
            ], 404);
        }

        if ($session->stopped_at === null) {
            return response()->json([
                'message' => 'Stop the timer session before confirming it.',
            ], 422);
        }

        if ($session->invoice_id !== null) {
            $existingInvoice = $this->applyActorScope(Invoice::query())
                ->whereKey((int) $session->invoice_id)
                ->first();

            if ($existingInvoice) {
                return response()->json([
                    'message' => 'Timer session is already confirmed on an invoice.',
                    'session' => $session,
                    'invoice' => $existingInvoice,
                ], 200);
            }
        }

        $task = $session->task;
        $project = $task ? $task->project : null;

        if (!$task || !$project) {
            return response()->json([
                'message' => 'Timer session task/project is missing. Reassign the session task before confirming.',
            ], 422);
        }

        if ($project->client_id === null) {
            return response()->json([
                'message' => 'Project must belong to a client before confirming timer sessions.',
            ], 422);
        }

        $teamId = $this->currentTeamIdOrFail();
        $userId = (int) Auth::id();
        $taskClientId = (int) $project->client_id;
        $sessionId = (int) $session->id;

        $assignment = DB::transaction(function () use ($sessionId, $teamId, $userId, $taskClientId): array {
            $lockedSession = $this->applyCurrentUserScope(TimerSession::query())
                ->lockForUpdate()
                ->whereKey($sessionId)
                ->first();

            abort_unless($lockedSession !== null, 404, 'Timer session not found for this user.');

            if ($lockedSession->invoice_id !== null) {
                $existingInvoice = $this->applyActorScope(Invoice::query())
                    ->whereKey((int) $lockedSession->invoice_id)
                    ->first();

                if ($existingInvoice) {
                    return [
                        'session' => $lockedSession,
                        'invoice' => $existingInvoice,
                    ];
                }
            }

            $draftInvoice = Invoice::query()
                ->where('team_id', $teamId)
                ->where('client_id', $taskClientId)
                ->where('status', 'draft')
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if (!$draftInvoice) {
                $draftInvoice = $this->createDraftInvoiceForClient($userId, $teamId, $taskClientId);
            }

            $lockedSession->invoice_id = (int) $draftInvoice->id;
            $lockedSession->save();

            return [
                'session' => $lockedSession,
                'invoice' => $draftInvoice,
            ];
        });

        /** @var TimerSession $assignedSession */
        $assignedSession = $assignment['session'];
        /** @var Invoice $assignedInvoice */
        $assignedInvoice = $assignment['invoice'];

        return response()->json([
            'message' => 'Timer session confirmed and assigned to draft invoice.',
            'session' => $assignedSession,
            'invoice' => $assignedInvoice,
        ]);
    }

    private function findRunningSession(): ?TimerSession
    {
        return $this->applyCurrentUserScope(TimerSession::query())
            ->whereNull('stopped_at')
            ->whereNull('paused_at')
            ->latest('started_at')
            ->first();
    }

    private function findPausedSession(): ?TimerSession
    {
        return $this->applyCurrentUserScope(TimerSession::query())
            ->whereNull('stopped_at')
            ->whereNotNull('paused_at')
            ->latest('paused_at')
            ->first();
    }

    private function findActiveSession(): ?TimerSession
    {
        return $this->applyCurrentUserScope(TimerSession::query())
            ->whereNull('stopped_at')
            ->latest('started_at')
            ->first();
    }

    private function calculateElapsedSeconds(?TimerSession $session, $at = null): int
    {
        if (!$session) {
            return 0;
        }

        $referenceTime = $at ?? now();
        $accumulated = (int) ($session->accumulated_seconds ?? 0);

        if ($session->paused_at !== null) {
            return $accumulated;
        }

        $elapsedSinceStart = (int) floor($session->started_at->diffInSeconds($referenceTime));

        return $accumulated + max(0, $elapsedSinceStart);
    }

    private function applyActorScope(Builder $query): Builder
    {
        return $this->applyTeamScope($query);
    }

    private function applyTeamScope(Builder $query): Builder
    {
        return $query->where('team_id', $this->currentTeamIdOrFail());
    }

    private function applyCurrentUserScope(Builder $query): Builder
    {
        return $this->applyTeamScope($query)
            ->where('user_id', Auth::id());
    }

    private function assertInvoiceBelongsToActor(int $invoiceId): void
    {
        $invoiceQuery = $this->applyActorScope(Invoice::query())
            ->whereKey($invoiceId);

        abort_unless($invoiceQuery->exists(), 403, 'Invoice does not belong to this user.');
    }

    private function findProjectForActorOrFail(int $projectId): Project
    {
        $project = Project::query()
            ->where('team_id', $this->currentTeamIdOrFail())
            ->whereKey($projectId)
            ->first();

        abort_unless($project !== null, 404, 'Project not found.');

        return $project;
    }

    private function findTaskForProjectOrFail(int $taskId, Project $project): Task
    {
        $task = Task::query()
            ->where('team_id', $this->currentTeamIdOrFail())
            ->where('project_id', $project->id)
            ->whereKey($taskId)
            ->first();

        abort_unless($task !== null, 422, 'Selected task does not belong to the selected project.');

        return $task;
    }

    private function createDraftInvoiceForClient(int $userId, int $teamId, int $clientId): Invoice
    {
        $financialYear = $this->findOrCreateFinancialYearForTeam($userId, $teamId, $this->defaultNzFinancialYearStart());

        $invoice = Invoice::create([
            'user_id' => $userId,
            'team_id' => $teamId,
            'client_id' => $clientId,
            'financial_year_id' => $financialYear->id,
            'invoice_number' => $this->generateTemporaryInvoiceNumber(),
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

    private function nzFinancialYearPeriod(int $financialYearStart): array
    {
        $start = CarbonImmutable::create($financialYearStart, 4, 1, 0, 0, 0, 'Pacific/Auckland');
        $end = $start->addYear()->subDay();

        return [
            'start' => $start,
            'end' => $end,
            'label' => $financialYearStart . '/' . ($financialYearStart + 1),
        ];
    }

    private function findOrCreateFinancialYearForTeam(int $userId, int $teamId, int $startYear): FinancialYear
    {
        $period = $this->nzFinancialYearPeriod($startYear);

        return FinancialYear::query()->firstOrCreate(
            [
                'team_id' => $teamId,
                'start_year' => $startYear,
            ],
            [
                'user_id' => $userId,
                'end_year' => $startYear + 1,
                'label' => $period['label'],
                'start_date' => $period['start']->toDateString(),
                'end_date' => $period['end']->toDateString(),
            ]
        );
    }

    private function generateTemporaryInvoiceNumber(): string
    {
        return 'TMP-' . (string) Str::uuid();
    }
}
