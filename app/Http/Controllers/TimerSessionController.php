<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimerSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $session = TimerSession::create([
            'user_id' => Auth::id(),
            'team_id' => $this->currentTeamIdOrFail(),
            'task_id' => $this->findDefaultTaskForProject($project)->id,
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
            'invoice_id' => 'required|integer|exists:invoices,id',
            'task_id' => 'nullable|integer|exists:tasks,id',
        ]);

        $invoice = $this->applyActorScope(Invoice::query())
            ->whereKey((int) $validated['invoice_id'])
            ->first();

        if (!$invoice) {
            abort(403, 'Invoice does not belong to this user.');
        }

        if (in_array($invoice->status, ['finalized', 'paid'], true)) {
            return response()->json([
                'message' => 'Finalized or paid invoices cannot receive new timer sessions.',
            ], 422);
        }

        $session = $this->applyCurrentUserScope(TimerSession::query())
            ->whereKey((int) $validated['session_id'])
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Timer session not found for this user.',
            ], 404);
        }

        if ($session->stopped_at === null) {
            return response()->json([
                'message' => 'Stop the timer session before submitting it to an invoice.',
            ], 422);
        }

        $task = $this->resolveInvoiceTaskForSession(
            $invoice,
            $session,
            isset($validated['task_id']) ? (int) $validated['task_id'] : null
        );

        $session->invoice_id = (int) $validated['invoice_id'];
        $session->task_id = $task->id;
        $session->save();

        return response()->json([
            'message' => 'Timer session submitted to invoice.',
            'session' => $session,
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

    private function findDefaultTaskForProject(Project $project): Task
    {
        $defaultTask = Task::query()
            ->where('project_id', $project->id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($defaultTask) {
            return $defaultTask;
        }

        $firstTask = Task::query()
            ->where('project_id', $project->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($firstTask) {
            return $firstTask;
        }

        abort(422, 'Create at least one active task in the selected project before starting a timer session.');
    }

    private function resolveInvoiceTaskForSession(Invoice $invoice, TimerSession $session, ?int $taskId = null): Task
    {
        if ($invoice->client_id === null) {
            abort(422, 'Assign a client to the invoice before submitting timer sessions.');
        }

        if ($taskId !== null) {
            $selectedTask = Task::query()
                ->whereKey($taskId)
                ->where('team_id', $this->currentTeamIdOrFail())
                ->where('is_active', true)
                ->first();

            if (!$selectedTask || (int) $selectedTask->client_id !== (int) $invoice->client_id) {
                abort(422, 'Selected task does not belong to the invoice client.');
            }

            return $selectedTask;
        }

        if ($session->task_id !== null) {
            $existingTask = Task::query()
                ->whereKey($session->task_id)
                ->where('team_id', $this->currentTeamIdOrFail())
                ->where('is_active', true)
                ->first();

            if ($existingTask && (int) $existingTask->client_id === (int) $invoice->client_id) {
                return $existingTask;
            }
        }

        return $this->findDefaultTaskForClient((int) $invoice->client_id);
    }

    private function findDefaultTaskForClient(int $clientId): Task
    {
        $defaultTask = Task::query()
            ->where('team_id', $this->currentTeamIdOrFail())
            ->where('client_id', $clientId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($defaultTask) {
            return $defaultTask;
        }

        $firstTask = Task::query()
            ->where('team_id', $this->currentTeamIdOrFail())
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($firstTask) {
            return $firstTask;
        }

        abort(422, 'Create at least one active task for this invoice client before submitting sessions.');
    }
}
