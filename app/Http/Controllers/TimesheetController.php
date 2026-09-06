<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\TimerSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TimesheetController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(Auth::check(), 401, 'Authentication required.');
        Gate::authorize('viewAny', TimerSession::class);

        $validated = $request->validate([
            'week' => ['nullable', 'date'],
            'client_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'invoice_status' => ['nullable', 'in:unassigned,draft,finalized,paid'],
        ]);

        $user = Auth::user();
        abort_unless($user instanceof User && $user->currentTeam, 403, 'Select a team to continue.');

        $team = $user->currentTeam;
        $timezone = (string) ($team->timezone ?: 'UTC');
        $weekStart = CarbonImmutable::parse($validated['week'] ?? 'now', $timezone)
            ->startOfWeek(CarbonInterface::MONDAY)
            ->startOfDay();
        $weekEndExclusive = $weekStart->addWeek();
        $canViewTeamSessions = Gate::allows('viewTeam', TimerSession::class);
        $selectedUserId = $canViewTeamSessions && isset($validated['user_id'])
            ? (int) $validated['user_id']
            : (!$canViewTeamSessions ? (int) $user->id : null);

        $query = TimerSession::query()
            ->where('team_id', $team->id)
            ->where('started_at', '>=', $weekStart->utc())
            ->where('started_at', '<', $weekEndExclusive->utc())
            ->with([
                'user:id,name',
                'invoice:id,status',
                'task:id,name,project_id',
                'task.project:id,name,client_id',
                'task.project.client:id,name',
            ]);

        if ($selectedUserId !== null) {
            $query->where(function (Builder $userQuery) use ($selectedUserId): void {
                $userQuery->where('user_id', $selectedUserId)
                    ->orWhere('user_id_snapshot', $selectedUserId);
            });
        }

        if (isset($validated['client_id'])) {
            $clientId = (int) $validated['client_id'];
            $query->where(function (Builder $clientQuery) use ($clientId): void {
                $clientQuery->where('client_id_snapshot', $clientId)
                    ->orWhereHas('task.project', fn (Builder $projectQuery) => $projectQuery->where('client_id', $clientId));
            });
        }

        if (isset($validated['project_id'])) {
            $projectId = (int) $validated['project_id'];
            $query->where(function (Builder $projectQuery) use ($projectId): void {
                $projectQuery->where('project_id_snapshot', $projectId)
                    ->orWhereHas('task', fn (Builder $taskQuery) => $taskQuery->where('project_id', $projectId));
            });
        }

        if (isset($validated['invoice_status'])) {
            $invoiceStatus = (string) $validated['invoice_status'];

            if ($invoiceStatus === 'unassigned') {
                $query->whereNull('invoice_id');
            } else {
                $query->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('status', $invoiceStatus));
            }
        }

        $generatedAt = now();
        $sessions = $query
            ->orderBy('started_at')
            ->get()
            ->map(function (TimerSession $session) use ($generatedAt, $timezone): array {
                $localStart = $session->started_at->copy()->setTimezone($timezone);
                $invoiceStatus = optional($session->invoice)->status;

                return [
                    'id' => (int) $session->id,
                    'user_id' => $session->user_id ?? $session->user_id_snapshot,
                    'user_name' => optional($session->user)->name ?? $session->user_name_snapshot ?? 'Unknown user',
                    'task_id' => $session->task_id ?? $session->task_id_snapshot,
                    'task_name' => optional($session->task)->name ?? $session->task_name_snapshot ?? 'General',
                    'project_id' => optional(optional($session->task)->project)->id ?? $session->project_id_snapshot,
                    'project_name' => optional(optional($session->task)->project)->name ?? $session->project_name_snapshot ?? 'Unassigned Project',
                    'client_id' => optional(optional(optional($session->task)->project)->client)->id ?? $session->client_id_snapshot,
                    'client_name' => optional(optional(optional($session->task)->project)->client)->name ?? $session->client_name_snapshot ?? 'Unassigned Client',
                    'invoice_id' => $session->invoice_id,
                    'invoice_status' => $invoiceStatus,
                    'invoice_locked' => in_array($invoiceStatus, ['finalized', 'paid'], true),
                    'started_at' => $session->started_at->toIso8601String(),
                    'started_time' => $localStart->format('H:i'),
                    'stopped_time' => $session->stopped_at
                        ? $session->stopped_at->copy()->setTimezone($timezone)->format('H:i')
                        : null,
                    'day_key' => $localStart->toDateString(),
                    'elapsed_seconds' => $session->elapsedSeconds($generatedAt),
                    'is_running' => $session->isRunning(),
                    'is_paused' => $session->isPaused(),
                    'can_update' => Gate::allows('update', $session),
                    'can_delete' => Gate::allows('delete', $session),
                    'can_operate' => Gate::allows('operate', $session),
                ];
            })
            ->values();

        $memberIds = DB::table('team_user')->where('team_id', $team->id)->pluck('user_id');
        $memberIds->push($team->user_id);
        $teamMembers = $canViewTeamSessions
            ? User::query()
                ->whereIn('id', $memberIds->unique())
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect([['id' => (int) $user->id, 'name' => (string) $user->name]]);

        return Inertia::render('Timesheets/Weekly', [
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekEndExclusive->subDay()->toDateString(),
            'timezone' => $timezone,
            'serverNow' => $generatedAt->toIso8601String(),
            'days' => collect(range(0, 6))->map(function (int $offset) use ($weekStart): array {
                $day = $weekStart->addDays($offset);

                return [
                    'key' => $day->toDateString(),
                    'short_label' => $day->format('D'),
                    'date_label' => $day->format('j M'),
                    'full_label' => $day->format('l, j F'),
                    'is_today' => $day->isToday(),
                ];
            })->all(),
            'sessions' => $sessions,
            'clients' => Client::query()
                ->where('team_id', $team->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'projects' => Project::query()
                ->where('team_id', $team->id)
                ->orderBy('name')
                ->get(['id', 'client_id', 'name']),
            'teamMembers' => $teamMembers,
            'canViewTeamSessions' => $canViewTeamSessions,
            'filters' => [
                'client_id' => isset($validated['client_id']) ? (int) $validated['client_id'] : null,
                'project_id' => isset($validated['project_id']) ? (int) $validated['project_id'] : null,
                'user_id' => $selectedUserId,
                'invoice_status' => $validated['invoice_status'] ?? null,
            ],
            'navigation' => [
                'previous_week' => $weekStart->subWeek()->toDateString(),
                'current_week' => CarbonImmutable::now($timezone)->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
                'next_week' => $weekStart->addWeek()->toDateString(),
            ],
        ]);
    }
}