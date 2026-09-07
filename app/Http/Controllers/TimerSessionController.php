<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimerSession;
use App\Models\User;
use App\Services\TimerSessionBillingSnapshot;
use App\Services\TimerSessionService;
use App\Services\TimesheetSessionPresenter;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TimerSessionController extends Controller
{
    private TimerSessionBillingSnapshot $billingSnapshots;

    private TimerSessionService $sessions;

    private TimesheetSessionPresenter $presenter;

    public function __construct(
        TimerSessionBillingSnapshot $billingSnapshots,
        TimerSessionService $sessions,
        TimesheetSessionPresenter $presenter
    ) {
        $this->billingSnapshots = $billingSnapshots;
        $this->sessions = $sessions;
        $this->presenter = $presenter;
    }

    private function currentTeamIdOrFail(): int
    {
        $user = Auth::user();

        abort_unless($user && $user->currentTeam, 403, 'Select a team to continue.');

        return (int) $user->currentTeam->id;
    }

    private function currentTeamTimezone(): string
    {
        $user = Auth::user();

        abort_unless($user && $user->currentTeam, 403, 'Select a team to continue.');

        return (string) ($user->currentTeam->timezone ?: 'UTC');
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');
        Gate::authorize('viewAny', TimerSession::class);

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'confirmed_only' => 'nullable|boolean',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
        ]);

        $limit = $validated['limit'] ?? 10;
        $confirmedOnly = (bool) ($validated['confirmed_only'] ?? false);
        $invoiceId = $validated['invoice_id'] ?? null;

        $query = Gate::allows('viewTeam', TimerSession::class)
            ? $this->applyTeamScope(TimerSession::query())
            : $this->applyCurrentUserScope(TimerSession::query());

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
            'elapsed_seconds' => $session ? $session->elapsedSeconds() : 0,
            'session' => $session,
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');
        Gate::authorize('create', TimerSession::class);

        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'task_id' => 'required|integer|exists:tasks,id',
        ]);

        $existing = $this->findActiveSession();

        if ($existing) {
            return response()->json([
                'message' => 'A timer is already running. Stop it before starting another.',
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

        $startedAt = now();
        $project->loadMissing('client');
        $user = Auth::user();
        abort_unless($user instanceof User, 401, 'Authentication required.');

        $session = TimerSession::create(array_merge([
            'user_id' => Auth::id(),
            'team_id' => $this->currentTeamIdOrFail(),
            'task_id' => $task->id,
            'started_at' => $startedAt,
            'active_started_at' => $startedAt,
            'accumulated_seconds' => 0,
        ], $this->billingSnapshots->attributes($user, $project->client, $task)));

        return response()->json([
            'message' => 'Timer started.',
            'session' => $session,
        ], 201);
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

        Gate::authorize('operate', $session);

        $stoppedAt = now();
        $session->stopped_at = $stoppedAt;
        $session->duration_seconds = $session->elapsedSeconds($stoppedAt);
        $session->active_started_at = null;
        $session->paused_at = null;
        $session->save();

        return response()->json([
            'message' => 'Timer stopped.',
            'session' => $session,
        ]);
    }

    public function destroy(int $sessionId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $sessionQuery = $this->applyTeamScope(TimerSession::query());

        $session = $sessionQuery
            ->with('invoice')
            ->whereKey($sessionId)
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Timer session not found for this user.',
            ], 404);
        }

        Gate::authorize('delete', $session);

        $session->delete();

        return response()->json([
            'message' => 'Timer session deleted.',
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

        Gate::authorize('update', $session);

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
            Gate::authorize('update', $lockedSession);

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

            $draftInvoice->loadMissing('client');
            $this->billingSnapshots->applyIfMissing($lockedSession, $draftInvoice->client);
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

    public function startSessionForTask(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');
        Gate::authorize('create', TimerSession::class);

        $validated = $request->validate([
            'project_id' => 'nullable|integer',
            'task_id' => 'nullable|integer|required_without:project_id',
            'session_date' => 'nullable|date',
        ]);

        $user = Auth::user();
        abort_unless($user instanceof User, 401, 'Authentication required.');

        $teamId = $this->currentTeamIdOrFail();
        $existing = $this->sessions->findActiveSessionForUser((int) $user->id, $teamId);

        if ($existing) {
            return response()->json([
                'message' => 'A timer is already running. Stop it before starting another.',
            ], 409);
        }

        $task = $this->sessions->resolveTaskForTeam(
            $teamId,
            null,
            isset($validated['project_id']) ? (int) $validated['project_id'] : null,
            isset($validated['task_id']) ? (int) $validated['task_id'] : null
        );

        if (!$task) {
            return response()->json([
                'message' => 'Select a valid active task before starting a timer.',
            ], 422);
        }

        if (optional($task->project)->is_active === false) {
            return response()->json([
                'message' => 'Cannot start a timer session on an archived project.',
            ], 422);
        }

        $startedAt = null;

        if (isset($validated['session_date'])) {
            $timezone = $this->currentTeamTimezone();
            $nowLocal = now()->setTimezone($timezone);
            $day = CarbonImmutable::parse($validated['session_date'], $timezone)->startOfDay();

            if (!$day->isSameDay($nowLocal)) {
                $startedAt = $day
                    ->setTime($nowLocal->hour, $nowLocal->minute, $nowLocal->second)
                    ->setTimezone('UTC');
            }
        }

        $session = $this->sessions->start($user, $teamId, $task, $startedAt);
        $this->sessions->assignToLatestDraftInvoice($session, $teamId, (int) $user->id);

        return $this->sessionResponse($session, 'Timer started.', 201);
    }

    public function updateSession(Request $request, int $sessionId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $session = $this->findTeamSessionOrFail($sessionId);
        Gate::authorize('update', $session);

        $validated = $request->validate([
            'project_id' => 'nullable|integer',
            'task_id' => 'nullable|integer',
            'session_date' => 'nullable|date',
            'duration_seconds' => 'nullable|integer|min:1|max:604800',
            'duration_minutes' => 'nullable|numeric|min:1|max:10080',
        ]);

        $changesTask = isset($validated['task_id']) || isset($validated['project_id']);
        $changesDate = isset($validated['session_date']);
        $changesDuration = isset($validated['duration_seconds']) || isset($validated['duration_minutes']);

        if (!$changesTask && !$changesDate && !$changesDuration) {
            return response()->json([
                'message' => 'Provide a task, date or duration to update.',
            ], 422);
        }

        if (($changesDate || $changesDuration) && $session->stopped_at === null) {
            return response()->json([
                'message' => 'Stop the timer session before editing its date or duration.',
            ], 422);
        }

        if ($changesTask) {
            $task = $this->sessions->resolveTaskForTeam(
                $this->currentTeamIdOrFail(),
                $this->invoiceClientIdForSession($session),
                isset($validated['project_id']) ? (int) $validated['project_id'] : null,
                isset($validated['task_id']) ? (int) $validated['task_id'] : null
            );

            if (!$task) {
                return response()->json([
                    'message' => 'Select a valid active task for this session before saving.',
                ], 422);
            }

            $this->sessions->updateTask($session, $task);
        }

        if ($changesDate) {
            $this->sessions->moveToDate(
                $session,
                Carbon::parse($validated['session_date']),
                $this->currentTeamTimezone()
            );
        }

        if ($changesDuration) {
            $this->sessions->updateDuration($session, $this->resolveDurationSeconds($validated));
        }

        return $this->sessionResponse($session, 'Timer session updated.');
    }

    public function stopSession(int $sessionId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $session = $this->findTeamSessionOrFail($sessionId);
        Gate::authorize('operate', $session);

        if ($session->stopped_at !== null) {
            return response()->json([
                'message' => 'This timer session is already stopped.',
            ], 422);
        }

        $this->sessions->stop($session);

        return $this->sessionResponse($session, 'Timer stopped.');
    }

    public function restartSession(int $sessionId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $session = $this->findTeamSessionOrFail($sessionId);
        Gate::authorize('operate', $session);

        if ($session->stopped_at === null) {
            return response()->json([
                'message' => 'This timer session is already active.',
            ], 422);
        }

        $activeSession = $this->sessions->findActiveSessionForUser((int) Auth::id(), $this->currentTeamIdOrFail());

        if ($activeSession) {
            return response()->json([
                'message' => 'A timer is currently running on another session. Stop it before resuming this one.',
            ], 422);
        }

        $this->sessions->restart($session);

        return $this->sessionResponse($session, 'Timer session resumed.');
    }

    public function attachSessionToInvoice(Request $request, int $sessionId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'invoice_id' => 'required|integer',
        ]);

        $session = $this->findTeamSessionOrFail($sessionId);
        Gate::authorize('update', $session);

        if ($session->stopped_at === null) {
            return response()->json([
                'message' => 'Only stopped timer sessions can be assigned to an invoice.',
            ], 422);
        }

        $invoice = $this->findDraftInvoiceForActorOrFail((int) $validated['invoice_id']);

        if ($session->invoice_id !== null && (int) $session->invoice_id !== (int) $invoice->id) {
            return response()->json([
                'message' => 'This timer session is already assigned to another invoice.',
            ], 422);
        }

        $this->sessions->attachToInvoice($session, $invoice);

        return $this->sessionResponse($session, 'Timer session added to invoice.');
    }

    public function detachSessionFromInvoice(int $sessionId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $session = $this->findTeamSessionOrFail($sessionId);
        Gate::authorize('update', $session);

        if ($session->invoice_id === null) {
            return response()->json([
                'message' => 'This timer session is not assigned to an invoice.',
            ], 422);
        }

        $this->sessions->detachFromInvoice($session);

        return $this->sessionResponse($session, 'Timer session removed from invoice.');
    }

    private function resolveDurationSeconds(array $validated): int
    {
        if (isset($validated['duration_seconds'])) {
            return (int) $validated['duration_seconds'];
        }

        return max(60, (int) round(((float) $validated['duration_minutes']) * 60));
    }

    private function invoiceClientIdForSession(TimerSession $session): ?int
    {
        $session->loadMissing('invoice');

        return $session->invoice && $session->invoice->client_id
            ? (int) $session->invoice->client_id
            : null;
    }

    private function findTeamSessionOrFail(int $sessionId): TimerSession
    {
        $session = $this->applyTeamScope(TimerSession::query())
            ->with(TimesheetSessionPresenter::RELATIONS)
            ->whereKey($sessionId)
            ->first();

        abort_unless($session !== null, 404, 'Timer session not found.');

        return $session;
    }

    private function findDraftInvoiceForActorOrFail(int $invoiceId): Invoice
    {
        $invoice = $this->applyActorScope(Invoice::query())
            ->with('client')
            ->whereKey($invoiceId)
            ->first();

        abort_unless($invoice !== null, 404, 'Invoice not found.');
        abort_if(in_array($invoice->status, ['finalized', 'paid'], true), 422, 'Finalized or paid invoices cannot be edited.');

        return $invoice;
    }

    private function sessionResponse(TimerSession $session, string $message, int $status = 200): JsonResponse
    {
        $session->refresh()->load(TimesheetSessionPresenter::RELATIONS);
        $generatedAt = now();

        return response()->json([
            'message' => $message,
            'session' => $this->presenter->present($session, $this->currentTeamTimezone(), $generatedAt),
            'server_now' => $generatedAt->toIso8601String(),
        ], $status);
    }

    private function findActiveSession(): ?TimerSession
    {
        return $this->applyCurrentUserScope(TimerSession::query())
            ->whereNull('stopped_at')
            ->latest('started_at')
            ->first();
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
        return $this->sessions->createDraftInvoiceForClient($userId, $teamId, $clientId);
    }
}
