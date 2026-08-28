<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\TimerSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    private function currentTeamIdOrFail(): int
    {
        $user = Auth::user();

        abort_unless($user && $user->currentTeam, 403, 'Select a team to continue.');

        return (int) $user->currentTeam->id;
    }

    public function show(Request $request, int $projectId): Response
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $selectedUserId = isset($validated['user_id']) ? (int) $validated['user_id'] : null;

        $project = $this->findProjectForActorOrFail($projectId)->load('client:id,name,currency,hourly_rate');
        $projectNotes = $this->projectNotesForActor($project)
            ->map(fn (ProjectNote $note): array => $this->formatProjectNote($note))
            ->values();
        $tasks = $project->tasks()->get(['id', 'project_id', 'name', 'description', 'is_active', 'is_default']);
        $taskIds = $tasks->pluck('id')->all();
        $hourlyRate = $project->client ? (float) ($project->client->hourly_rate ?? 0) : 0.0;

        if (empty($taskIds)) {
            return Inertia::render('Projects/Show', [
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'is_active' => (bool) $project->is_active,
                    'hourly_rate' => $hourlyRate,
                    'client' => $project->client,
                ],
                'summary' => [
                    'task_count' => 0,
                    'active_task_count' => 0,
                    'default_task_count' => 0,
                    'sessions_count' => 0,
                    'running_sessions_count' => 0,
                    'assigned_sessions_count' => 0,
                    'unassigned_sessions_count' => 0,
                    'total_duration_seconds' => 0,
                    'total_billable_amount' => 0,
                    'average_session_seconds' => 0,
                    'project_invoice_count' => 0,
                    'project_paid_invoice_count' => 0,
                    'project_sent_invoice_count' => 0,
                    'project_in_progress_invoice_count' => 0,
                    'project_overdue_invoice_count' => 0,
                    'first_tracked_at' => null,
                    'last_tracked_at' => null,
                ],
                'taskSummaries' => [],
                'recentSessions' => [],
                'projectNotes' => $projectNotes,
                'workers' => [],
                'selectedWorkerId' => $selectedUserId,
            ]);
        }

        $workers = TimerSession::query()
            ->whereIn('task_id', $taskIds)
            ->with('user:id,name')
            ->select('user_id')
            ->distinct()
            ->get()
            ->map(function (TimerSession $session): ?array {
                if ($session->user_id === null || $session->user === null) {
                    return null;
                }

                return [
                    'id' => (int) $session->user_id,
                    'name' => (string) $session->user->name,
                ];
            })
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $selectedWorkerExists = $selectedUserId === null
            || $workers->contains(fn (array $worker): bool => (int) $worker['id'] === $selectedUserId);

        if (!$selectedWorkerExists) {
            return Inertia::render('Projects/Show', [
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'is_active' => (bool) $project->is_active,
                    'hourly_rate' => $hourlyRate,
                    'client' => $project->client,
                ],
                'summary' => [
                    'task_count' => $tasks->count(),
                    'active_task_count' => (int) $tasks->where('is_active', true)->count(),
                    'default_task_count' => (int) $tasks->where('is_default', true)->count(),
                    'sessions_count' => 0,
                    'running_sessions_count' => 0,
                    'assigned_sessions_count' => 0,
                    'unassigned_sessions_count' => 0,
                    'total_duration_seconds' => 0,
                    'total_billable_amount' => 0,
                    'average_session_seconds' => 0,
                    'project_invoice_count' => 0,
                    'project_paid_invoice_count' => 0,
                    'project_sent_invoice_count' => 0,
                    'project_in_progress_invoice_count' => 0,
                    'project_overdue_invoice_count' => 0,
                    'first_tracked_at' => null,
                    'last_tracked_at' => null,
                ],
                'taskSummaries' => [],
                'recentSessions' => [],
                'projectNotes' => $projectNotes,
                'workers' => $workers,
                'selectedWorkerId' => $selectedUserId,
            ]);
        }

        $baseSessionsQuery = TimerSession::query()
            ->whereIn('task_id', $taskIds);

        if ($selectedUserId !== null) {
            $baseSessionsQuery->where('user_id', $selectedUserId);
        }

        $sessions = (clone $baseSessionsQuery)
            ->whereNotNull('stopped_at')
            ->with([
                'task:id,name,project_id',
                'invoice:id,status',
                'user:id,name',
            ])
            ->orderByDesc('started_at')
            ->get([
                'id',
                'user_id',
                'task_id',
                'invoice_id',
                'started_at',
                'stopped_at',
                'duration_seconds',
            ]);

        $runningSessionsCount = (clone $baseSessionsQuery)
            ->whereNull('stopped_at')
            ->count();

        $projectInvoiceIds = (clone $baseSessionsQuery)
            ->whereNotNull('invoice_id')
            ->distinct()
            ->pluck('invoice_id')
            ->map(fn ($invoiceId): int => (int) $invoiceId)
            ->all();

        $projectInvoiceCount = 0;
        $projectPaidInvoiceCount = 0;
        $projectSentInvoiceCount = 0;
        $projectInProgressInvoiceCount = 0;
        $projectOverdueInvoiceCount = 0;

        if (!empty($projectInvoiceIds)) {
            $projectInvoices = Invoice::query()
                ->whereIn('id', $projectInvoiceIds)
                ->get(['id', 'status', 'due_at']);

            $projectInvoiceCount = (int) $projectInvoices->count();

            foreach ($projectInvoices as $projectInvoice) {
                if ($projectInvoice->status === 'paid') {
                    $projectPaidInvoiceCount += 1;
                    continue;
                }

                $isOverdue = $projectInvoice->due_at !== null && $projectInvoice->due_at->lt(now());

                if ($isOverdue) {
                    $projectOverdueInvoiceCount += 1;
                } elseif ($projectInvoice->status === 'finalized') {
                    $projectSentInvoiceCount += 1;
                } else {
                    $projectInProgressInvoiceCount += 1;
                }
            }
        }

        $sessionCount = $sessions->count();
        $totalDurationSeconds = (int) $sessions->sum(fn (TimerSession $session): int => (int) ($session->duration_seconds ?? 0));
        $assignedSessionsCount = (int) $sessions->whereNotNull('invoice_id')->count();
        $unassignedSessionsCount = max(0, $sessionCount - $assignedSessionsCount);
        $averageSessionSeconds = $sessionCount > 0 ? (int) round($totalDurationSeconds / $sessionCount) : 0;
        $sessionUserIds = $sessions
            ->pluck('user_id')
            ->filter()
            ->map(fn ($userId): int => (int) $userId)
            ->unique()
            ->values()
            ->all();
        $userRateMap = $this->userChargeOutRateMapForIds($sessionUserIds);
        $sessionBillableById = [];
        $totalBillableAmount = 0.0;

        foreach ($sessions as $session) {
            $durationSeconds = max(0, (int) ($session->duration_seconds ?? 0));
            $sessionUserId = $session->user_id !== null ? (int) $session->user_id : null;
            $effectiveHourlyRate = $this->resolveSessionHourlyRate($sessionUserId, $hourlyRate, $userRateMap);
            $billableAmount = round(($durationSeconds / 3600) * $effectiveHourlyRate, 2);

            $sessionBillableById[(int) $session->id] = $billableAmount;
            $totalBillableAmount += $billableAmount;
        }

        $totalBillableAmount = round($totalBillableAmount, 2);

        $taskSummariesById = [];

        foreach ($tasks as $task) {
            $taskSummariesById[(int) $task->id] = [
                'id' => $task->id,
                'name' => $task->name,
                'description' => $task->description,
                'is_active' => (bool) $task->is_active,
                'is_default' => (bool) $task->is_default,
                'sessions_count' => 0,
                'assigned_sessions_count' => 0,
                'unassigned_sessions_count' => 0,
                'total_duration_seconds' => 0,
                'total_hours' => 0,
                'billable_amount' => 0.0,
                'average_session_seconds' => 0,
                'last_tracked_at' => null,
            ];
        }

        foreach ($sessions as $session) {
            $taskId = (int) $session->task_id;

            if (!array_key_exists($taskId, $taskSummariesById)) {
                continue;
            }

            $durationSeconds = (int) ($session->duration_seconds ?? 0);
            $summary = $taskSummariesById[$taskId];

            $summary['sessions_count'] += 1;
            $summary['total_duration_seconds'] += $durationSeconds;
            $summary['billable_amount'] += (float) ($sessionBillableById[(int) $session->id] ?? 0.0);

            if ($session->invoice_id !== null) {
                $summary['assigned_sessions_count'] += 1;
            } else {
                $summary['unassigned_sessions_count'] += 1;
            }

            if ($summary['last_tracked_at'] === null || ($session->stopped_at && $session->stopped_at->gt($summary['last_tracked_at']))) {
                $summary['last_tracked_at'] = $session->stopped_at;
            }

            $taskSummariesById[$taskId] = $summary;
        }

        $taskSummaries = array_values(array_map(function (array $summary): array {
            $summary['total_hours'] = round(((int) $summary['total_duration_seconds']) / 3600, 2);
            $summary['billable_amount'] = round((float) $summary['billable_amount'], 2);
            $summary['average_session_seconds'] = $summary['sessions_count'] > 0
                ? (int) round(((int) $summary['total_duration_seconds']) / (int) $summary['sessions_count'])
                : 0;
            $summary['last_tracked_at'] = $summary['last_tracked_at'] ? $summary['last_tracked_at']->toIso8601String() : null;

            return $summary;
        }, $taskSummariesById));

        usort($taskSummaries, function (array $a, array $b): int {
            if ((int) $a['total_duration_seconds'] === (int) $b['total_duration_seconds']) {
                return strcmp((string) $a['name'], (string) $b['name']);
            }

            return (int) $b['total_duration_seconds'] <=> (int) $a['total_duration_seconds'];
        });

        $recentSessions = $sessions->take(30)->map(function (TimerSession $session) use ($sessionBillableById): array {
            $durationSeconds = (int) ($session->duration_seconds ?? 0);

            return [
                'id' => $session->id,
                'user_id' => $session->user_id,
                'worker_name' => $session->user ? $session->user->name : 'Unknown user',
                'task_id' => $session->task_id,
                'task_name' => $session->task ? $session->task->name : 'Unknown task',
                'invoice_id' => $session->invoice_id,
                'invoice_status' => $session->invoice ? $session->invoice->status : null,
                'started_at' => $session->started_at ? $session->started_at->toIso8601String() : null,
                'stopped_at' => $session->stopped_at ? $session->stopped_at->toIso8601String() : null,
                'duration_seconds' => $durationSeconds,
                'billable_amount' => (float) ($sessionBillableById[(int) $session->id] ?? 0.0),
            ];
        })->values();

        $firstTrackedAt = $sessions->min('started_at');
        $lastTrackedAt = $sessions->max('stopped_at');

        return Inertia::render('Projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'is_active' => (bool) $project->is_active,
                'hourly_rate' => $hourlyRate,
                'client' => $project->client,
            ],
            'summary' => [
                'task_count' => $tasks->count(),
                'active_task_count' => (int) $tasks->where('is_active', true)->count(),
                'default_task_count' => (int) $tasks->where('is_default', true)->count(),
                'sessions_count' => $sessionCount,
                'running_sessions_count' => $runningSessionsCount,
                'assigned_sessions_count' => $assignedSessionsCount,
                'unassigned_sessions_count' => $unassignedSessionsCount,
                'total_duration_seconds' => $totalDurationSeconds,
                'total_billable_amount' => $totalBillableAmount,
                'average_session_seconds' => $averageSessionSeconds,
                'project_invoice_count' => $projectInvoiceCount,
                'project_paid_invoice_count' => $projectPaidInvoiceCount,
                'project_sent_invoice_count' => $projectSentInvoiceCount,
                'project_in_progress_invoice_count' => $projectInProgressInvoiceCount,
                'project_overdue_invoice_count' => $projectOverdueInvoiceCount,
                'first_tracked_at' => $firstTrackedAt ? $firstTrackedAt->toIso8601String() : null,
                'last_tracked_at' => $lastTrackedAt ? $lastTrackedAt->toIso8601String() : null,
            ],
            'taskSummaries' => $taskSummaries,
            'recentSessions' => $recentSessions,
            'projectNotes' => $projectNotes,
            'workers' => $workers,
            'selectedWorkerId' => $selectedUserId,
        ]);
    }

    public function storeNote(Request $request, int $projectId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $teamId = $this->currentTeamIdOrFail();
        $project = $this->findProjectForActorOrFail($projectId);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'visibility' => 'nullable|in:team,private',
        ]);

        $body = trim((string) $validated['body']);
        $visibility = isset($validated['visibility']) ? (string) $validated['visibility'] : 'team';

        if ($body === '') {
            return response()->json([
                'message' => 'Note cannot be empty.',
            ], 422);
        }

        $note = ProjectNote::create([
            'team_id' => $teamId,
            'project_id' => $project->id,
            'user_id' => (int) Auth::id(),
            'visibility' => $visibility,
            'body' => $body,
        ])->load('user:id,name');

        return response()->json([
            'message' => 'Project note added.',
            'note' => $this->formatProjectNote($note),
        ], 201);
    }

    public function updateNote(Request $request, int $projectId, int $noteId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $project = $this->findProjectForActorOrFail($projectId);
        $note = $this->findProjectNoteForActorOrFail($project, $noteId);

        abort_unless($this->canManageProjectNote($note), 403, 'You can only modify notes you created.');

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $body = trim((string) $validated['body']);

        if ($body === '') {
            return response()->json([
                'message' => 'Note cannot be empty.',
            ], 422);
        }

        $note->body = $body;
        $note->save();

        return response()->json([
            'message' => 'Project note updated.',
            'note' => $this->formatProjectNote($note->fresh()->load('user:id,name')),
        ]);
    }

    public function destroyNote(int $projectId, int $noteId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $project = $this->findProjectForActorOrFail($projectId);
        $note = $this->findProjectNoteForActorOrFail($project, $noteId);

        abort_unless($this->canManageProjectNote($note), 403, 'You can only remove notes you created.');

        $note->delete();

        return response()->json([
            'message' => 'Project note removed.',
            'note_id' => $noteId,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $teamId = $this->currentTeamIdOrFail();

        $validated = $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
        ]);

        $query = Project::query()
            ->where('team_id', $teamId)
            ->with('client:id,name')
            ->orderBy('name');

        if (isset($validated['client_id'])) {
            $query->where('client_id', (int) $validated['client_id']);
        }

        return response()->json([
            'projects' => $query->get(),
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $teamId = $this->currentTeamIdOrFail();

        $validated = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        $client = $this->findClientForActorOrFail((int) $validated['client_id']);
        $projectName = trim((string) $validated['name']);

        if ($projectName === '') {
            return response()->json([
                'message' => 'Project name is required.',
            ], 422);
        }

        $nameExists = Project::query()
            ->where('client_id', $client->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($projectName)])
            ->exists();

        if ($nameExists) {
            return response()->json([
                'message' => 'A project with this name already exists for this client.',
            ], 422);
        }

        $project = Project::create([
            'user_id' => Auth::id(),
            'team_id' => $teamId,
            'client_id' => $client->id,
            'name' => $projectName,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Project created.',
            'project' => $project->load('client:id,name'),
        ], 201);
    }

    public function update(Request $request, int $projectId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $project = $this->findProjectForActorOrFail($projectId);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'sometimes|boolean',
        ]);

        if (array_key_exists('name', $validated)) {
            $projectName = trim((string) $validated['name']);

            if ($projectName === '') {
                return response()->json([
                    'message' => 'Project name is required.',
                ], 422);
            }

            $nameExists = Project::query()
                ->where('client_id', $project->client_id)
                ->where('id', '!=', $project->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($projectName)])
                ->exists();

            if ($nameExists) {
                return response()->json([
                    'message' => 'A project with this name already exists for this client.',
                ], 422);
            }

            $project->name = $projectName;
        }

        if (array_key_exists('description', $validated)) {
            $project->description = $validated['description'];
        }

        if (array_key_exists('is_active', $validated)) {
            if ((bool) $validated['is_active'] === false) {
                $hasActiveTasks = $project->tasks()
                    ->where('is_active', true)
                    ->exists();

                if ($hasActiveTasks) {
                    return response()->json([
                        'message' => 'Archive active tasks in this project before archiving the project.',
                    ], 422);
                }
            }

            $project->is_active = (bool) $validated['is_active'];
        }

        $project->save();

        return response()->json([
            'message' => 'Project updated.',
            'project' => $project->fresh()->load('client:id,name'),
        ]);
    }

    public function destroy(int $projectId): JsonResponse
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');

        $project = $this->findProjectForActorOrFail($projectId);

        $hasTasks = $project->tasks()->exists();

        if ($hasTasks) {
            return response()->json([
                'message' => 'Delete or move all tasks from this project before deleting it.',
            ], 422);
        }

        $project->delete();

        return response()->json([
            'message' => 'Project deleted.',
        ]);
    }

    private function findProjectForActorOrFail(int $projectId): Project
    {
        $teamId = $this->currentTeamIdOrFail();

        $project = Project::query()
            ->where('team_id', $teamId)
            ->whereKey($projectId)
            ->first();

        abort_unless($project !== null, 404, 'Project not found.');

        return $project;
    }

    private function findClientForActorOrFail(int $clientId): Client
    {
        $teamId = $this->currentTeamIdOrFail();

        $client = Client::query()
            ->where('team_id', $teamId)
            ->whereKey($clientId)
            ->first();

        abort_unless($client !== null, 403, 'Client does not belong to this user.');

        return $client;
    }

    /**
     * @param array<int, int> $userIds
     * @return array<int, float>
     */
    private function userChargeOutRateMapForIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'hourly_rate'])
            ->mapWithKeys(fn (User $user): array => [(int) $user->id => (float) ($user->hourly_rate ?? 0.0)])
            ->all();
    }

    /**
     * @param array<int, float> $userRateMap
     */
    private function resolveSessionHourlyRate(?int $sessionUserId, float $clientHourlyRate, array $userRateMap): float
    {
        if ($sessionUserId !== null) {
            $userRate = (float) ($userRateMap[$sessionUserId] ?? 0.0);

            if ($userRate > 0) {
                return $userRate;
            }
        }

        return $clientHourlyRate;
    }

    private function findProjectNoteForActorOrFail(Project $project, int $noteId): ProjectNote
    {
        $teamId = $this->currentTeamIdOrFail();

        $note = ProjectNote::query()
            ->where('team_id', $teamId)
            ->where('project_id', $project->id)
            ->whereKey($noteId)
            ->first();

        abort_unless($note !== null, 404, 'Project note not found.');

        abort_unless($this->canViewProjectNote($note), 404, 'Project note not found.');

        return $note;
    }

    private function projectNotesForActor(Project $project)
    {
        $actorId = (int) Auth::id();

        return ProjectNote::query()
            ->where('team_id', $project->team_id)
            ->where('project_id', $project->id)
            ->where(function ($query) use ($actorId): void {
                $query->where('visibility', 'team')
                    ->orWhere(function ($innerQuery) use ($actorId): void {
                        $innerQuery->where('visibility', 'private')
                            ->where('user_id', $actorId);
                    });
            })
            ->with('user:id,name')
            ->latest('created_at')
            ->latest('id')
            ->get();
    }

    private function canViewProjectNote(ProjectNote $note): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ((string) $note->visibility === 'private') {
            return (int) $note->user_id === (int) $user->id;
        }

        return true;
    }

    private function canManageProjectNote(ProjectNote $note): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return (int) $note->user_id === (int) $user->id;
    }

    private function formatProjectNote(ProjectNote $note): array
    {
        $actor = Auth::user();
        $actorId = $actor ? (int) $actor->id : null;
        $canManage = $this->canManageProjectNote($note);

        return [
            'id' => (int) $note->id,
            'project_id' => (int) $note->project_id,
            'team_id' => (int) $note->team_id,
            'user_id' => (int) $note->user_id,
            'user_name' => $note->user ? (string) $note->user->name : 'Unknown user',
            'visibility' => (string) ($note->visibility ?: 'team'),
            'is_private' => (string) ($note->visibility ?: 'team') === 'private',
            'body' => (string) $note->body,
            'created_at' => $note->created_at ? $note->created_at->toIso8601String() : null,
            'updated_at' => $note->updated_at ? $note->updated_at->toIso8601String() : null,
            'is_authored_by_current_user' => $actorId !== null && (int) $note->user_id === $actorId,
            'can_edit' => $canManage,
            'can_delete' => $canManage,
        ];
    }
}
